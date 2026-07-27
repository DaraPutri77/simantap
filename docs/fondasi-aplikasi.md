# Fondasi Aplikasi SIMANTAP

Status: Baseline Tahap 4A

## 1. Tujuan

SIMANTAP adalah Sistem Manajemen Aset dan Persediaan untuk lingkungan internal BPS.

Sistem menyatukan pengelolaan:

- akun pegawai;
- barang dan stok;
- barang masuk;
- permintaan dan penyerahan barang;
- kendaraan dinas;
- peminjaman dan pengembalian motor dinas;
- pemeliharaan kendaraan;
- laporan dan kartu kendali;
- bukti foto dan tanda tangan;
- audit aktivitas.

## 2. Jenis Pengguna

### Admin

Admin mengelola akun, master data, transaksi, persetujuan, laporan, dan audit aktivitas.

### Pegawai

Pegawai menggunakan layanan SIMANTAP untuk melihat informasi, membuat pengajuan, memantau status, dan mengonfirmasi transaksi miliknya.

Sistem bukan jenis pengguna. Sistem merupakan proses otomatis yang menjalankan validasi, notifikasi, pencatatan riwayat, dan pembuatan laporan.

## 3. Status Akun

Status akun disimpan sebagai nilai string dan direpresentasikan oleh PHP backed enum:

- `pending`: akun dibuat tetapi belum diaktifkan;
- `active`: akun aktif dan dapat masuk;
- `suspended`: akses akun dihentikan tanpa menghapus riwayat.

Akun dan transaksi tidak dihapus permanen apabila masih diperlukan untuk audit.

## 4. Modul Aplikasi

1. Autentikasi dan Profil
2. Dashboard
3. Manajemen Akun dan Pegawai
4. Master Barang dan Stok
5. Barang Masuk
6. Permintaan dan Penyerahan Barang
7. Master Kendaraan
8. Peminjaman dan Pengembalian Motor Dinas
9. Pemeliharaan Kendaraan
10. Laporan dan Kartu Kendali
11. Audit Aktivitas

## 5. Autentikasi

Ketentuan autentikasi:

- tidak tersedia registrasi publik;
- satu Admin awal dibuat melalui proses instalasi;
- Admin membuat atau mengimpor akun Pegawai;
- NIP dan email harus unik;
- Admin tidak menentukan kata sandi Pegawai;
- akun baru berstatus `pending`;
- Pegawai menerima tautan aktivasi sekali pakai melalui email;
- tautan aktivasi berlaku selama 60 menit dan dapat dikirim ulang;
- Pegawai membuat kata sandi sendiri saat aktivasi;
- aktivasi sekaligus memverifikasi email dan mengubah status menjadi `active`;
- hanya akun `active` yang dapat masuk;
- identitas login awal menggunakan email;
- percobaan login dibatasi maksimal lima kali per menit untuk kombinasi email dan alamat IP;
- sesi diregenerasi setelah login;
- sesi dibatalkan dan token CSRF diperbarui saat logout;
- lupa kata sandi menggunakan tautan reset sekali pakai;
- tindakan sensitif meminta konfirmasi kata sandi.

Ketentuan kata sandi:

- minimal 12 karakter;
- harus mengandung huruf besar dan huruf kecil;
- harus mengandung angka;
- harus mengandung simbol;
- disimpan menggunakan hasher bawaan Laravel;
- tidak pernah disimpan atau dicatat sebagai teks biasa.

## 6. Matriks Hak Akses

