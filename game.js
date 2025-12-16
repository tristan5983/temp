(function bootstrapGame() {
/* ============================================================
    Royal Casino - 2D Slot Machine (Phaser 3 Conversion)
    ============================================================ */

let currentUser = null;
let phaserGame = null; // Renamed from gameEngine to phaserGame
let currentBet = 10;

// Slot symbols
const symbolKeys = [
    "BAR", "CHERRY", "CROWN", "DIAMOND",
    "FREE_SPIN", "SCATTER", "SEVEN", "WILD"
];

// Texture paths for each symbol
const symbolTextureMap = {
    BAR: "/textures/symbol_bar.png",
    CHERRY: "/textures/symbol_cherry.png",
    CROWN: "/textures/symbol_crown.png",
    DIAMOND: "/textures/symbol_diamond.png",
    FREE_SPIN: "/textures/symbol_freespin.png",
    SCATTER: "/textures/symbol_scatter.png",
    SEVEN: "/textures/symbol_seven.png",
    WILD: "/textures/symbol_wild.png"
};

/* ============================================================
    PHASER 3 SLOT MACHINE SCENE
    ============================================================ */

class SlotMachineScene extends Phaser.Scene {
    constructor() {
        super({ key: 'SlotMachineScene' });
        this.isSpinning = false;
        this.reels = [];
        this.reelContainers = [];
        this.glowFrames = [];
        this.particleEmitter = null;
        this.currentResults = []; // Stores the determined result symbols
    }

    preload() {
        // Load all symbol textures
        for (const key in symbolTextureMap) {
            this.load.image(key, symbolTextureMap[key]);
        }
        // Load a generic texture for the glow frame
        this.load.image('frame', '/textures/frame_glow.png'); 
        // Load particle texture (if available)
        this.load.image('coin', '/textures/coin.png');
        // Load a background/mask image for the slot machine
        this.load.image('slot_frame_ui', '/textures/slot_frame_ui.png');
    }

    create() {
        const gameWidth = this.game.config.width;
        const gameHeight = this.game.config.height;

        // Set Scene Background
        this.cameras.main.setBackgroundColor('#050014');

        // Create the slot machine frame (replaces the .glb model)
        const frameUI = this.add.image(gameWidth / 2, gameHeight / 2, 'slot_frame_ui');
        frameUI.setDisplaySize(gameWidth, gameHeight); // Scale to fit, adjust as needed

        // Reel Configuration (2D Layout)
        const reelXPositions = [gameWidth / 2 - 150, gameWidth / 2, gameWidth / 2 + 150];
        const symbolHeight = 150; // Height of each symbol sprite
        const visibleSymbols = 3; // Number of symbols visible on each reel

        // Create Reels and Glow Frames
        for (let i = 0; i < 3; i++) {
            const reelContainer = this.add.container(reelXPositions[i], gameHeight / 2);
            this.reelContainers.push(reelContainer);
            this.createReel(reelContainer, symbolHeight);

            // Create a viewport mask for the reel
            const maskShape = this.make.graphics();
            maskShape.fillRect(
                reelXPositions[i] - 70, 
                gameHeight / 2 - (symbolHeight * visibleSymbols / 2), 
                140, 
                symbolHeight * visibleSymbols
            );
            reelContainer.setMask(new Phaser.Display.Masks.GeometryMask(this, maskShape));


            // Glow Frame (Replaces BABYLON.MeshBuilder.CreatePlane for glow)
            const frame = this.add.image(reelXPositions[i], gameHeight / 2, 'frame');
            frame.setScale(0.9);
            frame.setTint(0xffaa00);
            frame.setVisible(false); // Initially hidden, only shown on win
            this.glowFrames.push(frame);
        }

        // Win Particles Setup
        this.createWinParticlesSystem();
        
        // Spot Light Interval (Simple color pulse effect on the background)
        this.time.addEvent({
            delay: 800,
            callback: this.pulseFrameColor,
            callbackScope: this,
            loop: true
        });

        // Expose spin to global scope for the button handler
        window.spin = this.spin.bind(this);
    }

    pulseFrameColor() {
        const colors = [0xff0080, 0x00ffff, 0xffff00, 0xff00ff];
        const color = colors[Phaser.Math.Between(0, colors.length - 1)];
        this.cameras.main.setBackgroundColor(color, 0.05);
        this.time.delayedCall(100, () => this.cameras.main.setBackgroundColor(0x050014), [], this);
    }

    createReel(container) {
        const reelSymbols = [];
        const reelLength = 20; // 20 symbols per reel
        const symbolHeight = 150;
        let symbolSet = [];

        // In Phaser, we need a long strip of symbols
        for (let i = 0; i < reelLength; i++) {
            // Randomly select a symbol key
            const key = symbolKeys[Phaser.Math.Between(0, symbolKeys.length - 1)];
            
            // Create the symbol sprite
            const yPos = i * symbolHeight - (reelLength * symbolHeight) / 2;
            const symbolImage = this.add.image(0, yPos, key);
            symbolImage.setScale(0.7); // Adjust scale if needed

            container.add(symbolImage);
            reelSymbols.push({ image: symbolImage, symbol: key });
            symbolSet.push(key);
        }

        this.reels.push(reelSymbols);
        return container;
    }

    createWinParticlesSystem() {
        // Creates a basic coin particle system in Phaser
        try {
            this.particleEmitter = this.add.particles('coin').createEmitter({
                x: this.game.config.width / 2,
                y: this.game.config.height / 2,
                angle: { min: 200, max: 340 },
                speed: { min: 400, max: 700 },
                gravityY: 1200,
                lifespan: 3000,
                quantity: 0, // Set to 0 initially
                scale: { start: 0.5, end: 0 },
                blendMode: 'ADD'
            });
        } catch (e) {
            console.log("Coin texture missing – particles disabled");
            this.particleEmitter = null;
        }
    }

    triggerWinParticles() {
        if (!this.particleEmitter) return;
        
        // Burst 300 particles
        this.particleEmitter.explode(300, this.game.config.width / 2, this.game.config.height / 2);

        // Turn off emitter after the burst
        this.time.delayedCall(1500, () => {
            // Optional: reset emitter to default state if you were using emit rate
        });
    }

    // --- SPIN & WIN LOGIC ---

    async spin() {
        if (!currentUser) return;
        if (this.isSpinning || currentUser.balance < currentBet) {
            if (currentUser.balance < currentBet) showResult("Insufficient funds!");
            return;
        }

        this.isSpinning = true;
        document.getElementById("spinButton").disabled = true;
        hideResult();
        this.glowFrames.forEach(f => f.setVisible(false));
        this.currentResults = []; // Clear previous results

        // 1. Determine the result on the server/client side first (Placeholder)
        // For this client side logic, we randomly generate indices.
        const resultIndices = this.reelContainers.map(() => Phaser.Math.Between(0, 19));

        // Wait for all reels to finish spinning
        await Promise.all(this.reelContainers.map((container, i) => this.spinReel(container, i, resultIndices[i])));

        // 2. Map the resulting symbols
        this.currentResults = this.reelContainers.map((container, i) => {
            const symbols = this.reels[i];
            const resultIndex = resultIndices[i];
            return symbols[resultIndex].symbol;
        });

        // 3. Submit result and handle win
        await submitGameResult(this.currentResults);

        this.isSpinning = false;
        document.getElementById("spinButton").disabled = false;
    }

    spinReel(container, index, targetIndex) {
        return new Promise(resolve => {
            const symbolHeight = 150;
            const reelLength = 20;

            // 1. Calculate the final landing Y position for the target symbol (targetIndex)
            // The target symbol's image.y should be centered in the container's viewport (y=0 in the container)
            // The image positions are relative to the container.
            // A symbol at index 'i' has a local Y position of (i * symbolHeight) - (reelLength * symbolHeight) / 2
            
            // To get the target symbol to Y=0, we need to offset the whole container.
            const targetYLocal = (targetIndex * symbolHeight) - (reelLength * symbolHeight) / 2;
            const finalContainerY = -targetYLocal; 
            
            // 2. Calculate the required distance to spin (ensure it spins several times)
            // Spin duration is based on the index to create a staggered stop
            const duration = 2000 + index * 600;
            
            // Spin distance: Spin past 3 full rotations (3 * reelLength) + the distance to the target
            const spinDistance = (3 * reelLength * symbolHeight) + Math.abs(finalContainerY - container.y);
            const intermediateY = container.y - spinDistance; 
            
            // 3. Create the tween
            this.tweens.add({
                targets: container,
                y: finalContainerY, // The final center position
                duration: duration,
                ease: Phaser.Math.Easing.Cubic.Out, // The same EASEOUT used in Babylon.js
                onComplete: () => {
                    // Phaser container position is now correct, but the Y is outside the logical range.
                    // Instead of calculating modulo like in the 3D version, Phaser's Y position is the source of truth.
                    resolve();
                }
            });
        });
    }

    pulseFrames() {
        this.glowFrames.forEach(f => {
            f.setVisible(true);
            this.tweens.add({
                targets: f.scale,
                x: { from: 0.9, to: 1.1 },
                y: { from: 0.9, to: 1.1 },
                duration: 200,
                yoyo: true,
                repeat: -1, // Loop indefinitely
                ease: 'Sine.easeInOut'
            });
        });
        // Stop pulsing after a short period
        this.time.delayedCall(4000, () => {
            this.glowFrames.forEach(f => {
                this.tweens.killTweensOf(f.scale);
                f.setScale(0.9);
                f.setVisible(false);
            });
        });
    }
}

// Global functions (Unchanged from original as they handle Auth/DOM/API)

/* ============================================================
    AUTH
    ============================================================ */
async function login() {
    const username = document.getElementById("loginUsername").value.trim();
    const password = document.getElementById("loginPassword").value;
    if (!username || !password) return showError("Please fill in all fields");
    try {
        const res = await fetch("/api/login", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ username, password })
        });
        const data = await res.json();
        if (res.ok) {
            currentUser = data.user;
            showLobby();
            loadJackpot();
        } else showError(data.error || "Login failed");
    } catch {
        showError("Connection error");
    }
}

