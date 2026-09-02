# Pemisahan SIMANTAP Resmi dan Demo

SIMANTAP resmi dan SIMANTAP demo memakai kode aplikasi yang sama, tetapi harus dipasang sebagai dua instalasi dengan `.env`, database, `APP_KEY`, session, cache, dan direktori `storage` yang berbeda.

| Instalasi | Contoh alamat | Database | Isi |
|---|---|---|---|
| Resmi BPS | domain utama | `simantap` | Akun dan data operasional BPS |
| Seminar | subdomain demo | `simantapdemo` | Dua akun dummy dan seluruh data seminar |

Data dari kedua instalasi tidak dapat bercampur karena setiap instalasi membuka database yang berbeda. Seeder dan perintah reset demo juga memiliki pengaman berlapis dan akan menolak berjalan pada database resmi.

## 1. Konfigurasi instalasi resmi

Pertahankan konfigurasi resmi yang sudah disiapkan. Bagian pemisahan minimalnya:

```dotenv
APP_ENV=production
APP_DEBUG=false
DB_DATABASE=simantap

SIMANTAP_PRODUCTION_DATABASE=simantap
SIMANTAP_DEMO_MODE=false
SIMANTAP_DEMO_DATABASE=simantapdemo

SESSION_COOKIE=simantap_session
CACHE_PREFIX=simantap_cache_
```

Gunakan seeder produksi biasa. Seeder ini tidak membuat akun dummy:

```powershell
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
```

Jangan pernah menjalankan `DemoSeeder` pada instalasi resmi.

## 2. Konfigurasi instalasi demo

Buat database kosong bernama `simantapdemo`, lalu gunakan `.env` tersendiri pada instalasi demo:

```dotenv
APP_NAME="SIMANTAP Demo"
APP_ENV=demo
APP_KEY=
APP_DEBUG=false
APP_URL=https://demo.alamat-anda.my.id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simantapdemo
DB_USERNAME=ISI_USER_DATABASE_DEMO
DB_PASSWORD=ISI_SANDI_DATABASE_DEMO

SIMANTAP_PRODUCTION_DATABASE=simantap
SIMANTAP_DEMO_MODE=true
SIMANTAP_DEMO_DATABASE=simantapdemo

SESSION_DRIVER=database
SESSION_COOKIE=simantapdemo_session
CACHE_STORE=database
CACHE_PREFIX=simantapdemo_cache_

SIMANTAP_DEMO_ADMIN_EMPLOYEE_NUMBER="DEMO-ADMIN-001"
SIMANTAP_DEMO_ADMIN_NAME="Administrator Demo"
SIMANTAP_DEMO_ADMIN_EMAIL="admin@bps.go.id"
SIMANTAP_DEMO_ADMIN_PASSWORD="admin123"
SIMANTAP_DEMO_ADMIN_WORK_UNIT="BPS Kabupaten Jombang - Demo"
SIMANTAP_DEMO_ADMIN_POSITION="Administrator Demo"

SIMANTAP_DEMO_EMPLOYEE_EMPLOYEE_NUMBER="DEMO-PEGAWAI-001"
SIMANTAP_DEMO_EMPLOYEE_NAME="Pegawai Demo"
SIMANTAP_DEMO_EMPLOYEE_EMAIL="pegawai@bps.go.id"
SIMANTAP_DEMO_EMPLOYEE_PASSWORD="pegawai123"
SIMANTAP_DEMO_EMPLOYEE_WORK_UNIT="BPS Kabupaten Jombang - Demo"
SIMANTAP_DEMO_EMPLOYEE_POSITION="Pegawai Demo"
```

Jalankan satu kali pada instalasi demo:

```powershell
php artisan key:generate
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=DemoSeeder --force
php artisan optimize:clear
```

Akun demo yang tersedia:

| Peran | Email | Sandi |
|---|---|---|
| Administrator | `admin@bps.go.id` | `admin123` |
| Pegawai | `pegawai@bps.go.id` | `pegawai123` |

Kedua sandi sederhana tersebut hanya boleh dipakai pada instalasi seminar yang terisolasi. Jangan gunakan sandi itu untuk akun resmi.

## 3. Mengembalikan demo ke kondisi awal

Lakukan simulasi terlebih dahulu. Simulasi tidak mengubah apa pun:

```powershell
php artisan simantap:reset-demo
```

Jika nama lingkungan dan database yang ditampilkan sudah benar, reset seluruh database demo:

```powershell
php artisan down
php artisan simantap:reset-demo --execute --confirmation=RESET-SIMANTAPDEMO
php artisan up
```

Perintah tersebut menjalankan `migrate:fresh`, sehingga seluruh transaksi seminar di `simantapdemo` dihapus dan dua akun dummy dibuat kembali. Perintah akan ditolak jika salah satu kondisi berikut terjadi:

- `APP_ENV` bukan `demo`;
- `SIMANTAP_DEMO_MODE` bukan `true`;
- database aktif tidak sama dengan `SIMANTAP_DEMO_DATABASE`;
- database aktif sama dengan `SIMANTAP_PRODUCTION_DATABASE`.

## 4. Jika penyedia hosting memberi awalan nama database

Sebagian hosting mengubah nama menjadi seperti `akun_simantap` dan `akun_simantapdemo`. Gunakan nama lengkap yang benar pada ketiga variabel berikut:

```dotenv
DB_DATABASE=akun_simantapdemo
SIMANTAP_DEMO_DATABASE=akun_simantapdemo
SIMANTAP_PRODUCTION_DATABASE=akun_simantap
```

Jangan memaksa nama pendek jika panel hosting menambahkan awalan.

## 5. Pemeriksaan setelah penerapan

Jalankan dari root proyek:

```powershell
.\vendor\bin\pint --test
php artisan test --filter=DemoEnvironmentTest
php artisan test --filter=DatabaseSeederTest
php artisan test
npm run build
git diff --check
php artisan optimize:clear
```

Setelah itu, pastikan instalasi resmi hanya menampilkan akun resmi dan instalasi demo hanya menampilkan dua akun dummy. Jangan menyalin file `.env` di antara kedua instalasi.
