# Pengajuan partner integrasi GoFood dan ShopeeFood untuk Yoka POS

Tujuan: membuat halaman Integrasi di Yoka POS tersambung sungguhan (login merchant,
sinkron menu, pesanan masuk ke kasir), seperti Moka.

Catatan: repo ini publik. Isi data perusahaan di salinan lokal, jangan di-commit.

Sumber resmi yang sudah dibaca (13 Nov 2026):
- GoBiz API: https://developer.gobiz.com/docs/api/intro
- GoBiz Facilitator: https://developer.gobiz.com/docs/category/facilitator
- GoBiz Food Integration: https://developer.gobiz.com/docs/api/food-integration
- ShopeeFood OpenAPI: https://developer.shopeefood.co.id/vendor/api-docs

## 1. Kesimpulan

Kedua platform punya dokumentasi API publik untuk vendor POS. Yang belum publik adalah
**cara mendapatkan kredensial**: tidak ada formulir daftar otomatis. Di kedua kasus kita harus
menghubungi tim mereka dulu.

| Hal | GoFood (GoBiz) | ShopeeFood |
|---|---|---|
| Peran kita | Facilitator (vendor POS) | Vendor |
| Kredensial | `client_id` dan `client_secret` dari Gojek | `VendorID` dan `VendorSecret` dari ShopeeFood |
| Cara mendapatkan | Tidak dijelaskan; hubungi Gojek lewat Contact Us di portal | Tidak dijelaskan; hubungi tim Shopee Partner |
| Model izin merchant | Merchant login GoBiz dan menyetujui (OAuth Authorization Code) | Tingkat vendor; merchant dipetakan lewat `store_id` |
| Sandbox | Ada: `https://api.partner-sandbox.gobiz.co.id/` (GoAuth staging) | Ada: `https://food-open.uat.shopee.co.id` |
| Production | `https://api.gobiz.co.id/` | `https://food-open.shopee.co.id` |
| Pesanan masuk | Lewat webhook / langganan event | Shopee memanggil endpoint milik kita (Submit Order To Vendor) |

Alur login GoBiz (nomor HP lalu OTP) yang kamu lihat di Moka adalah Authorization Code flow
dengan `user_type=merchant`. Setelah kita punya `client_id`, alur yang sama bisa dibuat di Yoka.

## 2. GoFood: yang perlu dilakukan

Dari dokumentasi resmi:

1. **Autentikasi**
   - Client Credentials (mesin ke mesin): POST ke `OAUTH_URL/oauth2/token` dengan Basic auth
     `client_id:client_secret`, `grant_type=client_credentials`, dan `scope`.
   - Authorization Code (login merchant): arahkan merchant ke `OAUTH_URL/oauth2/auth` dengan
     `client_id`, `response_type=code`, `scope` (minimal `openid`, tambah `offline` untuk
     refresh token), `state`, `redirect_uri`, dan `user_type=merchant`. Tukar `code` di
     `/oauth2/token`. Kode berlaku sekitar 2 menit dan sekali pakai.
   - Redirect URI harus didaftarkan saat kredensial dibuat.
   - Access token sekitar 3600 detik. Sesi berakhir setelah 9 bulan, lalu merchant harus
     login ulang (error `invalid_grant`).
2. **Scope yang akan kita minta:** `partner:outlet:read`, `gofood:catalog:read`,
   `gofood:catalog:write`, `gofood:order:read`, `gofood:order:write`, dan `promo:food_promo:write`
   bila promo mandiri dipakai.
3. **Tautkan outlet:** sebagian besar API outlet memerlukan outlet linking lewat
   "Link Outlet by Merchant ID". Ini cocok dengan kolom ID merchant GoBiz di form kita.
4. **Menu:** panggil Update GoFood Outlet Catalog (Sync Menu) tiap menu berubah.
5. **Pesanan:** langganan event order (Notification Subscriptions / Webhooks); pesanan masuk
   langsung ke POS. Lalu Order Acceptance dan Mark Food Ready dari kasir atau dapur.
6. **Outlet harus sudah terdaftar di GoBiz** sebelum disambungkan.

Yang belum diketahui: syarat menjadi facilitator, biaya, proses sertifikasi sebelum go-live.

## 3. ShopeeFood: yang perlu dilakukan

Dari dokumentasi resmi:

1. **Kredensial:** `VendorID` dan `VendorSecret` diberikan oleh ShopeeFood.
2. **Token:** POST `/api/adaptor/:vendor_id/v1/auth/token`, header
   `Authorization: Basic base64(vendor_id:vendor_secret)`, body
   `grant_type=client_credentials&scope=all`. Token berlaku 3600 detik.
3. **Tanda tangan permintaan:** setiap panggilan bisnis memakai header `X-SF-AppID`,
   `X-SF-AccessToken`, `X-SF-Timestamp`, `X-SF-Signature` (HMAC-SHA256 dengan `VendorSecret`,
   hasil HEX huruf kecil), dan `X-SF-RequestID` opsional.
4. **Yang kita panggil (endpoint Shopee):** Notice Store Menu Updated, Full Menu Submission,
   Query Menu Status, Update Dish, Get Order, Get Order List, Update Order Status,
   Update/Get Store Status, Mark As Ready, Cancel Order, Edit Order, option group dan option,
   serta Dish Price Slash.