async function register() {
    const username = document.getElementById("registerUsername").value.trim();
    const password = document.getElementById("registerPassword").value;
    if (!username || !password) return showError("Please fill in all fields");
    try {
        const res = await fetch("/api/register", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ username, password })
        });
        const data = await res.json();
        if (res.ok) {
            currentUser = data.user;
            showLobby();
            loadJackpot();
        } else showError(data.error || "Registration failed");
    } catch {
        showError("Connection error");
    }
}

async function logout() {
    await fetch("/api/logout", { method: "POST" });
    currentUser = null;
    location.reload();
}

function showLogin() {
    document.getElementById("loginForm").classList.remove("hidden");
    document.getElementById("registerForm").classList.add("hidden");
    clearError();
}

function showRegister() {
    document.getElementById("registerForm").classList.remove("hidden");
    document.getElementById("loginForm").classList.add("hidden");
    clearError();
}

function showError(msg) {
    const el = document.getElementById("errorMessage");
    el.textContent = msg;
    el.classList.remove("hidden");
}

function clearError() {
    document.getElementById("errorMessage").classList.add("hidden");
}

function showLobby() {
    document.getElementById("authContainer").style.display = "none";
    document.getElementById("lobbyContainer").style.display = "block";
    updateBalance();
}

function updateBalance() {
    if (!currentUser) return;
    document.getElementById("userBalance").textContent = currentUser.balance.toFixed(2);
    document.getElementById("gameBalance").textContent = currentUser.balance.toFixed(2);
}

