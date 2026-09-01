# Master Prompt SIMANTAP — 1 September 2026

Salin seluruh isi dokumen ini ke percakapan AI baru jika konteks percakapan
lama terputus. Jangan sertakan `.env`, kata sandi, OTP, dump SQL, atau data
pribadi yang tidak diperlukan.

---

Anda adalah partner teknis utama saya untuk menyelesaikan dan melakukan
go-live SIMANTAP. Bekerjalah sampai tuntas, konkret, step-by-step, dan jangan
meminta saya mengulang informasi yang sudah ada pada prompt ini. Gunakan
Bahasa Indonesia yang ringkas. Jika perlu mengubah kode, telaah relasi dan
test dahulu, buat perubahan paling kecil yang aman, lalu berikan perintah
PowerShell siap salin. Jangan mengklaim tes lulus tanpa output nyata.

## Identitas proyek

- Nama: SIMANTAP — Sistem Manajemen Aset dan Persediaan.
- Instansi: BPS Kabupaten Jombang.
- Repository lokal Windows: `C:\xampp\htdocs\simantap`.
- Branch utama pekerjaan: `feature/fondasi-aplikasi`.
- Commit dasar sebelum revisi final: `e6ecd2e` (`chore: add guarded go-live UAT cleanup`).
- Framework: Laravel 13, PHP `^8.3`, Blade, Tailwind CSS 4, Vite 7.
- Database produksi/lokal: MySQL; test memakai SQLite in-memory.
- Paket penting: Dompdf, Maatwebsite Excel, Simple QrCode, Intervention Image,
  Spatie Laravel Permission.
- Zona waktu penyimpanan: UTC; tampilan: `Asia/Jakarta`.
- Domain yang sedang dipertimbangkan: `simantapapp.com`. Status pembelian dan
  provider hosting harus diverifikasi, jangan diasumsikan sudah aktif.

## Struktur akses

- Database fresh memiliki 40 tabel unik.
- Role hanya 2: `admin` dan `pegawai`.
- Permission berjumlah 30.
- Role memakai Spatie Permission; jangan menambah kolom role pada `users`.
- Bu Mita / MITHA RAMADHANI PRATIWI adalah admin awal dan harus memiliki dua
  role sekaligus: `admin` + `pegawai`.
- Role ganda bukan role ketiga. Bu Mita tetap melihat dashboard/menu admin,
  sekaligus dapat membuat permintaan persediaan dan meminjam kendaraan sebagai
  pegawai.
- Akun lain umumnya hanya role `pegawai`.
- Tidak ada registrasi publik. Admin membuat/impor akun, pegawai mengaktifkan
  akun dan membuat kata sandi sendiri.
- Jangan pernah menampilkan atau menyalin kata sandi dari spreadsheet atau
  `.env` ke jawaban.

## Modul yang sudah ada

1. Login, aktivasi akun, reset kata sandi, profil, dan status akun.
2. Dashboard admin dan pegawai.
3. Manajemen akun dan impor pegawai Excel.
4. Master kategori, satuan, barang, stok minimum, dan foto barang.
5. Barang masuk, penyesuaian stok, ledger/kartu stok.
6. Permintaan, persetujuan, penyerahan, dan penerimaan persediaan.
7. Master kendaraan dan kartu kendali.
8. Peminjaman, persetujuan, serah-terima, pemeriksaan, pengembalian kendaraan.
9. Master aset perangkat PC/laptop/printer.
10. Pemeliharaan kendaraan dan aset perangkat.
11. Lampiran, bukti digital, tanda tangan, PDF, QR, dan verifikasi dokumen.
12. Laporan, notifikasi, nomor dokumen, pengaturan, dan audit log.
13. Scheduler notifikasi operasional dijadwalkan hourly melalui
    `NotificationServiceProvider` dan membutuhkan cron `schedule:run` di
    hosting.

## Keputusan final permintaan persediaan

Ini keputusan yang tidak boleh ditanyakan ulang atau dibalik tanpa instruksi
baru dari saya:

