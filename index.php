// Constants
const TOKEN_NAME = 'BNCL';
const MIN_BET = 1.00;
const MAX_BET = 10.00;
const BNCL_CONTRACT = '0x67aC2BB295F533D9E7f62Cf5B5Dc755E8bBb8A60';
const BNCL_WALLET = '0x67aC2BB295F533D9E7f62Cf5B5Dc755E8bBb8A60';
const ETRANSFER_EMAIL = 'etransfer@bignickelcasino.io';
const SIMULATED_TOPUP = 500;

const SYMBOL_MAP = { '🍒': 'Cherry', '🍋': 'Lemon', '7️⃣': 'Jackpot', '🔔': 'Bell' };
const SYMBOLS = Object.keys(SYMBOL_MAP);
const SYMBOL_HEIGHT = 80; // Must match .slot-reel-container height in CSS

// Game definitions for the lobby
const GAMES_CATALOG = [
    { id: 'slots', name: 'Lucky Sevens', category: 'slots', image: '🎰', icon: 'zap', hot: true, players: 234, minBet: 1, maxBet: 10 },
    { id: 'dice', name: 'Dice Roll', category: 'table', image: '🎲', icon: 'dices', hot: true, players: 189, minBet: 1, maxBet: 10 },
    { id: 'roulette', name: 'Roulette Royale', category: 'table', image: '🎡', icon: 'diamond', hot: false, players: 178, minBet: 5, maxBet: 10 },
    { id: 'poker', name: 'Poker Hold\'em', category: 'poker', image: '♠️', icon: 'spade', hot: true, players: 267, minBet: 2, maxBet: 200, disabled: true }, // Placeholder
    { id: 'crash', name: 'Crash Predictor', category: 'instant', image: '🚀', icon: 'zap', hot: false, players: 143, minBet: 1, maxBet: 10, disabled: true } // Placeholder
];
const CATEGORIES = [
    { id: 'all', name: 'All Games', icon: 'sparkles' },
    { id: 'slots', name: 'Slots', icon: 'zap' },
    { id: 'table', name: 'Table Games', icon: 'diamond' },
    { id: 'poker', name: 'Poker', icon: 'spade' }
];

// --- State and DOM ---
const SOUNDS = {
    spin: document.getElementById('spin-sound'),
    win: document.getElementById('win-sound'),
    jackpot: document.getElementById('jackpot-sound')
};
const playSound = (name) => { const s = SOUNDS[name]; if (s) { s.currentTime = 0; s.play().catch(() => {}); } };

const appContainer = document.getElementById('app-container');

let state = {
    userId: null,
    balance: 5.00,
    isLoading: true,
    error: null,
    isAuthReady: false,
    currentPage: 'lobby', // NEW: Navigation state
    currentGame: 'slots', // The currently loaded game logic
    lobbyCategory: 'all', // NEW: Lobby filter state
    isGameActive: false,
    slotsReels: ['?', '?', '?'],
    slotsMessage: 'Place your bet and spin!',
    slotsBetAmount: 1.00,
    rouletteResult: null,
    rouletteMessage: 'Place your bet on Red or Black.',
    rouletteBetAmount: 5.00,
    diceRollResult: null,
    diceMessage: 'Guess High (7+) or Low (6-).',
    diceBetAmount: 2.50
};

let finalReels = null; 

// State update
const updateState = (newState) => {
    Object.assign(state, newState);
    renderApp();
};

// --- Animations and Effects ---
// ... (Confetti, Jackpot, etc. functions remain the same) ...

const triggerConfetti = () => {
    confetti({ particleCount: 200, spread: 70, origin: { y: 0.6 } });
    setTimeout(() => confetti({ particleCount: 100, angle: 60, spread: 55, origin: { x: 0 } }), 100);
    setTimeout(() => confetti({ particleCount: 100, angle: 120, spread: 55, origin: { x: 1 } }), 200);
};

const showJackpotCelebration = (amount) => {
    document.body.classList.add('jackpot-active');
    playSound('jackpot');
    triggerConfetti();
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-50 flex items-center justify-center pointer-events-none bg-black/50';
    overlay.innerHTML = `<div class="text-center"><h1 class="text-6xl sm:text-8xl font-display text-yellow-400 jackpot-text mb-4">JACKPOT!</h1><p class="text-4xl text-white">+${amount.toFixed(2)} ${TOKEN_NAME}</p></div>`;
    document.body.appendChild(overlay);
    setTimeout(() => { document.body.classList.remove('jackpot-active'); overlay.remove(); }, 5000);
};


