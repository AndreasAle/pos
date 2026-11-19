// Yoka WA Gateway: pengganti Fonnte untuk nomor WhatsApp milik Yoka sendiri.
//
// - Login dengan scan QR di /qr (pakai token).
// - API kirim meniru Fonnte (POST /send: target, message, typing, delay) supaya
//   Laravel cukup ganti URL + token.
// - Pesan masuk diteruskan ke webhook Laravel dengan field yang sama seperti
//   Fonnte (sender, message, name, device, inboxid), jadi
//   FonnteWebhookController dan Adit tidak perlu diubah.
// - Semua kiriman melewati Guard (anti-spam) dan antrean satu per satu.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { setTimeout as sleep } from 'node:timers/promises';
import makeWASocket, { useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers } from 'baileys';
import QRCode from 'qrcode';
import pino from 'pino';
import { Guard } from './guard.js';

const env = (k, d) => process.env[k] ?? d;
const PORT = Number(env('PORT', 3100));
const HOST = env('HOST', '127.0.0.1');
const TOKEN = env('GATEWAY_TOKEN', '');
const WEBHOOK_URL = env('WEBHOOK_URL', '');
const DATA_DIR = path.resolve(env('DATA_DIR', './data'));
const MIN_GAP_MS = Number(env('MIN_GAP_MS', 2500));

if (TOKEN.length < 24) {
  console.error('GATEWAY_TOKEN wajib diisi minimal 24 karakter.');
  process.exit(1);
}
fs.mkdirSync(DATA_DIR, { recursive: true, mode: 0o700 });

const log = pino({ level: env('LOG_LEVEL', 'info') });
const num = (k) => (process.env[k] ? Number(process.env[k]) : undefined);
const guard = new Guard(path.join(DATA_DIR, 'guard.json'), Object.fromEntries(Object.entries({
  replyWindowHours: num('REPLY_WINDOW_HOURS'),
  perNumberHourly: num('PER_NUMBER_HOURLY'),
  perNumberDaily: num('PER_NUMBER_DAILY'),
  globalPerMinute: num('GLOBAL_PER_MINUTE'),
  globalDaily: num('GLOBAL_DAILY'),
  coldDaily: num('COLD_DAILY'),
  coldPerNumberGapHours: num('COLD_GAP_HOURS'),
}).filter(([, v]) => v !== undefined)));

let sock = null;
let status = 'starting';
let lastQr = null;
let me = null;

async function connect() {
  const { state, saveCreds } = await useMultiFileAuthState(path.join(DATA_DIR, 'auth'));
  const { version } = await fetchLatestBaileysVersion().catch(() => ({ version: undefined }));
  sock = makeWASocket({
    auth: state,
    version,
    browser: Browsers.ubuntu('Yoka Gateway'),
    logger: log.child({ module: 'baileys' }, { level: 'warn' }),
    markOnlineOnConnect: false,
    syncFullHistory: false,
  });
  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', async ({ connection, lastDisconnect, qr }) => {
    if (qr) {
      lastQr = qr;
      status = 'waiting_qr';
      log.info('QR baru tersedia di /qr');
    }
    if (connection === 'open') {
      status = 'connected';
      lastQr = null;
      me = (sock.user?.id || '').split(':')[0].split('@')[0];
      log.info({ me }, 'WhatsApp terhubung');
    }
    if (connection === 'close') {
      const code = lastDisconnect?.error?.output?.statusCode;
      if (code === DisconnectReason.loggedOut) {
        status = 'logged_out';
        fs.rmSync(path.join(DATA_DIR, 'auth'), { recursive: true, force: true });
        log.warn('Sesi keluar dari HP. Scan QR lagi di /qr.');
      } else {
        status = 'reconnecting';
        log.warn({ code }, 'Koneksi putus, menyambung ulang');
      }
      await sleep(3000);
      connect().catch((e) => log.error(e, 'gagal menyambung'));
    }
  });

  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    if (type !== 'notify') return;
    for (const m of messages) {
      try { await handleInbound(m); } catch (e) { log.error(e, 'gagal memproses pesan masuk'); }
    }
  });
}

function textOf(msg) {
  const c = msg.message || {};
  return c.conversation || c.extendedTextMessage?.text || c.imageMessage?.caption || c.videoMessage?.caption
    || c.ephemeralMessage?.message?.extendedTextMessage?.text || c.ephemeralMessage?.message?.conversation || '';
}

async function handleInbound(m) {
  const jid = m.key.remoteJid || '';
  // Hanya chat pribadi. Grup, status, channel, dan pesan dari diri sendiri diabaikan.
  if (m.key.fromMe || !jid.endsWith('@s.whatsapp.net')) return;
  const phone = jid.split('@')[0];
  const text = textOf(m).trim();
  if (!text) return;

  const optedOut = guard.recordInbound(phone, text);
  if (optedOut) log.info({ phone }, 'nomor minta STOP; semua kiriman ke nomor ini diblokir');

  if (!WEBHOOK_URL) return;
  const body = new URLSearchParams({ sender: phone, message: text, name: m.pushName || '', device: me || '', inboxid: m.key.id || '' });
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      const r = await fetch(WEBHOOK_URL, { method: 'POST', body, signal: AbortSignal.timeout(15000) });
      if (r.ok) return;
      log.warn({ status: r.status }, 'webhook menolak');
    } catch (e) {
      log.warn({ err: e.message, attempt }, 'webhook gagal');
    }
    await sleep(attempt * 2000);
  }
}