/* ============================================================
    JACKPOT + FUNDS
    ============================================================ */

async function loadJackpot() {
    try {
        const res = await fetch("/api/jackpot");
        const data = await res.json();

        document.getElementById("jackpotAmount").textContent = data.amount.toFixed(2);
        document.getElementById("gameJackpot").textContent = data.amount.toFixed(2);

        setInterval(async () => {
            try {
                const r = await fetch("/api/jackpot");
                const d = await r.json();
                animateValue(
                    "jackpotAmount",
                    parseFloat(document.getElementById("jackpotAmount").textContent),
                    d.amount,
                    1000
                );
                animateValue(
                    "gameJackpot",
                    parseFloat(document.getElementById("gameJackpot").textContent),
                    d.amount,
                    1000
                );
            } catch (e) {
                console.error("Jackpot refresh failed:", e);
            }
        }, 5000);
    } catch (e) {
        console.error("Jackpot load failed:", e);
    }
}

function animateValue(id, start, end, duration) {
    const el = document.getElementById(id);
    const range = end - start;
    let current = start;
    const increment = range !== 0 ? range / (duration / 16) : 0;

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
        el.textContent = current.toFixed(2);
    }, 16);
}

function showFundsModal() {
    document.getElementById("fundsModal").classList.add("show");
}