5. **Yang harus kita sediakan (endpoint milik Yoka, dipanggil Shopee):**
   - Get Token (Shopee mengambil token dari kita),
   - Get Vendor Menu,
   - Menu Sync Result Callback,
   - Submit Order To Vendor (payload pesanan),
   - Update Order Status To Vendor.
6. **Retry:** Shopee mengirim ulang sampai 5 kali bila endpoint kita tidak membalas atau
   membalas "fail". Endpoint harus idempoten.
7. **Postman collection** tersedia di halaman dokumentasi untuk uji.
8. **Pemetaan merchant:** `store_id` ShopeeFood, yaitu isi kolom "ID toko ShopeeFood" di form kita.

Yang belum diketahui: cara mendapatkan `VendorID`, biaya, dan kriteria persetujuan.

## 4. Data yang perlu disiapkan

Isi sebelum mengirim (jangan commit):

- Nama perusahaan / pemilik usaha: `______`
- Bentuk usaha dan NPWP: `______`
- Alamat dan kontak penanggung jawab: `______`
- Produk: Yoka POS, https://yokapos.com
- Perkiraan jumlah merchant dan outlet dalam 6 bulan: `______`
- Kontak teknis (email, nomor): `______`
- Redirect URI GoBiz yang akan didaftarkan: `https://yokapos.com/integrasi/gofood/callback`
  (rute belum dibuat, jadi nama final ditetapkan saat build)
- Base URL endpoint untuk Shopee: `https://yokapos.com/api/shopeefood/...` (belum dibuat)
- Dokumen usaha yang biasanya diminta: KTP, NPWP, akta atau NIB, rekening usaha

## 5. Draf pesan pengajuan

Subjek: Pengajuan integrasi POS Yoka POS dengan GoFood (ganti nama untuk Shopee)

Kepada Tim Partnership / Integrasi,

Kami mengembangkan Yoka POS (https://yokapos.com), aplikasi kasir berbasis web untuk usaha
F&B di Indonesia: kasir, dapur (KDS), stok bahan baku, laporan, dan pembayaran QRIS.

Kami sudah membaca dokumentasi API resmi dan ingin mendaftar sebagai facilitator / vendor
integrasi POS (GoBiz) agar merchant kami dapat:
- menautkan outlet lewat login merchant resmi (OAuth),
- menyinkronkan menu, harga, dan ketersediaan item,
- menerima, menerima-konfirmasi, dan menandai siap pesanan langsung dari kasir dan dapur,
- melihat penjualan online dan offline dalam satu laporan.

Kami meminta:
1. Syarat dan proses pendaftaran sebagai facilitator / vendor.
2. Kredensial sandbox (`client_id` dan `client_secret`, atau `VendorID` dan `VendorSecret`)
   dan pendaftaran redirect URI / base URL kami.
3. Proses sertifikasi atau uji sebelum akses production.
4. Biaya (jika ada) dan estimasi waktu.

Kontak kami: `______` (nama, email, nomor).

Terima kasih.

## 6. Pertanyaan yang masih terbuka (tanyakan tertulis)

1. Syarat dan kriteria persetujuan sebagai facilitator / vendor.
2. Ada biaya integrasi atau bagi hasil untuk vendor?
3. Proses sertifikasi dan lama proses sebelum production.
4. Batas laju (rate limit) dan kuota panggilan.
5. Berapa lama data pelanggan (nama, alamat, nomor) boleh disimpan?
6. Siapa kontak teknis untuk bantuan integrasi?
7. GoFood: apakah satu `client_id` bisa melayani banyak merchant tanpa pendaftaran ulang?
8. ShopeeFood: bagaimana `store_id` dipetakan saat merchant baru bergabung?

## 7. Rencana build di Yoka setelah kredensial sandbox keluar

Halaman Integrasi dan tabel `channel_integrations` sudah ada.

Umum:
- Simpan token terenkripsi per outlet dan kredensial di `.env` (bukan repo).
- Status `pending` menjadi `connected` otomatis setelah izin diterima.
- Antrean (queue) untuk sinkron menu dan pemrosesan pesanan, dengan pengiriman ulang.
- Pesanan online masuk ke order POS dan KDS, bertanda kanal GoFood atau ShopeeFood.

GoFood:
1. Tombol "Hubungkan" mengarahkan merchant ke login GoBiz (Authorization Code,
   `user_type=merchant`), lalu rute callback memverifikasi `state` dan menukar `code`.
2. Outlet linking by Merchant ID, sinkron katalog, langganan event order, terima dan tandai siap.

ShopeeFood:
1. Klien API bertanda tangan HMAC dan pengelola token.
2. Endpoint publik milik Yoka untuk Get Token, Get Vendor Menu, Submit Order To Vendor,
   Update Order Status, dan Menu Sync Callback, dengan validasi tanda tangan dan idempotensi.
3. Full Menu Submission, Mark As Ready, Cancel Order, status toko.

Uji di sandbox dulu, lalu satu outlet percobaan sebelum dibuka ke semua merchant.

Larangan proyek tetap berlaku: tidak menyentuh `pos_app` / `pos.conweb.id`, backup database
sebelum migrasi, dan tidak menyimpan rahasia di repo.
