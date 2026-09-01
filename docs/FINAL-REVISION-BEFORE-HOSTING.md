# Revisi Final SIMANTAP Sebelum Hosting

Tanggal keputusan: 1 September 2026

## Keputusan fungsional

1. Permintaan persediaan tidak lagi meminta tanda tangan pemohon saat diajukan.
2. PDF permintaan persediaan hanya menyediakan tiga pihak:
   - Pengelola Barang: tanda tangan digital dari proses persetujuan;
   - Penerima Barang: tanda tangan digital dari konfirmasi penerimaan;
   - Kasubbag: ruang tanda tangan basah opsional setelah PDF dicetak.
3. Tanda tangan Kasubbag tidak menjadi syarat perubahan status transaksi.
4. Formulir permintaan persediaan milik Pegawai hanya meminta tanggal, barang,
   dan jumlah. Keperluan serta seluruh catatan Pegawai tidak ditampilkan dan
   nilai yang disisipkan dari klien diabaikan oleh server.
5. Kolom lama pada database dipertahankan agar tidak memerlukan migration dan
   tidak merusak kompatibilitas. Sistem menyimpan keperluan internal tetap
   `Permintaan persediaan` dan catatan Pegawai sebagai `NULL`.
6. Formulir peminjaman kendaraan, termasuk kolom Keperluan dan tanda tangan
   peminjam, tidak diubah.
7. Admin awal SIMANTAP memiliki dua role sekaligus: `admin` dan `pegawai`.
   Jumlah jenis role tetap dua. Admin tetap memperoleh seluruh fungsi
   administrasi dan juga memperoleh menu Permintaan Saya, Peminjaman Saya,
   serta Pengembalian Saya.

## Dampak database

- Tidak ada migration baru.
- Tidak ada tabel atau data yang dihapus oleh revisi ini.
- Database fresh SIMANTAP terdiri dari 40 tabel aplikasi/infrastruktur.
- Role tetap 2 (`admin`, `pegawai`) dan permission tetap 30.
- Role ganda pada Bu Mita adalah dua penugasan role pada satu akun, bukan role
  ketiga.

## Quality gate di Windows

Jalankan dari root repository setelah patch diterapkan:

```powershell
cd C:\xampp\htdocs\simantap

.\vendor\bin\pint `
    app\Http\Controllers\InventoryRequestController.php `
    app\Http\Requests\StoreInventoryRequestRequest.php `
    app\Http\Requests\SubmitInventoryRequestRequest.php `
    app\Models\InventoryRequest.php `
    app\Services\DocumentVerificationService.php `
    app\Services\InventoryRequestService.php `
    app\Support\Navigation.php `
    database\seeders\AdminUserSeeder.php `
    tests\Feature\DatabaseSeederTest.php `
    tests\Feature\InventoryRequestTest.php

.\vendor\bin\pint --test
php artisan test --filter=InventoryRequestTest
php artisan test --filter=DatabaseSeederTest
php artisan test
npm run build
git diff --check
```

Seluruh perintah harus lulus sebelum commit atau deployment.

## Terapkan role ganda pada database yang sudah ada

Seeder tidak mengganti kata sandi akun yang sudah ada. Setelah backup database
dan quality gate lulus, jalankan:

```powershell
php artisan optimize:clear
php artisan db:seed --class=AdminUserSeeder --force
php artisan permission:cache-reset
```

Verifikasi tanpa menampilkan data rahasia:

```powershell
php artisan tinker --execute="dump(App\Models\User::where('name', 'MITHA RAMADHANI PRATIWI')->firstOrFail()->getRoleNames()->values()->all());"
```

Target:

```text
admin
pegawai
```

## Uji penerimaan wajib

1. Login sebagai Bu Mita. Pastikan label akun `Administrator / Pegawai` dan
   menu administrasi tetap tersedia.
2. Dari akun Bu Mita, buka Permintaan Saya dan Peminjaman Saya; keduanya harus
   dapat diakses.
3. Buat permintaan barang. Pastikan tidak ada Keperluan, Catatan, atau kotak
   tanda tangan pemohon.
4. Ajukan permintaan tanpa tanda tangan pemohon; status harus berubah ke tahap
   pemeriksaan.
5. Setujui sebagai Pengelola Barang dengan tanda tangan digital.
6. Serahkan dan konfirmasi penerimaan dengan tanda tangan digital penerima.
7. Unduh PDF. Pastikan hanya ada Pengelola Barang, Penerima Barang, dan
   Kasubbag; ruang Kasubbag bertuliskan `Tanda tangan basah (opsional)`.
8. Buat draft peminjaman kendaraan. Pastikan kolom Keperluan dan tanda tangan
   peminjam tetap ada.

## Larangan sebelum hosting

- Jangan menjalankan `migrate:fresh` pada database yang akan diserahkan.
- Jangan mengunggah `.env`, dump SQL, file akun, atau kata sandi ke Git/chat.
- Jangan menganggap revisi selesai hanya karena halaman terlihat benar;
  PHPUnit, Pint, build Vite, dan uji PDF wajib lulus.
- Jangan mengubah `APP_KEY` setelah aplikasi mulai menyimpan data produksi.
