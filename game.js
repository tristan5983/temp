// ======================================================
// GLOBAL STATE & CONSTANTS
// ======================================================

const API_ENDPOINT = '/api';
let currentUser = null;
let currentBet = 10.00;
let sceneInstance = null;
const SYMBOLS = [
    'symbol_seven', 'symbol_bar', 'symbol_diamond', 'symbol_crown', 
    'symbol_cherry', 'symbol_freespin', 'symbol_scatter', 'symbol_wild'
];

// ======================================================
// UTILITY FUNCTIONS (UI & ANIMATION)
// ======================================================

/**
 * Animates a value change over a duration, now using better formatting.
 */
function animateValue(id, start, end, duration) {
    const el = document.getElementById(id);
    if (!el) return;

    const range = end - start;
    let current = start;
    const increment = range !== 0 ? range / (duration / 16) : 0;

    // Use toLocaleString for better formatting (e.g., adds commas and two decimals)
    const formatter = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const timer = setInterval(() => {
        current += increment;
        if (
            (increment > 0 && current >= end) ||
            (increment < 0 && current <= end) ||
            increment === 0
        ) {
            current = end;
            clearInterval(timer);
        }
        el.textContent = formatter.format(current);
    }, 16);
}

/**
 * Updates the balance and bet displays across all relevant elements.
 */
function updateUI() {
    const balance = currentUser ? currentUser.balance : 0.00;
    const jackpot = currentUser ? currentUser.jackpot : 0.00;

    // Update Lobby
    document.getElementById('userBalance').textContent = balance.toFixed(2);
    animateValue('jackpotAmount', parseFloat(document.getElementById('jackpotAmount').textContent.replace(/,/g, '')), jackpot, 500);

    // Update Game
    document.getElementById('gameBalance').textContent = balance.toFixed(2);
    document.getElementById('gameJackpot').textContent = jackpot.toFixed(2);
    document.getElementById('betDisplay').textContent = currentBet.toFixed(2);
}

/**
 * Shows/hides different application screens.
 */
function showScreen(screenId) {
    document.getElementById('authContainer').style.display = screenId === 'auth' ? 'flex' : 'none';
    document.getElementById('lobbyContainer').style.display = screenId === 'lobby' ? 'flex' : 'none';
    document.getElementById('gameContainer').style.display = screenId === 'game' ? 'flex' : 'none';
    
    if (screenId !== 'game' && sceneInstance) {
        sceneInstance.scene.pause();
    } else if (screenId === 'game' && sceneInstance) {
        sceneInstance.scene.resume();
    }
}

// ======================================================
// AUTH & NAVIGATION FUNCTIONS
// ======================================================

function showRegister() {
    document.getElementById('loginForm').classList.add('hidden');
    document.getElementById('registerForm').classList.remove('hidden');
    document.getElementById('errorMessage').classList.add('hidden');
}

function showLogin() {
    document.getElementById('registerForm').classList.add('hidden');
    document.getElementById('loginForm').classList.remove('hidden');
    document.getElementById('errorMessage').classList.add('hidden');
}

function showLobby() {
    showScreen('lobby');
    updateUI();
}

function playGame() {
    showScreen('game');
    updateUI();
}

function backToLobby() {
    showScreen('lobby');
    updateUI();
}

function showMessage(message) {
    const el = document.getElementById('errorMessage');
    el.textContent = message;
    el.classList.remove('hidden');
}

function toggleControls(enable) {
    document.getElementById('spinButton').disabled = !enable;
}

// ======================================================
// FUNDS MODAL FUNCTIONS
// ======================================================

function showFundsModal() {
    document.getElementById('fundsModal').classList.add('show');
    document.getElementById('fundsError').classList.add('hidden');
}

function closeFundsModal() {
    document.getElementById('fundsModal').classList.remove('show');
    document.getElementById('fundsAmount').value = '';
}

async function handleFundsAction(action) {
    const amount = parseFloat(document.getElementById('fundsAmount').value);
    const fundsErrorEl = document.getElementById('fundsError');
    fundsErrorEl.classList.add('hidden');

    if (isNaN(amount) || amount <= 0) {
        fundsErrorEl.textContent = 'Please enter a valid amount.';
        fundsErrorEl.classList.remove('hidden');
        return;
    }
    
    if (action === 'withdraw' && amount > currentUser.balance) {
        fundsErrorEl.textContent = 'Insufficient balance for withdrawal.';
        fundsErrorEl.classList.remove('hidden');
        return;
    }

    try {
        const res = await fetch(`${API_ENDPOINT}/${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: currentUser.username, amount })
        });

        const data = await res.json();

        if (res.ok) {
            currentUser = data.user;
            updateUI();
            closeFundsModal();
        } else {
            fundsErrorEl.textContent = data.message || `Failed to ${action} funds.`;
            fundsErrorEl.classList.remove('hidden');
        }
    } catch (error) {
        fundsErrorEl.textContent = `Error connecting to server.`;
        fundsErrorEl.classList.remove('hidden');
    }
}

function addFunds() {
    handleFundsAction('deposit');
}

function withdrawFunds() {
    handleFundsAction('withdraw');
}

// ======================================================
// CORE API INTERACTIONS
// ======================================================

async function login() {
    const username = document.getElementById('loginUsername').value;
    const password = document.getElementById('loginPassword').value;
    showMessage(''); // Clear previous error

    try {
        const res = await fetch(`${API_ENDPOINT}/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });

        const data = await res.json();

        if (res.ok) {
            currentUser = data.user;
            showLobby();
        } else {
            showMessage(data.message || 'Login failed. Please check credentials.');
        }
    } catch (error) {
        showMessage('Error connecting to server.');
    }
}