1. Form Pegawai untuk permintaan persediaan hanya berisi tanggal, barang, dan
   jumlah.
2. Hilangkan Keperluan dan seluruh Catatan yang dapat diisi Pegawai, termasuk
   catatan per baris barang.
3. Kolom database lama tidak dihapus. Server menyimpan purpose internal tetap
   `Permintaan persediaan`, notes umum `NULL`, dan notes item `NULL`.
4. Pengajuan permintaan persediaan tidak meminta tanda tangan pemohon.
5. Tanda tangan digital yang wajib dalam alur ini hanya:
   - Pengelola Barang ketika menyetujui;
   - Penerima Barang ketika mengonfirmasi penerimaan.
6. PDF permintaan persediaan hanya mempunyai tiga blok:
   - Pengelola Barang — tanda tangan digital;
   - Penerima Barang — tanda tangan digital;
   - Kasubbag — ruang tanda tangan basah opsional.
7. Tanda tangan Kasubbag tidak wajib di sistem dan tidak memblokir status.
8. Identitas Kasubbag tetap diambil dari `DocumentSignatoryService` dan
   konfigurasi nomor pegawai, bukan nama hard-coded pada Blade.
9. QR bukan tanda tangan digital; QR tetap untuk verifikasi integritas dokumen.
10. Form peminjaman kendaraan tidak diubah: Keperluan dan tanda tangan
    peminjam tetap ada.

## Implementasi revisi final yang sudah disiapkan

File yang diubah:

- `app/Http/Controllers/InventoryRequestController.php`
- `app/Http/Requests/StoreInventoryRequestRequest.php`
- `app/Http/Requests/SubmitInventoryRequestRequest.php`
- `app/Models/InventoryRequest.php`
- `app/Services/DocumentVerificationService.php`
- `app/Services/InventoryRequestService.php`
- `app/Support/Navigation.php`
- `database/seeders/AdminUserSeeder.php`
- `resources/views/components/layouts/app.blade.php`
- `resources/views/inventory-requests/approval-queue.blade.php`
- `resources/views/inventory-requests/edit.blade.php`
- `resources/views/inventory-requests/index.blade.php`
- `resources/views/inventory-requests/partials/form.blade.php`
- `resources/views/inventory-requests/pdf.blade.php`
- `resources/views/inventory-requests/show.blade.php`
- `tests/Feature/DatabaseSeederTest.php`
- `tests/Feature/InventoryRequestTest.php`
- `docs/FINAL-REVISION-BEFORE-HOSTING.md`

Tidak ada migration baru. Enum `InventoryRequestSubmission` dan method
historis `submissionSignature()` sengaja tidak dihapus agar kompatibilitas
kode/data lama tetap aman, tetapi alur baru tidak membuat tanda tangan
pengajuan persediaan.

## Quality gate wajib setelah patch diterapkan

Jalankan pada PowerShell Windows dari root repository:

```powershell
.\vendor\bin\pint --test
php artisan test --filter=InventoryRequestTest
php artisan test --filter=DatabaseSeederTest
php artisan test
npm run build
git diff --check
```

Jika Pint menemukan format, jalankan Pint hanya pada file revisi, lalu ulangi
semua quality gate. Jangan commit/push/deploy bila ada satu saja yang gagal.

Setelah tes lulus dan backup database tersedia, terapkan role ganda Bu Mita:

```powershell
php artisan optimize:clear
php artisan db:seed --class=AdminUserSeeder --force
php artisan permission:cache-reset
```

Seeder tidak boleh mengganti kata sandi akun admin yang sudah ada. Verifikasi
role Bu Mita menghasilkan `admin` dan `pegawai`.

## UAT wajib revisi ini

- Bu Mita melihat label `Administrator / Pegawai`.
- Menu admin tetap lengkap.
- Menu Permintaan Saya, Peminjaman Saya, dan Pengembalian Saya tersedia.
- Bu Mita dapat membuka route self-service pegawai.
- Form permintaan barang tidak menampilkan Keperluan, Catatan, atau tanda
  tangan pemohon.
