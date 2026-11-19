# Adit CS: analisis buku "Pelayanan Prima" dan implementasinya

Sumber: Dr. Wier Ritonga, SE., MM., *Pelayanan Prima*, PT. Muara Karya, Surabaya, 2020 (ISBN 978-623-7669-03-6).
Buku dipakai sebagai referensi internal. Isinya tidak disalin ke repo. Yang ditulis di sini hanya ringkasan prinsip dan rujukan halaman.

Notasi halaman: **hlm.** = nomor cetak di buku, **pdf** = halaman file PDF. Rumusnya pdf = hlm. + 9.

## 1. Cakupan ekstraksi

- File berisi 278 halaman PDF. Semua halaman diekstrak sebagai teks dengan PyMuPDF.
- Hanya halaman 1 (sampul) yang tidak berisi teks. Halaman itu tidak memuat materi.
- Bab yang **dibaca penuh** karena relevan dengan CS:

| Bab | hlm. | pdf |
|---|---|---|
| II Komunikasi | 31-50 | 40-59 |
| III etika telepon | 51-55, 66-72 | 60-64, 75-81 |
| IV Pelayanan Prima, pelanggan, kode etik, humas | 95-118 | 104-127 |
| VI Layanan | 176-196 | 185-205 |
| VII Konflik | 197-212 | 206-221 |
| VIII Etika profesi | 213-226 | 222-235 |
| IX Kepuasan | 238-247 | 247-256 |
| X Loyalitas | 248-264 | 257-273 |

- Bab yang **dipindai** (dicari kata pelanggan, keluhan, komplain, klien) dan dinilai tidak relevan untuk agen chat:
  - Bab I Rapat.
  - Bagian teknis telekomunikasi di Bab III.
  - Kerja tim internal di Bab IV.
  - Bab V Komputer.
  - Sisa Bab VIII: kedokteran, teknik mesin, organisasi profesi.

## 2. Pemetaan buku ke implementasi

Kode lokasi:
- **PB**: `app/Services/PlatformAgents/CustomerService/ServicePlaybook.php`.
- **SIG**: `CustomerSignals.php`.
- **PK**: `ProductKnowledge.php`.
- **JOB**: `app/Jobs/DraftWhatsAppReply.php`.
- **T**: `tests/Feature/Platform/AditCustomerServiceTest.php`.

