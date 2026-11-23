// Run: node --test tests/js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { numberFormat, money, qty, ascii, wordwrap, renderReceipt, rawbtLink } from '../../resources/js/pos-offline/escpos.js';

test('numbers are formatted like PHP number_format(.., ",", ".")', () => {
    assert.equal(numberFormat(1234567), '1.234.567');
    assert.equal(numberFormat(0), '0');
    assert.equal(numberFormat(999.5), '1.000');
    assert.equal(money(40000), 'Rp40.000');
    assert.equal(qty(2), '2');
    assert.equal(qty(1.5), '1,5');
    assert.equal(qty(0.125), '0,125');
});

test('text is reduced to printable ASCII', () => {
    assert.equal(ascii('Café Lâtte ☕'), 'Cafe Latte');
    assert.equal(ascii(null), '');
});

test('wordwrap breaks at spaces and cuts long words like PHP', () => {
    assert.equal(wordwrap('Es Kopi Susu Aren Gula Merah', 10), 'Es Kopi\nSusu Aren\nGula Merah');
    assert.equal(wordwrap('ABCDEFGHIJKL', 5), 'ABCDE\nFGHIJ\nKL');
});

const sale = {
    offline_number: 'OFF-A7K-261123-0001',
    ordered_at: '2026-11-23T08:05:09.000Z',
    user_name: 'Kasir Tes',
    order_type: 'takeaway',
    items: [
        { name: 'Kopi Tubruk', qty: 2, price: 12000, subtotal: 34000, addons: [{ name: 'Extra Shot', price: 5000 }], notes: 'TES OFFLINE' },
    ],
    subtotal: 34000, tax: 0, service: 0, grand_total: 34000, paid_amount: 50000, change_amount: 16000,
};
const opts = { name: 'Toko Tes', footer: 'Terima kasih', cashier: true, order_type: true, total_qty: true, narrow: true };

test('receipt has the offline number, items, totals and fits 58mm', () => {
    const out = renderReceipt(sale, opts);
    assert.ok(out.startsWith('\x1B\x40'));
    assert.ok(out.endsWith('\x1D\x56\x41\x00'));
    assert.match(out, /No\. OFF-A7K-261123-0001\s+Takeaway/);
    assert.match(out, /1\. Kopi Tubruk/);
    assert.match(out, /2 x 12\.000\s+Rp34\.000/);
    assert.match(out, /\+ Extra Shot 5\.000/);
    assert.match(out, /\* TES OFFLINE/);
    assert.match(out, /Kembalian\s+Rp16\.000/);
    assert.match(out, /Transaksi offline/);
    const printable = out.replace(/\x1B.\x00|\x1B.\x01|\x1D.\x00|\x1D.\x01|\x1D\x56\x41\x00|\x1B\x40/g, '');
    for (const line of printable.split('\n')) assert.ok(line.length <= 32, `too wide: "${line}"`);
});

test('receipt is pure 8-bit so it base64-encodes into a rawbt link', () => {
    const link = rawbtLink(renderReceipt(sale, { ...opts, narrow: false }));
    assert.match(link, /^rawbt:base64,[A-Za-z0-9+/=]+$/);
});
