import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Guard } from '../src/guard.js';

const clock = () => { let t = 1_800_000_000_000; const f = () => t; f.add = (ms) => { t += ms; }; return f; };
const A = '6281111111111';

test('balasan ke orang yang baru chat diizinkan sebagai reply', () => {
  const now = clock(); const g = new Guard(null, {}, now);
  g.recordInbound(A, 'halo');
  assert.deepEqual(g.check(A, 'hai kak'), { ok: true, kind: 'reply' });
});

test('pesan ke nomor yang tidak pernah chat dihitung dingin dan dibatasi', () => {
  const now = clock(); const g = new Guard(null, { coldDaily: 2 }, now);
  for (let i = 0; i < 2; i++) {
    const p = `62822222222${i}0`;
    assert.equal(g.check(p, 'info trial').kind, 'cold');
    g.recordSent(p, 'info trial', 'cold');
  }
  assert.equal(g.check('6283333333330', 'info trial').ok, false);
});

test('pesan dingin ke nomor yang sama tidak boleh dua kali dalam 72 jam', () => {
  const now = clock(); const g = new Guard(null, {}, now);
  g.recordSent(A, 'satu', 'cold');
  now.add(3600_000);
  assert.match(g.check(A, 'dua').reason, /sekali per 72 jam/);
});

test('STOP memblokir semua kiriman, chat lagi membukanya', () => {
  const now = clock(); const g = new Guard(null, {}, now);
  assert.equal(g.recordInbound(A, 'STOP'), true);
  assert.equal(g.check(A, 'halo').ok, false);
  g.recordInbound(A, 'kak mau tanya');
  assert.equal(g.check(A, 'halo').ok, true);
});

test('batas per menit, per jam, dan pesan duplikat', () => {
  const now = clock(); const g = new Guard(null, { globalPerMinute: 3, perNumberHourly: 5 }, now);
  g.recordInbound(A, 'halo');
  for (let i = 0; i < 3; i++) g.recordSent(A, `m${i}`, 'reply');
  assert.match(g.check(A, 'baru').reason, /per menit/);
  now.add(61_000);
  assert.match(g.check(A, 'm0').reason, /sama persis/);
  g.recordSent(A, 'x', 'reply'); g.recordSent(A, 'y', 'reply');
  now.add(61_000);
  assert.match(g.check(A, 'z').reason, /per nomor per jam/);
});

test('kiriman lebih dari sehari lalu tidak dihitung lagi', () => {
  const now = clock(); const g = new Guard(null, { globalDaily: 1 }, now);
  g.recordSent(A, 'a', 'cold');
  assert.equal(g.check('6284444444440', 'b').ok, false);
  now.add(25 * 3600_000);
  assert.equal(g.check('6284444444440', 'b').ok, true);
});