async function register() {
    const username = document.getElementById('registerUsername').value;
    const password = document.getElementById('registerPassword').value;
    showMessage(''); // Clear previous error

    try {
        const res = await fetch(`${API_ENDPOINT}/register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });

        const data = await res.json();

        if (res.ok) {
            currentUser = data.user;
            showLobby();
        } else {
            showMessage(data.message || 'Registration failed.');
        }
    } catch (error) {
        showMessage('Error connecting to server.');
    }
}

function logout() {
    currentUser = null;
    showScreen('auth');
}

// ======================================================
// GAME ACTIONS
// ======================================================

function changeBet(amount) {
    const newBet = currentBet + amount;
    if (newBet >= 5.00 && newBet <= 50.00) {
        currentBet = newBet;
        document.getElementById('betDisplay').textContent = currentBet.toFixed(2);
    }
}

async function spin() {
    if (!currentUser || currentUser.balance < currentBet || sceneInstance.isSpinning) {
        if (!sceneInstance.isSpinning) {
             // Use the result overlay to show an error
             sceneInstance.showResultOverlay("INSUFFICIENT FUNDS", 1000); 
        }
        return;
    }

    toggleControls(false);
    
    // Deduct bet immediately for responsive feel
    currentUser.balance -= currentBet;
    updateUI(); 

    try {
        const res = await fetch(`${API_ENDPOINT}/spin`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: currentUser.username, bet: currentBet })
        });

        const result = await res.json();

        if (res.ok) {
            sceneInstance.startSpin(result);
        } else {
            // Revert balance if server rejects the spin for a non-fund reason
            currentUser.balance += currentBet;
            updateUI();
            sceneInstance.showResultOverlay(result.message || "SPIN ERROR", 2000);
            toggleControls(true);
        }

    } catch (error) {
        // Revert balance on connection failure
        currentUser.balance += currentBet;
        updateUI();
        sceneInstance.showResultOverlay("NETWORK ERROR", 2000);
        toggleControls(true);
    }
}

// ======================================================
// PHASER GAME SCENE
// ======================================================

class SlotMachineScene extends Phaser.Scene {
    constructor() {
        super('SlotMachineScene');
        this.isSpinning = false;
        this.reels = [];
        this.reelStops = [];
        this.glowFrames = [];
        this.winLines = [];
        this.gameHeight = 600;
        this.gameWidth = 800;
    }

    preload() {
        // Load symbols
        SYMBOLS.forEach(symbol => {
            this.load.image(symbol, `/textures/${symbol}.png`);
        });
        this.load.image('slot_frame_ui', '/textures/slot_frame_ui.png');
        this.load.image('coin', '/textures/coin.png');
        // The newly created asset (optional - can use rectangle for glow)
        // this.load.image('frame', '/textures/frame_glow.png'); 
    }

    create() {
        sceneInstance = this;

        const { gameWidth, gameHeight } = this;
        const reelCount = 3;
        const symbolSize = 150;
        
        // Updated reel positions to match the background windows
        const reelXPositions = [
            gameWidth / 2 - 177.5,  // Left reel
            gameWidth / 2,           // Center reel  
            gameWidth / 2 + 177.5    // Right reel
        ];
        
        // Vertical center for the reels
        const reelCenterY = gameHeight / 2 - 15; // Adjusted to match background window position
        
        // 1. Background Frame
        const frame = this.add.image(gameWidth / 2, gameHeight / 2, 'slot_frame_ui');
        frame.setDisplaySize(gameWidth, gameHeight);

        // 2. Create Reel Masks and Containers
        for (let i = 0; i < reelCount; i++) {
            // Create mask for each reel (matches the white/light gray windows in the background)
            const maskGraphics = this.make.graphics({ x: 0, y: 0, add: false });
            maskGraphics.fillRect(
                reelXPositions[i] - 77.5,  // Window width is 155px, so half is 77.5
                reelCenterY - 120,          // Window height is 240px, so half is 120
                155,                         // Width of visible reel window
                240                          // Height of visible reel window (shows ~1.6 symbols)
            );
            const mask = maskGraphics.createGeometryMask();

            // Create Reel Container
            const reel = this.add.container(reelXPositions[i], 0);
            reel.setMask(mask);
            this.reels.push(reel);
            this.reelStops.push(0);

            // Add glow frame for win effects (using rectangle instead of image)
            const glowFrame = this.add.rectangle(
                reelXPositions[i], 
                reelCenterY, 
                155, 
                240, 
                0xffaa00, 
                0
            );
            glowFrame.setStrokeStyle(4, 0xffaa00);
            glowFrame.setVisible(false);
            this.glowFrames.push(glowFrame);

            // Populate reel with symbols (20 positions for smooth spinning)
            for (let j = 0; j < 20; j++) {
                const randomSymbolKey = Phaser.Math.RND.pick(SYMBOLS);
                const symbol = this.add.image(
                    0, 
                    reelCenterY + (j - 10) * symbolSize, 
                    randomSymbolKey
                );
                symbol.setScale(0.85); // Slightly smaller to fit the window better
                reel.add(symbol);
            }
        }
    }
    
    /**
     * Shows a message on the result overlay for a duration.
     */
    showResultOverlay(message, duration = 1500) {
        const overlay = document.getElementById('resultOverlay');
        overlay.textContent = message;
        overlay.classList.add('show');
        
        this.time.delayedCall(duration, () => {
            overlay.classList.remove('show');
        });
    }

    /**
     * Initiates the spinning sequence based on server result.
     */
    startSpin(result) {
        this.isSpinning = true;
        this.clearWinEffects();
        
        const symbolSize = 150;
        const baseSpeed = 1000;
        const durationMultiplier = 0.5;
        const reelCenterY = this.gameHeight / 2 - 15; // Match the create() method

        const finalSymbolKeys = result.finalSymbols;

        this.reels.forEach((reel, i) => {
            // Find the target symbol to land on
            const targetSymbolKey = finalSymbolKeys[i];
            const targetIndex = reel.list.findIndex(
                img => img.texture.key === targetSymbolKey && 
                Math.abs(img.y - reelCenterY) < symbolSize / 2
            );

            // If exact match not found, find closest symbol with that key
            let symbolIndex = targetIndex;
            if (symbolIndex === -1) {
                symbolIndex = reel.list.findIndex(img => img.texture.key === targetSymbolKey);
            }

            const targetY = reel.list[symbolIndex].y;
            const yOffset = reelCenterY - targetY;
            
            // Random overshoot for realistic spin effect
            const overshoot = Phaser.Math.Between(5, 10) * symbolSize;
            const spinDistance = yOffset - overshoot;

            reel.y = spinDistance;

            this.tweens.add({
                targets: reel,
                y: yOffset,
                duration: baseSpeed + i * baseSpeed * durationMultiplier,
                ease: 'Back.easeOut',
                onComplete: () => {
                    this.reelStops[i] = targetY;
                    if (i === this.reels.length - 1) {
                        this.onSpinComplete(result);
                    }
                }
            });
        });
    }

    /**
     * Called when the final reel stops spinning.
     */
    onSpinComplete(result) {
        this.isSpinning = false;
        
        // Update user data and UI from server result
        currentUser = result.user;
        document.getElementById('gameWin').textContent = result.winAmount.toFixed(2);
        updateUI();

        if (result.winAmount > 0) {
            this.displayWin(result.winLines);
            this.showResultOverlay(`WIN! ${result.winAmount.toFixed(2)}`, 3000);
        } else {
            this.showResultOverlay("NO WIN", 1500);
        }

        toggleControls(true);
    }
    
    /**
     * Displays visual effects for winning lines.
     */
    displayWin(winLines) {
        // Simple win effect: highlight the entire middle row
        if (winLines.length > 0) {
            this.glowFrames.forEach(frame => {
                frame.setVisible(true);
            });
            
            this.time.delayedCall(3000, () => {
                this.clearWinEffects();
            });
        }
    }
    
    /**
     * Clears all visual win effects.
     */
    clearWinEffects() {
        this.glowFrames.forEach(frame => {
            frame.setVisible(false);
        });
    }
}


// ======================================================
// PHASER GAME CONFIGURATION
// ======================================================

function initGame() {
    const config = {
        type: Phaser.AUTO,
        width: 800,
        height: 600,
        parent: 'renderCanvas', // The DIV ID in index.html
        scene: SlotMachineScene,
        scale: {
            mode: Phaser.Scale.FIT, // Important for responsive scaling
            autoCenter: Phaser.Scale.CENTER_BOTH,
        }
    };

    new Phaser.Game(config);
}

// ======================================================
// APPLICATION STARTUP
// ======================================================

// Ensure all DOM elements exist before attempting to initialize
document.addEventListener('DOMContentLoaded', () => {
    // Start the Phaser game engine
    initGame(); 
    
    // Default to Auth screen on load
    showScreen('auth');
});
