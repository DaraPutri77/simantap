# Checkpoint SIMANTAP — Target 4 dan Target 5

Tanggal checkpoint: 28 Agustus 2026

## Posisi riwayat Git

- Target 3 sudah tergabung ke `feature/fondasi-aplikasi` melalui merge commit
  `43a5e683f54030b06fc085fd391830d780bb1c30`.
- Commit Target 3 milik Yazka tetap mempertahankan author
  `yazkaazahaa <yazkaazahaa5@gmail.com>`.
- Target 4 berada di branch `feature/target-4-maintenance-pdf`, commit
  `2a22767` sebelum Target 5 ditambahkan.
- Target 5 dibangun di atas Target 4 pada branch
  `feature/target-5-document-signatures`.

## Target 4 — PDF transaksi pemeliharaan

- PDF A4 portrait tersedia pada seluruh status pemeliharaan.
- Tampilan memakai identitas SIMANTAP, logo BPS dari aset proyek, warna biru
  `#163A5F`, dan aksen emas `#C9A227`.
- PDF memuat identitas transaksi, subjek kendaraan/aset perangkat, rincian
  pemeliharaan, bukti, riwayat status, dan pihak terkait.
- Bukti diperiksa ulang dengan checksum SHA-256 sebelum PDF dibuat. Bukti yang
  berubah atau hilang menghentikan unduhan dengan HTTP 409.
- QR verifikasi dan hash payload menghasilkan versi baru saat isi transaksi
  berubah, serta memakai ulang versi yang sama bila isi tidak berubah.
- Unduhan dicatat sebagai audit event `maintenance_pdf_downloaded`.
- Tidak ada migrasi database baru.

## Target 5 — pejabat penandatangan dokumen

- Pejabat aktif diselesaikan dari akun aktif berdasarkan `employee_number`,
  bukan nama yang ditulis langsung di template.
- Default Administrator/Pengelola Barang: `SIM-JBG-017`.
- Default Kasubbag Umum: `SIM-JBG-020`.
- Dua nilai tersebut dapat diganti melalui `.env`:
  `SIMANTAP_SIGNATORY_ADMINISTRATOR` dan `SIMANTAP_SIGNATORY_KASUBBAG`.
- Dukungan diterapkan pada kartu stok, permintaan persediaan, laporan,
  pemeliharaan, kartu kendali kendaraan, peminjaman kendaraan, serta dokumen
  serah-terima/pengembalian kendaraan.
- Tanda tangan digital pelaku transaksi dan snapshot historis tidak ditimpa.
  Pejabat aktif tampil sebagai ruang pengesahan manual yang terpisah.
- Pada dokumen ber-QR, identitas pejabat aktif ikut masuk ke payload hash.
  Pergantian pejabat menghasilkan versi verifikasi dokumen baru.
- Akun pejabat yang tidak aktif atau tidak ditemukan tidak ditampilkan sebagai
  penandatangan; template memakai placeholder yang jelas.
- Tidak ada migrasi database baru.

## Pemeriksaan wajib di komputer pengembang

Jalankan dari root proyek setelah branch Target 5 diambil:

```powershell
php artisan config:clear
php artisan test --filter=MaintenancePdfTest
php artisan test --filter=DocumentSignatoryServiceTest
php artisan test
npm ci
npm run build
```

Lakukan smoke test dengan mengunduh minimal satu PDF dari tiap keluarga
dokumen. Pastikan akun `SIM-JBG-017` dan `SIM-JBG-020` berstatus aktif atau
ubah nomor pegawai pada `.env` sesuai pejabat yang berlaku.

## Catatan validasi lingkungan checkpoint

Pemeriksaan whitespace Git (`git diff --check`) dilakukan pada workspace.
Runtime checkpoint tidak menyediakan PHP/Composer dan cache npm tidak lengkap,
sehingga suite Laravel dan build Vite wajib dijalankan pada komputer pengembang
atau CI sebelum branch digabung ke branch fondasi.
