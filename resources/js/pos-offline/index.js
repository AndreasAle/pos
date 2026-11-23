/**
 * Offline register engine (docs/offline-mode/01-baseline-and-design.md).
 *
 * Loaded only on /pos when FEATURE_POS_OFFLINE is on. The Alpine register
 * (resources/views/pos/index.blade.php) talks to it through window.YokaOffline:
 *
 *   const off = await YokaOffline.start(config, onChange)
 *   off.state                  -> { online, pending, failed, syncing, auth, lastOnlineAt }
 *   off.canSell()              -> '' or the reason selling offline is blocked
 *   await off.record(sale)     -> the queued sale, with its OFF-... number
 *   await off.sync()           -> push the queue now
 *   await off.list()           -> every sale this device holds for the business
 *   off.print(sale)            -> print through the tablet app / RawBT
 */
import { renderReceipt, rawbtLink } from './escpos.js';
import * as db from './store.js';

const PING_EVERY_MS = 30_000;
const PING_TIMEOUT_MS = 5_000;
const BATCH = 20;
const KEEP_SYNCED_DAYS = 7;

export function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

function ymd(d) {
    return String(d.getFullYear()).slice(2) + String(d.getMonth() + 1).padStart(2, '0') + String(d.getDate()).padStart(2, '0');
}

async function fetchJson(url, opts = {}, timeout = PING_TIMEOUT_MS) {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), timeout);
    try {
        const res = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            ...opts,
            headers: { Accept: 'application/json', ...(opts.headers || {}) },
            signal: ctrl.signal,
        });
        let body = null;
        try { body = await res.json(); } catch (e) { /* not JSON */ }
        return { status: res.status, body };
    } finally {
        clearTimeout(t);
    }
}

class OfflineRegister {
    constructor(config, onChange) {
        this.cfg = config;
        this.onChange = onChange || (() => {});
        this.deviceId = null;
        this.csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        this.state = {
            online: navigator.onLine,
            pending: 0,
            failed: 0,
            syncing: false,
            auth: true,           // false: session expired, sync waits for PIN login
            lastOnlineAt: null,
            lastSyncAt: null,
            persisted: false,
            error: '',
        };
        this._syncing = null;
    }

    async init() {
        this.deviceId = await db.getMeta('device_id');
        if (!this.deviceId) {
            this.deviceId = uuid();
            await db.setMeta('device_id', this.deviceId);
        }

        // The page was just served by the server (not from cache) when its
        // snapshot is fresh: that counts as having been online.
        const lastKey = this.key('last_online_at');
        const stored = await db.getMeta(lastKey);
        const snap = Date.parse(this.cfg.snapshot_at) || 0;
        this.state.lastOnlineAt = Math.max(stored || 0, snap) || null;

        try {
            if (navigator.storage?.persist) this.state.persisted = await navigator.storage.persist();
        } catch (e) { /* best effort */ }

        this.registerWorker();

        addEventListener('online', () => this.heartbeat());
        addEventListener('offline', () => this.setOnline(false));
        document.addEventListener('visibilitychange', () => { if (!document.hidden) this.heartbeat(); });
        setInterval(() => this.heartbeat(), PING_EVERY_MS);

        await this.refreshCounts();
        this.heartbeat();
        return this;
    }

    key(name) {
        return `${name}:${this.cfg.business_id}`;
    }