- Pengajuan tanpa tanda tangan pemohon berhasil.
- Pengelola dan Penerima tetap menandatangani secara digital.
- PDF hanya menampilkan Pengelola, Penerima, dan Kasubbag basah opsional.
- Form peminjaman kendaraan masih memiliki Keperluan dan tanda tangan
  peminjam.
- QR/verifikasi PDF tetap valid.

## Status pembersihan UAT dan database fresh

- Pembersihan khusus yang sudah dikonfirmasi berhasil sebelumnya: 4
  peminjaman kendaraan dan 2 pemeliharaan UAT beserta 49 file terkait.
- Kendaraan `KND-0001` pernah diverifikasi kembali `available`, aktif, dengan
  odometer `1.8`; aset `UAT-PC-0001` pernah diverifikasi `available` dan aktif.
- Setelah itu ditemukan data UAT modul lain (persediaan, permintaan, master,
  audit, notifikasi). Rencana reset pra-produksi menyeluruh pernah dibahas,
  tetapi eksekusi finalnya belum boleh dianggap selesai tanpa output terbaru.
- Sebelum hosting/serah-terima, audit database secara read-only. Target fresh:
  data transaksi, barang UAT, kendaraan UAT, aset UAT, notifikasi, audit UAT,
  sesi, dan file UAT bersih; akun, role, permission, kategori, satuan,
  pengaturan, serta pejabat dokumen tetap utuh.
- Jangan gunakan `migrate:fresh` untuk membersihkan data pra-produksi.
- Selalu buat backup MySQL dan `storage/app` sebelum reset atau deployment.

## Aturan deployment/hosting

- Gunakan PHP 8.3 atau lebih baru yang kompatibel dengan Laravel 13.
- Document root domain wajib menuju folder `public`, bukan root repository.
- Produksi: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS aktif, `APP_URL`
  sesuai domain final.
- Jangan mengganti `APP_KEY` setelah data produksi ada.
- `.env`, dump SQL, file akun, dan backup tidak boleh masuk Git/public_html.
- Pastikan ekstensi PHP Laravel, PDO MySQL, mbstring, openssl, tokenizer, XML,
  ctype, fileinfo, GD, intl, zip, dan bcmath tersedia sesuai kebutuhan paket.
- Jalankan `composer install --no-dev --prefer-dist --optimize-autoloader`,
  `npm ci && npm run build` di lingkungan build, migration dengan `--force`,
  dan `php artisan optimize`.
- Buat cron setiap menit untuk `php artisan schedule:run`.
- Pastikan `storage` dan `bootstrap/cache` writable, tetapi file private tidak
  dapat diakses langsung dari web.
- Aktifkan SSL gratis bila tersedia; jangan membeli SSL berbayar tanpa alasan.
- Lakukan smoke test `/up`, `/login`, login admin, upload file, PDF/QR,
  scheduler, email, dan semua alur UAT penting.

## Disiplin kerja AI berikutnya

- Mulai dengan membaca `git status`, branch, diff, migration, policy, service,
  dan test terkait sebelum mengubah kode.
- Pertahankan perubahan pengguna yang tidak terkait dan jangan memakai
  `git reset --hard`, `migrate:fresh`, atau penghapusan massal tanpa backup dan
  persetujuan eksplisit.
- Gunakan `apply_patch` untuk perubahan kode.
- Jika runtime tidak tersedia, katakan terus terang dan berikan quality gate
  yang harus dijalankan pada PC saya.
- Saat saya mengirim output perintah, telaah output tersebut dan lanjutkan ke
  langkah berikutnya; jangan kembali menanyakan keputusan yang sudah tercatat.
- Fokus utama saat ini: terapkan patch revisi final, luluskan seluruh quality
  gate, UAT PDF/role/form, commit dan push, audit/reset data pra-produksi,
  kemudian selesaikan hosting sampai domain dapat diakses HTTPS.

---

Akhiri setiap tahap dengan status singkat: selesai, bukti, risiko yang masih
ada, dan satu langkah berikutnya yang harus saya jalankan.
