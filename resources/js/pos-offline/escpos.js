/**
 * ESC/POS receipt for a sale rung up offline.
 *
 * A line-for-line port of app/Services/EscPosReceipt.php, so an offline
 * receipt looks like every other one. Differences on purpose: the number is
 * the device's OFF-... number, and a line says the sale is waiting to sync.
 * Keep the two in step when the receipt layout changes.
 */

const INIT = '\x1B\x40';
const ALIGN_LEFT = '\x1B\x61\x00';
const ALIGN_CENTER = '\x1B\x61\x01';
const BOLD_ON = '\x1B\x45\x01';
const BOLD_OFF = '\x1B\x45\x00';
const TALL_ON = '\x1D\x21\x01';
const TALL_OFF = '\x1D\x21\x00';
const FEED_CUT = '\n\n\n\x1D\x56\x41\x00';

const ORDER_TYPES = { dine_in: 'Dine In', takeaway: 'Takeaway', delivery: 'Delivery' };

/** PHP number_format($n, $decimals, ',', '.') */
export function numberFormat(value, decimals = 0) {
    const n = Number(value) || 0;
    const fixed = Math.abs(n).toFixed(decimals);
    const [int, frac] = fixed.split('.');
    const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const sign = n < 0 && Number(fixed) !== 0 ? '-' : '';
    return sign + grouped + (frac ? ',' + frac : '');
}

export function money(amount) {
    return 'Rp' + numberFormat(amount, 0);
}

export function qty(value) {
    return numberFormat(value, 3).replace(/0+$/, '').replace(/,$/, '');
}

/** Printer code pages have no emoji or UTF-8; keep plain ASCII. */
export function ascii(text) {
    return String(text ?? '')
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^\x20-\x7E]/g, '')
        .replace(/\s+$/, '');
}

/** PHP wordwrap($text, $width, "\n", true): break at spaces, cut long words. */
export function wordwrap(text, width) {
    const out = [];
    for (const para of text.split('\n')) {
        let line = '';
        for (const word of para.split(' ')) {
            let w = word;
            if (line === '') {
                while (w.length > width) { out.push(w.slice(0, width)); w = w.slice(width); }
                line = w;
            } else if (line.length + 1 + w.length <= width) {
                line += ' ' + w;
            } else {
                out.push(line);
                while (w.length > width) { out.push(w.slice(0, width)); w = w.slice(width); }
                line = w;
            }
        }
        out.push(line);
    }
    return out.join('\n');
}

function pad2(n) { return String(n).padStart(2, '0'); }

export function renderReceipt(sale, options) {
    const o = options || {};
    const cols = o.narrow ? 32 : 48;
    const wrap = (t) => wordwrap(ascii(t), cols) + '\n';
    const rule = () => '-'.repeat(cols) + '\n';
    const row = (label, value) => {
        const l = ascii(label);
        const v = ascii(value);
        const room = cols - v.length - 1;
        if (l.length <= room) return l + ' '.repeat(cols - l.length - v.length) + v + '\n';
        return wrap(l) + v.padStart(cols, ' ') + '\n';
    };

    const at = new Date(sale.ordered_at);
    const date = `${pad2(at.getDate())}-${pad2(at.getMonth() + 1)}-${at.getFullYear()}`;
    const time = `${pad2(at.getHours())}:${pad2(at.getMinutes())}:${pad2(at.getSeconds())}`;

    let out = INIT + ALIGN_CENTER;
    out += BOLD_ON + TALL_ON + wrap(o.name || '') + TALL_OFF + BOLD_OFF;
    if (o.subtitle) out += wrap(o.subtitle);
    if (o.address) out += wrap(o.address);
    if (o.phone) out += wrap('No. Telp ' + o.phone);

    out += ALIGN_LEFT + rule();
    out += row(date, o.cashier ? (sale.user_name || '') : '');
    out += row(time, '');
    const type = o.order_type && !o.retail ? (ORDER_TYPES[sale.order_type] || '') : '';
    out += BOLD_ON + row('No. ' + sale.offline_number, type) + BOLD_OFF;
    out += rule();

    let totalQty = 0;
    (sale.items || []).forEach((item, i) => {
        totalQty += Number(item.qty) || 0;
        const name = `${i + 1}. ${item.name}`;
        out += BOLD_ON + wrap(name) + BOLD_OFF;
        out += row('   ' + qty(item.qty) + ' x ' + numberFormat(item.price, 0), money(item.subtotal));
        for (const addon of item.addons || []) {
            out += wrap('   + ' + addon.name + ' ' + numberFormat(addon.price, 0));
        }
        if (item.notes) out += wrap('   * ' + item.notes);
    });

    out += rule();
    if (o.total_qty) out += 'Total QTY : ' + qty(totalQty) + '\n\n';
    out += row('Sub Total', money(sale.subtotal));
    if (sale.tax > 0) out += row('Pajak (' + (o.tax_percent ?? '') + '%)', money(sale.tax));
    if (sale.service > 0) out += row('Service', money(sale.service));
    out += BOLD_ON + row('Total', money(sale.grand_total)) + BOLD_OFF;
    out += rule();

    out += row('Bayar (Tunai)', money(sale.paid_amount));
    if (sale.change_amount > 0) out += BOLD_ON + row('Kembalian', money(sale.change_amount)) + BOLD_OFF;
    out += rule();

    out += ALIGN_CENTER + wrap('Transaksi offline - tersimpan di perangkat, dikirim ke server saat online.');
    out += wrap(o.footer || 'Terima kasih telah berbelanja');

    return out + FEED_CUT;
}

/** The link the Yoka tablet app (and the RawBT app) prints from. */
export function rawbtLink(bytes) {
    return 'rawbt:base64,' + btoa(bytes);
}