| # | hlm. (pdf) | Prinsip buku | Kompetensi | Perilaku Adit | Kode | Tes | Status |
|---|---|---|---|---|---|---|---|
| 1 | 42-44, 47 (51-53, 56) | Pesan punya konten kognitif dan afektif. Refleksikan perasaan dengan intensitas yang sesuai. | Mendengar | Menanggapi fakta dan perasaan yang tertulis, tanpa melebih-lebihkan atau mendiagnosis | PB `fakta_dan_rasa` | skenario "error rugi", "marah kasar" | Selesai |
| 2 | 47-49 (56-58) | Sepuluh respons verbal: parafrase, klarifikasi, checking out, informing, meringkas | Mendengar aktif | Mengulang masalah dengan kata sendiri. Bertanya satu hal hanya bila perlu. | PB `klarifikasi`, situasi KELUHAN | `test_repeated_explanation...` | Selesai |
| 3 | 49 (58) | Pisahkan menginformasikan dari menasihati | Komunikasi | Memberi fakta dan langkah; keputusan tetap di pelanggan | PB `informasi_bukan_menggurui` | skenario "banding moka" | Selesai |
| 4 | 39-40, 102 (48-49, 111) | Syarat komunikasi: lengkap, tepat, jelas, bahasa benar. Hambatan semantik. | Komunikasi | Info utama di kalimat pertama, tidak ambigu | PB `syarat_pesan` | semua skenario (prompt) | Selesai |
| 5 | 100-102 (109-111) | Sikap simpati dan rendah hati. Meremehkan menghambat komunikasi. | Sikap | Tidak meremehkan, tidak memerintah | PB `sikap` | skenario "marah kasar" | Selesai |
| 6 | 67-70 (76-79) | Etika telepon: respons cepat dan lugas, hangat; hindari nada memerintah; jangan dioper berkali-kali; ulangi pesan untuk mencegah salah | Etika kanal | Ringkas dan hangat. Satu kali eskalasi, bukan dilempar. | PB `sikap`, situasi MINTA ORANG | `test_human_request...` | Diadaptasi ke chat |
| 7 | 109-111 (118-120) | Konsep A3: sikap, perhatian (mendengar, mengamati, berpikir), tindakan | Pelayanan prima | Membaca seluruh riwayat sebelum menjawab, lalu memberi langkah nyata | PB `dengar`, JOB (riwayat 16 pesan) | `test_messages_typed_in_a_burst...` | Selesai |
| 8 | 179-180 (188-189) | Enam A: ability, attitude, appearance, attention, action, accountability | Pelayanan prima | Ability lewat PK, attention lewat SIG, accountability lewat kanban | PB, PK, JOB | semua | Selesai |
| 9 | 178 (187) | Dimensi kualitas: cepat, akurat, sopan, bertanggung jawab atas komplain, info tersedia | Kualitas | Satu panggilan model, panduan resmi, keluhan selalu tercatat | JOB, PK | `test_one_model_call...` | Selesai |
| 10 | 186-187 (195-196) | Benar-benar mendengar, jangan menyela, jangan abaikan pertanyaan. Janji yang mudah dipenuhi. | Mendengar, integritas | Setiap pertanyaan dijawab. Tidak ada janji waktu atau hasil. | PB `jangan_abaikan`, `janji`; JOB UNVERIFIED_CLAIM | `test_unverified_claims...` | Selesai |
| 11 | 189 (198) | Kuasai produk | Pengetahuan | Fitur dari FAKTA PRODUK, langkah dari panduan resmi | PK, AgentBrain::productFacts | `test_printer_question...` | Selesai |
| 12 | 189-190 (198-199) | Sikap positif. Tulisan mudah terdengar dingin. | Sikap | Kata hangat. Emoji paling banyak satu (buku menganjurkan emoji; aturan rumah membatasi). | PB `sikap`, prompt rumah | n/a (rubrik manusia) | Selesai |
| 13 | 191-192 (200-201) | Tanggapi cepat, tapi jangan menutup sebelum tuntas | Kecepatan | Situasi MENDESAK: langkah tercepat agar toko tetap jualan | PB situasi MENDESAK | skenario "lemot rame" | Selesai |
| 14 | 192 (201) | Personalisasi; pelanggan tidak ingin diperlakukan seperti robot | Personalisasi | Memakai konteks yang diceritakan pelanggan | PB `personal` | rubrik manusia | Selesai |
| 15 | 192-193 (201-202) | Bantu pelanggan membantu diri sendiri (self-service) | Swalayan | Memberi tautan yokapos.com/panduan/... | PB `bantu_mandiri`, PK | `test_printer_question...` | Selesai |
| 16 | 193-194 (202-203) | Mendengar aktif: "Kedengarannya...", ulangi masalah, jangan berasumsi, sesuaikan dengan emosi | Mendengar aktif | Situasi KELUHAN langkah 2; situasi SUDAH MENJELASKAN | PB, SIG REPEATED | skenario "sudah jelasin" | Selesai |
| 17 | 195 (204) | Pegang kata-kata. Janji yang dilanggar ditebus dengan kompensasi. | Integritas | Pegang kata: ya. **Kompensasi: tidak** (keputusan uang = manusia). | JOB UNVERIFIED_CLAIM, eskalasi | `test_unverified_claims...` | Sebagian, disengaja |
| 18 | 195-196 (204-205) | Proaktif mengantisipasi kebutuhan; contoh voucher dan hadiah | Proaktif | Menawarkan langkah berikutnya yang relevan. **Voucher/hadiah: tidak.** | PB, FORBIDDEN_SENTENCE | `test_discount_request...` | Sebagian, disengaja |
| 19 | 107-108 (116-117) | Selesaikan konflik: suasana saling menguntungkan, kendalikan emosi, fokus ke masalah sebenarnya, win-win | Konflik | Tenang, satu empati, langsung ke solusi | PB situasi KESAL, KELUHAN | skenario "error rugi" | Selesai |
| 20 | 198-205 (207-214) | Metode konflik: pemecahan masalah terpadu dan kolaborasi lebih baik daripada pemaksaan | Konflik | Tidak menekan, tidak berdebat. Mencari solusi bersama. | PB situasi KESAL | rubrik manusia | Selesai |
| 21 | 199-200 (208-209) | Mediasi dan konsultasi oleh pihak ketiga | Eskalasi | Eskalasi ke manusia (sebagai pihak ketiga) bila Adit tidak bisa menyelesaikan | JOB eskalasi | `test_third_unresolved...` | Selesai |
| 22 | 118, 127 (127) | Harapan pelanggan: penjual jujur soal kondisi produk; layanan purna jual | Kejujuran | Fitur yang belum ada dijawab "belum ada", tidak dikarang | PB `jujur`, productFacts | skenario "banding majoo" | Selesai |
| 23 | 218-219 (227-228) | Etika: tanggung jawab, integritas, objektivitas, kompetensi, kerahasiaan | Etika | Objektif soal pesaing, tidak menyebut data toko lain | PB `jujur`, `rahasia` | skenario "password" | Selesai |
| 24 | 218, 223 (227, 232) | Keadilan; dilarang diskriminasi; dilarang memberi keterangan palsu; dilarang memuji diri berlebihan | Etika | Semua pelanggan dilayani sama; tidak memuji Yoka berlebihan | PB `adil`, `jujur` | rubrik manusia | Selesai |
| 25 | 238-240 (247-249) | Kepuasan = kinerja dibanding harapan | Kepuasan | Menetapkan harapan yang akurat; tidak menjanjikan berlebih | PB `janji` | `test_unverified_claims...` | Selesai |
| 26 | 241 (250) | Sistem keluhan dan saran agar perusahaan bisa bereaksi | Umpan balik | Setiap keluhan tercatat di kanban beserta sinyal, intent, dan emosi | JOB recordOnKanban | `test_model_flagging...` | Selesai |
| 27 | 243-244 (252-253) | Lost customer analysis: tanyakan alasan pelanggan berhenti | Retensi | Situasi INGIN BERHENTI: hormati, tanya alasan sekali, tandai ke manusia | PB, JOB eskalasi CHURN | skenario "berhenti", "batal langganan" | Selesai |
| 28 | 254 (263) | Loyalitas: layanan purna jual maksimal saat pelanggan kesulitan | Retensi | Bantuan teknis dari panduan resmi | PK | `test_printer_question...` | Selesai |
| 29 | 257 (266) | Pelanggan pertama: pengalaman pertama yang buruk membahayakan hubungan. Pendekatan konsultan, bukan sekadar menjual. | Retensi | Kebutuhan pelanggan didahulukan; tidak menjual sambil ada keluhan | PB situasi PENUTUP, prompt rumah | rubrik manusia | Selesai |
| 30 | 184-185, 239 (193-194, 248) | Harapan pelanggan: harga dan nilai sebanding | Harga | Situasi KEBERATAN HARGA: nilai yang relevan, trial, tanpa diskon | PB | skenario "harga mahal" | Selesai |
| 31 | 70-71, 106-107 (79-80, 115-116) | Penutup: salam penutup dan terima kasih | Penutup | Penutup singkat dan spesifik. Tidak membuka jualan baru. | PB situasi PENUTUP | `test_thanks_after_fix...` | Selesai |

