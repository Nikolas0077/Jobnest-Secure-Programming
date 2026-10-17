# Rancangan Implementasi & Timeline Web App: Job Portal ("KerjaAman")

Rancangan untuk tugas AOL **COMP6695001 – Secure Programming**. Nama aplikasi hanya contoh, silakan diganti.

> Dokumen revisi untuk implementasi **PHP native + MySQL** tanpa Laravel/Blade. Kontrol keamanan di bawah adalah **persyaratan implementasi dan pengujian**, bukan klaim bahwa aplikasi sudah aman. Timeline memakai asumsi 5 minggu dan dapat digeser mengikuti deadline.

---

## 1. Gambaran Singkat

Job portal sederhana tempat **perusahaan** memasang lowongan kerja dan **pelamar** mencari serta melamar lowongan dengan mengunggah CV. **Admin** bertugas memverifikasi perusahaan dan memoderasi lowongan.

Prinsip keamanan yang dipakai di seluruh aplikasi:
1. **Jangan percaya input pengguna.** Semua input divalidasi di server, dan semua output di-escape.
2. **Tolak secara default.** Setiap halaman dan aksi dicek: sudah login? role-nya benar? data ini miliknya?
3. **Hak akses minimum.** Setiap peran hanya bisa melakukan apa yang memang perlu.
4. **Data sensitif dilindungi.** Password di-hash, CV disimpan private, dan rahasia tidak masuk ke Git.

## 2. Tech Stack & Batasan

| Bagian | Pilihan | Catatan |
|---|---|---|
| Backend | **PHP native (PHP 8.2+)** | Tanpa Laravel dan framework PHP lainnya |
| Database | **MySQL** | Gunakan **PDO prepared statements** |
| Frontend | **HTML, CSS, JavaScript, PHP templates (`include`/`require`)** | Tanpa Blade (Blade milik Laravel) |
| Styling | CSS native; Bootstrap **opsional** | Gunakan Bootstrap hanya jika dosen mengizinkan library CSS |
| Lingkungan lokal | XAMPP atau Laragon | Apache + PHP + MySQL; samakan versi tim |
| Version control | Git + GitHub private | Branch fitur dan pull request |

**Konfirmasi ke dosen:** apakah library frontend seperti Bootstrap diperbolehkan? Jika tidak, pakai CSS native. Semua validasi penting wajib di PHP server-side; JavaScript hanya untuk UX.

### 2.1 Pengganti Fitur Laravel pada PHP Native

| Sebelumnya (Laravel) | PHP native |
|---|---|
| `Hash::make`, `Hash::check` | `password_hash()`, `password_verify()` |
| Eloquent / Query Builder | PDO + prepared statement, whitelist identifier SQL |
| Blade `{{ ... }}` | `htmlspecialchars($value, ENT_QUOTES, ENT_SUBSTITUTE, 'UTF-8')` melalui helper `e()` |
| Middleware `auth`, `role`, `verified` | `require_login()`, `require_role()`, `require_verified()` pada endpoint server |
| Laravel Policy | Periksa ownership pada tiap query dan endpoint (`user_id`, `company_id`) |
| `@csrf` | Token `random_bytes()` di session + `hash_equals()` saat submit |
| `session()->regenerate()` | `session_regenerate_id(true)` setelah login |
| Rate limiter Laravel | Simpan penghitung/cooldown secara server-side; uji batasnya |
| `Storage::download()` | File di luar web root; endpoint PHP cek hak akses lalu stream file |
| Laravel logging | Helper `log_activity()` menulis ke `activity_logs` |

### 2.2 Struktur Folder yang Disepakati

```text
kerjaaman/
├── public/                    # Satu-satunya document root Apache
│   ├── index.php
│   ├── login.php
│   ├── register.php
│   ├── jobs/                  # daftar dan detail lowongan
│   ├── applicant/
│   ├── company/
│   ├── admin/
│   └── assets/{css,js,img}/
├── app/
│   ├── config/database.php
│   ├── includes/{auth,csrf,security,layout,logger}.php
│   └── services/              # fungsi bisnis/query bersama
├── storage/private/cvs/       # di luar public/
├── storage/private/images/    # jika perlu simpan upload sementara
├── database/schema.sql
├── database/seed.sql
├── tests/                      # skenario test & bukti
├── .env.example                # contoh konfigurasi tanpa kredensial
└── .gitignore
```

