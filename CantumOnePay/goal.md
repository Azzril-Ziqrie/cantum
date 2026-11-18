# Cantum OnePay — Goal

## Ringkasan
Sistem pemesanan dine-in untuk bisnis F&B. Pelanggan scan QR atau tap NFC di meja, memesan dan membayar langsung di web lewat payment gateway. Pesanan yang sudah dibayar muncul otomatis di dashboard staf pada denah meja, tanpa refresh halaman.

## Tujuan
1. Pelanggan memesan dan membayar dari HP tanpa install aplikasi dan tanpa menunggu pelayan.
2. Staf melihat pesanan masuk secara realtime pada denah meja (nomor meja, status, notifikasi).
3. Satu resto bisa punya banyak cabang. Menu, akun, dan pengaturan dipakai bersama, sedangkan denah meja dan ketersediaan menu diatur per cabang.
4. Dijual putus (satu instalasi per resto), bukan langganan.

## Model bisnis
- **Jual putus**: satu instalasi per resto, dengan banyak cabang di dalamnya.
- Pembayaran memakai **akun Kasera Pay milik resto sendiri**. Kami tidak memegang dana pelanggan.
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
- Role: owner, manager cabang, kasir, dapur/bar, gudang (lihat matriks hak akses di "Backoffice F&B").

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

## Backoffice F&B
Bagian ini melengkapi "Admin / owner" di atas. Backoffice dibagi per fase supaya **empat prioritas rilis pertama tidak tertunda**. Modul diaktifkan per instalasi lewat konfigurasi, jadi resto kecil tidak dipaksa memakai semuanya.

### Pembagian fase
| Fase | Isi |
|---|---|
| **1 (rilis pertama)** | Kelola menu, kategori, modifier, meja dan QR, cabang, pengguna dan role, tombol "habis", diskon, laporan penjualan (yang sudah ada di atas). |
| **2** | Resep dan bahan baku, stok bahan per cabang, karyawan, shift dan jadwal. |
| **3** | Pembelian dan supplier, absensi lanjutan, pengeluaran, laba rugi sederhana, transfer stok antar cabang. |

### 0. Meja dan denah (fase 1)
Satu editor yang sama dipakai untuk mengatur meja di admin dan menjadi dasar tampilan denah di dashboard staf.
- **Area / lantai** per cabang (indoor, outdoor, lantai 2, VIP): tambah, ubah nama, urutkan, nonaktifkan. Satu cabang bisa punya banyak area, dan tiap area punya kanvas denahnya sendiri.
- **Meja**: nomor atau nama (mis. "A3"), kapasitas kursi, area, bentuk (persegi, bulat, panjang), ukuran, dan status aktif. Meja yang dinonaktifkan tidak bisa dipesan tetapi riwayat pesanannya tetap ada.
- **Editor denah dengan tarik dan letakkan**: geser meja, ubah ukuran, putar, salin meja, tambah banyak meja sekaligus (mis. A1 sampai A10), snap ke grid, dan kunci posisi agar tidak tergeser tidak sengaja. Posisi disimpan sebagai koordinat x dan y di kanvas area.
- **Elemen dekoratif** opsional (dinding, pintu, kasir, bar) supaya denah mirip ruang aslinya. Elemen ini tidak bisa dipesan.
- **QR dan NFC per meja**: generate QR (unduh PNG atau SVG, dan cetak banyak meja sekaligus sebagai lembar stiker dengan nomor meja), tampilkan URL untuk ditulis ke tag NFC, dan **ganti token** bila QR bocor (QR lama langsung tidak berlaku, dengan konfirmasi dan catatan di log audit).
- **Status meja di dashboard** (hanya tampilan, tidak diedit di sini): kosong, ada pesanan baru, sedang diproses, siap disajikan, menunggu dibayar.
- **Salin denah** dari cabang lain sebagai titik awal, lalu disesuaikan. Perubahan denah tidak mengubah pesanan yang sudah ada.
- Hak akses: owner dan manager cabang (cabangnya saja). Perubahan token dan penonaktifan meja masuk log audit.
- Meja tidak boleh dihapus permanen bila sudah punya pesanan. Cukup dinonaktifkan.