### Ditolak atau tidak diterapkan (beserta alasan)

| hlm. (pdf) | Isi | Alasan |
|---|---|---|
| 112-113 (121-122) | Menebak sifat pelanggan dari bentuk tubuh (piknis, leptosome, atletis) | Diskriminatif, tidak teramati di chat teks, dan bertentangan dengan prinsip keadilan buku itu sendiri (hlm. 218). Sengaja tidak dipakai. |
| 41-42, 44-45 (50-51, 53-54) | Membaca dan menampilkan bahasa tubuh | Tidak ada di kanal teks. Diterjemahkan menjadi membaca isyarat tulisan saja (nada, tanda seru, kata). |
| 195, 205, 243, 259-264 (204, 214, 252, 268-273) | Kompensasi, voucher, diskon, hadiah, membercard, poin | Keputusan uang dan harga hanya untuk manusia (AGENTS.md, otonomi tingkat 2/3). Diblokir kode lewat FORBIDDEN_SENTENCE. |
| 67-68 (76-77) | Membuka telepon dengan nama perusahaan, bukan "halo" | Diadaptasi: Adit menyebut nama dan tim hanya bila ditanya. Gaya rumah melarang sapaan formal. |
| Bab I, III (teknis), IV (tim internal), V, VII (konflik internal pegawai), VIII (contoh profesi lain) | Rapat, teknis telepon, kerja tim, komputer, konflik internal | Bukan interaksi pelanggan. |
| 241-247 (250-256) | Survei kepuasan, ghost shopping, statistik | Proaktif ke pelanggan, jadi wajib lewat "Setujui & kirim". Bukan wewenang balasan otomatis. Dicatat sebagai peluang berikutnya. |