**Kontrak umum:** `public/` adalah web root; tidak ada endpoint yang mempercayai role, user ID, ownership, atau lokasi file dari request pengguna. Kredensial lokal tidak di-commit.

## 3. Peran Pengguna

| Peran | Bisa melakukan |
|---|---|
| **Tamu** (belum login) | Melihat daftar dan detail lowongan, mencari lowongan, registrasi, login |
| **Pelamar** | Mengelola profil, mengunggah CV, melamar lowongan, melihat status lamaran sendiri, menyimpan lowongan favorit |
| **Perusahaan** | Mengelola profil perusahaan dan logo, membuat/mengedit/menutup lowongan, melihat pelamar di lowongannya sendiri, mengubah status lamaran |
| **Admin** | Memverifikasi akun perusahaan, menonaktifkan pengguna, menghapus lowongan palsu, melihat log aktivitas |

## 4. Daftar Fitur dan Pengamanannya

### 4.1 Registrasi, Login & Akun
**Fitur:** registrasi sebagai pelamar atau perusahaan, verifikasi email, login, logout, lupa password, dan ganti password.

**✅ Pengamanan:**
- Password di-hash dengan **bcrypt/argon2** (`password_hash()`) dan tidak pernah disimpan dalam bentuk asli.
- Aturan password kuat: minimal 8 karakter, dengan huruf besar, huruf kecil, angka, dan simbol.
- **Role tidak bisa dipilih bebas.** Hanya `applicant` atau `company` yang diterima (whitelist). Role ditetapkan server melalui whitelist; akun admin hanya dibuat lewat skrip CLI/database terproteksi.
- **Rate limiting** untuk login (5x/menit), registrasi, dan lupa password (3x/menit), untuk mencegah brute force dan spam.
- Pesan error login selalu sama: "Email atau password salah".
- Lupa password selalu menampilkan "Jika email terdaftar, link sudah dikirim", jadi email pengguna tidak bisa ditebak.
- Token reset password acak, sekali pakai, dan berlaku 60 menit.
- **Verifikasi email wajib** sebelum bisa melamar atau memasang lowongan (pengecekan server `require_verified()`).
- **Ganti password** wajib memasukkan password lama, lalu semua session lain otomatis logout (session pengguna lain harus diinvalidasi melalui mekanisme session version/revocation di server).
- Setelah login, **session ID diganti** (`session_regenerate_id(true)`) untuk mencegah session fixation.
- Saat logout, session dihapus total (hapus data session, cookie, dan `session_destroy()`).
- Cookie session diset `HttpOnly`, `Secure`, `SameSite=Lax`, dengan **timeout 30 menit** jika tidak aktif.
- Redirect setelah login hanya ke URL internal (validasi path relatif internal dengan whitelist tujuan), sehingga tidak ada **open redirect**.
- Pengecekan `is_active` pada setiap request terautentikasi: user yang dinonaktifkan admin langsung dikeluarkan pada request berikutnya.

### 4.2 Profil Pelamar & Upload CV
**Fitur:** mengisi data diri, pendidikan, dan keahlian, lalu mengunggah CV (PDF) dan foto profil.

**✅ Pengamanan:**
- Validasi file berdasarkan **isi asli (MIME type)**, bukan hanya ekstensi:
  - CV hanya PDF (MIME via `finfo_file()` + pemeriksaan PDF + maksimum 2 MB).
  - Foto hanya JPG/PNG (MIME via `finfo_file()` + decoding gambar + maksimum 1 MB). **SVG ditolak** karena bisa berisi JavaScript.
- Nama file **diacak** (UUID). Nama asli dari pengguna tidak dipakai, sehingga tidak ada path traversal atau penimpaan file.
- CV disimpan di **storage private** (`storage/private` di luar `public/`), bukan di folder `public`, jadi tidak bisa diakses lewat URL langsung.
- Foto profil di-*re-encode* (diproses ulang dengan library gambar) agar kode tersembunyi di dalam file ikut hilang.
- Semua teks profil dibatasi panjangnya dan di-escape saat ditampilkan.