### 1. Menu dan resep (fase 1 untuk menu, fase 2 untuk resep)
- Menu, kategori, foto, deskripsi, harga, modifier, penanda best seller, urutan tampil, menu paket (bundle), jadwal tampil (mis. menu sarapan), dan stasiun penyiapan (dapur, bar).
- **Resep (BOM)**: tiap menu dan tiap opsi modifier punya daftar bahan dan takaran (mis. Es Kopi Susu = 18 g kopi + 120 ml susu + 20 g es). Opsi modifier bisa menambah atau mengganti bahan (ukuran besar, topping).
- **HPP dihitung otomatis** dari resep dan harga beli bahan terakhir (atau rata-rata). Sebelum resep ada, HPP diisi manual seperti sekarang.
- Salin menu dan resep antar cabang, ubah massal harga, impor dan ekspor CSV.

### 2. Inventori bahan baku (fase 2)
- **Master bahan**: nama, kategori (bahan kering, dingin, beku, kemasan), satuan dasar (g, ml, pcs) dan satuan beli (kg, liter, dus) dengan konversinya, stok minimum, penanda mudah rusak.
- **Stok per cabang** dengan riwayat pergerakan (setiap tambah dan kurang tercatat, tidak ada angka yang berubah tanpa jejak).
- **Pengurangan otomatis saat pesanan `paid`**: stok bahan berkurang sesuai resep dan modifier yang dipilih. Pesanan yang dibatalkan atau di-refund mengembalikan atau mencatat stok sesuai alasannya (dikembalikan bila belum dimasak, dicatat sebagai waste bila sudah).
- **Penerimaan barang** (barang masuk dari supplier), **penyesuaian stok**, dan **stock opname** (hitung fisik, selisih dicatat dengan alasan dan nama petugas).
- **Waste / spoilage**: bahan rusak, tumpah, kedaluwarsa, makan staf, sampel, dengan alasan.
- **Peringatan stok menipis** di bawah minimum, dan opsi **menu otomatis "habis"** bila bahan utamanya habis di cabang itu.
- Opsional: tanggal kedaluwarsa per batch dan urutan pakai FEFO. Transfer stok antar cabang (fase 3).
- Resep wajib atau opsional? Bila resto tidak mengisi resep, stok bahan tidak dikurangi otomatis dan sistem tetap berjalan dengan tombol "habis" saja.

### 3. Pembelian dan supplier (fase 3)
- Data supplier (kontak, bahan yang dipasok, tempo bayar).
- Purchase order, penerimaan barang terhadap PO (sebagian atau penuh), dan pencatatan harga beli per bahan sehingga HPP ikut diperbarui.
- Hutang supplier sederhana (tagihan, jatuh tempo, ditandai lunas). Pembukuan akuntansi penuh tidak termasuk.
- Saran pembelian dari stok menipis dan pemakaian rata-rata.

### 4. Karyawan (fase 2)
- Data karyawan: nama, kontak, jabatan, cabang penempatan, status aktif, tanggal masuk. Satu karyawan boleh ditugaskan di beberapa cabang.
- Akun login memakai role (lihat matriks di bawah). Login cepat dengan **PIN** untuk perangkat dapur dan kasir, dan karyawan yang keluar langsung dinonaktifkan tanpa menghapus riwayatnya.
- Dokumen dan catatan internal sederhana (kontrak, catatan HR) bersifat opsional.

### 5. Shift dan jadwal (fase 2)
- **Template shift** per cabang (pagi, siang, malam, split) dengan jam mulai dan selesai, dan **jumlah minimum staf per jabatan** per shift.
- **Jadwal mingguan** per cabang: tarik dan letakkan karyawan ke shift, salin jadwal minggu lalu, peringatan bila kekurangan staf, bentrok jadwal, atau melebihi jam kerja maksimal.
- **Publikasi jadwal**: karyawan melihat jadwalnya sendiri dari HP (read-only).
- **Tukar shift dan izin/cuti**: karyawan mengajukan, manager menyetujui atau menolak, semuanya tercatat.
- Jadwal bisa mengikuti jam sibuk dari laporan penjualan (mis. tambah staf di jam makan siang).

### 6. Absensi dan jam kerja (fase 2 dasar, fase 3 lanjutan)
- **Clock in dan clock out** per shift lewat PIN di perangkat cabang. Pilihan penguat: scan QR khusus cabang, atau lokasi. Foto selfie bersifat opsional dan **perlu persetujuan karyawan** karena menyangkut data pribadi (UU PDP).
- Keterlambatan, pulang cepat, lupa clock out (ditandai untuk dikoreksi manager dengan alasan), dan lembur.
- **Rekap jam kerja** per karyawan per periode, bisa diekspor (CSV) untuk dihitung gajinya di luar sistem. **Penggajian lengkap** (komponen gaji, PPh 21, BPJS, slip gaji) tidak termasuk.
- Laporan biaya tenaga kerja terhadap omzet (perkiraan, dari jam kerja dan tarif per jam yang diisi owner).