## 3. Audit arsitektur (sebelum perubahan)

| Bagian | Kondisi awal |
|---|---|
| Alur masuk | Gateway Baileys atau Fonnte → `FonnteWebhookController` (dedup, abaikan grup) → `DraftWhatsAppReply` |
| Model | Satu panggilan `chat/completions` per pesan, JSON `{relevant, reply, closing, summary}`, `services.yoka_ai.model` |
| Persona | Prompt di JOB: gaya rumah ("kak", tanpa basa-basi) dan blok "ILMU CS" statis |
| Memori | 16 pesan terakhir per nomor dan kartu kanban harian. Tidak ada tabel baru. |
| Pengetahuan produk | `AgentBrain::productFacts()`, paket aktif dari DB, alur trial. Panduan resmi (`GuideController::guides()`) **belum dipakai** Adit. |
| Rem keras | Sensitif → satu bubble "sedang dicek". Buang diskon/promo/janji tanggal. Buang penutup basa-basi. Batas per nomor, burst, dan harian. Satu pesan diproses sekali. |
| Eskalasi | Hanya topik sensitif (regex). **Belum ada**: minta orang langsung, keluhan berulang, pembayaran belum aktif, ingin berhenti. |
| Anti-halusinasi | Daftar fitur tertutup. **Belum ada** pembuang klaim "tiket dibuat", "teknisi sedang memperbaiki", "akun sudah aktif", atau "sudah diteruskan ke tim" yang tidak benar. |
| Tes | `PlatformWhatsAppInboxTest` (14 tes) |

## 4. Yang dibangun

1. **Domain 1, cara melayani (`ServicePlaybook`)**
   - 14 prinsip dasar selalu dikirim.
   - 14 panduan situasi hanya dikirim bila situasinya terdeteksi.
   - Setiap prinsip menyimpan rujukan halaman.
2. **Sinyal deterministik (`CustomerSignals`)**
   - Mendeteksi 19 sinyal: keluhan, kesal, mendesak, bingung, sudah menjelaskan, harga, keberatan harga, minta diskon, ingin berhenti, pembayaran, pesaing, terima kasih, sapaan, tanya Yoka, cara, tanya nama, tanya bot, minta tidak dihubungi, minta orang langsung.
   - Biayanya nol karena tanpa model.
3. **Domain 2, fakta produk (`ProductKnowledge`)**
   - Mencocokkan kata kunci ke panduan resmi `yokapos.com/panduan`.
   - Maksimal dua panduan per pesan, lengkap dengan langkah dan tautan.
   - Tanpa vector DB.
4. **Domain 3, konteks**
   - Pesan beruntun sejak balasan terakhir dibaca sebagai satu maksud.
   - Kartu kanban harian menyimpan `complaint_turns`, `signals`, `intent`, `emotion`, `escalation`, dan `needs_human`. Semua masuk kolom JSON `payload` yang sudah ada, jadi **tanpa migrasi**.
5. **Eskalasi di kode**
   - Pemicu: minta orang langsung, topik sensitif, pembayaran atau aktivasi, keluhan ke-3 dalam sehari, atau ingin berhenti.
   - Eskalasi dicatat ke kanban **sebelum** model dipanggil. Hanya dalam kondisi itu Adit boleh bilang "sudah diteruskan".
   - Bila model menilai perlu manusia (`needs_human`), kartu ditandai, tapi pelanggan tidak diberi tahu diteruskan.
6. **Rem klaim**
   - Kalimat berikut dibuang: tiket dibuat, teknisi sedang memperbaiki, sudah diperbaiki, akun sudah aktif, refund diproses, janji waktu ("1x24 jam", "dalam 2 jam", "besok pasti").
   - Klaim "sudah diteruskan ke tim" atau "tim akan menghubungi" dibuang bila eskalasi belum tersimpan.
7. **Keluaran model**
   - Ditambah `intent`, `emotion`, dan `needs_human` di JSON yang sama, jadi panggilan model tidak bertambah.
   - Model diminta memutuskan dulu dalam hati (kebutuhan, yang diketahui, yang kurang, perlu manusia) tanpa menuliskannya ke pelanggan.