| Kemampuan | Admin | Pegawai |
|---|---|---|
| Melihat dashboard | Ringkasan seluruh sistem | Ringkasan aktivitas sendiri |
| Mengelola akun Pegawai | Ya | Tidak |
| Mengaktifkan, menangguhkan, atau mengirim ulang aktivasi | Ya | Tidak |
| Melihat profil Pegawai | Semua | Profil sendiri |
| Mengubah profil | Semua sesuai kebutuhan administrasi | Profil sendiri yang diizinkan |
| Melihat daftar barang dan stok | Ya | Ya |
| Mengelola master barang dan stok | Ya | Tidak |
| Mencatat barang masuk | Ya | Tidak |
| Membuat permintaan barang | Dapat mengelola seluruh transaksi | Ya, untuk diri sendiri |
| Melihat permintaan barang | Semua | Milik sendiri |
| Menyetujui atau menolak permintaan | Ya | Tidak |
| Mencatat penyerahan barang | Ya | Tidak |
| Mengonfirmasi penerimaan barang | Dapat memantau | Milik sendiri |
| Mengelola master kendaraan | Ya | Tidak |
| Melihat ketersediaan kendaraan | Ya | Ya |
| Membuat peminjaman motor | Dapat mengelola seluruh transaksi | Ya, untuk diri sendiri |
| Melihat peminjaman | Semua | Milik sendiri |
| Menyetujui atau menolak peminjaman | Ya | Tidak |
| Mengunggah bukti dan tanda tangan | Dapat memantau/mengelola | Untuk transaksi sendiri |
| Mengonfirmasi pengembalian | Dapat memproses | Untuk peminjaman sendiri |
| Mengelola pemeliharaan kendaraan | Ya | Tidak |
| Melihat laporan lengkap dan ekspor | Ya | Tidak |
| Melihat riwayat pribadi | Ya | Ya |
| Melihat audit aktivitas | Ya | Tidak |

## 7. Arsitektur Hak Akses

Fondasi hak akses menggunakan fasilitas bawaan Laravel:

- middleware `auth` untuk pengguna yang sudah masuk;
- middleware khusus `active` untuk status akun;
- middleware khusus `role:admin` untuk area administratif;
- Gate untuk kemampuan umum yang tidak terkait satu model;
- Policy untuk setiap sumber daya atau transaksi;
- prinsip deny by default;
- Admin dapat diberi override global melalui `Gate::before`;
- Pegawai hanya dapat mengakses transaksi dengan `user_id` miliknya;
- validasi akses wajib dilakukan pada server, bukan hanya menyembunyikan tombol.

Tidak digunakan tabel permission dinamis atau paket role/permission pihak ketiga pada fondasi awal. Struktur dapat dikembangkan apabila nanti terdapat peran tambahan seperti pimpinan atau pejabat persetujuan.

## 8. Audit Aktivitas

Minimal aktivitas berikut harus tercatat:

- pembuatan dan aktivasi akun;
- perubahan status atau peran akun;
- login berhasil dan logout;
- percobaan akses terlarang yang penting;
- pembuatan dan perubahan master data;
- perubahan stok;
- pengajuan, persetujuan, penolakan, penyerahan, dan konfirmasi;
- peminjaman, pengembalian, dan pemeliharaan;
- pembuatan atau ekspor laporan.

Catatan audit menyimpan pelaku, jenis tindakan, objek, waktu, alamat IP, serta ringkasan perubahan tanpa menyimpan kata sandi atau token.

## 9. Pilihan Teknis

- Autentikasi: Laravel Fortify dengan session cookie.
- Antarmuka: Blade dan Tailwind CSS.
- Pengguna: satu tabel `users`.
- Peran: kolom string `role` dengan PHP backed enum.
- Status akun: kolom string `status` dengan PHP backed enum.
- Otorisasi: middleware, Gate, dan Policy bawaan Laravel.
- Aktivasi: password broker Laravel dengan notifikasi aktivasi khusus.
- API token, OAuth, SSO, dan registrasi publik belum digunakan.

## 10. Kriteria Penerimaan Fondasi

Fondasi dianggap selesai apabila pengujian membuktikan bahwa:

1. rute registrasi publik tidak tersedia;
2. pengguna anonim diarahkan ke halaman login;
3. akun `pending` dan `suspended` tidak dapat masuk;
4. akun `active` dapat masuk;
5. login memiliki rate limit;
6. tautan aktivasi kedaluwarsa dan hanya dapat digunakan sekali;
7. Pegawai tidak dapat membuka halaman Admin;
8. Pegawai tidak dapat membaca transaksi milik Pegawai lain;
9. Admin dapat mengakses fungsi administratif;
10. seluruh perubahan penting menghasilkan catatan audit;
11. build frontend, audit dependency, dan seluruh tes berhasil.
