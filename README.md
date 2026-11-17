# Layanan Tata Usaha SMAN 104 Jakarta - Backend (API)

REST API untuk portal layanan administrasi Tata Usaha SMA Negeri 104 Jakarta. Dipakai oleh repo frontend: **<URL-REPO-FRONTEND>**.

## Fitur

- Autentikasi token (Laravel Sanctum): masuk dengan username, token berlaku 8 jam
- Peran `siswa`, `guru`, `tu`; petugas TU bisa menjadi **admin** dan diberi **izin per modul** (`pengajuan`, `sk`, `pegawai`, `pengumuman`, `banner`, `kontak`)
- Pengajuan layanan lengkap dengan lampiran, riwayat status, perbaikan dan kirim ulang, serta cek status publik
- Pengelolaan SK, pengumuman, data pegawai (impor Excel), kontak sekolah, dan FAQ
- **Keamanan akun:** akun aktif/nonaktif, kunci login (5 kali gagal = dikunci 15 menit), ganti kata sandi, konfirmasi kata sandi untuk aksi sensitif (berlaku 5 menit), log audit

## Teknologi

PHP 8.2+, Laravel 12, Laravel Sanctum, MySQL, PhpSpreadsheet (impor Excel).

## Prasyarat

- PHP 8.2 atau lebih baru (produksi memakai 8.3) dengan ekstensi: `pdo_mysql`, `gd`, `zip`, `bcmath`, `mbstring`, `xml`, `fileinfo`
- Composer 2
- MySQL 8 atau MariaDB (mis. lewat XAMPP atau Laragon)

## Instalasi (lokal)

```bash
git clone <URL-REPO-BACKEND>
cd tu-backend
composer install
```

Salin berkas konfigurasi lalu buat kunci aplikasi:

```bash
# Windows (PowerShell / CMD)
copy .env.example .env
# macOS / Linux
cp .env.example .env

php artisan key:generate
```

Buat database kosong di MySQL, mis. bernama `tu_sman104` (lewat phpMyAdmin, HeidiSQL, atau konsol MySQL). Lalu buka `.env` dan atur bagian database (baris `DB_*` selain `DB_CONNECTION` mungkin masih berupa komentar berawalan `#`, hapus tanda `#`-nya):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tu_sman104
DB_USERNAME=root
DB_PASSWORD=
```

> Isi `DB_PASSWORD` sesuai MySQL di komputer Anda (di XAMPP biasanya kosong). **Jangan pernah meng-commit file `.env`.**

Jalankan migrasi, data awal, dan tautan penyimpanan file:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

API berjalan di `http://localhost:8000/api`. Uji cepat: buka `http://localhost:8000/api/kontak` di browser, dan data JSON harus muncul.

> Skrip `composer setup` dan `composer dev` bawaan Laravel di `composer.json` memanggil `npm`, padahal backend ini tidak memakai aset Vite. Gunakan langkah manual di atas.

### Akun awal

`php artisan db:seed` membuat satu akun petugas TU (username `tu`). Sandi awalnya ada di `database/seeders/DatabaseSeeder.php` dan **hanya untuk lokal**. Segera ganti lewat menu Ganti Kata Sandi setelah login pertama, terutama di produksi. Akun siswa dan guru dibuat lewat dashboard admin (menu **Kelola Akun**).

### Alternatif cepat dengan SQLite (hanya untuk mencoba)

Atur `DB_CONNECTION=sqlite` di `.env`, buat berkas kosong `database/database.sqlite`, lalu jalankan `php artisan migrate`. Proyek ini dikembangkan dan di-deploy dengan MySQL, jadi gunakan MySQL untuk pengembangan sungguhan.

## Variabel lingkungan penting

