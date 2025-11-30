<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Big Nickel Slots</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; }
        .font-display { font-family: 'Righteous', cursive; }
        .dot { display: flex; align-items: center; justify-content: center; }
        
        @keyframes spin-fast { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .animate-spin-fast { animation: spin-fast 0.05s linear infinite; }

        /* Roulette Spin Animation - Apply directly to the image */
        @keyframes spin-slow { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .animate-spin-slow { animation: spin-slow 1s linear infinite; animation-timing-function: linear; } /* Added linear timing */

        /* Custom scrollbar for token info block */
        .token-info::-webkit-scrollbar { width: 6px; }
        .token-info::-webkit-scrollbar-track { background: #1e293b; }
        .token-info::-webkit-scrollbar-thumb { background: #38bdf8; border-radius: 3px; }
        .token-info::-webkit-scrollbar-thumb:hover { background: #0ea5e9; }
    </style>
</head>
<body class="min-h-screen p-4 sm:p-8 flex items-start justify-center">
    <?php require 'config.php'; ?>

    <div id="app-container" class="w-full max-w-lg mx-auto bg-slate-800 rounded-2xl shadow-2xl p-6 border-4 border-cyan-400/80">
        <div class="text-center p-8 text-xl text-cyan-400">Loading Application...</div>
    </div>
    
<script>
    // --- Simulation Constants ---
    const INITIAL_BALANCE = 500;
    const TOKEN_NAME = 'BNCL'; // Big Nickel Token
    const MIN_BET = 0.00001;
    const MAX_BET = 10.00;
    const BNCL_WALLET_ADDRESS = "0xBNCL...DemoWalletAddress...42"; // Simulated Wallet Address
    const ETRANSFER_EMAIL = "etransfer@bignickelcasino.io"; // Simulated E-transfer email
    const SIMULATED_TOPUP_AMOUNT = 500; // Amount added when "Add Funds" is clicked

    const SYMBOL_MAP = {
        '🍒': 'Cherry',
        '🍋': 'Lemon',
        '7️⃣': 'Jackpot',
        '🔔': 'Bell'
    };
    
    let state = {
        userId: null,
        balance: INITIAL_BALANCE,
        isLoading: true,
        error: null,
        isAuthReady: false,
        currentGame: 'slots', // Can be 'slots', 'roulette', 'dice', or 'purchase'
        isGameActive: false,
        slotsReels: ['?', '?', '?'],
        slotsMessage: 'Place your bet and spin!',
        slotsBetAmount: 1.00, // Default bet is 1.00 BNCL
        rouletteResult: null,
        rouletteMessage: 'Place your bet on Red or Black.',
        rouletteBetAmount: 5.00, // Default bet is 5.00 BNCL
        diceRollResult: null,
        diceMessage: 'Guess if the roll will be High (7+) or Low (6-).',
        diceBetAmount: 2.50, // Default bet is 2.50 BNCL
    };

    const appContainer = document.getElementById('app-container');

    // ────────────────────── AUTH ──────────────────────
    function showLogin() {
        appContainer.innerHTML = `
            <div class="text-center p-8 text-white">
                <h2 class="text-5xl font-display text-yellow-500 mb-8">BIG NICKEL SLOTS</h2>
                <p class="text-red-400 mb-4">You must log in to play.</p>
                <input id="username" placeholder="Username" class="w-full p-3 rounded mb-3 bg-slate-700 text-white">
                <input id="password" type="password" placeholder="Password" class="w-full p-3 rounded mb-4 bg-slate-700 text-white">
                <div class="flex gap-3">
                    <button onclick="login()" class="flex-1 bg-cyan-600 hover:bg-cyan-700 py-3 rounded font-bold text-lg transition">Login</button>
                    <button onclick="register()" class="flex-1 bg-green-600 hover:bg-green-700 py-3 rounded font-bold text-lg transition">Sign Up</button>
                </div>
                <p id="auth-msg" class="mt-4 text-red-400 h-6"></p>
            </div>`;
    }

    async function login() {
        const u = document.getElementById('username').value.trim();
        const p = document.getElementById('password').value;
        const res = await fetch('login.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'login',username:u,password:p})});
        const data = await res.json();
        if(data.success){ 
            state.isAuthReady = true; 
            await loadBalance(); 
            renderApp(); 
        } else {
            document.getElementById('auth-msg').textContent = data.msg || "Login failed";
        }
    }

    async function register() {
        const u = document.getElementById('username').value.trim();
        const p = document.getElementById('password').value;
        const res = await fetch('login.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'register',username:u,password:p})});
        const data = await res.json();
        if(data.success) {
            await login(); 
        } else {
            document.getElementById('auth-msg').textContent = data.msg || "Register failed";
        }
    }

    async function logout() {
        await fetch('logout.php');
        location.reload();
    }

    // ────────────────────── BALANCE HELPERS ──────────────────────
    async function loadBalance() {
        state.isLoading = true;
        renderApp(); 

        const res = await fetch('api/balance.php');
        const data = await res.json();
        
        if (data.balance !== undefined) {
            state.balance = parseFloat(data.balance).toFixed(5);
            state.isLoading = false;
        } else if (data.error === 'Not logged in') {
            state.isAuthReady = false;
            showLogin();
            return; 
        } else {
            state.error = data.error || "Failed to load BNCL from API.";
            state.isLoading = false;
        }
        state.isAuthReady = true; 
        renderApp();
    }

    async function deductBet(amount) {
        const res = await fetch('api/bet.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({amount})});
        const data = await res.json();
        if (!data.success && data.insufficient) throw new Error("Insufficient funds");
        if (!data.success) throw new Error("Bet failed");
        await loadBalance();
    }

    async function addWin(amount) {
        await fetch('api/win.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({amount})});
        await loadBalance();
    }

    // ────────────────────── RENDERING HELPERS ──────────────────────
    const updateState = (newState) => { 
        Object.assign(state, newState); 
        renderApp(); 
    };
    
    const renderLoading = () => `<div class="text-center p-8 text-xl text-cyan-400">
        <svg class="animate-spin h-8 w-8 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p>Connecting to database...</p>
    </div>`;
    
    const renderError = (msg) => `<div class="text-center p-8 text-red-500">Error: ${msg}</div>`;

    const DiceFace = (value) => { 
        const patterns = {
            1: [5], 2: [0, 10], 3: [0, 5, 10], 4: [0, 2, 8, 10], 5: [0, 2, 5, 8, 10], 6: [0, 2, 3, 7, 8, 10]
        };
        const dots = [];
        for(let i = 0; i < 11; i++) {
            dots.push(`<div class="w-2 h-2 rounded-full ${patterns[value].includes(i) ? 'bg-black' : 'opacity-0'}"></div>`);
        }
        return `<div class="grid grid-cols-3 gap-1 p-2 bg-white w-12 h-12 rounded-lg shadow-lg">${dots.join('')}</div>`;
    };

    const renderGameButton = (name) => {
        const isCurrent = state.currentGame === name.toLowerCase();
        return `<button onclick="setCurrentGame('${name.toLowerCase()}')" 
            class="flex-1 py-3 rounded font-bold text-lg transition ${isCurrent ? 'bg-yellow-500 text-slate-900' : 'bg-slate-700 hover:bg-slate-600 text-white'}">
            ${name}
        </button>`;
    };
    
    // Function to handle bet selection
    const BetSelector = (currentGame) => {
        let currentBet, setBetFunc;
        if (currentGame === 'slots') {
            currentBet = state.slotsBetAmount;
            setBetFunc = 'setSlotsBet';
        } else if (currentGame === 'roulette') {
            currentBet = state.rouletteBetAmount;
            setBetFunc = 'setRouletteBet';
        } else { // dice
            currentBet = state.diceBetAmount;
            setBetFunc = 'setDiceBet';
        }

        const betOptions = [
            MIN_BET.toFixed(5), 
            1.00.toFixed(2), 
            5.00.toFixed(2), 
            MAX_BET.toFixed(2)
        ];

        return `
            <div class="flex flex-col space-y-3">
                <div class="flex justify-center items-center space-x-2">
                    <label class="text-white text-lg font-semibold">Bet (${TOKEN_NAME}):</label>
                    <input type="number" min="${MIN_BET}" max="${MAX_BET}" step="0.00001" value="${currentBet.toFixed(5)}" onchange="${setBetFunc}(this.value)" 
                           class="w-32 p-2 rounded bg-slate-700 text-cyan-300 text-center font-mono">
                </div>
                <div class="flex justify-center space-x-2">
                    ${betOptions.map(amount => `
                        <button onclick="${setBetFunc}('${amount}')" 
                            class="text-xs px-2 py-1 rounded-full font-bold transition 
                            ${parseFloat(amount) === parseFloat(currentBet) ? 'bg-yellow-500 text-slate-900' : 'bg-slate-600 hover:bg-slate-500 text-white'}">
                            ${amount}
                        </button>
                    `).join('')}
                </div>
            </div>
        `;
    }

    const renderSlotMachine = () => {
        return `<div class="text-center">
            <div class="flex justify-center my-6 space-x-4">
                ${state.slotsReels.map(r => 
                    `<div class="text-6xl p-4 w-1/4 h-20 bg-slate-700 rounded-lg text-yellow-500 flex items-center justify-center ${state.isGameActive ? 'animate-spin-fast' : ''}">
                        ${r}
                    </div>`
                ).join('')}
            </div>
            <p class="text-xl h-6 text-yellow-500 mb-6">${state.slotsMessage}</p>
            ${BetSelector('slots')}
            <button onclick="spinSlots()" ${state.isGameActive || state.balance < state.slotsBetAmount ? 'disabled' : ''} 
                    class="mt-6 w-full bg-cyan-600 hover:bg-cyan-700 py-3 rounded-xl font-bold text-xl disabled:opacity-50 transition shadow-lg shadow-cyan-900/50">
                Spin
            </button>
            <p class="text-sm text-cyan-400 mt-4">Match 3 symbols for a big win! (7️⃣ pays highest)</p>
        </div>`;
    };

    const renderRoulette = () => {
        const resultTextColor = 
            state.rouletteResult === 'Red' ? 'text-red-500' : 
            state.rouletteResult === 'Black' ? 'text-white' : 
            'text-green-500';

        const resultHtml = state.rouletteResult 
            ? `<p class="text-3xl font-bold mb-4 ${resultTextColor}">${state.rouletteResult} Wins!</p>` 
            : '';
            
        const wheelHtml = state.isGameActive 
            ? `<img src="images/roulette-wheel.png" alt="Roulette Wheel" class="w-48 h-48 mx-auto mb-6 animate-spin-slow rounded-full"/>` // Spinning image
            : `<img src="images/roulette-wheel.png" alt="Roulette Wheel" class="w-48 h-48 mx-auto mb-6 rounded-full"/>`; // Static image
            
        return `<div class="text-center">
            ${resultHtml}
            ${wheelHtml}
            <p class="text-xl h-6 text-yellow-500 mb-6">${state.rouletteMessage}</p>
            ${BetSelector('roulette')}
            <div class="flex justify-center space-x-4 mt-6">
                <button onclick="handleBetRoulette('Red')" ${state.isGameActive || state.balance < state.rouletteBetAmount ? 'disabled' : ''} 
                    class="flex-1 bg-red-600 hover:bg-red-700 py-3 rounded-xl font-bold text-lg disabled:opacity-50 transition shadow-lg shadow-red-900/50">Bet Red</button>
                <button onclick="handleBetRoulette('Black')" ${state.isGameActive || state.balance < state.rouletteBetAmount ? 'disabled' : ''} 
                    class="flex-1 bg-black text-white border-2 border-white/50 hover:bg-gray-800 py-3 rounded-xl font-bold text-lg disabled:opacity-50 transition shadow-lg shadow-gray-900/50">Bet Black</button>
            </div>
            <p class="text-sm text-cyan-400 mt-4">Winning color pays 2x your bet.</p>
        </div>`;
    };

    const renderDiceRoll = () => {
        const resultHtml = state.diceRollResult 
            ? `<div class="flex justify-center space-x-4 mb-4">
                 ${DiceFace(state.diceRollResult[0])}
                 ${DiceFace(state.diceRollResult[1])}
               </div>
               <p class="text-3xl font-bold text-yellow-500 mb-4">Total: ${state.diceRollResult[0] + state.diceRollResult[1]}</p>`
            : `<p class="text-6xl text-cyan-400 mb-6 h-20">🎲🎲</p>`;
            
        return `<div class="text-center">
            ${resultHtml}
            <p class="text-xl h-6 text-yellow-500 mb-6">${state.diceMessage}</p>
            ${BetSelector('dice')}
            <div class="flex justify-center space-x-4 mt-6">
                <button onclick="rollDice('High')" ${state.isGameActive || state.balance < state.diceBetAmount ? 'disabled' : ''}
                    class="flex-1 bg-green-600 hover:bg-green-700 py-3 rounded-xl font-bold text-lg disabled:opacity-50 transition shadow-lg shadow-green-900/50">Guess High (7+)</button>
                <button onclick="rollDice('Low')" ${state.isGameActive || state.balance < state.diceBetAmount ? 'disabled' : ''}
                    class="flex-1 bg-red-600 text-white hover:bg-red-700 py-3 rounded-xl font-bold text-lg disabled:opacity-50 transition shadow-lg shadow-red-900/50">Guess Low (6-)</button>
            </div>
            <p class="text-sm text-cyan-400 mt-4">Winning guess pays 2x your bet.</p>
        </div>`;
    };

    // New render function for the "Add Funds" page
    const renderTokenPurchase = () => {
        return `<div class="text-center p-2">
            <h2 class="text-3xl font-display text-cyan-400 mb-6">Buy ${TOKEN_NAME} Tokens</h2>
            <div class="bg-slate-700 p-4 rounded-xl shadow-inner mb-6 space-y-4 text-left token-info h-96 overflow-y-auto">
                <p class="text-lg text-yellow-500 font-semibold border-b border-slate-600 pb-2">Purchase Simulation Details</p>

                <!-- Token Purchase via QuickSwap (Simulated) -->
                <div>
                    <h3 class="font-bold text-white mb-1">1. Purchase BNCL Token (Simulated)</h3>
                    <p class="text-sm text-slate-400 mb-2">In a real scenario, you would buy the BNCL token on a decentralized exchange like QuickSwap.</p>
                    <div class="bg-slate-800 p-3 rounded-lg font-mono text-sm text-green-400 break-all">
                        <span class="font-semibold text-white">Contract Address:</span> 0xBNCL...DemoContract...42
                    </div>
                </div>

                <!-- Token Transfer (Simulated) -->
                <div>
                    <h3 class="font-bold text-white mb-1">2. Transfer to Game Wallet (Simulated)</h3>
                    <p class="text-sm text-slate-400 mb-2">After purchase, you would send your BNCL tokens to this game wallet address to credit your account.</p>
                    <div class="bg-slate-800 p-3 rounded-lg font-mono text-sm text-green-400 break-all">
                        <span class="font-semibold text-white">Your Deposit Address:</span> ${BNCL_WALLET_ADDRESS}
                    </div>
                </div>

                <!-- E-Transfer Option (Simulated) -->
                <div>
                    <h3 class="font-bold text-white mb-1">3. E-transfer Option (Simulated)</h3>
                    <p class="text-sm text-slate-400 mb-2">For a simulated fiat purchase, you could use an E-transfer option.</p>
                    <div class="bg-slate-800 p-3 rounded-lg font-mono text-sm text-green-400 break-all">
                        <span class="font-semibold text-white">E-transfer Email:</span> ${ETRANSFER_EMAIL}
                    </div>
                </div>
                
                <p class="text-red-400 text-xs italic pt-2">Disclaimer: This is a demo. Clicking "Add Funds" below will instantly credit your account with ${SIMULATED_TOPUP_AMOUNT} ${TOKEN_NAME} for demonstration purposes only. No real money or crypto transaction will occur.</p>
            </div>
            
            <button onclick="simulateTopUp()" class="w-full bg-green-600 hover:bg-green-700 py-3 rounded-xl font-bold text-xl text-white transition shadow-lg shadow-green-900/50">
                Simulate Add ${SIMULATED_TOPUP_AMOUNT} ${TOKEN_NAME}
            </button>
            <button onclick="setCurrentGame('slots')" class="mt-4 w-full bg-slate-600 hover:bg-slate-500 py-3 rounded-xl font-bold text-lg text-white transition">
                Back to Slots
            </button>
        </div>`;
    };

    window.simulateTopUp = async () => {
        // In a real app, this would be a secure server-side call validating the transaction.
        updateState({isLoading: true});
        try {
            // Call the API to add funds
            await addWin(SIMULATED_TOPUP_AMOUNT);
            updateState({
                isLoading: false, 
                currentGame: 'slots', 
                slotsMessage: `+${SIMULATED_TOPUP_AMOUNT} ${TOKEN_NAME} added! Ready to play.`
            });
        } catch (e) {
            updateState({isLoading: false, error: "Failed to simulate top-up."});
        }
    };
    
    // ────────────────────── GAME LOGIC ──────────────────────
    // Ensure all setBet functions parse to float and apply limits
    window.setSlotsBet = (v) => {
        const val = parseFloat(v) || MIN_BET;
        const newBet = Math.max(MIN_BET, Math.min(MAX_BET, val));
        updateState({slotsBetAmount: newBet});
    };
    window.setDiceBet = (v) => {
        const val = parseFloat(v) || MIN_BET;
        const newBet = Math.max(MIN_BET, Math.min(MAX_BET, val));
        updateState({diceBetAmount: newBet});
    };
    window.setRouletteBet = (v) => {
        const val = parseFloat(v) || MIN_BET;
        const newBet = Math.max(MIN_BET, Math.min(MAX_BET, val));
        updateState({rouletteBetAmount: newBet});
    };

    window.setCurrentGame = (game) => updateState({currentGame: game, isGameActive: false, rouletteResult: null});

    window.spinSlots = async () => {    
        const BET_AMOUNT = state.slotsBetAmount;
        if (state.isGameActive || state.balance < BET_AMOUNT) return;
        
        updateState({isGameActive:true, slotsMessage:`Spinning ${BET_AMOUNT.toFixed(5)} ${TOKEN_NAME}...`, slotsReels:['🎰','🎰','🎰']});
        
        try {        
            await deductBet(BET_AMOUNT);                         
            
            const SPIN_DURATION = 2500; 
            
            setTimeout(() => { 
                const symbols = Object.keys(SYMBOL_MAP);
                const finalReels = [
                    symbols[Math.floor(Math.random() * symbols.length)],
                    symbols[Math.floor(Math.random() * symbols.length)],
                    symbols[Math.floor(Math.random() * symbols.length)],
                ];
                determineSlotWin(finalReels, BET_AMOUNT);
            }, SPIN_DURATION);
        } catch(e) {
            updateState({isGameActive:false, slotsMessage: e.message.includes("Insufficient") ? `Not enough ${TOKEN_NAME}!` : "Error"});
        }
    };

    const determineSlotWin = async (finalReels, BET_AMOUNT) => {
        let profit = 0;
        const [r1, r2, r3] = finalReels;
        
        if (r1 === r2 && r2 === r3) {
            if (r1 === '7️⃣') { profit = BET_AMOUNT * 15; } // Multiplier
            else if (r1 === '🔔') { profit = BET_AMOUNT * 7.5; }
            else if (r1 === '🍒') { profit = BET_AMOUNT * 5; }
            else if (r1 === '🍋') { profit = BET_AMOUNT * 4; }
        } 
        else if (r1 === r2) {
             profit = BET_AMOUNT * 1; // Double your bet (return bet + profit)
        }

        if (profit > 0) {
            const totalWin = profit + BET_AMOUNT;
            await addWin(totalWin);
            updateState({isGameActive: false, slotsReels: finalReels, slotsMessage: `💰 JACKPOT! You won ${totalWin.toFixed(5)} ${TOKEN_NAME}!`});
        } else {
            updateState({isGameActive: false, slotsReels: finalReels, slotsMessage: "Try again! You lost your bet."});
        }
    };
    
    window.handleBetRoulette = async (color) => {
        const BET_AMOUNT = state.rouletteBetAmount;
        if (state.isGameActive || state.balance < BET_AMOUNT) return;
        
        updateState({isGameActive:true, rouletteResult: null, rouletteMessage:`Betting ${BET_AMOUNT.toFixed(5)} ${TOKEN_NAME} on ${color.toUpperCase()}...`});
        
        try {
            await deductBet(BET_AMOUNT);
            setTimeout(async () => {
                const roll = Math.floor(Math.random() * 37); // 0-36
                const resultColor = (roll === 0) ? 'Green' : (roll % 2 === 0) ? 'Red' : 'Black';
                const youWon = (resultColor.toUpperCase() === color.toUpperCase());
                
                let winAmount = 0;
                if (youWon) {
                    winAmount = 2 * BET_AMOUNT; // 2x payout (returns original bet + profit)
                    await addWin(winAmount);
                }

                updateState({
                    isGameActive:false, 
                    rouletteResult: resultColor,
                    rouletteMessage: winAmount > 0 ? `Landed on ${resultColor}! You win ${winAmount.toFixed(5)} ${TOKEN_NAME}!` : `Landed on ${resultColor}. Better luck next time.`
                });
            }, 3000);
        } catch(e) { 
            updateState({isGameActive:false, rouletteMessage: e.message.includes("Insufficient") ? `Not enough ${TOKEN_NAME}!` : "Error"});
        }
    };

    window.rollDice = async (guess) => {
        const BET_AMOUNT = state.diceBetAmount;
        if (state.isGameActive || state.balance < BET_AMOUNT) return;
        updateState({isGameActive:true, diceMessage:`Rolling...`});
        try {
            await deductBet(BET_AMOUNT);
            setTimeout(async () => {
                const die1 = Math.floor(Math.random() * 6) + 1;
                const die2 = Math.floor(Math.random() * 6) + 1;
                const total = die1 + die2;
                
                let win = false;
                if (guess === 'High' && total >= 7) win = true;
                if (guess === 'Low' && total <= 6) win = true; 

                let winAmount = 0;
                if (win) {
                    winAmount = 2 * BET_AMOUNT; // 2x payout (returns original bet + profit)
                    await addWin(winAmount);
                }

                updateState({
                    isGameActive:false, 
                    diceRollResult: [die1, die2],
                    diceMessage: winAmount > 0 ? `Total is ${total}! You win ${winAmount.toFixed(5)} ${TOKEN_NAME}!` : `Total is ${total}. You lose.`
                });
            }, 2000);
        } catch(e) { 
            updateState({isGameActive:false, diceMessage: e.message.includes("Insufficient") ? `Not enough ${TOKEN_NAME}!` : "Error"});
        }
    };

    // ────────────────────── MAIN RENDER ──────────────────────
    const renderApp = () => {
        if (!state.isAuthReady) {
            showLogin();
            return;
        }

        if (state.currentGame === 'purchase') {
            appContainer.innerHTML = renderTokenPurchase();
            return;
        }

        const header = `<header class="text-center mb-6">
            <h1 class="text-4xl font-display text-yellow-500 border-b-4 border-yellow-500 inline-block pb-1 tracking-widest">
                BIG NICKEL SLOTS
            </h1>
            <div class="flex items-center justify-center space-x-4 mt-4">
                <p class="text-xl text-white">
                    ${TOKEN_NAME}: 
                    <span class="text-cyan-400 font-bold">${state.balance}</span>
                </p>
                <button onclick="setCurrentGame('purchase')" 
                    class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1 rounded-full font-bold transition">
                    + Add Funds
                </button>
            </div>
            <div class="flex justify-center gap-2 mt-4">
                ${renderGameButton('Slots')}
                ${renderGameButton('Roulette')}
                ${renderGameButton('Dice')}
            </div>
        </header>`;

        let gameContent;
        if (state.isLoading) {
            gameContent = renderLoading();
        } else if (state.error) {
            gameContent = renderError(state.error);
        } else {
            switch (state.currentGame) {
                case 'slots':
                    gameContent = renderSlotMachine();
                    break;
                case 'roulette':
                    gameContent = renderRoulette();
                    break;
                case 'dice':
                    gameContent = renderDiceRoll();
                    break;
                default:
                    gameContent = renderError("Game not found.");
            }
        }

        const footer = `<footer class="mt-8 pt-4 border-t border-slate-700">
            <p class="text-center text-xs text-slate-500">Simulation Game - ${TOKEN_NAME} are not real currency.</p>
            <!-- Logout button is present here -->
            <button onclick="logout()" class="mt-4 w-full bg-red-600 hover:bg-red-700 py-2 rounded font-bold text-sm text-white transition">
                Logout
            </button>
        </footer>`;
        
        appContainer.innerHTML = header + gameContent + footer;
    };

    // ────────────────────── START ──────────────────────
    const phpSessionActive = document.cookie.includes('PHPSESSID');

    if (phpSessionActive) {
        loadBalance();
    } else {
        showLogin();
    }
</script>
</body>
</html>