// Antrean kirim: satu pesan dalam satu waktu, dengan jeda minimum + acak.
let chain = Promise.resolve();
let lastSentAt = 0;
function enqueue(job) {
  const p = chain.then(job, job);
  chain = p.catch(() => {});
  return p;
}

async function send(target, message, typing, delaySec) {
  const verdict = guard.check(target, message);
  if (!verdict.ok) return { status: false, reason: verdict.reason };
  if (status !== 'connected') return { status: false, reason: `gateway belum terhubung (${status})` };

  return enqueue(async () => {
    // Cek ulang di dalam antrean: kiriman sebelumnya bisa sudah memenuhi batas.
    const again = guard.check(target, message);
    if (!again.ok) return { status: false, reason: again.reason };
    const jid = `${target}@s.whatsapp.net`;
    if (again.kind === 'cold') {
      const [exists] = await sock.onWhatsApp(jid).catch(() => []);
      if (!exists?.exists) return { status: false, reason: 'nomor tidak terdaftar di WhatsApp' };
    }
    const gap = MIN_GAP_MS + crypto.randomInt(0, 1500) - (Date.now() - lastSentAt);
    if (gap > 0) await sleep(gap);
    if (typing) {
      await sock.sendPresenceUpdate('composing', jid).catch(() => {});
      await sleep(Math.min(15, Math.max(1, delaySec)) * 1000 + crypto.randomInt(0, 800));
      await sock.sendPresenceUpdate('paused', jid).catch(() => {});
    } else if (delaySec > 0) {
      await sleep(Math.min(15, delaySec) * 1000);
    }
    const res = await sock.sendMessage(jid, { text: message });
    lastSentAt = Date.now();
    guard.recordSent(target, message, again.kind);
    return { status: true, id: res?.key?.id, kind: again.kind };
  });
}

function authorized(req, url) {
  const given = req.headers.authorization || url.searchParams.get('token') || '';
  const a = Buffer.from(given);
  const b = Buffer.from(TOKEN);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

async function readBody(req) {
  let raw = '';
  for await (const chunk of req) {
    raw += chunk;
    if (raw.length > 64 * 1024) throw new Error('body terlalu besar');
  }
  if ((req.headers['content-type'] || '').includes('application/json')) return JSON.parse(raw || '{}');
  return Object.fromEntries(new URLSearchParams(raw));
}

const json = (res, code, data) => { res.writeHead(code, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(data)); };

http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://local');
  try {
    if (url.pathname === '/health') return json(res, 200, { ok: true, status });
    if (!authorized(req, url)) return json(res, 401, { status: false, reason: 'token salah' });

    if (req.method === 'GET' && url.pathname === '/qr') {
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store', 'Referrer-Policy': 'no-referrer' });
      const img = lastQr ? `<img src="${await QRCode.toDataURL(lastQr, { width: 320 })}" alt="QR">` : '';
      const msg = { connected: `Terhubung sebagai ${me}.`, waiting_qr: 'Buka WhatsApp di HP: Perangkat tertaut, lalu Tautkan perangkat, lalu scan kode ini.' }[status] || `Status: ${status}. Halaman memuat ulang otomatis.`;
      return res.end(`<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="15"><title>Yoka WA Gateway</title><body style="font-family:sans-serif;text-align:center;padding:40px"><h2>Yoka WA Gateway</h2><p>${msg}</p>${img}</body>`);
    }
    if (req.method === 'GET' && url.pathname === '/status') return json(res, 200, { status, me, guard: guard.stats() });
    if (req.method === 'POST' && url.pathname === '/send') {
      const b = await readBody(req);
      const target = String(b.target || '').replace(/\D/g, '');
      if (!/^62\d{8,13}$/.test(target)) return json(res, 422, { status: false, reason: 'target harus format 62xxxx' });
      const out = await send(target, String(b.message || ''), ['1', 'true', true].includes(b.typing), Number(b.delay || 0));
      return json(res, out.status ? 200 : 429, out);
    }
    if (req.method === 'POST' && url.pathname === '/logout') {
      await sock?.logout().catch(() => {});
      return json(res, 200, { status: true });
    }
    json(res, 404, { status: false, reason: 'tidak ada' });
  } catch (e) {
    log.error(e, 'request gagal');
    json(res, 500, { status: false, reason: 'kesalahan gateway' });
  }
}).listen(PORT, HOST, () => log.info(`Gateway jalan di http://${HOST}:${PORT}`));

connect().catch((e) => { log.error(e, 'gagal start'); process.exit(1); });