### 7. Kas dan tutup shift (fase 3, hanya bila bayar tunai didukung)
- Buka shift kasir dengan modal awal, tutup shift dengan hitung kas fisik, selisih dicatat dengan alasan.
- Tidak aktif selama bayar tunai belum didukung (lihat pertanyaan terbuka). Rekonsiliasi pembayaran non-tunai tetap lewat "Tutup harian" di laporan.

### 8. Pengeluaran dan laba rugi (fase 3)
- Catat biaya operasional (sewa, listrik, gaji, perawatan, dan sebagainya) dengan kategori, cabang, tanggal, dan lampiran foto nota.
- **Laba rugi sederhana** per cabang dan periode: omzet bersih, dikurangi HPP (dari resep atau manual), dikurangi biaya operasional. Ini ringkasan manajemen, bukan laporan akuntansi resmi.

### 9. Pengaturan operasional (fase 1 dan 2)
- Per cabang: jam buka per hari, hari libur, status buka atau tutup, estimasi lama penyajian, stasiun dapur dan bar, dan batas waktu pesanan masuk menjelang tutup.
- Per brand: pajak, service charge, pembulatan, identitas dan warna di halaman pelanggan, bahasa.
- Cadangan dan ekspor data: ekspor menu, stok, dan pesanan ke CSV, serta pencatatan backup terakhir.

### 10. Role dan hak akses
Role: **owner, manager cabang, kasir, dapur/bar, gudang, dan pelayan (opsional)**. Hak akses dicek di sisi server, per cabang.

| Fitur | Owner | Manager | Kasir | Dapur/Bar | Gudang |
|---|---|---|---|---|---|
| Dashboard dan pesanan | ✓ | ✓ | ✓ | ✓ (layar stasiun) | |
| Refund dan pembatalan | ✓ | ✓ | dengan persetujuan | | |
| Menu, harga, diskon | ✓ | ✓ (cabangnya) | | tombol "habis" | |
| Stok, opname, waste | ✓ | ✓ | | lapor waste | ✓ |
| Pembelian dan supplier | ✓ | ✓ | | | ✓ |
| Karyawan, jadwal, absensi | ✓ | ✓ (cabangnya) | lihat sendiri | lihat sendiri | lihat sendiri |
| Laporan penjualan dan laba rugi | ✓ | cabangnya | | | |
| Gateway, pajak, pengguna | ✓ | | | | |
Matriks ini draft dan bisa diubah. Setiap perubahan stok, harga, jadwal, dan refund masuk **log audit** (siapa, kapan, nilai lama dan baru).

### 11. Laporan backoffice (tambahan di atas laporan penjualan)
- Nilai persediaan, pemakaian bahan per periode, **food cost %** (HPP dibanding penjualan), waste per alasan, dan selisih stock opname.
- Bahan yang sering menipis dan perkiraan kapan habis.
- Jam kerja per karyawan, keterlambatan, dan biaya tenaga kerja dibanding omzet.
- Laba rugi sederhana per cabang, dan perbandingan antar cabang untuk owner.

## Keputusan teknis (sementara)
- Stack: **Laravel 12** (PHP 8.2+) + MySQL, kode di folder `onepay/`. Target hosting: Hostinger (butuh SSH untuk Composer, dan cron `php artisan schedule:run` tiap menit). Tes: `php artisan test`.
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

Backoffice (fase 2 dan 3):
ingredients (satuan dasar, satuan beli, konversi, stok minimum)
 ├─ branch_ingredients (stok per cabang)
 ├─ recipes / recipe_items (menu atau modifier -> bahan, takaran)
 ├─ stock_movements (setiap tambah/kurang: jenis, jumlah, alasan, pengguna, order_id bila dari pesanan)
 ├─ stock_counts / stock_count_items (opname)
 └─ waste_logs
suppliers ── purchase_orders / purchase_order_items ── goods_receipts
employees (user_id, jabatan, tarif/jam, PIN) ── employee_branches
shift_templates ── schedules (tanggal, cabang, karyawan, shift)
 ├─ shift_swaps / leave_requests (status persetujuan)
 └─ attendance (clock_in, clock_out, metode, koreksi + alasan)
