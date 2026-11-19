// Rem anti-spam. Semua batas ditegakkan di sini, bukan di prompt AI atau di
// Laravel, supaya bug di sisi mana pun tidak bisa membuat nomor mengirim massal.
//
// Dua jenis kirim:
//  - balasan  : target pernah chat ke kita dalam REPLY_WINDOW_HOURS terakhir.
//  - pesan dingin (cold): target belum chat. Dibatasi jauh lebih ketat.
import fs from 'node:fs';

const HOUR = 3600_000;
const DAY = 24 * HOUR;

export const defaults = {
  replyWindowHours: 24,
  perNumberHourly: 20,
  perNumberDaily: 60,
  globalPerMinute: 12,
  globalDaily: 500,
  coldDaily: 20,
  coldPerNumberGapHours: 72,
  duplicateWindowMinutes: 10,
  maxLength: 2000,
};

const OPT_OUT = /^\s*(stop|berhenti|unsubscribe|jangan (chat|kirim|hubungi)( saya| aku)?( lagi)?)\s*[.!]*\s*$/i;

export class Guard {
  constructor(file, limits = {}, now = () => Date.now()) {
    this.file = file;
    this.limits = { ...defaults, ...limits };
    this.now = now;
    this.state = { inbound: {}, sent: [], optOut: {} };
    if (file && fs.existsSync(file)) {
      try { this.state = { ...this.state, ...JSON.parse(fs.readFileSync(file, 'utf8')) }; } catch { /* file rusak: mulai bersih */ }
    }
  }

  /** Catat pesan masuk. Mengembalikan true bila pesan berisi permintaan berhenti. */
  recordInbound(phone, text) {
    this.state.inbound[phone] = this.now();
    if (OPT_OUT.test(text || '')) {
      this.state.optOut[phone] = this.now();
      this.save();
      return true;
    }
    // Chat lagi setelah opt-out = setuju dihubungi lagi.
    delete this.state.optOut[phone];
    this.save();
    return false;
  }

  /** @returns {{ok:true, kind:'reply'|'cold'} | {ok:false, reason:string}} */
  check(phone, message) {
    const L = this.limits;
    const t = this.now();
    this.prune(t);
    if (!message || !message.trim()) return { ok: false, reason: 'pesan kosong' };
    if (message.length > L.maxLength) return { ok: false, reason: `pesan lebih dari ${L.maxLength} karakter` };
    if (this.state.optOut[phone]) return { ok: false, reason: 'nomor ini minta berhenti dihubungi (STOP)' };

    const last = this.state.inbound[phone];
    const kind = last && t - last <= L.replyWindowHours * HOUR ? 'reply' : 'cold';
    const sent = this.state.sent;
    const toPhone = sent.filter((s) => s.phone === phone);

    if (sent.filter((s) => t - s.at < 60_000).length >= L.globalPerMinute) return { ok: false, reason: 'batas global per menit' };
    if (sent.length >= L.globalDaily) return { ok: false, reason: 'batas global harian' };
    if (toPhone.filter((s) => t - s.at < HOUR).length >= L.perNumberHourly) return { ok: false, reason: 'batas per nomor per jam' };
    if (toPhone.length >= L.perNumberDaily) return { ok: false, reason: 'batas per nomor per hari' };
    if (toPhone.some((s) => s.hash === hash(message) && t - s.at < L.duplicateWindowMinutes * 60_000)) {
      return { ok: false, reason: 'pesan sama persis baru saja dikirim ke nomor ini' };
    }
    if (kind === 'cold') {
      if (sent.filter((s) => s.kind === 'cold').length >= L.coldDaily) return { ok: false, reason: 'batas pesan dingin harian' };
      if (toPhone.some((s) => s.kind === 'cold' && t - s.at < L.coldPerNumberGapHours * HOUR)) {
        return { ok: false, reason: `pesan dingin ke nomor ini maksimal sekali per ${L.coldPerNumberGapHours} jam` };
      }
    }
    return { ok: true, kind };
  }

  recordSent(phone, message, kind) {
    this.state.sent.push({ phone, at: this.now(), kind, hash: hash(message) });
    this.save();
  }

  prune(t = this.now()) {
    this.state.sent = this.state.sent.filter((s) => t - s.at < DAY);
    for (const [p, at] of Object.entries(this.state.inbound)) if (t - at > 7 * DAY) delete this.state.inbound[p];
  }

  stats() {
    const t = this.now();
    this.prune(t);
    return {
      sentToday: this.state.sent.length,
      coldToday: this.state.sent.filter((s) => s.kind === 'cold').length,
      lastMinute: this.state.sent.filter((s) => t - s.at < 60_000).length,
      optedOut: Object.keys(this.state.optOut).length,
      limits: this.limits,
    };
  }

  save() {
    if (!this.file) return;
    const tmp = `${this.file}.tmp`;
    fs.writeFileSync(tmp, JSON.stringify(this.state));
    fs.renameSync(tmp, this.file);
  }
}

function hash(s) {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (Math.imul(31, h) + s.charCodeAt(i)) | 0;
  return h;
}