### 4.3 Profil Perusahaan & Lowongan Kerja
**Fitur:** mengisi profil perusahaan (nama, deskripsi, website, logo), lalu membuat, mengedit, menutup, dan menghapus lowongan.

**✅ Pengamanan:**
- **Hanya perusahaan terverifikasi** yang bisa memasang lowongan dan melihat pelamar.
- **Cek kepemilikan (otorisasi PHP server-side):** perusahaan hanya bisa mengubah atau menghapus lowongan miliknya. Mengganti ID di URL akan menghasilkan 403, jadi tidak ada **IDOR**.
- Deskripsi lowongan ditampilkan dengan helper `e()` berbasis `htmlspecialchars()`. Deskripsi awalnya ditampilkan sebagai plain text; jika ingin rich text, wajib disanitasi secara eksplisit.
- Field **website** hanya menerima URL yang diawali `http://` atau `https://`, sehingga link `javascript:` (XSS) ditolak. Link keluar diberi `rel="noopener noreferrer"`.
- Logo mengikuti aturan upload yang sama seperti foto profil (tanpa SVG, di-*re-encode*, nama diacak).
- Validasi logika di server: judul maks 150 karakter, deskripsi maks 5000 karakter, `salary_min ≤ salary_max`, deadline tidak di masa lalu, kategori dari daftar tetap.
- Semua form dan aksi hapus memakai **token CSRF** dan method POST/PUT/DELETE (tidak pernah GET).

### 4.4 Pencarian & Filter Lowongan
**Fitur:** cari lowongan berdasarkan kata kunci, lokasi, kategori, dan rentang gaji.

**✅ Pengamanan:**
- Query memakai **PDO prepared statements**. Tidak ada string SQL yang digabung dengan input, sehingga aman dari **SQL injection**.
- Kolom sorting dibatasi *whitelist* (`created_at`, `salary_min`), dan input lain ditolak.
- Kata kunci dibatasi maksimal 100 karakter dan di-escape saat ditampilkan ulang (aman dari **reflected XSS**).
- Hanya lowongan berstatus `open` dari perusahaan terverifikasi yang ditampilkan.
- Pagination (misalnya 10 per halaman) dan rate limit pencarian, agar server tidak dibanjiri request.

### 4.5 Melamar Lowongan & Lowongan Tersimpan
**Fitur:** pelamar memilih CV, menulis surat lamaran, lalu mengirim lamaran. Pelamar bisa melihat status lamaran dan menyimpan lowongan favorit.

**✅ Pengamanan:**
- Hanya role **pelamar** dengan email terverifikasi yang bisa melamar (helper `require_login()`, `require_verified()`, `require_role('applicant')`).
- Server mengecek ulang: lowongan masih `open`, belum lewat deadline, dan perusahaannya terverifikasi. Request langsung (misalnya lewat Postman) ke lowongan tertutup tetap ditolak.
- Satu pelamar hanya bisa melamar satu kali per lowongan (**unique constraint** di database + validasi).
- CV yang dipakai **disalin** saat melamar, sehingga lamaran tidak berubah jika pelamar mengganti CV di profil.
- Surat lamaran maksimal 2000 karakter dan di-escape saat dibaca perusahaan (aman dari **stored XSS**).
- Rate limit tombol lamar (misalnya 10x/menit) untuk mencegah spam.
- Daftar lamaran dan lowongan tersimpan selalu difilter `where user_id = auth()->id()`, sehingga pelamar tidak bisa melihat atau menghapus milik orang lain.

### 4.6 Kelola Pelamar (Perusahaan)
**Fitur:** melihat daftar pelamar per lowongan, mengunduh CV, dan mengubah status lamaran.

**✅ Pengamanan:**
- Perusahaan **hanya** bisa melihat pelamar dari lowongan miliknya. Dicek di server melalui query ownership server-side: `application->jobPost->company_id == user->company->id`.
- CV diunduh lewat **controller yang mengecek hak akses**, bukan link langsung ke file.
- Path file diambil dari **database**, tidak pernah dari parameter URL (aman dari path traversal seperti `../../.env`).
- File dikirim dengan header `Content-Disposition: attachment` dan `X-Content-Type-Options: nosniff`, sehingga PDF tidak dijalankan di browser sebagai halaman.
- Status lamaran hanya menerima nilai yang diizinkan (`reviewed`, `accepted`, `rejected`), dengan token CSRF.