expenses (kategori, cabang, jumlah, tanggal, lampiran)   cash_shifts (bila tunai didukung)
```
Aturan: pergerakan stok hanya bertambah (append-only) supaya riwayat bisa diaudit. Stok saat ini adalah hasil jumlah pergerakan, atau kolom ringkasan yang selalu diperbarui bersamaan.
Prinsip: nama dan harga disalin ke `order_items` saat pesanan dibuat, supaya laporan tidak berubah ketika menu diubah atau dihapus. Uang disimpan sebagai bilangan bulat Rupiah.
Skema SQL lengkap (`database.sql`) serta daftar fitur dashboard dan contoh query laporan (`dashboard.md`) belum dibuat. Keduanya mengikuti keputusan bill dipisah per pesanan.

## Keputusan
- **Bill dipisah per pesanan.** Satu meja bisa punya banyak pesanan, masing-masing dibayar sendiri. Tidak ada `table_sessions` dan tidak ada penggabungan bill.

- **Gateway: Kasera Pay** (`https://pay.kasera.id`, docs di `/docs`). Satu instalasi memakai **akun Kasera milik resto itu sendiri** (API key dan webhook secret per instalasi), di domain milik resto. Dana pelanggan langsung ke resto. Tidak ada kode multi-tenant.
- **Pembayaran langsung di halaman sendiri** (Direct API): QRIS dan virtual account bank tampil di halaman OnePay, pelanggan tidak dilempar ke halaman checkout Kasera. E-wallet (alur `checkout_url`) ditunda dari rilis pertama.

## Integrasi Kasera Pay (cara kerja, dari project Noirlab)
Acuan kode yang sudah terbukti jalan: `htdocs/noir` (`lib/kasera-client.php`, `kasera-webhook.php`, `payment.php`, `order-status.php`). Di Noirlab objeknya tiket, di OnePay objeknya pesanan meja. Cara kerjanya sama.

### Konfigurasi per instalasi
Disimpan di file config atau env di luar database dan di luar web root, bukan di kode:
- `KASERA_API_BASE_URL` (`https://pay.kasera.id/v1`), `KASERA_API_KEY`, `KASERA_WEBHOOK_SECRET`, `SITE_BASE_URL` (domain resto), `KASERA_WEBHOOK_LOG_FILE`.
- Awalan kunci menentukan mode: `kp_test_...` untuk tes (sandbox), `kp_live_...` untuk transaksi sungguhan. Selalu uji dengan kunci tes dulu.
- Kunci tidak pernah ditulis di log, di respons, maupun di chat.

### 1. Membuat transaksi (`POST /v1/transactions`)
- Header: `Authorization: Bearer <API key>`, `Content-Type: application/json`, dan **`Idempotency-Key: order-<kode_pesanan>`**.
- Body untuk metode langsung (QRIS atau VA):
  - `amount` (Rupiah bulat), `description`, `external_id` dan `merchant_ref` (keduanya = kode pesanan).
  - `customer`: `name`, `email`, `phone` (format E.164, mis. `+62812...`).
  - `payment_methods: ["qris"]` atau `["va_bca"]`, dan sebagainya. **Jangan kirim `checkout.steps`** untuk metode langsung.
  - `return_url` (halaman status pesanan di domain resto) dan `expires_in_minutes`.
- Respons yang disimpan ke database: `id` (payment request id), `payment_method`, `payment` (berisi `qr_string` untuk QRIS, atau `payment_code` dan bank untuk VA), `instructions` (`title`, `steps`), dan `expires_at`. `checkout_url` ikut dikembalikan tetapi **tidak dipakai** untuk metode langsung.
- Metode dan batas nominal (dari dokumentasi Kasera, `/v1/payment_methods`):
  - `qris`: minimal Rp 1.000, maksimal Rp 10.000.000.
  - `va_bca`, `va_bri`, `va_bni`, `va_mandiri`, `va_permata`, `va_cimb`, `va_danamon`, `va_maybank`: minimal Rp 10.000, maksimal Rp 10.000.000.
- Nama pelanggan untuk VA wajib ASCII dan maksimal 30 karakter: transliterasi, buang karakter selain `[A-Za-z0-9 .,'-]`, dan siapkan nama cadangan bila kosong. Bila pelanggan tidak mengisi nama, pakai nama default (mis. "Meja 12").
- **Whitelist kode metode di server** dan **hitung ulang total dari harga di database**. Nilai dari form browser tidak boleh dipercaya.
- Aturan Kasera untuk pembayaran di atas Rp 1.000.000: wajib mengirim `delivery_date` (hari ini, zona WIB) dan `delivery_terms_shown: true`, kalau tidak respons HTTP 422 (`delivery_date_required` atau `delivery_terms_not_shown`). Di Noirlab ini diberlakukan sejak 26 Okt 2026. **Perlu dicek ulang apakah berlaku untuk F&B dine-in**, dan ketentuannya perlu ditampilkan di halaman bayar bila ya.

