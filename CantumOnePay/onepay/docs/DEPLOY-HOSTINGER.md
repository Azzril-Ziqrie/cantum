# Memasang OnePay di Hostinger

Panduan ini untuk satu resto, satu domain. Waktu yang dibutuhkan sekitar 30 menit. Menu hPanel bisa sedikit berbeda antar versi; nama menu di bawah adalah yang umum.

## 0. Yang harus ada dulu

| Kebutuhan | Keterangan |
|---|---|
| Paket hosting dengan **akses SSH** | Cek di hPanel, menu **Advanced > SSH Access**. Paket paling murah biasanya tidak punya SSH; kalau tidak ada, pakai paket yang lebih tinggi atau VPS. Tanpa SSH, `composer install` dan `artisan` tidak bisa dijalankan. |
| **PHP 8.2 atau lebih baru** | Diatur per domain di hPanel. |
| Database **MySQL** | Dibuat di hPanel. |
| Domain atau subdomain dengan **SSL** | Webhook Kasera dan cookie login membutuhkan https. |
| **Akun Kasera Pay milik resto** | API key `kp_test_...` (uji) dan webhook secret. |

## 1. Domain dan SSL

1. Di hPanel buat domain atau subdomain untuk pelanggan, misalnya `pesan.contoh-resto.com`.
2. Menu **Security > SSL**: pasang SSL gratis, lalu nyalakan **Force HTTPS**.

## 2. PHP

Menu **Advanced > PHP Configuration**:

1. Pilih versi **8.2** atau **8.3** untuk domain itu.
2. Pastikan ekstensi ini aktif: `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.
3. Naikkan `upload_max_filesize` ke `8M` agar foto menu bisa diunggah.

## 3. Database

Menu **Databases > MySQL Databases**: buat database, pengguna, dan kata sandi. Catat ketiganya dan host-nya (biasanya `localhost`; pakai `127.0.0.1` bila `localhost` gagal).

## 4. Mengunggah kode

Masuk lewat SSH (alamat dan port ada di halaman SSH Access):

```bash
ssh -p PORT USER@HOST
cd ~/domains/pesan.contoh-resto.com
git clone URL-REPOSITORI onepay
cd onepay
composer install --no-dev --optimize-autoloader
```

Cek `php -v`. Kalau PHP di terminal bukan 8.2+, ganti `php` dengan path versi yang benar (tanya support Hostinger atau lihat folder `/opt/alt/php82/usr/bin/`). Cara yang sama berlaku untuk perintah `artisan` di bawah. Kalau `composer` tidak ada, unduh `composer.phar` dari getcomposer.org dan jalankan `php composer.phar install ...`.

Tanpa git: unggah zip proyek lewat File Manager, ekstrak ke folder `onepay`, lalu jalankan `composer install` seperti di atas.

### Arahkan domain ke folder `public/`

Folder `public/` adalah satu-satunya yang boleh terbuka ke internet. Pilih salah satu:

**A. Document root khusus (paling aman).** Bila hPanel memberi pilihan folder saat membuat subdomain, atur ke `.../onepay/public`.

**B. Symlink.** Kode tetap di `~/onepay` dan `public_html` diganti tautan ke `public/`:

```bash
cd ~/domains/pesan.contoh-resto.com
mv public_html public_html.lama
ln -s onepay/public public_html
```

**C. Kode langsung di folder domain.** Bila A dan B tidak memungkinkan, letakkan seluruh proyek di folder domain. Berkas `.htaccess` di akar proyek mengarahkan semua permintaan ke `public/` dan menolak akses ke `.env`, `app/`, `storage/`, `vendor/`, dan lainnya. Ini jaring pengaman, bukan cara terbaik. Setelah selesai, buka `https://domain-kamu/.env` dan `https://domain-kamu/composer.json`; keduanya **harus** menampilkan 403 atau 404.

## 5. Menjalankan installer

```bash
php artisan onepay:install
```

Installer akan memeriksa server, menanyakan alamat situs, data database, dan kunci Kasera (boleh dikosongkan dulu), lalu menulis `.env` produksi, membuat tabel, membuat brand, cabang pertama, dan akun owner, dan mengaktifkan cache produksi. **Catat kata sandi owner yang ditampilkan; tidak muncul lagi.**

Versi tanpa pertanyaan:

```bash
php artisan onepay:install --url=https://pesan.contoh-resto.com \
  --db-name=u123_onepay --db-user=u123_onepay --db-pass='KATA-SANDI' \
  --kasera-key=kp_test_xxx --kasera-secret=whsec_xxx \
  --brand="Kopi Contoh" --branch="Cabang Utama" \
  --owner-name="Budi" --owner-email=budi@contoh-resto.com
```

Aman dijalankan ulang: `.env` yang sudah terisi dan data yang sudah ada tidak ditimpa.

## 6. Cron (wajib)

Tanpa cron, pesanan yang tidak dibayar tidak kedaluwarsa dan backup harian tidak berjalan. Menu **Advanced > Cron Jobs**, tambah cron **setiap menit** (`* * * * *`):

```
/usr/bin/php /home/USER/domains/pesan.contoh-resto.com/onepay/artisan schedule:run >> /dev/null 2>&1
```

Ganti `/usr/bin/php` dengan path PHP 8.2 yang dipakai di langkah 4 (cek dengan `which php`). Satu menit kemudian, buka **Pengaturan** sebagai owner: baris "Cron (scheduler)" di kartu Kesehatan sistem harus hijau.

## 7. Daftarkan webhook di Kasera

Di dashboard Kasera resto, menu **Developer > Webhooks**:

1. URL: `https://pesan.contoh-resto.com/kasera-webhook`
2. Aktifkan event `payment.paid`, `payment.expired`, `payment.failed`.
3. Salin **webhook secret** ke **Pengaturan > Gateway** di OnePay (atau `KASERA_WEBHOOK_SECRET` di `.env`).

## 8. Uji dengan kunci tes

Pakai kunci `kp_test_...`. Jangan lanjut ke live sebelum semua ini lulus:

- [ ] Buka QR meja di HP, pesan, bayar dengan QRIS. Pesanan muncul di denah meja dalam sekitar 3 detik dan layar dapur.
- [ ] Bayar dengan setiap VA bank yang diaktifkan.
- [ ] Biarkan satu pesanan kedaluwarsa; statusnya menjadi `expired`.
- [ ] Bayar pesanan yang sama dua kali (atau kirim ulang webhook dari Kasera); pesanan tidak masuk dapur dua kali.
- [ ] Matikan webhook sementara di Kasera, lalu bayar; halaman bayar tetap berubah lewat sinkronisasi dalam sekitar 30 detik.
- [ ] Batalkan pesanan terbayar dari dashboard dengan alasan; muncul di halaman Refund.
- [ ] Cek **Pengaturan > Log webhook**: semua webhook tercatat dengan "signature valid".

## 9. Pindah ke live

1. Di **Pengaturan > Gateway** ganti API key ke `kp_live_...` dan webhook secret live. Daftarkan webhook yang sama di akun Kasera mode produksi.
2. Lakukan satu transaksi kecil sungguhan dan pastikan masuk ke dashboard dan rekening resto.
3. Cetak stiker QR di **Meja dan denah > Cetak stiker semua meja** dan tempel di meja.
4. Jalankan `php artisan onepay:doctor` atau lihat kartu Kesehatan sistem; tidak boleh ada yang merah. Kartu Kasera akan menampilkan mode `produksi`.

## 10. Jika ada masalah

| Gejala | Penyebab umum | Perbaikan |
|---|---|---|
| Halaman putih atau 500 | `.env` salah, atau izin folder | `php artisan onepay:doctor`; lihat `storage/logs/laravel.log`; `chmod -R 775 storage bootstrap/cache` |
| Foto menu tidak tampil | `storage:link` belum dibuat | `php artisan storage:link` |
| Pesanan tidak kedaluwarsa, backup tidak jalan | Cron belum aktif atau path PHP salah | Periksa cron di hPanel; lihat kartu Kesehatan sistem |
| Pembayaran sudah masuk tapi pesanan belum `paid` | Webhook tidak sampai | Cek URL webhook dan secret; lihat **Log webhook**; sinkronisasi cadangan akan menyusul dalam sekitar 30 detik |
| Webhook 401 | Webhook secret berbeda | Samakan dengan yang di dashboard Kasera |
| "No application encryption key" | `APP_KEY` kosong | `php artisan key:generate --force` lalu `php artisan config:cache` |
| Mengubah `.env` tapi tidak berpengaruh | Konfigurasi ter-cache | `php artisan config:cache` |

Backup, pembaruan, dan pemulihan: lihat `UPDATE.md` dan `PEMULIHAN.md`.