| Variabel | Lokal | Produksi |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | **`false`** |
| `APP_KEY` | hasil `php artisan key:generate` | buat sendiri: `php artisan key:generate --show` |
| `APP_URL` | `http://localhost:8000` | domain backend |
| `DB_CONNECTION` | `mysql` | `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL lokal | dari layanan MySQL di Railway |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | `database` | `database` |

`CACHE_STORE=database` memakai tabel `cache` yang dibuat migrasi. Konfirmasi kata sandi untuk aksi sensitif memakai cache ini.

## CORS (wajib disesuaikan)

Daftar domain frontend yang boleh memanggil API ada di `config/cors.php`:

```php
'allowed_origins' => [
    'https://layanan-tu-sman104.vercel.app',
    'http://localhost:5173',
],
```

Jika Anda men-deploy frontend di domain lain, tambahkan domain itu ke daftar ini, lalu deploy ulang backend.

## Ringkasan API

Seluruh alamat berawalan `/api`. Daftar lengkap: `php artisan route:list --path=api`.

| Kelompok | Endpoint utama |
|---|---|
| Publik | `POST /login`, `GET /kontak`, `GET /faq`, `GET /sk`, `GET /pegawai`, `GET /pengumuman`, `GET /cek-status/{nomor}` |
| Setelah login | `GET /me`, `POST /logout`, `POST /ganti-sandi`, `POST /konfirmasi-sandi`, `GET /pengajuan/{nomor}` |
| Siswa dan guru | `GET`, `POST /pengajuan`, `POST /pengajuan/{nomor}/kirim-ulang` |
| Staf TU (`/admin/...`, sesuai izin) | pengajuan, SK, pegawai, pengumuman, kontak dan FAQ, banner |
| Khusus admin | `/admin/akun...` (kelola akun), `/admin/audit` (log audit) |

Aksi sensitif (hapus data, impor pegawai, tambah/ubah/reset/nonaktifkan akun) menjawab **428** sampai pengguna memasukkan kata sandi lewat `POST /konfirmasi-sandi`.

## Struktur folder

```
tu-backend/
├─ routes/api.php                   seluruh rute API
├─ app/Http/Controllers/
│  ├─ Api/                          publik dan pengguna login
│  └─ Admin/                        staf TU dan admin
├─ app/Http/Middleware/             EnsureRole, EnsureAkunAktif, EnsureIzin, EnsureAdmin, EnsureSandiUlang
├─ app/Models/                      User, Pengajuan, AuditLog, Faq, KontakSekolah, dst.
├─ app/Services/                    PegawaiImporter (impor Excel)
├─ database/migrations/             struktur tabel
├─ database/seeders/                akun TU awal
├─ config/cors.php  config/sanctum.php
└─ Dockerfile                       untuk deploy di Railway
```

> Berkas `vite.config.js`, `package.json`, dan `resources/js` adalah sisa bawaan Laravel dan tidak dipakai.

## Deploy ke Railway

1. Buat proyek baru dari repo GitHub ini; Railway memakai `Dockerfile` secara otomatis.
2. Tambahkan layanan **MySQL** di proyek yang sama.
3. Di layanan backend, isi variabel: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, `DB_CONNECTION=mysql`, serta `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (dari layanan MySQL).
4. Pasang **Volume** pada folder `/app/storage/app/public`. **Tanpa Volume, file unggahan (lampiran, gambar, PDF SK) hilang setiap deploy ulang.**
5. Deploy. Saat kontainer menyala, otomatis dijalankan `php artisan migrate --force` dan `storage:link`.
6. Jalankan seeder **sekali** lewat terminal layanan di Railway: `php artisan db:seed --force`. Lalu segera ganti sandi akun `tu`.
7. Buat domain publik, lalu isi `VITE_API_URL` di Vercel dengan `https://<domain>/api`.

> `Dockerfile` memakai `php artisan serve`, yang cukup untuk skala sekolah tetapi bukan server produksi yang ideal untuk jangka panjang.

## Keamanan

- `.env` tidak boleh di-commit (sudah ada di `.gitignore`). Jika pernah terbagikan, ganti password database dan `APP_KEY`.
- Pastikan `APP_DEBUG=false` di produksi.
- Ganti sandi akun awal dari seeder.
- Akun tidak dihapus, hanya dinonaktifkan, agar riwayat pengajuan tetap utuh.
- Setiap aksi penting tercatat di log audit (tabel `audit_logs`); sandi tidak pernah dicatat.

## Pemecahan masalah

| Gejala | Penyebab dan solusi |
|---|---|
| `SQLSTATE[HY000] [2002] ... actively refused it` | MySQL belum menyala. Nyalakan lewat XAMPP/Laragon, lalu cek dengan `php artisan db:show` |
| `Unknown database 'tu_sman104'` | Database belum dibuat; buat lebih dulu |
| `Access denied for user` | `DB_USERNAME` atau `DB_PASSWORD` di `.env` tidak cocok |
| `could not find driver` | Aktifkan ekstensi `pdo_mysql` di `php.ini` |
| Frontend kena error CORS | Tambahkan domain frontend di `config/cors.php` |
| Perubahan `.env` atau rute tidak terbaca | Jalankan `php artisan optimize:clear` |
| Respons 428 pada aksi sensitif | Normal; frontend akan meminta kata sandi. Jika memakai alat lain (Postman), panggil `POST /api/konfirmasi-sandi` dulu |
| File unggahan hilang setelah deploy | Volume belum terpasang di Railway |

## Berkontribusi

```bash
git checkout -b fitur/nama-fitur
# ... kerjakan, lalu:
php artisan test
git add .
git commit -m "feat: deskripsi singkat perubahan"
git push origin fitur/nama-fitur
```

Buka Pull Request ke cabang utama. Jangan melakukan commit langsung ke cabang utama.

## Tim

<isi nama anggota kelompok>
