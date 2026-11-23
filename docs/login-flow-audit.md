# Audit Alur Masuk Aplikasi Yoka (akun vs PIN kasir)

Tanggal: 2026-11-23 · Status: audit, belum ada perubahan kode.
Sumber: kode `POS` (Laravel) dan `POS-Android` (flavor `yoka`), serta pengamatan di tablet.

Penanda: **[FAKTA]** dari kode/pengamatan · **[ASUMSI]** perlu dicek.

---

## 1. Alur sekarang

### Di aplikasi Android (flavor yoka)
```
Splash ─┬─ belum onboarding ──> Onboarding ──> Welcome
        ├─ sudah login / sudah dipasangkan ──> WebView /kasir
        └─ lainnya ──> Welcome

Welcome (3 tombol):
  [Mulai Berjualan]                    -> form daftar native -> verifikasi email (web) -> /onboarding/bisnis -> /dashboard -> dialihkan ke /pos
  [Sudah punya akun? Masuk]            -> /pos -> /login (email + password) -> /dashboard -> dialihkan ke /pos
  [Masuk sebagai kasir dengan PIN]     -> /kasir -> "Pasangkan Perangkat" (isi Kode Outlet) -> pilih nama -> PIN -> /pos
```
[FAKTA] `SplashActivity.route()`, `WelcomeActivity`, `YokaFlow.PATH_*`, `MainActivity.handleBackOffice()`.

### Di server
- **Login akun** (`LoginController`): email + password, lalu ke `/dashboard`. Di app dialihkan ke `/pos`. [FAKTA]
- **Login PIN** (`PinLoginController`): perangkat dipasangkan ke outlet lewat **Kode Outlet** (cookie 1 tahun). Layar PIN menampilkan daftar staf outlet yang **punya PIN**, lalu staf memasukkan PIN. [FAKTA]
- **Logout dari kasir** ("Ganti Kasir"): kalau perangkat sudah dipasangkan, kembali ke layar PIN. Kalau belum, ke `/login`. [FAKTA]

## 2. Temuan

### A. Bug keamanan: Kode Outlet tidak unik (prioritas tertinggi)
- Setiap bisnis baru dari onboarding otomatis mendapat outlet ber-kode **`MAIN`** (`OnboardingController::store`). [FAKTA]
- Kolom `outlets.code` **tidak unik**, dan pemilik bebas mengisi kode sendiri, misalnya `VR01`. [FAKTA]
- `PinLoginController::pair()` mencari `Outlet::where('code', …)->first()` **tanpa batas bisnis**. [FAKTA]
- **Akibat:** siapa pun yang mengetik `MAIN` akan dipasangkan ke outlet bisnis **lain** (yang pertama di database). Daftar nama staf bisnis itu tampil, dan PIN-nya bisa dicoba. Ada pembatasan percobaan (5 per menit per perangkat, 30 per 15 menit per outlet), tapi PIN 4 digit tetap lemah terhadap percobaan berulang dari banyak IP. Pemilik baru juga tidak akan pernah bisa memasangkan perangkat ke outletnya sendiri dengan `MAIN`.

### B. Terlalu banyak "pintu masuk" di layar pertama
- Welcome menampilkan 3 tombol. Tombol **"Masuk sebagai kasir dengan PIN"** hanya berguna di perangkat yang sudah dipasangkan, padahal di perangkat baru ia langsung meminta Kode Outlet yang tidak diketahui kasir. [FAKTA]
- Tidak ada penjelasan kapan memakai akun dan kapan memakai PIN. Pengguna (termasuk pemilik produk) jadi bingung. [FAKTA: dari pengalaman pemilik]

### C. Pemasangan perangkat memakai kode manual
- Pemilik harus membuka back office di **browser**, melihat kode di menu Outlet, lalu mengetiknya di tablet. Halaman Outlet **diblokir di app** (`KASIR_PAGES`), jadi kode tidak bisa dilihat dari tablet itu sendiri. [FAKTA]