function closeFundsModal() {
    document.getElementById("fundsModal").classList.remove("show");
}

async function addFunds() {
    const amount = parseFloat(document.getElementById("fundsAmount").value);
    if (isNaN(amount) || amount <= 0) return showFundsError("Enter valid amount");

    try {
        const res = await fetch("/api/funds/add", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ amount })
        });

        const data = await res.json();
        if (res.ok) {
            currentUser.balance = data.balance;
            updateBalance();
            closeFundsModal();
        } else showFundsError(data.error || "Failed");
    } catch (e) {
        showFundsError("Network error");
    }
}

async function withdrawFunds() {
    const amount = parseFloat(document.getElementById("fundsAmount").value);
    if (isNaN(amount) || amount <= 0) return showFundsError("Enter valid amount");

    try {
        const res = await fetch("/api/funds/withdraw", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ amount })
        });

        const data = await res.json();
        if (res.ok) {
            currentUser.balance = data.balance;
            updateBalance();
        } else showFundsError(data.error || "Failed");
    } catch (e) {
        showFundsError("Network error");
    }
}

function showFundsError(msg) {
    const el = document.getElementById("fundsError");
    el.textContent = msg;
    el.classList.remove("hidden");
}

/* ============================================================
    GAME NAVIGATION
    ============================================================ */

function playGame() {
    document.getElementById("lobbyContainer").style.display = "none";
    document.getElementById("gameContainer").style.display = "block";
    
    // Cleanup previous game engine if any
    if (phaserGame) {
        phaserGame.destroy(true);
    }
    
    initGame();
    updateGameUI();
}

function backToLobby() {
    document.getElementById("gameContainer").style.display = "none";
    document.getElementById("lobbyContainer").style.display = "block";

    // Stop and destroy the Phaser Game when going back to lobby
    if (phaserGame) {
        phaserGame.destroy(true);
        phaserGame = null;
    }

    fetch("/api/user")
        .then(r => r.json())
        .then(d => {
            currentUser = d;
            updateBalance();
        })
        .catch(e => console.error("Failed to refresh user:", e));
}

function updateGameUI() {
    if (!currentUser) return;
    document.getElementById("gameBalance").textContent = currentUser.balance.toFixed(2);
    document.getElementById("gameBet").textContent = currentBet.toFixed(2);
    document.getElementById("betDisplay").textContent = currentBet.toFixed(2);
}

function changeBet(delta) {
    if (!currentUser) return;
    currentBet = Math.max(5, Math.min(currentUser.balance, currentBet + delta));
    updateGameUI();
}