### 2. Urutan yang aman saat pelanggan menekan "Bayar"
1. Pesanan disimpan dulu dengan status `pending_payment`, **sebelum** memanggil Kasera.
2. Server memanggil Kasera dengan `Idempotency-Key` yang terkunci ke kode pesanan. Memanggil dua kali untuk pesanan yang sama mengembalikan tagihan yang sama, bukan tagihan baru.
3. Berhasil: redirect ke halaman status pesanan milik sendiri, yang menampilkan QR atau nomor VA.
4. Gagal (jaringan atau error Kasera): pesanan tetap tersimpan, pelanggan diarahkan ke halaman status yang mencoba membuat transaksinya lagi. Pesanan tidak hilang.
5. **Jangan mengulang pembuatan transaksi dengan isi berbeda** bila Idempotency-Key sama dan data pembayaran sudah ada, karena akan bentrok.
6. Tombol bayar dinonaktifkan setelah ditekan (mencegah pesanan ganda) dan direset saat halaman kembali dari cache browser (`pageshow`).

### 3. Webhook (satu-satunya yang boleh menandai `paid`)
- URL: `https://<domain-resto>/kasera-webhook.php`, didaftarkan di dashboard Kasera resto (Developer > Webhooks).
- **Verifikasi signature** dari header `Kasera-Signature-V1` berformat `t=<unix>,v1=<hex>[,v1=<hex>]`:
  - `v1 = HMAC-SHA256(secret, "<t>.<rawBody>")`, dihitung dari **body mentah**, bukan JSON yang sudah di-decode.
  - Tolak bila selisih waktu `t` terlalu jauh (anti-replay).
  - Terima bila **salah satu** `v1` cocok (masa tenggang saat secret dirotasi).
  - Bandingkan dengan `hash_equals`. Ada cadangan untuk skema lama (HMAC polos tanpa timestamp, header `Kasera-Signature`).
- Event yang dipakai: `payment.paid`, `payment.expired`, `payment.failed` (nama event ada di field `event` atau `type`, datanya di `data`).
- Kode pesanan dicari di payload lewat beberapa kemungkinan nama field: `external_id`, `merchant_ref`, `reference_id`, `reference`, `order_id`, `order_code`. Nama persisnya belum dikonfirmasi 100%, jadi **log payload asli** dan sesuaikan.
- Kode balasan:
  - `401` bila signature tidak valid, `400` bila payload bukan JSON.
  - `200` (di-ack, tanpa aksi) bila tidak ada tipe event, tidak ada referensi pesanan, atau pesanan tidak ditemukan. Ini supaya Kasera tidak mengirim ulang terus untuk event yang memang tidak bisa diproses, termasuk ping uji dari dashboard.
  - `200` bila berhasil diproses.
  - `500` bila ada error saat memproses, supaya Kasera **mengirim ulang** webhooknya.
- Setiap webhook yang masuk dicatat ke log lengkap dengan hasil verifikasi signature dan isi body.

### 4. Menerapkan status ke pesanan (satu fungsi, dipakai bersama)
Logika perubahan status ada di **satu tempat** dan dipakai oleh webhook, polling cadangan, dan tombol "Cek status" di admin.
- **Idempotent**: pesanan yang sudah final tidak diproses ulang, jadi aman kalau webhook datang dobel.
- Status sukses dari Kasera yang sebenarnya adalah **`succeeded`**. Daftar "paid" harus memuat `succeeded` selain `paid`, `success`, `settlement`, `completed`. Daftar gagal: `expired`, `failed`, `cancelled`, `canceled`, `void`.
- Efek samping (di Noirlab: stok tiket dan email tiket) baru terjadi **tepat saat `paid`**. Di OnePay efek ini adalah **pesanan masuk ke dashboard dapur**, bunyi notifikasi, dan pengurangan stok bila dipakai. Pesanan yang tidak jadi dibayar tidak mengunci apa pun.
- **Efek samping tidak boleh menggagalkan konfirmasi.** Di Noirlab, kirim email dibungkus `try/catch (Throwable)`, kegagalannya dicatat dan ditandai supaya bisa dikirim ulang, karena pesanan yang sudah `confirmed` tidak diproses lagi saat webhook di-retry. Untuk OnePay: bila pengiriman ke dashboard atau notifikasi gagal, status `paid` tetap tersimpan dan ada penanda untuk diulang.
- Perbedaan dengan Noirlab yang **harus diubah** di OnePay: di Noirlab `expired` atau `failed` langsung menjadi final (`rejected`). Di OnePay, bila pembayaran masuk **setelah** pesanan `expired`, pesanan harus ditandai untuk review dan refund (lihat "Kasus tepi"), jadi `expired` tidak boleh dianggap final secara buta.