### 4.7 Panel Admin
**Fitur:** verifikasi perusahaan, menonaktifkan user, menghapus lowongan, dan melihat log aktivitas.

**✅ Pengamanan:**
- Semua route admin dilindungi pemeriksaan PHP `require_login()` + `require_role('admin')`. Pengecekan dilakukan **di server**, bukan hanya dengan menyembunyikan tombol.
- Akun admin hanya dibuat lewat skrip CLI/database terproteksi, tidak bisa lewat form registrasi.
- Admin tidak bisa menonaktifkan akunnya sendiri.
- Aksi berbahaya (hapus, nonaktifkan) memakai konfirmasi + token CSRF.

### 4.8 Logging Aktivitas
**Fitur:** mencatat kejadian penting ke tabel `activity_logs`.

**✅ Pengamanan:**
- Yang dicatat: login berhasil/gagal, ganti password, aksi admin, dan akses ditolak (403), lengkap dengan user, waktu, dan IP.
- **Password, token, dan isi CV tidak pernah dicatat.**
- Log hanya bisa dibaca admin dan tidak bisa diubah dari aplikasi.

### 4.9 Pengamanan Umum (berlaku untuk seluruh aplikasi)
- **HTTPS** dipaksa (aktifkan TLS saat deployment; pengujian HTTP lokal didokumentasikan sebagai pengecualian).
- **Security headers** dipasang lewat konfigurasi Apache atau helper response global:
  - `Content-Security-otorisasi: default-src 'self'` (tanpa `unsafe-inline`, jadi JavaScript ditaruh di file `.js` terpisah)
  - `X-Frame-Options: DENY` (anti clickjacking)
  - `X-Content-Type-Options: nosniff`
  - `Referrer-otorisasi: strict-origin-when-cross-origin`
- `display_errors=Off` pada deployment agar detail error dan stack trace tidak bocor. Halaman error 403/404/500 dibuat custom.
- File `.env` (password database, konfigurasi rahasia) masuk `.gitignore` dan tidak pernah di-commit.
- User database aplikasi hanya punya hak `SELECT, INSERT, UPDATE, DELETE` (bukan root).
- Dependency dicek dengan pemeriksaan dependency yang benar-benar dipakai; jika tanpa Composer, dokumentasikan tidak ada dependency Composer sebelum code review.

## 5. Struktur Database (gambaran)

```
users
  id, name, email (unique), password (hash), role [applicant|company|admin],
  is_active, email_verified_at, created_at, updated_at

applicant_profiles
  id, user_id (FK users), phone, address, education, skills,
  photo_path, cv_path

companies
  id, user_id (FK users), company_name, description, website,
  logo_path, is_verified, created_at

job_posts
  id, company_id (FK companies), title, description, location, category,
  salary_min, salary_max, status [open|closed], deadline, created_at

applications
  id, job_post_id (FK job_posts), applicant_id (FK users),
  cv_path, cover_letter, status [submitted|reviewed|accepted|rejected],
  created_at
  UNIQUE (job_post_id, applicant_id)

saved_jobs
  id, user_id (FK users), job_post_id (FK job_posts)
  UNIQUE (user_id, job_post_id)

activity_logs
  id, user_id, action, description, ip_address, created_at
```

Relasi singkat:
- 1 `users` (company) → 1 `companies` → banyak `job_posts`
- 1 `job_posts` → banyak `applications` ← 1 `users` (pelamar)

## 6. Daftar Halaman

| Halaman | URL contoh | Akses |
|---|---|---|
| Beranda + daftar lowongan | `/` , `/jobs` | Semua |
| Detail lowongan | `/jobs/{id}` | Semua |
| Registrasi / Login / Lupa password | `/register`, `/login`, `/forgot-password` | Tamu |
| Dashboard pelamar | `/applicant/dashboard` | Pelamar |
| Edit profil & upload CV | `/applicant/profile` | Pelamar |
| Lamaran saya | `/applicant/applications` | Pelamar |
| Lowongan tersimpan | `/applicant/saved` | Pelamar |
| Dashboard perusahaan | `/company/dashboard` | Perusahaan |
| Profil perusahaan | `/company/profile` | Perusahaan |
| Kelola lowongan (CRUD) | `/company/jobs` | Perusahaan terverifikasi |
| Daftar pelamar per lowongan | `/company/jobs/{id}/applicants` | Perusahaan pemilik lowongan |
| Dashboard admin | `/admin` | Admin |
| Verifikasi perusahaan | `/admin/companies` | Admin |
| Kelola user & lowongan | `/admin/users`, `/admin/jobs` | Admin |
| Log aktivitas | `/admin/logs` | Admin |