// --- API / Authentication ---
// ... (Login, Register, Logout, Balance functions remain the same) ...

async function showLogin() {
    // Styling updated to match the new dark blue background
    appContainer.innerHTML = `
        <div class="p-8 text-white bg-black/30 min-h-screen">
            <div class="max-w-md mx-auto">
                <h2 class="text-4xl sm:text-5xl font-display text-yellow-500 mb-8 text-center">ROYAL CASINO</h2>
                <p class="text-red-400 mb-4 text-center">You must log in to play.</p>
                <input id="username" placeholder="Username" class="w-full p-3 rounded mb-3 bg-slate-700 text-white border border-yellow-500/20">
                <input id="password" type="password" placeholder="Password" class="w-full p-3 rounded mb-4 bg-slate-700 text-white border border-yellow-500/20">
                <div class="flex gap-3">
                    <button onclick="login()" class="flex-1 bg-yellow-500 hover:bg-yellow-600 py-3 rounded-full font-bold text-lg transition text-slate-900">Login</button>
                    <button onclick="register()" class="flex-1 bg-purple-600 hover:bg-purple-700 py-3 rounded-full font-bold text-lg transition">Sign Up</button>
                </div>
                <p id="auth-msg" class="mt-4 text-red-400 h-6 text-center"></p>
            </div>
        </div>`;
}

