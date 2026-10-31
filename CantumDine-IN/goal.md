# CantumDine-IN — Goal

## Ringkasan
Sistem pemesanan dine-in untuk bisnis F&B. Pelanggan scan QR atau tap NFC di meja, memesan dan membayar langsung di web lewat payment gateway. Pesanan yang sudah dibayar muncul otomatis di dashboard staf pada denah meja, tanpa refresh halaman.

## Tujuan
1. Pelanggan memesan dan membayar dari HP tanpa install aplikasi dan tanpa menunggu pelayan.
2. Staf melihat pesanan masuk secara realtime pada denah meja (nomor meja, status, notifikasi).
3. Satu resto bisa punya banyak cabang. Menu, akun, dan pengaturan dipakai bersama, sedangkan denah meja dan ketersediaan menu diatur per cabang.
4. Dijual putus (satu instalasi per resto), bukan langganan.

## Model bisnis
- **Jual putus**: satu instalasi per resto, dengan banyak cabang di dalamnya.
- Pembayaran memakai **akun payment gateway milik resto sendiri**. Kami tidak memegang dana pelanggan.
- Domain dan hosting milik resto, atau dipasang di server yang disepakati.
- Pendapatan: harga sistem, biaya setup, paket maintenance opsional, biaya per cabang tambahan.

## Alur utama
1. Pelanggan scan QR / tap NFC. URL berisi token meja, yang menentukan cabang dan meja.
2. Pelanggan memilih menu dan membuat pesanan (status `pending_payment`).
3. Pelanggan membayar lewat gateway (QRIS atau virtual account bank).
4. Webhook gateway masuk, server memverifikasi signature, lalu status menjadi `paid`.
5. Dashboard cabang menerima pesanan baru: meja berkedip, bunyi notifikasi, tanpa refresh.
6. Staf mengubah status: diproses, disajikan, selesai. Status juga tampil di HP pelanggan.

## Lingkup fitur
### Pelanggan
- Menu digital per cabang, keranjang, catatan pesanan.
- Pembayaran lewat gateway, halaman status pesanan.

### Dashboard staf
- Denah meja (nomor meja, warna status, posisi bisa diatur, area/lantai).
- Notifikasi realtime (visual dan suara) per cabang.
- Antrian pesanan dengan filter status dan waktu tunggu.
- Layar dapur/bar terpisah per stasiun, item bisa ditandai "sedang dibuat / siap".
- Detail pesanan per meja, perubahan status, pembatalan dan refund dengan alasan.
- Tandai menu habis dan buka/tutup cabang dengan satu klik.
- Role: owner, manager cabang, kasir, dapur.

### Admin / owner
- Kelola cabang, area, meja (generate QR/NFC, regenerate token), kategori, dan menu.
- Menu: foto, deskripsi, harga, HPP, modifier (ukuran, topping, level gula/pedas), penanda best seller.
- Ketersediaan, harga khusus, dan stok opsional per cabang.
- **Diskon & promo:**
  - Tipe persen (dengan plafon) atau nominal tetap.
  - Cakupan: seluruh pesanan, item tertentu, atau kategori.
  - Syarat: minimal belanja, periode, hari dan jam (happy hour), batas pemakaian.
  - Kode promo atau otomatis, berlaku di semua cabang atau cabang tertentu.
- Pengaturan gateway (kunci API terenkripsi, sandbox/produksi, metode aktif, URL webhook), pajak, service charge.
- Pengguna dan akses per cabang, log audit, log webhook.

### Laporan penjualan
Bisa difilter per cabang dan rentang tanggal, diekspor ke CSV/PDF.
- Omzet (bruto dan bersih), jumlah pesanan, rata-rata nilai pesanan.
- Menu terlaris dan kurang laku, dipasangkan dengan margin (harga − HPP).
- Penjualan per jam (jam sibuk), per hari, dan per kategori.
- Rincian metode pembayaran.
- Performa meja/area dan konversi scan QR menjadi pesanan.
- Total diskon, tingkat pembatalan dan refund.
- Rekap pajak dan service charge.
- Perbandingan antar cabang (owner).
- Tutup harian: pesanan terbayar vs belum, per metode bayar, untuk rekonsiliasi dengan gateway.

## Keputusan teknis (sementara)
- Stack: PHP + MySQL (jalan di hosting biasa/XAMPP).
- Realtime: **polling AJAX** 2-3 detik per cabang. SSE atau WebSocket bisa menyusul bila perlu.
- Satu kode dasar untuk semua klien. Perbedaan klien (nama, logo, warna, gateway) lewat konfigurasi, bukan kode yang diubah.
- Webhook harus idempotent dan memverifikasi signature.
- Token meja acak dan bisa diganti bila QR bocor. Pesanan baru masuk dapur hanya setelah pembayaran terkonfirmasi.