## 7. Ringkasan Pengamanan Aplikasi

Tabel berikut merangkum ancaman yang diantisipasi dan pengamanan yang diterapkan untuk mencegahnya.

| Ancaman | Fitur terkait | Pengamanan yang diterapkan | Bagian |
|---|---|---|---|
| SQL Injection | Pencarian, login, semua query | PDO prepared statements, whitelist kolom sort | 4.4 |
| XSS (stored & reflected) | Deskripsi lowongan, surat lamaran, profil, hasil pencarian | Escape output, server-side validation dan escaping output, CSP tanpa inline | 4.3, 4.4, 4.5, 4.9 |
| XSS lewat link & SVG | Website perusahaan, logo/foto | Hanya `http(s)://`, SVG ditolak | 4.2, 4.3 |
| CSRF | Semua form & aksi ubah/hapus | Token CSRF, SameSite cookie, tidak ada aksi lewat GET | 4.3, 4.6, 4.7 |
| Broken Access Control / IDOR | Edit lowongan, lihat pelamar, download CV, lamaran, panel admin | ownership check + role guard pada PHP, cek kepemilikan di server | 4.3, 4.5, 4.6, 4.7 |
| Privilege Escalation | Registrasi | Role di-whitelist di server, admin lewat CLI/database | 4.1 |
| Unrestricted File Upload | CV, foto, logo | Validasi MIME & ukuran, nama acak, storage private, re-encode | 4.2 |
| Path Traversal | Download CV | Path dari database, nama file acak | 4.6 |
| Brute Force | Login, registrasi, lupa password, lamar | Rate limiting | 4.1, 4.5 |
| User Enumeration | Login, lupa password | Pesan yang sama untuk semua kasus | 4.1 |
| Session Fixation / Hijacking | Login, logout | Regenerate session, cookie `HttpOnly`/`Secure`, timeout | 4.1 |
| Open Redirect | Redirect setelah login | Hanya URL internal | 4.1 |
| Business Logic Bypass | Melamar lowongan tertutup, lamaran ganda | Cek status di server, unique constraint | 4.5 |
| Clickjacking | Seluruh halaman | `X-Frame-Options: DENY` | 4.9 |
| Sensitive Data Exposure | CV, password, error page | Hash, storage private, `display_errors=Off` pada deployment, HTTPS | 4.1, 4.2, 4.9 |
| Security Misconfiguration | Header, `.env`, user database | Security headers, file konfigurasi rahasia di-gitignore, user DB terbatas | 4.9 |
| Insufficient Logging | Login, aksi admin | `activity_logs` tanpa data sensitif | 4.8 |

---

## 8. Pembagian Kerja Tim (5 Orang)

**Model: hybrid 2 frontend + 3 backend/integrasi.** Setiap halaman memiliki **PIC UI** dan **PIC server**. Semua orang tetap bertanggung jawab atas secure coding pada perubahan kodenya; QA lintas modul bukan pengganti unit test masing-masing.