### D. Pemilik baru buntu setelah daftar di app
- Setelah daftar dan onboarding, pemilik diarahkan ke `/pos`. Untuk berjualan ia butuh produk, PIN kasir, dan kode outlet, tetapi **produk, staf, dan outlet adalah halaman back office yang diblokir di app** ("Dibuka lewat web"). [FAKTA]
- [ASUMSI] Onboarding tidak membuat produk contoh. Perlu dicek di data trial nyata, karena kalau benar, kasir pertama kali kosong.

### E. Pemilik tidak punya PIN secara bawaan
- PIN hanya diisi lewat menu Pengguna (back office). Layar PIN hanya menampilkan staf yang **punya PIN**. [FAKTA]
- Akibatnya, pemilik yang menekan "Ganti Kasir" di perangkat yang sudah dipasangkan akan mendarat di layar PIN tanpa dirinya di daftar. Jalan keluarnya hanya link kecil "Login back office". [FAKTA]

### F. Hal kecil
- Setelah login akun di app, pengguna selalu dibawa ke `/dashboard` lalu dialihkan ke `/pos` oleh app. Sesekali dashboard sempat tampil sebentar. [ASUMSI]

## 3. Pola yang dipakai POS lain (pengetahuan umum, belum diverifikasi ulang)
Aplikasi kasir seperti Moka, Majoo, dan Olsera umumnya memakai pola **dua lapis**:
1. **Sekali per perangkat:** pemilik/admin login dengan akun (email/HP + password), lalu **memilih outlet dari daftar**. Perangkat terikat ke outlet itu. Tidak ada kode yang diketik.
2. **Setiap ganti orang:** layar kunci berisi daftar karyawan + **PIN**. Ini yang dipakai sehari-hari.

Kasir tidak pernah melihat layar "daftar akun" atau "kode outlet". Pilihan "login pakai akun" hanya muncul di perangkat yang belum terikat.

## 4. Rekomendasi alur baru

```
Perangkat BARU (belum terikat):
  Welcome: [Daftar bisnis baru]   [Masuk]
    Masuk = email + password pemilik/admin
      -> 1 outlet: perangkat langsung terikat
      -> >1 outlet: pilih outlet dari daftar -> terikat
      -> pemilik belum punya PIN: minta buat PIN sekarang (4–6 digit)
      -> langsung ke kasir

Perangkat TERIKAT (setiap buka app / Ganti Kasir):
  Layar kunci: daftar karyawan (termasuk pemilik) -> PIN -> kasir
  Link kecil: "Bukan outlet ini? Lepas perangkat" (butuh login akun pemilik)
```

| # | Perubahan | Dampak | Ukuran |
|---|---|---|---|
| 1 | **Perbaiki bug A:** pasangkan perangkat lewat login akun (pilih outlet), bukan kode. Kalau kode tetap dipakai, jadikan unik global dan acak (mis. `YK-7F3Q2`), jangan `MAIN`. | Menutup kebocoran antar-bisnis | S–M |
| 2 | Hapus "Masuk sebagai kasir dengan PIN" dari Welcome. Layar PIN otomatis tampil kalau perangkat sudah terikat. | Satu pintu masuk, tidak bingung | S (APK) |
| 3 | Setelah login akun di perangkat baru: pilih outlet, buat PIN pemilik, langsung ke `/pos`. | Tanpa kode manual, tanpa back office | M |
| 4 | Pemilik/admin selalu muncul di layar PIN. Kalau belum punya PIN, tombol "Masuk dengan akun" ada di layar itu. | Tidak buntu setelah Ganti Kasir | S |
| 5 | Setup minimal di app untuk pemilik baru: tambah produk cepat + tambah kasir & PIN (atau produk contoh saat onboarding). | Pemilik baru bisa langsung jualan dari tablet | M–L |
| 6 | Login akun dari app langsung ke `/pos` (pakai `intended`), bukan lewat `/dashboard`. | Lebih mulus | S |

Urutan yang disarankan: **1** (keamanan) → **2 + 4** → **3** → **6** → **5**.
Nomor 2 butuh rilis APK. Nomor 1, 3, 4, dan 6 bisa di server saja, karena layar pemilihan outlet bisa berupa halaman web.
