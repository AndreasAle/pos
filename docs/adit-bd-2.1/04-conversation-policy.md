# 04 — Kebijakan percakapan

Tujuan balasan diputuskan `ConversationStrategy::decide` (urutan prioritas):
1. CLOSE_CONVERSATION — "stop" (OPTED_OUT), "belum butuh" (NOT_INTERESTED), "nanti aku kabarin / makasih / pikir-pikir dulu" tanpa pertanyaan (CUSTOMER_WILL_RECONTACT).
2. REQUEST_HUMAN_APPROVAL — minta orang/meeting atau permintaan harga yang butuh approval.
3. BUILD_TRUST — pengalaman buruk / ragu.
4. OFFER_TRIAL — minta coba/daftar.
5. IDENTIFY_HARDWARE_INTEREST — printer/tablet/perangkat.
6. ANSWER_DIRECT_QUESTION — tanya harga atau ada tanda tanya.
7. HANDLE_OBJECTION, lalu DISCOVER_NEED.

Aturan:
- Maksimal satu pertanyaan; tidak bertanya saat menutup, saat memberi link trial yang diminta, atau saat menunggu approval.
- Link trial hanya bila diminta, saat menjawab harga (belum pernah dikirim), atau prospek sudah terkualifikasi.
- Penutupan: terima kasih, sebut satu kebutuhan yang tadi diceritakan, tawarkan bantuan bila nanti lanjut; tanpa pertanyaan, link, atau jualan baru. Dicatat `intelligence.last_outcome` dengan `follow_up_allowed=false`; lead tidak ditandai lost.
- Tujuan internal tidak pernah disebut ke prospek.
- Satu pesan WhatsApp per giliran untuk prospek (`bd21.one_message`).
- Jendela gabung pesan beruntun opsional (`ADIT_INBOUND_COALESCE_SECONDS`, maks 15 detik, default mati). Tanpa itu, penggabungan tetap terjadi bila pesan berikutnya masuk sebelum job berjalan (perilaku lama).
- Tidak ada jeda palsu/"mengetik" tambahan; Adit mengaku asisten AI.