| Anggota | Fokus | Deliverable wajib | Pengujian keamanan yang dimiliki |
|---|---|---|---|
| **Orang 1** | **Frontend A — Public + Auth** | Homepage, list/detail lowongan, search/filter UI, register/login/forgot password UI, layout publik, responsive | Escaping hasil search, tidak membuat aksi mutasi via GET, form CSRF terpasang |
| **Orang 2** | **Frontend B — Dashboard** | UI dashboard pelamar, perusahaan, admin; form profil, upload CV, CRUD job, daftar pelamar, tampilan status | Escape semua data dinamis, file form dan state UI aman, tidak mengandalkan hidden button untuk authorization |
| **Orang 3** | **Backend A — Auth & Foundation** | `schema.sql` awal bersama, koneksi PDO, auth, role guard, CSRF helper, session/cookie, password reset, verifikasi email jika diwajibkan, shared validation helper | Brute force, session fixation, role escalation, user enumeration, CSRF |
| **Orang 4** | **Backend B — Lowongan & Pelamar** | Query daftar/detail/filter lowongan, company job CRUD, pelamar apply/saved jobs, pengecekan ownership dan duplikasi | SQLi, IDOR job/application, business logic bypass, stored/reflected XSS |
| **Orang 5** | **Backend C — Admin, Files & QA Integration** | Profil perusahaan + verifikasi admin, moderasi user/job, activity logs, upload CV/foto/logo + authenticated download, integrasi & test plan | Unrestricted upload, path traversal, IDOR unduh CV, admin authorization, error handling |

### 8.1 Kontrak Kolaborasi & Area Ownership

- **Orang 3** memimpin desain database awal, tapi **semua anggota backend** mereview tabel dan constraint di minggu 1. Perubahan skema setelah itu lewat PR dan wajib diumumkan.
- **Orang 1 & 2** membuat desain dan PHP view; **Orang 3–5** menyediakan handler PHP dan query. API JSON tidak wajib: form POST → handler PHP → redirect juga valid.
- **Orang 4** adalah PIC alur pelamar dan lowongan; **Orang 5** adalah PIC keamanan file dan admin. Mereka harus menyepakati nama field `applications.cv_path`, kondisi akses download, dan relasi `job_posts.company_id` sejak minggu 1.
- **Orang 5** mengelola checklist integrasi serta regression test, tetapi anggota lain wajib menyediakan test case pada PR masing-masing.
- Tambahan pekerjaan verifikasi email / lupa password menggunakan email sungguhan bergantung dukungan SMTP dan aturan tugas. Jika tidak tersedia, jangan berpura-pura mengirim email: gunakan simulasi lokal yang didokumentasikan atau konfirmasi scope ke dosen.

### 8.2 Kontrak Antarmuka Awal

| Halaman / Aksi | PIC frontend | PIC backend | Tabel utama |
|---|---|---|---|
| Login, register, logout, reset | 1 | 3 | users, reset_tokens (jika dipakai) |
| Browse, detail, search/filter job | 1 | 4 | job_posts, companies |
| Profil pelamar + upload CV | 2 | 5 (file) + 4 (profil) | applicant_profiles |
| Apply, saved jobs, status saya | 2 | 4 | applications, saved_jobs |
| Profil perusahaan & verifikasi | 2 | 5 | companies |
| CRUD lowongan perusahaan | 2 | 4 | job_posts |
| Lihat pelamar / download CV / ubah status | 2 | 4 (status/list) + 5 (download) | applications, job_posts |
| Admin user/job moderation & logs | 2 | 5 | users, job_posts, activity_logs |

## 9. Timeline Pengerjaan — Baseline 5 Minggu

> **Asumsi perencanaan:** 5 minggu dari kickoff; bukan tanggal deadline resmi. Sesuaikan jika dosen memberi jadwal lain. Targetnya fitur end-to-end siap di **akhir minggu 4**, sehingga minggu 5 digunakan untuk hardening, pengujian, dan dokumentasi.

| Minggu | Orang 1 — FE Public | Orang 2 — FE Dashboard | Orang 3 — BE Auth | Orang 4 — BE Jobs | Orang 5 — BE Admin/File/QA | Milestone |
|---|---|---|---|---|---|---|
| **M1 — Design & Foundation** | Wireframe beranda/auth; shared layout | Wireframe 3 dashboard; komponen form | Schema, PDO, auth/CSRF/session contract | Desain query dan alur CRUD/apply | Desain upload/download, audit test cases | **ERD + schema v1 + routes/field contract disetujui** |
| **M2 — Core Features I** | Register/login & public jobs UI | Applicant profile + company profile UI | Register/login/logout, role check, password hashing | List/detail/search jobs, CRUD job dasar | Upload CV/foto/logo, company profile, verifikasi admin dasar | **Login + job listing + upload dasar jalan** |
| **M3 — Core Features II** | Filter/sort/pagination + responsive | Apply/saved jobs, company applicants UI, admin UI | Reset/password change, rate limit, session hardening | Apply, saved jobs, status, ownership checks | Download CV aman, admin moderation, activity logs | **Alur pelamar–perusahaan–admin tersambung** |
| **M4 — Integration** | Hook UI ke handler, error/empty states | Hook dashboard, validasi feedback | Fix auth flows, shared guard + CSRF seluruh POST | Query optimization, validasi business rules | Integrasi antar modul, uji authorization & file flow | **Feature freeze: demo end-to-end** |
| **M5 — Security & Delivery** | Perbaikan UI / aksesibilitas | Perbaikan UI / validasi | Uji auth/session/CSRF + fix | Uji SQLi/XSS/IDOR + fix | Uji upload/admin/headers, regression, koordinasi laporan | **Test evidence + README + demo final** |