## Struktur data (draft)
```
brand (pengaturan resto: pajak, service, warna)
 ├─ branches
 │   ├─ floor_areas
 │   ├─ tables (nomor, token, posisi x/y)  ── table_scans
 │   └─ users / user_branches (role, akses cabang)
 ├─ categories / items
 │   ├─ branch_items (tersedia/habis, harga override, stok)
 │   └─ modifier_groups / modifiers
 ├─ discounts ── discount_branches / discount_targets
 └─ payment_gateways (kunci terenkripsi)

orders (branch_id, table_id, status, subtotal, diskon, service, pajak, total)
 ├─ order_items ── order_item_modifiers   (nama & harga di-snapshot)
 ├─ order_status_logs
 └─ payments ── payment_webhooks (idempotensi), refunds

daily_sales (ringkasan opsional)   audit_logs
```
Prinsip: nama dan harga disalin ke `order_items` saat pesanan dibuat, supaya laporan tidak berubah ketika menu diubah atau dihapus. Uang disimpan sebagai bilangan bulat Rupiah.
Skema SQL lengkap (`database.sql`) serta daftar fitur dashboard dan contoh query laporan (`dashboard.md`) belum dibuat. Keduanya mengikuti keputusan bill dipisah per pesanan.

## Keputusan
- **Bill dipisah per pesanan.** Satu meja bisa punya banyak pesanan, masing-masing dibayar sendiri. Tidak ada `table_sessions` dan tidak ada penggabungan bill.

## Prioritas (wajib ada di rilis pertama)
1. **Pembayaran yang benar**: webhook idempotent, signature diverifikasi, status `paid` hanya dari webhook (atau cek status ke gateway).
2. **Pesanan masuk dapur hanya setelah bayar.**
3. **Dashboard realtime yang bisa dipercaya**: tidak ada pesanan terlewat, indikator terputus, ambil ulang saat tersambung kembali, izin suara.
4. **Pembatalan dan refund** dengan alasan, termasuk kasus sudah dibayar tapi tidak bisa dipenuhi.

Fitur lain di bagian "Lingkup fitur" tetap dikerjakan, tetapi tidak boleh menunda empat hal di atas.

## Di luar lingkup (untuk saat ini)
- Multi-tenant SaaS, billing langganan, domain kustom otomatis per tenant.
- Pembayaran lewat akun platform / split payment.
- Integrasi POS dan printer dapur (kandidat fase berikutnya).
- Pencatatan pengeluaran, stok bahan baku dan resep, notifikasi WhatsApp/email, rating pelanggan (fase berikutnya).

## Pertanyaan terbuka
- Gateway pertama yang didukung: Midtrans atau Xendit?
- Hosting: disediakan kami atau dibeli resto?
- Diskon: boleh ditumpuk lebih dari satu, atau satu per pesanan?
- Stok: dilacak per item atau cukup tombol "habis"?
- Pembatalan dan refund pesanan yang sudah dibayar.
- Pajak dan service charge: per brand atau per cabang?
- Hak atas source code: diserahkan penuh atau hanya hak pakai?
- Harga paket (dasar, menengah, lengkap) dan masa garansi.
- Bayar tunai di kasir: didukung atau tidak? Bila ya, kasir menandai `paid` secara manual dan pesanan baru masuk dapur setelah itu.
- Siapa yang menanggung biaya gateway: resto atau pelanggan?
- Harga menu sudah termasuk pajak atau belum, dan aturan pembulatan.
- Cara update banyak instalasi (patch bug, migrasi database) dan installer/setup wizard.

## Kasus tepi (aturan awal, bisa diubah)
- **Prinsip**: pesanan masuk dapur hanya setelah pembayaran terkonfirmasi. Pesanan `pending_payment` tidak pernah tampil di dashboard dapur.
- **Kedaluwarsa**: `pending_payment` otomatis menjadi `expired` setelah batas waktu (mis. 15 menit). Bila pembayaran masuk setelah expired, tandai untuk review dan refund.
- **Dibayar tapi tidak bisa dipenuhi** (menu habis, dapur menolak): refund dengan alasan, dicatat di `refunds` dan `audit_logs`.
- **Webhook tidak datang**: ada tombol/cron cek status ke gateway untuk pesanan `pending_payment`.
- **Pesanan tambahan di meja yang sama**: dicatat sebagai pesanan baru, dibayar terpisah.
- **Dashboard terputus**: tampilkan indikator "terputus" dan ambil ulang semua pesanan aktif saat tersambung kembali.
- **Notifikasi suara**: browser memblokir audio tanpa interaksi, jadi staf menekan tombol "aktifkan suara" saat membuka dashboard.

## Keamanan
- Kunci API gateway dienkripsi, kunci enkripsi disimpan di luar database (config/env).
- Rate limit pembuatan pesanan per token meja dan per IP, karena QR bisa difoto dan dipakai dari jauh. Pesanan tanpa bayar tidak mencapai dapur, jadi risikonya terbatas pada spam `pending_payment`.
- CSRF token, batas percobaan login, dan pengecekan role serta akses cabang di sisi server.
- Data pelanggan diminimalkan (tidak wajib daftar). Bila nomor HP dikumpulkan, patuhi UU PDP.
- Backup database terjadwal dan log error untuk polling serta webhook.