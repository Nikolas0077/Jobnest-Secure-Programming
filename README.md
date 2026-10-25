# JobNest

JobNest adalah portal lowongan kerja dengan empat peran: Guest, Pelamar, Perusahaan, dan Admin. Aplikasi terdiri dari dua bagian yang berjalan terpisah:

- **API** (`api/`): PHP Native + MySQL, respons JSON di `/api/...`.
- **UI** (`public/`): halaman PHP yang memanggil API.

Peta fitur dan pembagian tugas ada di [panduan kolaborasi](docs/fitur-dan-kolaborasi.md). Format endpoint API ada di [kontrak API](docs/api-contract.md).

## Kebutuhan

- PHP 8.1 atau lebih baru, dengan ekstensi `pdo_mysql`, `curl`, dan `fileinfo` aktif
- MySQL 8 atau MariaDB 10.6+
- Node.js 18+ (hanya untuk build CSS)

## 1. Siapkan database

Jalankan dari folder root proyek:

```powershell
mysql -u root -p < database/schema.sql
```

Buat akun database khusus aplikasi dengan hak terbatas. Ganti `<password-db>` dengan password kuat milik Anda:

```sql
CREATE USER 'jobnest_app'@'localhost' IDENTIFIED BY '<password-db>';
GRANT SELECT, INSERT, UPDATE, DELETE ON jobnest.* TO 'jobnest_app'@'localhost';
```

## 2. Konfigurasi API

```powershell
copy api\.env.example api\.env
```

Buka `api/.env`, lalu isi `DB_PASS` dengan `<password-db>` dari langkah 1. File `api/.env` sudah diabaikan oleh Git dan tidak boleh di-commit.

## 3. Isi data demo

```powershell
php database/seed.php
```

Skrip akan meminta satu password untuk semua akun demo (admin, perusahaan, dan pelamar). Ketikan tidak ditampilkan di layar dan password tidak dicetak. Syaratnya minimal 12 karakter dan berisi huruf besar, huruf kecil, serta angka.

Setelah selesai, skrip menampilkan daftar email akun demo per peran. Gunakan email tersebut dengan password yang tadi dimasukkan.

Akun admin hanya bisa dibuat lewat skrip ini, tidak lewat halaman daftar. Skrip cukup dijalankan sekali; jika dijalankan lagi, data dan password yang sudah ada tidak diubah.

## 4. Jalankan aplikasi

Terminal 1 (API):

```powershell
php -S 127.0.0.1:8001 -t api/public api/public/index.php
```

Terminal 2 (UI):

```powershell
npm ci
npm run build:css
$env:JOBNEST_API_BASE_URL = 'http://127.0.0.1:8001/api'
php -S localhost:8000 -t public
```

Buka `http://localhost:8000`, lalu masuk memakai salah satu akun demo.

## 5. Jalankan tes

Pastikan langkah 1–3 sudah selesai. Server tes dijalankan otomatis, jadi tidak perlu menyalakan terminal API/UI.

```powershell
php tests/public-ui.php
node tests/http-integration.mjs
$env:SEED_PASSWORD = [System.Net.NetworkCredential]::new('', (Read-Host 'Password demo' -AsSecureString)).Password
node tests/api-security.mjs
```

Saat diminta, ketik password yang dimasukkan saat menjalankan `seed.php`. Ketikan tidak ditampilkan dan tidak tersimpan di riwayat terminal. Tes keamanan API membuat beberapa akun uji baru di database, jadi jalankan di database lokal saja.

## Catatan

- CV yang diunggah disimpan di `storage/private/cv/`, di luar folder `public/`. Folder ini otomatis dibuat dan diabaikan oleh Git.
- CSS hasil build sudah tersimpan di `public/assets/css/`, jadi PHP bisa menyajikan halaman tanpa proses Node yang terus berjalan.
- Saat mengubah styling halaman publik, jalankan `npm run watch:css` di terminal lain.
- Jika halaman dashboard diubah, build ulang CSS-nya:
  `npx @tailwindcss/cli -i ./assets/css/dashboard.input.css -o ./public/assets/css/dashboard.css --minify`