## 5. Contoh sebelum dan sesudah

Contoh ini **ilustrasi perilaku yang dituju** berdasarkan prompt dan rem, bukan rekaman model nyata.

| Pesan | Sebelum | Sesudah |
|---|---|---|
| "Saya mau bicara sama orang langsung." | Dijawab sendiri oleh Adit, tidak ada tanda ke tim | Kartu kanban merah dibuat dulu. Adit: "siap kak, sudah aku teruskan ke tim Yoka ya. sambil nunggu, boleh ceritain kendalanya?" |
| "Saya sudah bayar tapi akun saya belum aktif." | Bisa dijawab "akun kakak sudah aktif kok" (klaim tanpa bukti) | Klaim aktivasi dibuang. Eskalasi pembayaran. Adit meminta email akun, waktu bayar, dan metode bayar. |
| "Printer Bluetooth saya nggak mau connect." | Langkah umum, berisiko mengarang | Langkah dari panduan resmi (Pengaturan Struk → Bluetooth lewat RawBT → pair di Android) dan tautan panduan |
| Keluhan ke-3 tanpa selesai | Terus dijawab bot | Otomatis diteruskan ke manusia |
| "Saya kemarin sudah jelasin, kok ditanya lagi?" | Bisa bertanya ulang | Meringkas yang sudah dijelaskan, minta maaf singkat, lanjut tanpa bertanya hal yang sama |

## 6. Hasil uji dan biaya

- `AditCustomerServiceTest`:
  - Berisi 33 skenario percakapan (termasuk 10 skenario dari brief, prompt injection, kasar, ambigu, dan banding pesaing) dan 13 tes perilaku (multi-giliran, eskalasi, rem klaim, panduan produk, model mati).
  - Model di-mock.
- `PlatformWhatsAppInboxTest`: 14 tes lama tetap lulus.
- Ukuran prompt sistem, diukur dari panggilan yang tercatat di tes. Token ≈ karakter / 3,6.

| Pesan | Sebelum | Sesudah |
|---|---|---|
| Sapaan "halo kak" | 6.083 karakter (~1.690 token) | 6.858 (~1.905), +13% |
| Keluhan printer (dengan panduan) | 6.083 (~1.690) | 8.544 (~2.373), +40% |
| Keluhan marah | 6.104 (~1.696) | 7.501 (~2.084), +23% |

- Jumlah panggilan model per pesan tetap **1**, tanpa layanan berbayar baru.
- Latensi model di produksi **belum diukur**.

## 7. Rubrik penilaian manusia (untuk sampling percakapan nyata)

Nilai 1-3 untuk tiap aspek:
- **Akurat**: hanya fakta dari FAKTA PRODUK atau panduan.
- **Empati**: mengakui perasaan sekali, pas takarannya.
- **Jelas**: info utama di depan.
- **Profesional**: sopan, tidak memerintah.
- **Relevan**: menjawab yang ditanya.
- **Ingat konteks**: tidak bertanya ulang.
- **Menyelesaikan**: ada langkah nyata.
- **Eskalasi tepat**: diteruskan bila perlu, tidak mengklaim palsu.
- **Bebas halusinasi**.

Ambil 20 percakapan per minggu dari kanban `whatsapp_conversation`.

Lulus mock-test **tidak** membuktikan kepuasan pelanggan nyata.

## 8. Risiko, batasan, dan deploy

- Sinyal berbasis kata bisa salah tangkap, misalnya ejaan tidak biasa. Dampaknya kecil karena sinyal hanya menambah panduan atau mengeskalasi. Eskalasi palsu hanya membuat kartu merah.
- Eskalasi keluhan menghitung pesan keluhan dalam satu hari dan direset oleh ucapan "sudah bisa/makasih".
- Pengetahuan produk hanya selengkap panduan resmi. Untuk menambah topik (misalnya hardware), tambahkan panduan di `GuideController`.
- Adit tidak bisa memeriksa status pembayaran atau tiket. Karena itu kasus pembayaran selalu diteruskan ke manusia.
- **Deploy**:
  - Tanpa migrasi dan tanpa `.env` baru.
  - Langkahnya: `cd ~/yokapos_app && ./deploy.sh` (skrip mem-backup DB dulu).
  - Gateway dan sesi Baileys tidak disentuh.
