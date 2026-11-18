# Memulihkan backup

## Di mana backup berada

Backup harian dibuat otomatis pukul 03:30 oleh cron (`onepay:backup`) dan disimpan di:

```
onepay/storage/app/backups/onepay-TANGGAL-JAM.sql.gz
```

Disimpan 14 hari terakhir (atur dengan `ONEPAY_BACKUP_KEEP_DAYS` di `.env`). Backup manual: `php artisan onepay:backup`. Tanggal backup terakhir tampil di kartu Kesehatan sistem.

**Backup di server yang sama tidak menolong bila server atau akun hosting hilang.** Setiap minggu, unduh satu berkas backup ke komputer atau penyimpanan cloud (File Manager, SFTP, atau `scp`).

## Yang dicakup dan yang tidak

| Dicakup backup database | Harus disalin sendiri |
|---|---|
| Menu, harga, meja, denah, pesanan, pembayaran, pengguna, diskon, log audit, kunci Kasera yang tersimpan di Pengaturan (terenkripsi) | **Berkas `.env`**, terutama `APP_KEY` |
| | Foto menu di `storage/app/public/` |

`APP_KEY` penting: kunci API dan webhook secret yang tersimpan di database dienkripsi dengan kunci itu. Database yang dipulihkan dengan `APP_KEY` berbeda tidak bisa membuka kunci tersebut (kunci Kasera harus diisi ulang). Simpan salinan `.env` di tempat aman, misalnya pengelola kata sandi.

## Cara memulihkan

Pemulihan **mengganti seluruh isi database** dengan isi backup. Data yang masuk setelah backup dibuat akan hilang, jadi buat dulu backup keadaan sekarang:

```bash
cd ~/domains/pesan.contoh-resto.com/onepay
php artisan onepay:backup
php artisan down
```

### Lewat SSH (disarankan)

```bash
gunzip -c storage/app/backups/onepay-20261118-033000.sql.gz | mysql -h localhost -u NAMA_PENGGUNA -p NAMA_DATABASE
```

Isi kata sandi database saat diminta. Data pengguna, host, dan nama database ada di `.env` (`DB_*`).

### Lewat phpMyAdmin (tanpa SSH)

1. hPanel > **Databases > phpMyAdmin**, pilih database OnePay.
2. Tab **Import**, pilih berkas `.sql.gz` (phpMyAdmin menerima berkas terkompresi; bila ditolak karena batas ukuran, ekstrak dulu menjadi `.sql`).
3. Format SQL, klik **Import**.

### Sesudahnya

```bash
php artisan migrate --force          # menyamakan struktur bila backup dibuat dari versi lebih lama
php artisan optimize:clear
php artisan config:cache
php artisan up
php artisan onepay:doctor
```

Buka dashboard dan cek pesanan terakhir. Karena data kembali ke waktu backup, periksa **Pengaturan > Log webhook** dan dashboard Kasera: pembayaran yang terjadi setelah waktu backup mungkin tidak tercatat di OnePay dan perlu dicocokkan dengan **Laporan > Tutup harian**.

## Pindah ke server baru

1. Pasang OnePay di server baru sesuai `DEPLOY-HOSTINGER.md`, tetapi **pakai `.env` lama** (terutama `APP_KEY`) dan ubah hanya `APP_URL` dan `DB_*`.
2. Pulihkan database dari backup seperti di atas.
3. Salin folder `storage/app/public/` (foto menu), lalu `php artisan storage:link`.
4. Daftarkan ulang webhook Kasera ke domain baru bila domainnya berubah.
5. Pasang cron di server baru, lalu jalankan `php artisan onepay:doctor`.

## Uji pemulihan

Sekali setiap beberapa bulan, pulihkan satu backup ke database kosong terpisah (bukan database live) dan pastikan menu dan pesanan terbaca. Backup yang belum pernah diuji belum tentu berfungsi.
