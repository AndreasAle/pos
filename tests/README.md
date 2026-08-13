# Menjalankan Test

Test berjalan di **MySQL**, bukan SQLite, karena migration dan `PosOrderService`
memakai SQL khusus MySQL (`ALTER TABLE ... MODIFY COLUMN ENUM` dan `SUBSTRING_INDEX`).

Sekali saja, buat database test-nya:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS fnb_pos_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Pastikan MySQL (XAMPP) hidup, lalu:

```bash
php artisan test
```

Koneksi test di-override di `phpunit.xml` (`DB_DATABASE=fnb_pos_test`), jadi database
development `fnb_pos` tidak pernah tersentuh. Setiap test dibungkus transaksi
(`RefreshDatabase`), jadi datanya bersih tiap kali.

## Isi

| File | Yang diuji |
| --- | --- |
| `Feature/Pos/OrderTotalsTest.php` | Subtotal, add-on, promo, diskon manual, pajak, service, ongkir, kembalian, split payment, penomoran order, batas tenant |
| `Feature/Pos/InventoryDeductionTest.php` | Potong stok lewat resep & bundle, `allow_negative_stock`, penolakan saat stok kurang (+ rollback penuh dan respons 422), jejak `stock_movements` |
| `Feature/Pos/CartIntegrityTest.php` | Harga/varian/add-on diambil dari database bukan dari request, add-on teks bebas ditolak, varian & add-on milik produk lain ditolak, qty nol/negatif ditolak |
| `Feature/Pos/ShiftCashTest.php` | Rekonsiliasi kas shift: split payment hanya porsi tunainya, order non-tunai, void, selisih asli tetap terdeteksi, shift lain tidak ikut terhitung |
| `Feature/Pos/LoyaltyPointsTest.php` | Poin didapat, poin ditukar, penolakan tukar melebihi saldo, poin hanya terpakai sebanyak yang diserap tagihan, counter belanja pelanggan, order pending |
| `Feature/Orders/VoidAndRefundTest.php` | Void & refund: stok kembali persis sebanyak yang dipotong, poin dibalik, ledger cocok, tidak bisa dobel, batas tenant |
| `Feature/Balance/BalanceServiceTest.php` | Saldo merchant: kredit QRIS idempoten, fee platform, pembalikan refund, penarikan dana + row lock, rekonsiliasi ledger |
| `Feature/Balance/BalanceIntegrationTest.php` | Konfirmasi QRIS manual mengkredit saldo, webhook susulan tidak dobel, refund/void mengembalikan saldo, order tunai tidak menyentuh saldo |
| `Feature/Auth/LoginThrottleTest.php` | Kunci login setelah 5 percobaan gagal, password benar tetap ditolak saat terkunci, counter direset setelah login sukses |
| `Feature/Reports/ReportServiceTest.php` | Angka laporan penjualan/produk/kasir/shift/inventory/laba, order void & refund dikecualikan, filter tanggal & outlet, isolasi tenant |
| `Feature/Subscription/SubscriptionAccessTest.php` | Gerbang langganan: trial/aktif/kedaluwarsa/dibatalkan/pending, jalur keluar yang harus tetap terbuka, batas tenant, gerbang role owner |
| `Feature/Subscription/SubscribeTest.php` | Ambil paket: aktivasi manual 30 hari, jalur Midtrans saat dikonfigurasi, akses pulih setelah berlangganan, riwayat tagihan per tenant |

Fixture-nya ada di `Concerns/BuildsPosScenario.php` — bikin business + langganan aktif
+ outlet + kasir dengan shift terbuka, plus helper produk/bahan/resep/bundle.
