# Keamanan Cantum OnePay

Ringkasan pemeriksaan keamanan, perbaikan, dan sisa risiko. Tes otomatisnya ada di `tests/Feature/SecurityTest.php` (`php artisan test --filter=SecurityTest`).

## Yang diperiksa

| Area | Cara periksa | Hasil |
|---|---|---|
| Akses antar cabang (IDOR) | 37 endpoint staf dan admin yang menyentuh data cabang lain, dipanggil oleh staf cabang 1; data cabang 2 tidak boleh terbaca maupun berubah (sidik jari tabel sebelum dan sesudah) | Tidak ada celah; semua ditolak 403/404/422 |
| Akses antar role | Kasir, dapur, gudang, manager ke halaman admin dan halaman khusus owner | Aman |
| Tanpa login | Semua route `staff/*` dan `admin/*` dipanggil sebagai tamu | Semua 302/401 |
| CSRF | Semua route POST/PUT/PATCH/DELETE harus memuat middleware CSRF, kecuali webhook | Aman |
| SQL injection | Semua `whereRaw/selectRaw/DB::raw`: hanya ekspresi tetap; input pengguna selalu lewat binding | Aman |
| Mass assignment | Controller memakai `validate()`/`only()`; token dan branch_id tidak bisa diubah lewat request | Aman |
| XSS | Blade ter-escape; JSON di `<script>` memakai `@json` (tag di-escape); JS memakai `textContent` | Satu temuan, diperbaiki (lihat di bawah) |
| Upload foto menu | SVG, skrip berkedok `.jpg`, dan HTML berkedok `.png` ditolak (jenis berkas dibaca dari ISI); nama file acak | Aman |
| Webhook | Signature diverifikasi sebelum JSON di-parse, `hash_equals`, jendela waktu 5 menit, idempoten | Dua temuan, diperbaiki |
| Data pelanggan | Halaman status tidak menampilkan nomor HP | Aman |

## Temuan dan perbaikan

| # | Tingkat | Temuan | Perbaikan |
|---|---|---|---|
| 1 | Sedang | Akun yang dinonaktifkan tetap bisa memakai sesi yang sudah terbuka | `EnsureUserIsActive` mengecek tiap request; sesi langsung dicabut |
| 2 | Sedang | Mengganti kata sandi tidak mengakhiri sesi lain milik akun itu | `authenticateSessions()` (AuthenticateSession): sesi dengan hash kata sandi lama ditolak |
| 3 | Sedang | Tidak ada header keamanan atau CSP | `SecurityHeaders`: CSP dengan nonce per request (skrip inline tanpa nonce diblokir), `frame-ancestors 'none'`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS di HTTPS. Semua handler inline (`onclick=`, `onsubmit=`) diganti atribut `data-*` yang ditangani `ui.js` |
| 4 | Sedang | Nama cabang masuk ke string JavaScript di atribut `onsubmit` (XSS tersimpan bila nama memuat tanda kutip) | Dihapus bersama handler inline; tes memastikan tidak ada handler inline di view mana pun |
| 5 | Sedang | Login hanya dibatasi per IP (5 percobaan) dan email tidak dikenal menjawab lebih cepat dari email terdaftar | Pembatas per email+IP (5) dan per IP (30) dalam 15 menit; email tak dikenal tetap menjalankan hash; pesan galat sama |
| 6 | Rendah | Kata sandi hanya minimal 8 karakter | Minimal 10 karakter, wajib huruf dan angka |
| 7 | Rendah | Webhook palsu bisa memenuhi tabel log dengan body besar tanpa batas | Body dibatasi 256 KB (413), yang signature-nya salah hanya disimpan 500 karakter, route dibatasi 120/menit |
| 8 | Rendah | Ekspor menu CSV tidak menetralkan rumus (`=`, `+`, `-`, `@`) | `CsvCell::safe` dipakai di semua ekspor |
| 9 | Rendah | Disk `local` memasang route `storage/{path}` yang tidak dibutuhkan | `serve => false` |
| 10 | Rendah | Halaman bayar memicu panggilan ke Kasera tanpa pembatas | `throttle:60,1` |
| 11 | Rendah | Menandai menu habis dengan id yang tidak ada menghasilkan galat server | Route model binding: 404 |
| 12 | Rendah | Tautan (QR meja, `return_url` pembayaran) dibangun dari header `Host` request | Di produksi hanya host `APP_URL` yang dilayani (`trustHosts`) dan URL dipaksa ke `APP_URL` |
| 13 | Rendah | Cookie sesi tidak otomatis `secure` | Bila `APP_URL` berskema https: cookie secure dan semua tautan dipaksa https |
| 14 | Rendah | `APP_DEBUG=true` di produksi akan membocorkan kode dan `.env` pada halaman galat | Di produksi `app.debug` dipaksa `false` |
| 15 | Rendah | Berkas unggahan berekstensi `.php` di `/storage` bisa dieksekusi oleh Apache | `public/.htaccess` menolak ekstensi skrip di `/storage/*` |

## Sisa risiko (diketahui, tidak diubah)

- **Pembatasan pesanan belum dibayar per IP tidak atomik.** Dua request serentak bisa melewati batas 5 satu-dua pesanan. Dampaknya hanya spam `pending_payment` (tidak masuk dapur), dan route dibatasi 20 request per menit.
- **Kode promo bisa ditebak pelan-pelan.** `promo/check` dibatasi 30 per menit per IP. Gunakan kode yang panjang untuk promo bernilai besar.
- **Kode pesanan berfungsi sebagai kunci halaman status** (8 karakter dari 32 huruf/angka, sekitar 40 bit, dan dibatasi 60 request per menit). Halaman itu menampilkan isi pesanan dan data pembayaran, bukan nomor HP.
- **`style-src 'unsafe-inline'`** masih diizinkan karena banyak atribut `style=` dan warna aksen brand disisipkan di halaman. Skrip tidak diizinkan inline tanpa nonce.
- **Nama field referensi pesanan di webhook Kasera** belum dikonfirmasi 100%. Signature tetap wajib valid, jadi risikonya salah-cocok, bukan pemalsuan.
- **Tanpa autentikasi dua langkah.** Owner memegang kunci gateway; pakai kata sandi panjang dan unik.
- **Backup dan log** menyimpan data pesanan; simpan di luar web root dan batasi aksesnya di server.
- **Pengujian lingkungan Windows (XAMPP):** `finfo` PHP di XAMPP gagal membaca berkas yang berisi kode PHP tertentu; di Linux/Hostinger ini tidak terjadi. Berkas seperti itu tetap ditolak.

## Daftar periksa produksi

1. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://domain-resto` (menyalakan cookie secure, HSTS, dan kunci host).
2. Dokumen root server mengarah ke `public/`; `.env` tidak boleh bisa diunduh.
3. `php artisan storage:link`, dan pastikan `public/.htaccess` ikut ter-upload.
4. Kunci Kasera: `kp_live_` hanya di server produksi; ganti bila pernah terkirim lewat chat.
5. Jalankan `php artisan test --filter=SecurityTest` setelah setiap pembaruan besar.