    registerWorker() {
        if (!('serviceWorker' in navigator) || !this.cfg.sw_url) return;
        navigator.serviceWorker.register(this.cfg.sw_url, { scope: '/' }).catch((e) => {
            this.state.error = 'Service worker gagal dipasang: ' + e.message;
            this.emit();
        });
        // The very first visit loaded the page before the worker existed, so
        // nothing is cached yet. Ask it to fetch the register and the CDN files
        // now, so going offline right after the first visit already works.
        navigator.serviceWorker.ready.then((reg) => {
            const cdn = [...document.querySelectorAll('script[src], link[rel="stylesheet"][href]')]
                .map((el) => el.src || el.href)
                .filter((u) => /^https:\/\/(cdn\.jsdelivr\.net|fonts\.bunny\.net)\//.test(u));
            reg.active?.postMessage({ type: 'warm', urls: cdn });
        }).catch(() => {});
    }

    emit() {
        try { this.onChange({ ...this.state }); } catch (e) { /* UI problem, not ours */ }
    }

    setOnline(online) {
        if (this.state.online !== online) {
            this.state.online = online;
            this.emit();
        }
    }

    /** Ask the server. navigator.onLine alone says "Wi-Fi up", not "internet works". */
    async heartbeat() {
        if (!navigator.onLine) { this.setOnline(false); return false; }
        try {
            const { status, body } = await fetchJson(this.cfg.ping_url);
            if (status === 200 && body?.ok) {
                this.csrf = body.csrf || this.csrf;
                // A page served from the offline cache carries an old token; the
                // register's own fetch() calls read it from this meta tag.
                document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', this.csrf);
                this.state.auth = true;
                this.state.lastOnlineAt = Date.now();
                await db.setMeta(this.key('last_online_at'), this.state.lastOnlineAt);
                this.setOnline(true);
                if (this.state.pending > 0) this.sync();
                return true;
            }
            if (status === 401 || status === 419) {
                // Reachable but signed out: sales stay queued until a PIN login.
                this.state.auth = false;
                this.setOnline(true);
                return false;
            }
            this.setOnline(false);
            return false;
        } catch (e) {
            this.setOnline(false);
            return false;
        }
    }

    async refreshCounts() {
        const sales = await db.salesFor(this.cfg.business_id);
        this.state.pending = sales.filter((s) => s.status === 'queued' || s.status === 'retry').length;
        this.state.failed = sales.filter((s) => s.status === 'rejected').length;
        this.emit();
        return sales;
    }

    /** '' when a cash sale may be rung up offline, otherwise why not. */
    canSell() {
        const hours = this.cfg.max_hours || 12;
        const last = this.state.lastOnlineAt || 0;
        if (!last || Date.now() - last > hours * 3600_000) {
            return `Sudah lebih dari ${hours} jam tanpa internet. Sambungkan internet agar transaksi tersinkron, lalu lanjutkan.`;
        }
        if (this.state.pending >= (this.cfg.max_orders || 300)) {
            return `Antrean offline penuh (${this.cfg.max_orders} transaksi). Sambungkan internet untuk sinkron.`;
        }
        return '';
    }

    async offlineNumber(at) {
        const day = ymd(at);
        const seq = await db.nextCounter(`seq:${this.cfg.business_id}:${day}`);
        const code = this.deviceId.replace(/-/g, '').slice(0, 3).toUpperCase();
        return `OFF-${code}-${day}-${String(seq).padStart(4, '0')}`;
    }

    /**
     * Keep a finished cash sale. Written before the receipt prints and before
     * the cart clears: if this throws, the sale did not happen.
     */
    async record(sale) {
        // Same sale tapped twice (or a cart restored after a crash): keep the
        // first record, never overwrite money already taken.
        if (sale.client_uuid) {
            const existing = await db.getSale(sale.client_uuid);
            if (existing) return existing;
        }
        const at = new Date();
        const full = {
            ...sale,
            client_uuid: sale.client_uuid || uuid(),
            business_id: this.cfg.business_id,
            outlet_id: this.cfg.outlet_id,
            user_id: this.cfg.user_id,
            user_name: this.cfg.user_name,
            shift_id: this.cfg.shift_id,
            catalog_version: this.cfg.catalog_version,
            ordered_at: at.toISOString(),
            offline_number: await this.offlineNumber(at),
            status: 'queued',
            attempts: 0,
            message: '',
        };
        await db.putSale(full);
        await this.refreshCounts();
        return full;
    }

    async list() {
        const sales = await db.salesFor(this.cfg.business_id);
        return sales.sort((a, b) => (b.ordered_at || '').localeCompare(a.ordered_at || ''));
    }

    sync() {
        if (!this._syncing) {
            this._syncing = this._sync().finally(() => { this._syncing = null; });
        }
        return this._syncing;
    }

    async _sync() {
        if (!(await this.heartbeat()) && !this.state.online) return;
        if (!this.state.auth) return;

        this.state.syncing = true;
        this.emit();
        try {
            for (let round = 0; round < 50; round++) {
                const queue = (await db.salesFor(this.cfg.business_id))
                    .filter((s) => s.status === 'queued' || s.status === 'retry')
                    .sort((a, b) => (a.ordered_at || '').localeCompare(b.ordered_at || ''))
                    .slice(0, BATCH);
                if (!queue.length) break;

                const { status, body } = await fetchJson(this.cfg.sync_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ device_id: this.deviceId, orders: queue.map(toPayload) }),
                }, 30_000);

                if (status === 419) { await this.heartbeat(); continue; }
                if (status === 401) { this.state.auth = false; break; }
                if (status !== 200 || !body?.results) {
                    this.state.error = `Sinkron gagal (HTTP ${status}). Dicoba lagi otomatis.`;
                    break;
                }

                for (const r of body.results) {
                    const sale = queue.find((s) => s.client_uuid === r.client_uuid);
                    if (!sale) continue;
                    if (r.status === 'synced' || r.status === 'duplicate') {
                        Object.assign(sale, {
                            status: 'synced',
                            order_id: r.order_id,
                            order_number: r.order_number,
                            review: r.review || null,
                            synced_at: new Date().toISOString(),
                            message: '',
                        });
                    } else if (r.status === 'rejected') {
                        Object.assign(sale, { status: 'rejected', message: r.message || 'Ditolak server.' });
                    } else {
                        Object.assign(sale, { status: 'retry', attempts: (sale.attempts || 0) + 1, message: r.message || '' });
                    }
                    await db.putSale(sale);
                }
                this.state.error = '';
                this.state.lastSyncAt = Date.now();
                if (body.results.every((r) => r.status === 'retry')) break;
            }
            await this.prune();
        } catch (e) {
            this.state.error = 'Sinkron terputus. Dicoba lagi otomatis.';
        } finally {
            this.state.syncing = false;
            await this.refreshCounts();
        }
    }

    /** Drop confirmed sales after a week; the server has them. */
    async prune() {
        const cutoff = Date.now() - KEEP_SYNCED_DAYS * 86400_000;
        for (const s of await db.salesFor(this.cfg.business_id)) {
            if (s.status === 'synced' && Date.parse(s.synced_at || 0) < cutoff) await db.deleteSale(s.client_uuid);
        }
    }

    receiptBytes(sale) {
        return renderReceipt(sale, this.cfg.receipt);
    }

    /** Hand the receipt to the tablet app (it intercepts rawbt:) or to RawBT. */
    print(sale) {
        window.location.href = rawbtLink(this.receiptBytes(sale));
    }
}

function toPayload(s) {
    return {
        client_uuid: s.client_uuid,
        offline_number: s.offline_number,
        user_id: s.user_id,
        shift_id: s.shift_id,
        ordered_at: s.ordered_at,
        order_type: s.order_type,
        table_number: s.table_number || null,
        items: s.items.map((i) => ({
            product_id: i.product_id,
            qty: i.qty,
            variant_id: i.variant_id || null,
            notes: i.notes || null,
            addons: (i.addons || []).map((a) => ({ addon_id: a.id })),
        })),
        paid_amount: s.paid_amount,
        change_amount: s.change_amount,
        grand_total: s.grand_total,
        catalog_version: s.catalog_version,
    };
}

window.YokaOffline = {
    uuid,
    async start(config, onChange) {
        if (!config || !config.enabled || !('indexedDB' in window)) return null;
        const reg = new OfflineRegister(config, onChange);
        return reg.init();
    },
};
window.dispatchEvent(new Event('yoka-offline:ready'));
