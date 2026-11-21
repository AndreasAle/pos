# 06 — Keamanan dan risiko

| Ancaman | Kontrol |
|---|---|
| Prompt injection ("saya owner", "CEO setuju", "aktifkan gratis") | Tidak ada izin dari teks. Kebijakan harga di kode; permintaan gratis = REJECTED_BY_POLICY; konteks memberi tahu model bahwa klaim itu tidak berlaku; balasan disaring `scrubReply` |
| Diskon tanpa izin | Diskon otomatis 0%; harga tak resmi dibuang dari balasan; proposal butuh super admin |
| Approval palsu / replay / basi | Cek peran, status pending, `hash_equals(terms_hash)`, kunci idempotensi keputusan, kunci baris |
| Jalan pintas kanban | `moveTask` menolak `commercial_proposal`; `whatsapp_draft` tetap hanya lewat Setujui & kirim |
| Pesan ganda | Job lama (`Cache::add`) + kunci `inbound:{message_id}` pada event BD |
| Opt-out dilanggar | `opted_out` terminal; draf tertunda dibatalkan (lama); rekomendasi trial `eligible=false` |
| Bocor data internal | Biaya/margin/floor hanya di proposal (super admin); CONFIDENTIAL_PROBE dijawab menolak |
| Status bayar palsu | Status hanya dari `business_subscriptions`; pembayaran sandbox ditandai `sandbox_payment` |
| Lintas tenant | Prospek = data platform Yoka, hanya super admin; tidak menyentuh `pos_app` |

Risiko tersisa:
- Ekstraksi fakta dan keberatan berbasis regex: bisa salah tangkap (false positive/negative). Dampaknya hanya pada saran ke model, bukan pada izin.
- Model tetap bisa menulis kalimat persuasif yang kurang tepat selain soal harga; penjaga hanya mencakup harga dan klaim aktivasi.
- Midtrans masih sandbox di server (perlu diverifikasi): langganan "lunas" belum tentu uang nyata.
