// server.js
require('dotenv').config();
const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const cookieParser = require('cookie-parser');
const mysql = require('mysql2/promise');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
const server = http.createServer(app);
const io = new Server(server, { cors: { origin: true, credentials: true }});

app.use(express.json());
app.use(cookieParser());
app.use(require('cors')({ origin: true, credentials: true }));

const db = mysql.createPool({
  host: process.env.DB_HOST, user: process.env.DB_USER, password: process.env.DB_PASS, database: process.env.DB_NAME,
  waitForConnections: true, connectionLimit: 10, queueLimit: 0
});

const JWT_SECRET = process.env.JWT_SECRET || 'devsecret';

function signToken(user) {
  return jwt.sign({ id: user.id, email: user.email, is_admin: user.is_admin }, JWT_SECRET, { expiresIn: '7d' });
}
async function getUserFromToken(req) {
  const token = req.cookies.token;
  if (!token) return null;
  try {
    const payload = jwt.verify(token, JWT_SECRET);
    const [rows] = await db.query('SELECT id,email,display_name,is_admin FROM users WHERE id=?', [payload.id]);
    return rows[0] || null;
  } catch (e) { return null; }
}

// Register
app.post('/api/register', async (req, res) => {
  const { email, password, display_name } = req.body;
  if (!email || !password) return res.status(400).json({ error: 'missing fields' });
  const hash = await bcrypt.hash(password, 10);
  try {
    const [r] = await db.query('INSERT INTO users (email,password_hash,display_name) VALUES (?,?,?)', [email, hash, display_name || null]);
    const userId = r.insertId;
    // create wallet
    await db.query('INSERT INTO wallets (user_id,balance) VALUES (?, 0)', [userId]);
    const token = signToken({ id: userId, email, is_admin: 0 });
    res.cookie('token', token, { httpOnly: true, secure: process.env.COOKIE_SECURE === 'true' });
    res.json({ ok: true });
  } catch (err) {
    res.status(400).json({ error: 'user exists or db error', detail: err.message });
  }
});

// Login
app.post('/api/login', async (req, res) => {
  const { email, password } = req.body;
  const [rows] = await db.query('SELECT id,email,password_hash,is_admin FROM users WHERE email=?', [email]);
  const user = rows[0];
  if (!user) return res.status(400).json({ error: 'invalid' });
  const ok = await bcrypt.compare(password, user.password_hash);
  if (!ok) return res.status(400).json({ error: 'invalid' });
  const token = signToken(user);
  res.cookie('token', token, { httpOnly: true, secure: process.env.COOKIE_SECURE === 'true' });
  res.json({ ok: true });
});

// Logout
app.post('/api/logout', (req, res) => {
  res.clearCookie('token');
  res.json({ ok: true });
});

// Profile/wallet
app.get('/api/me', async (req, res) => {
  const user = await getUserFromToken(req);
  if (!user) return res.status(401).json({ error: 'unauth' });
  const [walletRows] = await db.query('SELECT balance FROM wallets WHERE user_id=?', [user.id]);
  res.json({ user, wallet: walletRows[0] || { balance: 0 } });
});

// Admin endpoint to add funds manually (for your workflow)
app.post('/api/admin/add-funds', async (req, res) => {
  const user = await getUserFromToken(req);
  if (!user || !user.is_admin) return res.status(403).json({ error: 'forbidden' });
  const { user_id, amount, note } = req.body;
  if (!user_id || !amount) return res.status(400).json({ error: 'missing' });
  const conn = await db.getConnection();
  try {
    await conn.beginTransaction();
    await conn.query('UPDATE wallets SET balance = balance + ? WHERE user_id=?', [amount, user_id]);
    await conn.query('INSERT INTO transactions (user_id,amount,type,meta) VALUES (?,?,?,?)', [user_id, amount, 'admin_adjust', JSON.stringify({ note })]);
    await conn.commit();
    // notify user via socket.io
    io.emit('wallet:update', { user_id, amount });
    res.json({ ok: true });
  } catch (err) {
    await conn.rollback();
    res.status(500).json({ error: err.message });
  } finally {
    conn.release();
  }
});

// Game spin (simple deterministic server-side handling)
app.post('/api/spin', async (req, res) => {
  const user = await getUserFromToken(req);
  if (!user) return res.status(401).json({ error: 'unauth' });
  const { bet } = req.body;
  if (!bet || bet <= 0) return res.status(400).json({ error: 'invalid bet' });
  const conn = await db.getConnection();
  try {
    await conn.beginTransaction();
    const [wrows] = await conn.query('SELECT balance FROM wallets WHERE user_id=? FOR UPDATE', [user.id]);
    if (!wrows[0] || Number(wrows[0].balance) < Number(bet)) { await conn.rollback(); return res.status(400).json({ error: 'insufficient' });}
    // Deduct bet
    await conn.query('UPDATE wallets SET balance = balance - ? WHERE user_id=?', [bet, user.id]);
    await conn.query('INSERT INTO transactions (user_id,amount,type,meta) VALUES (?,?,?,?)', [user.id, -bet, 'bet', JSON.stringify({ bet })]);

    // Simple spin logic: 3 reels, each 0..5 symbol
    const reels = [Math.floor(Math.random()*6), Math.floor(Math.random()*6), Math.floor(Math.random()*6)];
    let payout = 0;
    // Example winning: three of a kind pays 10x, two of a kind pays 1.5x, special jackpot symbol index 5 triggers progressive jackpot
    if (reels[0] === reels[1] && reels[1] === reels[2]) {
      payout = bet * 10;
      // If jackpot symbol (5), pay jackpot pool instead
      if (reels[0] === 5) {
        const [jr] = await conn.query('SELECT id,pool FROM jackpots WHERE name=? LIMIT 1', ['main']);
        if (jr[0]) {
          payout = Number(jr[0].pool);
          await conn.query('UPDATE jackpots SET pool = 0, triggered_at = NOW() WHERE id=?', [jr[0].id]);
          io.emit('jackpot:hit', { user_id: user.id, amount: payout });
        }
      }
    } else if (reels[0] === reels[1] || reels[1] === reels[2] || reels[0] === reels[2]) {
      payout = bet * 1.5;
    } else {
      // add a small percent to jackpot pool
      const poolAdd = Number(bet) * 0.02;
      await conn.query('UPDATE jackpots SET pool = pool + ? WHERE name=?', [poolAdd, 'main']);
    }

    if (payout > 0) {
      await conn.query('UPDATE wallets SET balance = balance + ? WHERE user_id=?', [payout, user.id]);
      await conn.query('INSERT INTO transactions (user_id,amount,type,meta) VALUES (?,?,?,?)', [user.id, payout, 'payout', JSON.stringify({ payout, reels })]);
    }

    await conn.query('INSERT INTO games (user_id, game_type, bet_amount, result) VALUES (?,?,?,?)', [user.id, 'slots', bet, JSON.stringify({ reels, payout })]);

    await conn.commit();
    res.json({ reels, payout });
  } catch (err) {
    await conn.rollback();
    res.status(500).json({ error: err.message });
  } finally {
    conn.release();
  }
});

// Basic lobby endpoint
app.get('/api/lobby', async (req, res) => {
  // return game list and current jackpot
  const [j] = await db.query('SELECT name,pool FROM jackpots');
  res.json({ games: [{ id:'slots', name:'Classic Slots', min_bet:0.1, max_bet:100 }], jackpots: j });
});

// Start server
const PORT = process.env.PORT || 4000;
server.listen(PORT, () => console.log('Server listening', PORT));