### 5. Halaman bayar dan status pesanan di sisi pelanggan
- Saat `pending` dan ada data pembayaran, halaman menampilkan:
  - Nama metode dan **hitung mundur** ke `expires_at` (berkedip saat kurang dari 5 menit, diganti pesan kedaluwarsa saat habis).
  - Nominal dengan tombol salin.
  - **QRIS**: QR dirender di browser dari `payment.qr_string` (payload EMV mentah) memakai `qrcodejs`, dengan tombol unduh QR dan salin teks QR, serta pesan cadangan bila library gagal dimuat.
  - **VA**: logo bank, `payment_code` dalam font monospace besar dengan tombol salin, dan petunjuk "transfer nominal persis".
  - Petunjuk dari Kasera (`instructions.title` dan `steps`), selalu di-escape.
- **Polling status** dari browser tiap sekitar 4 detik. Setiap polling ketiga ditambah `&sync=1`.
- **Cadangan bila webhook tidak datang**: dengan `sync=1`, server langsung menanyakan `GET /v1/transactions/{id}` ke Kasera lalu menerapkan hasilnya dengan fungsi yang sama seperti webhook. Dibatasi **satu kali per 10 detik per pesanan** (lock file di folder temp), karena endpoint ini publik.
- Halaman me-reload otomatis saat status berubah menjadi sukses atau gagal.
- Logo bank disimpan di `assets/banks/<kode>.svg|png|webp` (nama file = kode metode, mis. `va_bca.svg`), dengan cadangan berupa teks bila file tidak ada.

### 6. Hal yang sudah terbukti jadi masalah (jangan diulang)
- Salah menyangka status sukses itu `paid`, padahal nilainya `succeeded`.
- Kegagalan email atau notifikasi di dalam webhook yang menggagalkan konfirmasi dan membuat pesanan hilang diam-diam saat di-retry.
- Mengubah isi permintaan dengan Idempotency-Key yang sama.
- Mengonfirmasi pesanan dari `return_url` atau redirect browser. Itu bisa dipalsukan siapa pun.

### 7. Cara menguji (kunci `kp_test_...`)
Bayar QRIS, bayar tiap jenis VA, biarkan kedaluwarsa, bayar dua kali, kirim webhook yang sama dua kali, lalu matikan webhook dan pastikan polling `sync` tetap mengonfirmasi.

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
- Notifikasi WhatsApp/email dan rating pelanggan (fase berikutnya).
- Penggajian penuh (komponen gaji, PPh 21, BPJS, slip gaji) dan pembukuan akuntansi resmi. Backoffice hanya menyediakan rekap jam kerja dan ringkasan laba rugi manajemen.
- Stok bahan baku, resep, pengeluaran, karyawan, shift, dan jadwal **tidak lagi di luar lingkup**. Semuanya masuk "Backoffice F&B" dengan fase 2 dan 3.

## Pertanyaan terbuka
- ~~Gateway pertama yang didukung~~ → **Kasera Pay**, akun per resto (lihat bagian "Keputusan" dan "Integrasi Kasera Pay").
- Hosting: disediakan kami atau dibeli resto?
- Diskon: boleh ditumpuk lebih dari satu, atau satu per pesanan?
- Stok: rilis pertama cukup tombol "habis". Stok bahan lewat resep masuk fase 2 (lihat "Backoffice F&B"). Perlu diputuskan: resep wajib atau opsional per resto?
- Absensi: metode apa yang dipakai (PIN saja, QR cabang, lokasi, selfie)? Selfie butuh persetujuan karyawan.
- Rekap jam kerja cukup diekspor CSV, atau perlu perhitungan tarif dan lembur di sistem?
- Perlu peran pelayan (mengantar pesanan dan menandai "disajikan") atau cukup dapur dan kasir?
- Satu karyawan boleh bekerja di beberapa cabang pada hari yang sama?
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