window.login = async () => {
    const u = document.getElementById('username').value.trim();
    const p = document.getElementById('password').value;
    const res = await fetch('login.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'login', username: u, password: p})});
    const data = await res.json();
    if (data.success) {
        state.isAuthReady = true;
        await loadBalance();
        renderApp();
    } else {
        document.getElementById('auth-msg').textContent = data.msg || 'Login failed';
    }
};

window.register = async () => {
    const u = document.getElementById('username').value.trim();
    const p = document.getElementById('password').value;
    const res = await fetch('login.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'register', username: u, password: p})});
    const data = await res.json();
    if (data.success) {
        await login();
    } else {
        document.getElementById('auth-msg').textContent = data.msg || 'Register failed';
    }
};

window.logout = async () => {
    await fetch('logout.php');
    location.reload();
};

async function loadBalance() {
    state.isLoading = true;
    renderApp();
    const res = await fetch('api/balance.php');
    const data = await res.json();
    if (data.balance !== undefined) {
        state.balance = parseFloat(data.balance).toFixed(2);
        state.isLoading = false;
    } else if (data.error === 'Not logged in') {
        state.isAuthReady = false;
        showLogin();
        return;
    } else {
        state.error = data.error || 'Failed to load balance.';
        state.isLoading = false;
    }
    state.isAuthReady = true;
    renderApp();
}

async function deductBet(amount) {
    const res = await fetch('api/bet.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({amount})});
    const data = await res.json();
    if (!data.success) throw new Error(data.insufficient ? 'Insufficient funds' : 'Bet failed');
    await loadBalance();
}

async function addWin(amount) {
    await fetch('api/win.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({amount})});
    await loadBalance();
}


// --- LOBBY RENDERERS ---

// Helper function to render Lucide Icons
const Icon = (name, classes = 'w-4 h-4') => {
    const iconFn = lucide[name];
    if (iconFn) return iconFn({ class: classes }).outerHTML;
    return '';
};

const renderLobbyHeader = () => {
    return `
        <div class="bg-black/30 backdrop-blur-sm border-b border-white/10 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 py-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        ${Icon('crown', 'w-8 h-8 text-yellow-400')}
                        <h1 class="text-2xl font-bold bg-gradient-to-r from-yellow-400 to-orange-500 bg-clip-text text-transparent">
                            Royal Casino
                        </h1>
                    </div>
                    <div class="flex items-center gap-4">
                        <button onclick="setCurrentPage('purchase')" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-full font-bold text-sm">+ Add</button>
                        <div class="bg-gradient-to-r from-yellow-500 to-orange-500 px-4 py-2 rounded-full font-bold shadow-lg text-slate-900">
                            ${parseFloat(state.balance).toLocaleString()} ${TOKEN_NAME}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
};

const renderHeroBanner = () => {
    return `
        <div class="relative overflow-hidden bg-gradient-to-r from-pink-600 via-purple-600 to-indigo-600 mx-4 mt-4 rounded-2xl shadow-2xl">
            <div class="absolute inset-0 bg-black/20"></div>
            <div class="relative px-6 py-8">
                <div class="flex items-center gap-2 mb-2">
                    ${Icon('sparkles', 'w-5 h-5 text-yellow-300 animate-pulse')}
                    <span class="text-yellow-300 font-semibold text-sm uppercase tracking-wide">
                        Welcome Bonus
                    </span>
                </div>
                <h2 class="text-3xl font-bold mb-2">Deposit Match!</h2>
                <p class="text-white/90 mb-4">Up to 200% match on your first deposit</p>
                <button onclick="setCurrentPage('purchase')" class="bg-white text-purple-900 px-6 py-2 rounded-full font-bold hover:bg-yellow-300 transition-all transform hover:scale-105">
                    Claim Now
                </button>
            </div>
            <div class="absolute -right-8 -bottom-8 text-9xl opacity-20">🎰</div>
        </div>
    `;
};

const renderCategoryFilter = () => {
    return `
        <div class="px-4 mt-6">
            <div class="flex gap-2 overflow-x-auto pb-2 hide-scrollbar">
                ${CATEGORIES.map(cat => {
                    const isSelected = state.lobbyCategory === cat.id;
                    return `
                        <button
                            onclick="updateState({lobbyCategory: '${cat.id}'})"
                            class="flex items-center gap-2 px-4 py-2 rounded-full font-semibold whitespace-nowrap transition-all ${
                                isSelected
                                    ? 'bg-gradient-to-r from-yellow-500 to-orange-500 text-white shadow-lg scale-105'
                                    : 'bg-white/10 text-white/70 hover:bg-white/20'
                            }"
                        >
                            ${Icon(cat.icon, 'w-4 h-4')}
                            ${cat.name}
                        </button>
                    `;
                }).join('')}
            </div>
        </div>
    `;
};

const renderGameCard = (game) => {
    const disabledClass = game.disabled ? 'opacity-50 pointer-events-none' : '';
    const clickHandler = game.disabled ? '' : `onclick="setCurrentGame('${game.id}')"`;
    
    return `
        <div 
            ${clickHandler}
            class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl overflow-hidden cursor-pointer hover:scale-105 hover:border-yellow-500/50 transition-all ${disabledClass}"
        >
            <div class="relative bg-gradient-to-br from-purple-600/30 to-indigo-600/30 p-8 flex items-center justify-center">
                <div class="text-6xl">${game.image}</div>
                ${game.hot ? `
                    <div class="absolute top-2 right-2 bg-gradient-to-r from-orange-500 to-red-500 text-white text-xs px-2 py-1 rounded-full font-bold flex items-center gap-1">
                        ${Icon('trending-up', 'w-3 h-3')} HOT
                    </div>
                ` : ''}
            </div>
            <div class="p-3">
                <h4 class="font-bold mb-1">${game.name}</h4>
                <div class="flex justify-between items-center text-xs text-white/60">
                    <span>${game.minBet}-${game.maxBet} ${TOKEN_NAME}</span>
                    <span class="flex items-center gap-1">
                        <div class="w-2 h-2 ${game.disabled ? 'bg-red-400' : 'bg-green-400'} rounded-full ${!game.disabled ? 'animate-pulse' : ''}"></div>
                        ${game.disabled ? 'Coming Soon' : game.players}
                    </span>
                </div>
            </div>
        </div>
    `;
};

const renderHotGames = () => {
    const hotGames = GAMES_CATALOG.filter(g => g.hot);
    if (hotGames.length === 0) return '';
    
    return `
        <div class="px-4 mt-6">
            <div class="flex items-center gap-2 mb-3">
                ${Icon('flame', 'w-5 h-5 text-orange-400')}
                <h3 class="text-xl font-bold">🔥 Hot Games</h3>
            </div>
            <div class="flex gap-3 overflow-x-auto pb-2 hide-scrollbar">
                ${hotGames.map((game) => `
                    <div
                        onclick="${game.disabled ? '' : `setCurrentGame('${game.id}')`}"
                        class="flex-shrink-0 w-40 bg-gradient-to-br from-orange-500/20 to-red-500/20 backdrop-blur-sm border border-orange-500/30 rounded-xl p-4 cursor-pointer hover:scale-105 transition-transform ${game.disabled ? 'opacity-50' : ''}"
                    >
                        <div class="text-5xl mb-2">${game.image}</div>
                        <h4 class="font-bold text-sm mb-1">${game.name}</h4>
                        <div class="flex items-center gap-1 text-xs text-white/70">
                            <span>${game.disabled ? 'Maintenance' : `${game.players} playing`}</span>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
};

const renderGamesGrid = () => {
    const filteredGames = state.lobbyCategory === 'all' 
        ? GAMES_CATALOG
        : GAMES_CATALOG.filter(game => game.category === state.lobbyCategory);
        
    const categoryName = CATEGORIES.find(c => c.id === state.lobbyCategory)?.name || 'All Games';

    return `
        <div class="px-4 mt-6 pb-28">
            <h3 class="text-xl font-bold mb-3">${categoryName}</h3>
            <div class="grid grid-cols-2 gap-3">
                ${filteredGames.map(renderGameCard).join('')}
            </div>
        </div>
    `;
};

const renderLobbyFooter = () => {
    return `
        <div class="fixed bottom-0 left-0 right-0 bg-black/40 backdrop-blur-lg border-t border-white/10 max-w-lg mx-auto">
            <div class="flex justify-around items-center py-3 px-4">
                <button onclick="setCurrentPage('lobby')" class="flex flex-col items-center gap-1 text-yellow-400">
                    ${Icon('dices', 'w-6 h-6')}
                    <span class="text-xs font-semibold">Games</span>
                </button>
                <button onclick="alert('Not Implemented')" class="flex flex-col items-center gap-1 text-white/50 hover:text-white/80">
                    ${Icon('heart', 'w-6 h-6')}
                    <span class="text-xs">Favorites</span>
                </button>
                <button onclick="alert('Not Implemented')" class="flex flex-col items-center gap-1 text-white/50 hover:text-white/80">
                    ${Icon('trending-up', 'w-6 h-6')}
                    <span class="text-xs">Rewards</span>
                </button>
                <button onclick="logout()" class="flex flex-col items-center gap-1 text-white/50 hover:text-white/80">
                    ${Icon('log-out', 'w-6 h-6')}
                    <span class="text-xs">Logout</span>
                </button>
            </div>
        </div>
    `;
};

const renderLobby = () => {
    return `
        ${renderLobbyHeader()}
        <main class="min-h-screen pb-20">
            ${renderHeroBanner()}
            ${renderCategoryFilter()}
            ${state.lobbyCategory === 'all' ? renderHotGames() : ''}
            ${renderGamesGrid()}
        </main>
        ${renderLobbyFooter()}
    `;
};


// --- GAME RENDERERS (Updated) ---
const renderLoading = () => `<div class="text-center p-8 text-xl text-cyan-400">Connecting...</div>`;
const renderError = (msg) => `<div class="text-center p-8 text-red-500">Error: ${msg}</div>`;

// Dice Face
const DiceFace = (value) => {
    const patterns = {1: [5], 2: [0,10], 3: [0,5,10], 4: [0,2,8,10], 5: [0,2,5,8,10], 6: [0,2,3,7,8,10]};
    const dots = Array.from({length: 11}, (_, i) => `<div class="w-2.5 h-2.5 rounded-full ${patterns[value] && patterns[value].includes(i) ? 'bg-black' : 'opacity-0'}"></div>`).join('');
    return `<div class="dice-face dot grid grid-cols-3 gap-1 p-3 bg-white w-14 h-14 rounded-xl shadow-lg">${dots}</div>`;
};

// Bet Selector
const BetSelector = (gameId) => {
    const game = GAMES_CATALOG.find(g => g.id === gameId);
    if (!game) return '';
    
    let currentBet, setBetFunc;
    if (gameId === 'slots') { currentBet = state.slotsBetAmount; setBetFunc = 'setSlotsBet'; }
    else if (gameId === 'roulette') { currentBet = state.rouletteBetAmount; setBetFunc = 'setRouletteBet'; }
    else { currentBet = state.diceBetAmount; setBetFunc = 'setDiceBet'; }

    const betOptions = [1,2,3,4,5,6,7,8,9,10].filter(n => n >= game.minBet && n <= game.maxBet).map(n => n.toFixed(2));

    return `
        <div class="flex flex-col space-y-3">
            <div class="flex justify-center items-center space-x-2">
                <label class="text-white text-lg font-semibold">Bet (${TOKEN_NAME}):</label>
                <input type="number" min="${game.minBet}" max="${game.maxBet}" step="1" value="${parseFloat(currentBet).toFixed(2)}" onchange="${setBetFunc}(this.value)" class="w-28 p-2 rounded bg-slate-700 text-cyan-300 text-center font-mono border border-cyan-500/20">
            </div>
            <div class="flex flex-wrap justify-center gap-2">
                ${betOptions.map(amount => `<button onclick="${setBetFunc}('${amount}')" class="px-2 py-1 rounded-full font-bold text-sm transition ${parseFloat(amount) === parseFloat(currentBet) ? 'bg-yellow-500 text-slate-900' : 'bg-slate-600 hover:bg-slate-500 text-white'}">${amount}</button>`).join('')}
            </div>
        </div>
    `;
};

// Reel strip renderer
const createReelStrip = (finalSymbol, reelIndex) => {
    const stripSymbols = [...SYMBOLS, ...SYMBOLS, ...SYMBOLS, ...SYMBOLS]; 
    let html = stripSymbols.map(sym => `<div>${sym}</div>`).join('');
    html += `<div>${finalSymbol === '?' ? SYMBOLS[0] : finalSymbol}</div>`; 

    const isSpinning = state.isGameActive;
    const reelClass = isSpinning ? 'animate-spin-fast' : '';
    const winClass = state.isGameActive === false && finalSymbol !== '?' && state.slotsMessage.includes('Win') ? 'win-flash' : '';
    
    return `<div id="reel-${reelIndex}" class="slot-reel-strip text-yellow-500 ${reelClass} ${winClass}">${html}</div>`;
};

// Render Games
const renderSlotMachine = () => {
    return `<div class="text-center p-4 sm:p-6 bg-slate-800 rounded-2xl shadow-2xl border-4 border-cyan-400/80 max-w-lg mx-auto">
        <h2 class="text-3xl font-display text-yellow-500 mb-6">Lucky Sevens Slots</h2>
        <div class="relative flex justify-center my-6 space-x-4"> 
            ${state.slotsReels.map((r, i) => `
                <div class="slot-reel-container w-1/4 rounded-lg shadow-inner">
                    ${createReelStrip(r, i)}
                </div>
            `).join('')}
            <div id="win-line" class="${state.slotsMessage.includes('Win!') && !state.isGameActive ? 'win-line-active' : 'hidden'}"></div>
        </div>
        <p class="text-xl h-6 text-yellow-500 mb-6">${state.slotsMessage}</p>
        ${BetSelector('slots')}
        <button onclick="spinSlots()" ${state.isGameActive || state.balance < state.slotsBetAmount ? 'disabled' : ''} class="mt-6 w-full bg-cyan-600 hover:bg-cyan-700 py-3 rounded-xl font-bold text-xl disabled:opacity-50 transition shadow-lg">
            Spin
        </button>
        <p class="text-sm text-cyan-400 mt-4">Match 3 for win! (7️⃣ pays 8x - RTP ~80%)</p>
    </div>`;
};

const renderRoulette = () => {
    const resultColor = state.rouletteResult === 'Red' ? 'text-red-500' : state.rouletteResult === 'Black' ? 'text-white' : 'text-green-500';
    const resultHtml = state.rouletteResult ? `<p class="text-3xl font-bold mb-4 ${resultColor}">${state.rouletteResult} Wins!</p>` : '';
    const wheelHtml = state.isGameActive 
        ? `<img src="images/roulette_wheel.svg" class="w-40 h-40 sm:w-48 sm:h-48 mx-auto mb-6 bg-slate-700 rounded-full animate-spin-slow" alt="Roulette Wheel Spinning">` 
        : `<img src="images/roulette_wheel.svg" class="w-40 h-40 sm:w-48 sm:h-48 mx-auto mb-6 bg-slate-700 rounded-full" alt="Roulette Wheel">`;
    
    return `<div class="text-center p-4 sm:p-6 bg-slate-800 rounded-2xl shadow-2xl border-4 border-cyan-400/80 max-w-lg mx-auto">
        <h2 class="text-3xl font-display text-yellow-500 mb-6">Roulette Royale</h2>
        ${resultHtml}
        ${wheelHtml}
        <p class="text-xl h-6 text-yellow-500 mb-6">${state.rouletteMessage}</p>
        ${BetSelector('roulette')}
        <div class="flex justify-center space-x-4 mt-6">
            <button onclick="handleBetRoulette('Red')" ${state.isGameActive || state.balance < state.rouletteBetAmount ? 'disabled' : ''} class="flex-1 bg-red-600 hover:bg-red-700 py-3 rounded-xl font-bold text-lg disabled:opacity-50">Bet Red</button>
            <button onclick="handleBetRoulette('Black')" ${state.isGameActive || state.balance < state.rouletteBetAmount ? 'disabled' : ''} class="flex-1 bg-black text-white border-2 border-white/50 hover:bg-gray-800 py-3 rounded-xl font-bold text-lg disabled:opacity-50">Bet Black</button>
        </div>
        <p class="text-sm text-cyan-400 mt-4">1.5x payout (house edge 33% - RTP ~67%)</p>
    </div>`;
};

const renderDiceRoll = () => {
    const isRolling = state.isGameActive;
    const diceClass = isRolling ? 'dice-rolling' : '';
    
    const resultHtml = state.diceRollResult && state.diceRollResult[0] !== 0 ? 
        `<div class="flex justify-center space-x-4 mb-4">${DiceFace(state.diceRollResult[0]).replace('dice-face', `dice-face ${diceClass}`)}${DiceFace(state.diceRollResult[1]).replace('dice-face', `dice-face ${diceClass}`)}</div><p class="text-3xl font-bold text-yellow-500 mb-4">Total: ${state.diceRollResult[0] + state.diceRollResult[1]}</p>` : 
        `<p class="text-6xl text-cyan-400 mb-6"><span class="${diceClass}">🎲</span> <span class="${diceClass}">🎲</span></p>`;
        
    return `<div class="text-center p-4 sm:p-6 bg-slate-800 rounded-2xl shadow-2xl border-4 border-cyan-400/80 max-w-lg mx-auto">
        <h2 class="text-3xl font-display text-yellow-500 mb-6">Dice Roll</h2>
        ${resultHtml}
        <p class="text-xl h-6 text-yellow-500 mb-6">${state.diceMessage}</p>
        ${BetSelector('dice')}
        <div class="flex justify-center space-x-4 mt-6">
            <button onclick="rollDice('High')" ${state.isGameActive || state.balance < state.diceBetAmount ? 'disabled' : ''} class="flex-1 bg-green-600 hover:bg-green-700 py-3 rounded-xl font-bold text-lg disabled:opacity-50">High (7+)</button>
            <button onclick="rollDice('Low')" ${state.isGameActive || state.balance < state.diceBetAmount ? 'disabled' : ''} class="flex-1 bg-red-600 hover:bg-red-700 py-3 rounded-xl font-bold text-lg disabled:opacity-50">Low (6-)</button>
        </div>
        <p class="text-sm text-cyan-400 mt-4">1.5x payout (house edge 33% - RTP ~67%)</p>
    </div>`;
};


// Add Funds Page
const renderTokenPurchase = () => {
    return `<div class="text-center p-2 bg-slate-800 rounded-2xl shadow-2xl border-4 border-cyan-400/80 max-w-lg mx-auto min-h-[400px]">
        <h2 class="text-3xl font-display text-cyan-400 mb-6 mt-4">Add ${TOKEN_NAME}</h2>
        <div class="bg-slate-700 p-4 rounded-xl shadow-inner mb-6 space-y-4 text-left token-info h-96 overflow-y-auto">
            <p class="text-lg text-yellow-500 font-semibold border-b pb-2">Simulation Details</p>
            <div><h3 class="font-bold text-white mb-1">1. Buy BNCL (Sim)</h3><p class="text-sm text-slate-400 mb-2">Contract: <code class="text-green-400">${BNCL_CONTRACT}</code></p></div>
            <div><h3 class="font-bold text-white mb-1">2. Transfer (Sim)</h3><p class="text-sm text-slate-400 mb-2">Wallet: <code class="text-green-400">${BNCL_WALLET}</code></p></div>
            <div><h3 class="font-bold text-white mb-1">3. E-Transfer (Sim)</h3><p class="text-sm text-slate-400 mb-2">Email: <code class="text-green-400">${ETRANSFER_EMAIL}</code></p></div>
            <div class="bg-red-900/40 p-3 rounded-lg border border-red-500"><h3 class="font-bold text-red-400 mb-1">Note: Include username in memo!</h3></div>
            <p class="text-red-400 text-xs italic">Demo: Adds ${SIMULATED_TOPUP} ${TOKEN_NAME} instantly.</p>
        </div>
        <button onclick="simulateTopUp()" class="w-full bg-green-600 hover:bg-green-700 py-3 rounded-xl font-bold text-xl">Add ${SIMULATED_TOPUP} ${TOKEN_NAME}</button>
        <button onclick="setCurrentPage('lobby')" class="mt-4 w-full bg-slate-600 py-3 rounded-xl font-bold">Back to Lobby</button>
    </div>`;
};

window.simulateTopUp = async () => {
    updateState({isLoading: true});
    try {
        await addWin(SIMULATED_TOPUP);
        updateState({currentPage: 'lobby', lobbyCategory: 'all'}); // Navigate back to lobby after top-up
    } catch (e) {
        updateState({error: 'Top-up failed.'});
    }
};

// --- Game Logic ---

// Bet Setters
window.setSlotsBet = (v) => { const val = Math.round(parseFloat(v) || MIN_BET); updateState({slotsBetAmount: Math.max(MIN_BET, Math.min(MAX_BET, val))}); };
window.setRouletteBet = (v) => { const val = Math.round(parseFloat(v) || MIN_BET); updateState({rouletteBetAmount: Math.max(MIN_BET, Math.min(MAX_BET, val))}); };
window.setDiceBet = (v) => { const val = Math.round(parseFloat(v) || MIN_BET); updateState({diceBetAmount: Math.max(MIN_BET, Math.min(MAX_BET, val))}); };

// NEW Navigation Handlers
window.setCurrentPage = (page) => updateState({currentPage: page, isGameActive: false, rouletteResult: null, diceRollResult: null});
window.setCurrentGame = (gameId) => updateState({currentPage: 'game', currentGame: gameId, isGameActive: false, rouletteResult: null, diceRollResult: null});

// Slot Logic (includes the crash fix)
const stopReel = (reelElement, finalSymbol, delay) => {
    return new Promise(resolve => {
        setTimeout(() => {
            const reelStrip = reelElement.querySelector('.slot-reel-strip');
            if (!reelStrip) return resolve();

            const targetSymbolIndex = SYMBOLS.indexOf(finalSymbol);
            const fullStripCount = 4 * SYMBOLS.length;
            const stopIndex = fullStripCount + targetSymbolIndex; 
            const targetY = stopIndex * SYMBOL_HEIGHT;

            reelStrip.style.transition = `transform 1.2s cubic-bezier(0.2, 0.8, 0.4, 1)`; 
            reelStrip.style.transform = `translateY(-${targetY}px)`;
            reelStrip.classList.remove('animate-spin-fast');

            reelStrip.addEventListener('transitionend', function handler() {
                reelStrip.removeEventListener('transitionend', handler);
                resolve();
            }, { once: true });

        }, delay);
    });
};

window.spinSlots = async () => {
    const bet = state.slotsBetAmount;
    if (state.isGameActive || state.balance < bet) return;
    
    playSound('spin');
    // First update: Start spinning UI and disable button
    updateState({isGameActive: true, slotsMessage: `Spinning ${bet.toFixed(2)} ${TOKEN_NAME}...`, slotsReels: ['?', '?', '?']});
    
    try {
        await deductBet(bet);
        
        finalReels = [
            SYMBOLS[Math.floor(Math.random() * SYMBOLS.length)], 
            SYMBOLS[Math.floor(Math.random() * SYMBOLS.length)], 
            SYMBOLS[Math.floor(Math.random() * SYMBOLS.length)]
        ];
        
        // Second update (CRASH FIX): Re-render with final reels set, 
        // but 'isGameActive' keeps the CSS animation running until the next update.
        updateState({slotsReels: finalReels});

        const reelElements = document.querySelectorAll('.slot-reel-container');
        
        await Promise.all([
            stopReel(reelElements[0], finalReels[0], 500),
            stopReel(reelElements[1], finalReels[1], 1000),
            stopReel(reelElements[2], finalReels[2], 1500)
        ]);
        
        await determineSlotWin(finalReels, bet);
        
    } catch (e) {
        updateState({isGameActive: false, slotsMessage: 'Insufficient funds!'});
    }
};

const determineSlotWin = async (reels, bet) => {
    let profit = 0;
    if (reels[0] === reels[1] && reels[1] === reels[2]) {
        const sym = reels[0];
        profit = sym === '7️⃣' ? bet * 7 : sym === '🔔' ? bet * 4 : sym === '🍒' ? bet * 2 : bet * 1; 
    }
    
    // We set isGameActive to false here to remove the spinning class and trigger win-flash/win-line
    updateState({isGameActive: false}); 
    
    if (profit > 0) {
        const total = profit + bet;
        await addWin(total);
        playSound(profit >= bet * 5 ? 'jackpot' : 'win');
        if (profit >= bet * 5) {
            showJackpotCelebration(total);
        } else {
            setTimeout(() => {
                // Clear the win animation (forces re-render)
                updateState({slotsMessage: `Win! +${total.toFixed(2)} ${TOKEN_NAME}`}); 
            }, 3000); 
        }
        updateState({slotsMessage: `Win! +${total.toFixed(2)} ${TOKEN_NAME}`});
    } else {
        updateState({slotsMessage: 'No win. Try again!'});
    }
};

window.handleBetRoulette = async (color) => {
    const bet = state.rouletteBetAmount;
    if (state.isGameActive || state.balance < bet) return;
    playSound('spin');
    updateState({isGameActive: true, rouletteResult: null, rouletteMessage: `Bet ${bet.toFixed(2)} ${TOKEN_NAME} on ${color}...`});
    try {
        await deductBet(bet);
        setTimeout(async () => {
            const roll = Math.floor(Math.random() * 37);
            const result = roll === 0 ? 'Green' : roll % 2 === 0 ? 'Red' : 'Black';
            const won = result.toUpperCase() === color.toUpperCase();
            let winAmount = 0;
            if (won) {
                winAmount = bet * 1.5; 
                await addWin(winAmount);
                playSound('win');
            }
            updateState({isGameActive: false, rouletteResult: result, rouletteMessage: won ? `Win ${winAmount.toFixed(2)} ${TOKEN_NAME}!` : `Lost on ${result}.`});
        }, 6000);
    } catch (e) {
        updateState({isGameActive: false, rouletteMessage: 'Insufficient funds!'});
    }
};

window.rollDice = async (guess) => {
    const bet = state.diceBetAmount;
    if (state.isGameActive || state.balance < bet) return;
    updateState({isGameActive: true, diceMessage: 'Rolling...', diceRollResult: [0, 0]}); 
    try {
        await deductBet(bet);
        setTimeout(async () => {
            const d1 = Math.floor(Math.random() * 6) + 1;
            const d2 = Math.floor(Math.random() * 6) + 1;
            const total = d1 + d2;
            const won = (guess === 'High' && total >= 7) || (guess === 'Low' && total <= 6);
            let winAmount = 0;
            if (won) {
                winAmount = bet * 1.5; 
                await addWin(winAmount);
                playSound('win');
            }
            updateState({isGameActive: false, diceRollResult: [d1, d2], diceMessage: won ? `Win ${winAmount.toFixed(2)} ${TOKEN_NAME}!` : `Total ${total}. Lost.`});
        }, 2000);
    } catch (e) {
        updateState({isGameActive: false, diceMessage: 'Insufficient funds!'});
    }
};

// --- Main App Logic ---

const renderApp = () => {
    if (!state.isAuthReady) { showLogin(); return; }

    if (state.currentPage === 'purchase') { appContainer.innerHTML = renderTokenPurchase(); return; }
    if (state.currentPage === 'lobby') { appContainer.innerHTML = renderLobby(); return; }

    // Render Game Page
    let gameContent;
    if (state.isLoading) gameContent = renderLoading();
    else if (state.error) gameContent = renderError(state.error);
    else if (state.currentGame === 'slots') gameContent = renderSlotMachine();
    else if (state.currentGame === 'roulette') gameContent = renderRoulette();
    else if (state.currentGame === 'dice') gameContent = renderDiceRoll();
    else gameContent = renderError('Game not found.');
    
    // Header/Footer for Game Page
    const gameHeader = `<header class="text-center py-4 bg-black/30 backdrop-blur-sm border-b border-white/10 sticky top-0 z-50 flex justify-between items-center px-4 max-w-lg mx-auto">
        <button onclick="setCurrentPage('lobby')" class="text-white/70 hover:text-white">${Icon('arrow-left', 'w-6 h-6')}</button>
        <h1 class="text-xl font-display text-yellow-500">${GAMES_CATALOG.find(g => g.id === state.currentGame)?.name || 'Game'}</h1>
        <div class="bg-gradient-to-r from-yellow-500 to-orange-500 px-3 py-1 rounded-full font-bold shadow-lg text-sm text-slate-900">${state.balance} ${TOKEN_NAME}</div>
    </header>`;
    
    const gameFooter = `<footer class="mt-8 pt-4 border-t border-slate-700 text-center text-xs text-slate-500 p-4">RTP ~80% | <button onclick="logout()" class="bg-red-600 hover:bg-red-700 py-1 px-3 rounded text-white font-bold ml-2">Logout</button></footer>`;
    
    appContainer.innerHTML = `<div class="min-h-screen pb-20">${gameHeader} <div class="p-4">${gameContent}</div> ${gameFooter}</div> ${renderLobbyFooter()}`;
};

// Start
document.addEventListener('DOMContentLoaded', () => {
    if (document.cookie.includes('PHPSESSID')) loadBalance();
    else showLogin();
});