### 9.1 Dependency yang Harus Didahulukan

1. **Schema, route, dan nama field** (M1) → dibutuhkan seluruh backend dan frontend.
2. **Auth/session/role guard minimal** (awal M2) → dipakai semua halaman terproteksi.
3. **CRUD job + upload CV** (M2) → prasyarat alur apply end-to-end (M3).
4. **Apply + company applicant listing** (M3) → prasyarat demo dan pengujian IDOR (M4–M5).
5. **Integration freeze** (akhir M4) → hanya bug fix dan hardening pada M5.

### 9.2 Aturan Git dan Definition of Done

- Branch: `feature/fe-public`, `feature/fe-dashboard`, `feature/auth`, `feature/jobs`, `feature/admin-files`; merge ke `main` hanya melalui PR yang direview anggota lain.
- PR harus menyertakan: ringkasan, screenshot bila UI, migrasi/SQL jika ada perubahan skema, cara menjalankan, dan minimal satu positive test serta satu negative/security test.
- Fitur dianggap **done** jika berhasil dijalankan dengan database bersama, seluruh endpoint memeriksa role/ownership, input server divalidasi, output di-escape, mutasi memakai CSRF, dan tidak ada error yang mengekspos kredensial.
- Gunakan akun uji: pelamar A/B, perusahaan A/B, admin. Uji kasus silang A mencoba mengakses aset B.

### 9.3 Checklist Security Demo

| Skenario | Hasil yang diharapkan | PIC |
|---|---|---|
| Login berkali-kali dengan password salah | Batas percobaan berlaku | 3 |
| Ubah role dari request registrasi menjadi admin | Ditolak / diabaikan server | 3 |
| POST tanpa token CSRF | Ditolak | 3 + semua pemilik endpoint |
| Input HTML/script di search atau cover letter | Ditampilkan sebagai teks, bukan dieksekusi | 1, 2, 4 |
| SQL injection pada keyword/filter | Tidak mengubah struktur query | 4 |
| Perusahaan A mengedit job perusahaan B | 403 / 404 tanpa perubahan data | 4 |
| Pelamar A membuka lamaran pelamar B | 403 / 404 tanpa bocor data | 4 |
| Upload file non-PDF sebagai CV | Ditolak walau ekstensi disamarkan | 5 |
| Akses CV via URL langsung / ganti ID download | Ditolak tanpa otorisasi | 5 |
| User biasa membuka `/admin/` | Ditolak di server | 5 |

## 10. Scope Control (Jika Deadline Lebih Pendek)

**Prioritas P0 (wajib untuk MVP):** login/register/logout, role guard, job browsing, CRUD job oleh perusahaan terverifikasi, profil + upload CV, apply, daftar pelamar, admin verifikasi, security dasar (PDO, output encoding, CSRF, upload aman, ownership).

**P1 (jika waktu cukup):** saved jobs, filter gaji lanjutan, activity logs lebih detail, ganti password, status lamaran lengkap.

**P2 (opsional, perlu konfirmasi requirement):** verifikasi email via SMTP, forgot password via email, desain UI ekstra, fitur pencarian lebih kompleks. Jika ada fitur P1/P2 yang diwajibkan rubrik, naikkan ke P0.

**Keputusan teknis:** jangan memperkenalkan Laravel/Blade secara diam-diam. Jika dosen mengizinkan library frontend, Bootstrap opsional; jika tidak, gunakan CSS native. Semua keamanan harus tetap ditegakkan di PHP backend.
