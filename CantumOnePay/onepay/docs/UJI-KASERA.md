# Checklist uji Kasera Pay (kunci `kp_test_` sungguhan)

Lakukan sekali di tiap instalasi sebelum membuka ke pelanggan, dan ulangi setelah kunci atau domain berubah.
Semua langkah memakai **kunci sandbox** (`kp_test_...`) dari akun Kasera milik resto itu sendiri.

## Persiapan

1. Isi `.env` (atau Admin > Pengaturan > Gateway Kasera Pay): `KASERA_API_KEY=kp_test_...` dan `KASERA_WEBHOOK_SECRET=...`.
2. Di dashboard Kasera: Developer > Webhooks, daftarkan `https://<domain-resto>/kasera-webhook` dan salin secret-nya.
   Domain harus bisa dijangkau dari internet (untuk uji lokal pakai tunnel, mis. ngrok, dan arahkan `APP_URL` ke sana).
3. Buka Admin > Pengaturan > Gateway: mode harus tertulis **sandbox**, status "Siap dipakai", lalu tekan **Uji koneksi**.
4. Pastikan cron `php artisan schedule:run` berjalan tiap menit (untuk kedaluwarsa pesanan).

## Yang diuji

Catat hasil tiap baris (lolos / gagal + catatan). Payload webhook asli ada di Admin > Pengaturan > Log webhook.

| # | Skenario | Cara | Hasil yang diharapkan |
|---|---|---|---|
| 1 | QRIS | Pesan lewat QR meja, pilih QRIS, bayar lewat simulasi sandbox Kasera | QR tampil dan bisa diunduh; pesanan jadi **Dibayar** sendiri (halaman pelanggan reload), muncul di denah dan layar dapur |
| 2 | Tiap jenis VA | Ulangi untuk `va_bca`, `va_bri`, `va_bni`, `va_mandiri` dan VA lain yang diaktifkan | Nomor VA tampil, nominal persis, pesanan terkonfirmasi setelah dibayar |
| 3 | Nama VA | Isi nama pelanggan dengan huruf beraksen/emoji dan nama panjang (> 30 huruf) | Tagihan tetap terbentuk (nama di-ASCII-kan dan dipotong 30 karakter) |
| 4 | Kedaluwarsa | Buat pesanan, jangan dibayar sampai lewat batas waktu | Hitung mundur habis, pesan "Waktu pembayaran habis" tampil, pesanan jadi **Expired** (lihat juga webhook `payment.expired`) |
| 5 | Bayar setelah kedaluwarsa | Bayar tagihan yang sudah kedaluwarsa (bila sandbox mengizinkan) | Pesanan tidak masuk dapur, muncul di "Perlu ditinjau" untuk refund |
| 6 | Bayar dua kali / webhook dobel | Kirim ulang webhook yang sama dari dashboard Kasera | Log webhook: pertama `applied`, berikutnya `duplicate`; status tidak berubah dua kali |
| 7 | Webhook dimatikan | Nonaktifkan webhook di Kasera, lalu bayar | Dalam maks. ~30 detik halaman pelanggan tetap berubah jadi dibayar lewat sync (`sync=1`, log aktor `system:sync`) |
| 8 | Signature salah | Ubah secret di aplikasi sementara, kirim webhook | Dibalas 401, tercatat `signature_invalid`, pesanan tidak berubah. Kembalikan secret setelahnya |
| 9 | Nominal di atas Rp 1.000.000 | Pesan hingga total > Rp 1.000.000 (mis. 50 porsi) | Ketentuan layanan tampil di keranjang dan halaman bayar; Kasera menerima (tanpa 422 `delivery_date_required` / `delivery_terms_not_shown`) |
| 10 | Batas nominal | Total di bawah Rp 10.000 untuk VA, dan di atas Rp 10.000.000 | Ditolak dengan pesan yang jelas sebelum memanggil Kasera |
| 11 | Gagal buat tagihan | Putuskan internet server sebentar saat menekan Bayar | Pesanan tersimpan (`pending_payment`), halaman bayar menampilkan "Tagihan belum bisa dibuat" dan tombol Coba lagi; setelah pulih, tagihan terbentuk tanpa duplikat |
| 12 | Tombol bayar | Tekan Bayar berulang cepat | Hanya satu pesanan terbentuk |

## Hal yang harus dicek di payload asli

- Nama field referensi pesanan di webhook (kode kita dikirim sebagai `external_id` / `merchant_ref`): bila beda, sesuaikan `REFERENCE_FIELDS` di `KaseraWebhookController`.
- Nama field nominal yang dibayar (`amount_paid` / `paid_amount` / `amount`) dan apakah `data.id` sama dengan `id` tagihan yang kita simpan. Bila beda, pesanan akan ditandai "perlu ditinjau" atau diabaikan (`amount_mismatch` / `ref_mismatch` di log webhook), jadi cek log setelah uji pertama.
- Bentuk galat 409/422 dari Kasera saat Idempotency-Key bentrok. Bila badan galat menyebut id tagihan yang sudah ada, aplikasi membacanya otomatis; bila tidak, halaman bayar menampilkan tombol Coba lagi dan staf bisa melihat log.

## Sebelum pindah ke produksi

1. Ganti ke `kp_live_...` dan webhook secret produksi. Pengaturan menampilkan peringatan bila mode kunci tidak cocok dengan lingkungan (`APP_ENV=production` dengan kunci `kp_test_`, atau sebaliknya).
2. Ulangi baris 1, 2, 6, dan 7 dengan transaksi kecil sungguhan, lalu refund manual dari dashboard Kasera.
3. Pastikan `APP_DEBUG=false` dan log tidak memuat kunci (log hanya mencatat metode, rute, status, dan lama permintaan).
