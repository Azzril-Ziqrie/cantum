# Panduan Cantum OnePay untuk Resto

Panduan singkat, berurutan, dari nol sampai pesanan pertama. Menu di sidebar kiri bisa berbeda untuk tiap role: kamu hanya melihat yang boleh kamu buka.

| Role | Bisa apa |
|---|---|
| **Owner** | Semuanya: semua cabang, pengguna, pengaturan, gateway, laporan |
| **Manager** | Cabangnya: denah, menu, diskon, laporan, refund, dashboard |
| **Kasir** | Denah meja dan pesanan; mengajukan pembatalan (manager yang menyetujui) |
| **Dapur/Bar** | Layar dapur atau bar, dan tombol menu habis |
| **Gudang** | Halaman penyambut; modul stok menyusul |

---

## A. Menyiapkan resto (Owner)

### 1. Masuk
Buka alamat dashboard resto (`https://domain-resto/staff`), masukkan email dan kata sandi. Setelah masuk, kamu otomatis diarahkan ke halaman yang sesuai role.

### 2. Atur identitas dan aturan (Pengaturan)
1. Buka **Pengaturan**.
2. Isi nama resto, slogan, dan warna aksen. Ini yang dilihat pelanggan di HP.
3. Isi **pajak**, **service charge**, dan **pembulatan**. Aturan ini berlaku untuk semua cabang, kecuali kamu mengisi aturan khusus di cabang tertentu (langkah 3).
4. Pilih **metode pembayaran** yang diaktifkan (QRIS dan bank virtual account).

### 3. Tambah cabang
1. Di **Pengaturan**, tambah cabang: nama dan alamat.
2. Atur jam buka per hari, hari libur, estimasi penyajian, dan batas pesanan menjelang tutup.
3. (Opsional) **Aturan khusus cabang**: pajak, service, pembulatan, atau metode bayar yang berbeda. Kosong berarti ikut resto.
4. Tombol **buka/tutup** menutup cabang seketika. Pelanggan tidak bisa membayar saat cabang tutup.

### 4. Hubungkan pembayaran (Kasera Pay)
1. Di **Pengaturan > Gateway**, masukkan API key dan webhook secret dari akun Kasera milik resto.
2. Salin **URL webhook** yang tampil, lalu daftarkan di dashboard Kasera (Developer > Webhooks).
3. Tekan **uji koneksi**. Pakai kunci `kp_test_` dulu dan coba satu pesanan sebelum memakai kunci `kp_live_`.

### 5. Tambah pengguna
Buka **Pengguna**, tambah staf, pilih role, dan tugaskan ke cabang. Karyawan yang keluar cukup dinonaktifkan; riwayatnya tetap ada.

---

## B. Meja dan QR (Owner/Manager)

1. Buka **Meja dan denah**, pilih cabang.
2. **Tambah meja**: satu per satu, atau banyak sekaligus (mis. `A` dari 1 sampai 10).
3. **Tata denah**: geser meja ke posisi aslinya, atur ukuran dan bentuk (persegi, bulat, panjang), putar, dan kunci posisi agar tidak tergeser. Tambahkan elemen bar, kasir, atau pintu supaya denah mirip ruangan asli. Perubahan tersimpan otomatis.
4. **Cetak QR**: pilih meja lalu unduh QR (SVG/PNG), atau buka **lembar stiker** untuk mencetak banyak meja sekaligus. Alamat yang sama bisa ditulis ke tag NFC.
5. Bila QR bocor atau salah tempel, pakai **Ganti token**. QR lama langsung tidak berlaku, jadi cetak ulang.

Meja yang sudah punya pesanan tidak bisa dihapus, hanya dinonaktifkan.

---

## C. Menu dan promo (Owner/Manager)

### Menu
1. Buka **Menu**. Buat **kategori** dulu (mis. Kopi, Makanan).
2. Tambah **menu**: nama, deskripsi, foto, harga, HPP (modal), stasiun (dapur atau bar), dan penanda best seller.
3. Tambah **pilihan** bila perlu (ukuran, topping, level pedas), wajib atau opsional, dengan tambahan harga.
4. Di tab **Cabang**: tandai menu **habis**, atau beri harga khusus untuk satu cabang.
5. Tab lain: ubah harga massal (dengan pratinjau), impor dan ekspor CSV.

