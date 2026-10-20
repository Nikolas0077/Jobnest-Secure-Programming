# JobNest

Peta fitur, status tiap aktor, dan cara menyambungkan halaman baru ada di [panduan kolaborasi](docs/fitur-dan-kolaborasi.md).

Jalankan dari root proyek. API backend harus berjalan terpisah dan mengikuti [kontrak API](docs/api-contract.md):

```powershell
npm ci
npm run build:css
$env:JOBNEST_API_BASE_URL = 'http://127.0.0.1:8001/api'
php -S localhost:8000 -t public
```

Buka `http://localhost:8000`. Saat mengubah styling, jalankan `npm run watch:css` di terminal lain. CSS hasil build sudah disimpan di `public/assets/css/public.css`, jadi PHP dapat menyajikan halaman tanpa proses Node yang terus berjalan.

Halaman lowongan meminta data lewat PHP. Form login, daftar, dan keluar mengirim POST ke `auth-action.php`; file PHP itu meneruskan JSON dan token CSRF ke backend. Endpoint API belum diimplementasikan dalam repo ini; tanpa backend, halaman menampilkan state error yang jelas.

Tes lokal tanpa backend asli: `php tests/public-ui.php` dan `node tests/http-integration.mjs`.
