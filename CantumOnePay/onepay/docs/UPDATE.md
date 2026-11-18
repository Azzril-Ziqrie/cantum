# Memperbarui instalasi OnePay

Lakukan di jam sepi (bukan saat makan siang atau malam). Satu pembaruan biasanya selesai dalam 2 sampai 5 menit.

## Urutan aman

```bash
cd ~/domains/pesan.contoh-resto.com/onepay

# 1. Backup dulu. Jangan lewati.
php artisan onepay:backup

# 2. Tutup sementara (pelanggan melihat halaman "sedang pemeliharaan")
php artisan down --retry=60

# 3. Ambil kode baru
git fetch --tags
git pull --ff-only          # atau: git checkout vX.Y.Z

# 4. Pasang dependensi
composer install --no-dev --optimize-autoloader

# 5. Ubah struktur database (hanya menambah; aman diulang)
php artisan migrate --force

# 6. Segarkan cache
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Buka kembali dan periksa
php artisan up
php artisan onepay:doctor
```

Setelah `up`, buka dashboard dan lakukan satu pesanan uji dengan kunci tes bila instalasi masih tahap uji. Untuk instalasi live cukup pastikan dashboard terbuka, denah tampil, dan kartu Kesehatan sistem di Pengaturan tidak merah.

## Catatan

- **Pesanan yang sedang dibayar** saat `down` aman: webhook Kasera tetap diterima di endpoint webhook setelah `up`, dan Kasera mengirim ulang bila gagal. Karena itu jangan membiarkan mode pemeliharaan lebih lama dari yang perlu.
- **`git pull` gagal karena berkas diubah di server**: jangan ubah kode di server. Simpan perubahan dengan `git stash`, tarik pembaruan, lalu minta pengembang memasukkan perubahan itu ke repositori.
- **Konfigurasi** (`.env`) tidak disentuh oleh pembaruan. Bila rilis baru membutuhkan kunci baru, catatan rilis akan menyebutnya dan `onepay:doctor` akan memberi tahu.
- **Banyak instalasi (banyak resto)**: jalankan urutan yang sama di masing-masing, satu per satu, dan mulai dari resto yang paling tidak sibuk. Jangan memperbarui semuanya sekaligus.

## Jika pembaruan bermasalah

1. Kembalikan kode: `git checkout KODE-SEBELUMNYA` (lihat `git log` atau `git reflog`), lalu `composer install --no-dev --optimize-autoloader`.
2. Bila migrasi sudah mengubah database dan aplikasi tidak jalan, pulihkan backup yang dibuat di langkah 1: lihat `PEMULIHAN.md`.
3. Segarkan cache dan buka kembali: `php artisan optimize:clear && php artisan config:cache && php artisan up`.