### Diskon dan promo
1. Buka **Diskon dan promo > Buat**.
2. Pilih potongan persen (dengan batas maksimal) atau nominal.
3. Pilih cakupan: seluruh pesanan, menu tertentu, atau kategori.
4. Atur syarat: minimal belanja, periode, hari dan jam (happy hour), dan batas pemakaian.
5. Pilih **kode promo** (pelanggan mengetik) atau **otomatis**, dan cabang yang berlaku.

Satu pesanan hanya memakai satu promo (yang potongannya terbesar).

---

## D. Hari-hari biasa

### Pelanggan memesan
Pelanggan memindai QR atau menempel HP ke tag NFC di meja, memilih menu, lalu membayar dengan QRIS atau virtual account di halaman yang sama. Tidak perlu aplikasi.

### Menerima pesanan (Denah meja)
- Meja pelanggan yang sudah membayar **berkedip** dan berbunyi. **Tekan "Aktifkan suara" sekali** setiap kali membuka dashboard; browser memblokir suara sebelum itu.
- Lampu **Terhubung** di kanan atas harus menyala. Bila berubah **Terputus**, periksa internet; data diambil ulang otomatis saat tersambung.
- Pesanan yang belum dibayar **tidak muncul** di dapur.
- Klik meja untuk melihat pesanannya, lalu tekan tombol langkah berikutnya: **Proses**, **Sajikan**, **Selesai**. Status ikut terlihat di HP pelanggan.
- Warna meja: kosong, menunggu bayar, baru, diproses, siap disajikan, disajikan.

### Dapur dan bar
Buka **Dapur dan bar**, pilih stasiun. Tekan **Mulai** saat membuat item dan **Siap** saat selesai. Bila ada menu yang habis, tandai di sini atau di dashboard; menu langsung hilang dari halaman pelanggan.

### Menutup atau membuka cabang
Pakai tombol **Tutup cabang / Buka cabang** di dashboard. Owner bisa melihat semua cabang sekaligus di **Semua cabang**.

---

## E. Pembatalan dan refund

1. Kasir menekan **Ajukan pembatalan** pada pesanan dan wajib mengisi alasan.
2. Manager atau owner membuka **Refund**, lalu **menyetujui** atau **menolak** (alasan penolakan wajib).
3. Setelah disetujui, pesanan dibatalkan dan masuk daftar **refund tertunda**.
4. Kembalikan dana lewat dashboard Kasera, lalu tekan **Tandai selesai** dan isi catatan (mis. nomor referensi).

**Pembayaran yang masuk terlambat** (setelah pesanan kedaluwarsa) tampil di bagian **Perlu ditinjau**. Pesanan itu tidak masuk dapur. Kembalikan dananya lalu tandai selesai dengan catatan.

---

## F. Tutup harian dan laporan (Owner/Manager)

1. Buka **Laporan**, pilih cabang dan tanggal.
2. Lihat omzet, jumlah pesanan, menu terlaris dan margin, jam sibuk, metode bayar, diskon, dan pembatalan. Unduh **CSV** atau cetak halaman untuk simpan sebagai PDF.
3. Buka **Tutup harian** setiap malam: cocokkan jumlah pesanan terbayar per metode dengan saldo di dashboard Kasera. Selisih biasanya berarti ada pembayaran terlambat atau refund yang belum selesai.

---

## G. Bila ada masalah

| Gejala | Yang dicek |
|---|---|
| Pesanan sudah dibayar tapi belum muncul | Tunggu beberapa detik. Bila masih belum, periksa **Pengaturan > Log webhook**. Halaman pelanggan juga memeriksa ke Kasera sendiri. |
| Pelanggan melihat "Tagihan belum bisa dibuat" | Periksa API key di Pengaturan dan uji koneksi. Pesanan tidak hilang; halaman mencoba lagi. |
| Dashboard "Terputus" | Periksa internet perangkat. |
| QR tidak bisa dibuka | Meja dinonaktifkan, atau token sudah diganti. Cetak ulang QR. |
| Tidak ada bunyi | Tekan **Aktifkan suara** dan pastikan volume menyala. |
| Lupa kata sandi | Minta owner mengganti kata sandi di **Pengguna**. |
