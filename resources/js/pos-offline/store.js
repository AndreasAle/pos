/**
 * IndexedDB for the offline register: the outbox of cash sales waiting to
 * reach the server, and a little device state (id, receipt counters, last
 * time the server answered).
 *
 * The outbox is the only record of money already taken, so nothing here is
 * ever deleted before the server has confirmed the sale.
 */

const DB_NAME = 'yoka-pos-offline';
const DB_VERSION = 1;

let dbPromise = null;

export function openDb() {
    if (dbPromise) return dbPromise;
    dbPromise = new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains('outbox')) {
                const s = db.createObjectStore('outbox', { keyPath: 'client_uuid' });
                s.createIndex('business_id', 'business_id');
            }
            if (!db.objectStoreNames.contains('meta')) {
                db.createObjectStore('meta', { keyPath: 'key' });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => { dbPromise = null; reject(req.error); };
    });
    return dbPromise;
}

function tx(store, mode, fn) {
    return openDb().then((db) => new Promise((resolve, reject) => {
        const t = db.transaction(store, mode);
        const s = t.objectStore(store);
        let result;
        Promise.resolve(fn(s)).then((r) => { result = r; });
        t.oncomplete = () => resolve(result);
        t.onerror = () => reject(t.error);
        t.onabort = () => reject(t.error || new Error('IndexedDB transaction aborted'));
    }));
}

function req2promise(r) {
    return new Promise((resolve, reject) => {
        r.onsuccess = () => resolve(r.result);
        r.onerror = () => reject(r.error);
    });
}

export async function getMeta(key, fallback = null) {
    const row = await tx('meta', 'readonly', (s) => req2promise(s.get(key)));
    return row ? row.value : fallback;
}

export function setMeta(key, value) {
    return tx('meta', 'readwrite', (s) => { s.put({ key, value }); });
}

/** Atomically bump a counter (receipt numbers per device per day). */
export function nextCounter(key) {
    return tx('meta', 'readwrite', (s) => new Promise((resolve, reject) => {
        const g = s.get(key);
        g.onsuccess = () => {
            const value = ((g.result && g.result.value) || 0) + 1;
            s.put({ key, value });
            resolve(value);
        };
        g.onerror = () => reject(g.error);
    }));
}

export function putSale(sale) {
    return tx('outbox', 'readwrite', (s) => { s.put(sale); });
}

export function getSale(uuid) {
    return tx('outbox', 'readonly', (s) => req2promise(s.get(uuid)));
}

export function salesFor(businessId) {
    return tx('outbox', 'readonly', (s) => req2promise(s.index('business_id').getAll(businessId)));
}

export function deleteSale(uuid) {
    return tx('outbox', 'readwrite', (s) => { s.delete(uuid); });
}