/* ============================================================
    PHASER 3 SETUP (Replaces Babylon.js setup)
    ============================================================ */

function initGame() {
    const config = {
        type: Phaser.AUTO,
        width: 800, // Fixed width/height for 2D slot machine
        height: 600,
        parent: 'renderCanvas', // The canvas container ID
        scene: [SlotMachineScene]
    };

    // phaserGame replaces gameEngine
    phaserGame = new Phaser.Game(config);

    // The game is controlled by the SlotMachineScene instance now.
    window.addEventListener("resize", () => {
        if (phaserGame) phaserGame.resize(window.innerWidth, window.innerHeight);
    });
}

/* ============================================================
    SPIN & WIN LOGIC (Modified to call Phaser Scene methods)
    ============================================================ */

// Note: The main `spin` function is now bound to the Phaser Scene instance
// and accessed globally via `window.spin = this.spin.bind(this);` in the scene's create method.

async function submitGameResult(symbols) {
    let winAmount = 0;
    const s = symbols[0];
    const scene = phaserGame.scene.getScene('SlotMachineScene');

    if (symbols[0] === symbols[1] && symbols[1] === symbols[2]) {
        winAmount =
            currentBet *
            ({
                DIAMOND: 100,
                SEVEN: 50,
                CROWN: 25,
                BAR: 15,
                WILD: 10,
                CHERRY: 8,
                SCATTER: 5
            }[s] || 2);

        showResult(`JACKPOT! +$${winAmount.toFixed(2)}`);
        if (scene) {
            scene.triggerWinParticles();
            scene.pulseFrames();
        }
    } else if (symbols[0] === symbols[1] || symbols[1] === symbols[2]) {
        winAmount = currentBet * 2;
        showResult(`WIN +$${winAmount.toFixed(2)}`);
        if (scene) {
            scene.triggerWinParticles();
        }
    } else {
        showResult(`NO WIN - Try Again`);
    }

    try {
        const res = await fetch("/api/game/result", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                betAmount: currentBet,
                winAmount,
                symbols,
                gameType: "slots"
            })
        });

        const data = await res.json();
        if (res.ok) {
            currentUser.balance = data.balance;
            updateGameUI();
            document.getElementById("gameWin").textContent = winAmount.toFixed(2);
        }
    } catch (e) {
        console.error("Result submit failed:", e);
    }
}

/* ============================================================
    RESULT DISPLAY
    ============================================================ */

function showResult(msg) {
    const el = document.getElementById("resultOverlay");
    el.textContent = msg;
    el.classList.add("show");
    setTimeout(hideResult, 4000);
}

function hideResult() {
    document.getElementById("resultOverlay").classList.remove("show");
}

/* ============================================================
    BOOTSTRAP
    ============================================================ */

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

    function init() {
        console.log("🎰 Game JS loaded successfully!");

        const loginBtn = document.getElementById("loginButton");
        if (loginBtn) loginBtn.addEventListener("click", login);

        const registerBtn = document.getElementById("registerButton");
        if (registerBtn) registerBtn.addEventListener("click", showRegister);

        document.getElementById("loginPassword")?.addEventListener("keypress", e => {
            if (e.key === "Enter") login();
        });
        document.getElementById("registerPassword")?.addEventListener("keypress", e => {
            if (e.key === "Enter") register();
        });

        // Global bindings for inline onclick handlers (Phaser methods are bound in the Scene.create())
        window.login = login;
        window.showRegister = showRegister;
        window.showLogin = showLogin;
        window.register = register;
        window.playGame = playGame;
        window.logout = logout;
        window.addFunds = addFunds;
        window.withdrawFunds = withdrawFunds;
        window.showFundsModal = showFundsModal;
        window.closeFundsModal = closeFundsModal;
        // window.spin is bound within the SlotMachineScene.create()
        window.changeBet = changeBet;
        window.backToLobby = backToLobby;
    }
})();
