# Cantum OnePay

Pesan dan bayar dari meja untuk restoran dan kafe. Pelanggan memindai QR di meja, memesan, dan membayar lewat QRIS atau virtual account (Kasera Pay). Pesanan yang sudah dibayar muncul di dashboard staf pada denah meja. Satu instalasi untuk satu resto, dengan banyak cabang di dalamnya.

Laravel 12, PHP 8.2+, MySQL. Tujuan dan aturan produk ada di `../goal.md`.

## Memasang

Panduan lengkap untuk Hostinger: [`docs/DEPLOY-HOSTINGER.md`](docs/DEPLOY-HOSTINGER.md).

```bash
composer install --no-dev --optimize-autoloader
php artisan onepay:install
```

`onepay:install` memeriksa server, menulis `.env` produksi, membuat tabel, brand, cabang pertama, dan akun owner. Aman dijalankan ulang.

Satu cron wajib, setiap menit:

```
* * * * * php /path/ke/onepay/artisan schedule:run >> /dev/null 2>&1
```

## Perintah

| Perintah | Fungsi |
|---|---|
| `php artisan onepay:install` | Pemasangan awal (interaktif atau lewat opsi) |
| `php artisan onepay:doctor` | Periksa kesiapan produksi; `--json` untuk dipantau mesin |
| `php artisan onepay:backup` | Backup database ke `storage/app/backups` |
| `php artisan onepay:expire-orders` | Tutup pesanan belum dibayar yang lewat batas waktu (dijadwalkan tiap menit) |
| `php artisan onepay:simulate-webhook KODE` | Kirim webhook Kasera palsu bertanda tangan (hanya non-produksi) |

Kartu **Kesehatan sistem** di Pengaturan (owner) menampilkan hasil `onepay:doctor`.

## Dokumen

- [`docs/DEPLOY-HOSTINGER.md`](docs/DEPLOY-HOSTINGER.md): pasang dari nol, uji dengan kunci tes, pindah ke live
- [`docs/UPDATE.md`](docs/UPDATE.md): memperbarui instalasi dengan aman
- [`docs/PEMULIHAN.md`](docs/PEMULIHAN.md): memulihkan backup dan pindah server

## Pengembangan

```bash
cp .env.example .env     # lalu sesuaikan: APP_ENV=local, APP_DEBUG=true, DB lokal
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8081
php artisan test
```

Pakai kunci `kp_test_...` untuk Kasera. Jangan pernah memasukkan kunci ke git; `.env` sudah diabaikan.
