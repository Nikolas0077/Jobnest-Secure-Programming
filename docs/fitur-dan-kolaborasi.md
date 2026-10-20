# Peta fitur JobNest dan panduan kolaborasi

## Aktor dan status saat ini

| Aktor | Cara masuk | Halaman yang sudah ada | Berikutnya |
| --- | --- | --- | --- |
| Guest | Tanpa login | Beranda, cari/filter/detail lowongan, login, daftar, info lupa password | Tidak ada |
| Pelamar (`applicant`) | Daftar publik dengan role `applicant`, lalu login | Form login/daftar dan navbar sesi | Dashboard, profil, upload CV, lamar, riwayat lamaran |
| Perusahaan (`company`) | Daftar publik dengan role `company`, lalu login | Form login/daftar dan navbar sesi | Dashboard, profil/verifikasi, kelola lowongan, kelola pelamar |
| Admin (`admin`) | Akun dibuat oleh pengelola, **bukan** dari form daftar publik | Belum ada halaman | Verifikasi perusahaan dan moderasi minimum |

**Penting:** saat ini belum ada implementasi API backend maupun halaman `public/applicant/`, `public/company/`, atau `public/admin/`. Link dashboard di navbar sudah mengarah ke lokasi tersebut sebagai target integrasi, tetapi akan 404 sampai halamannya dibuat. Login frontend baru bisa bekerja jika backend mengikuti [kontrak API](api-contract.md).

## Fitur yang sudah dikerjakan di repo ini

- Beranda: form pencarian dan preview lowongan terbaru dari `GET /api/jobs`.
- Daftar lowongan: filter kata kunci, lokasi, kategori, rentang gaji, urutan, dan pagination dari `GET /api/jobs`.
- Detail lowongan: `GET /api/jobs/{id}`.
- Form login, registrasi pelamar/perusahaan, dan logout: POST HTML ke `public/auth-action.php`, lalu PHP memanggil API auth. Form menggunakan token sesi UI; request tulis ke API menggunakan `X-CSRF-Token` dari `GET /api/auth/csrf`.
- State kosong, loading filter, error, dan responsif. Semua teks dari input dan API di-escape sebelum dicetak sebagai HTML.
- Lupa password baru halaman informasi. Endpoint reset dan layanan email ditandai P1 di kontrak.

## Halaman dan urutan kerja berikutnya

1. **Pelamar:** `public/applicant/dashboard.php` menampilkan ringkasan profil/CV dan lamaran. Lanjutkan form profil, upload CV PDF (maksimum 2 MB), aksi lamar dari detail lowongan, dan riwayat lamaran. Endpoint: `/api/applicant/profile`, `/api/applicant/cv`, `/api/jobs/{id}/applications`, `/api/applicant/applications`.
2. **Perusahaan:** `public/company/dashboard.php` menampilkan status verifikasi, lowongan milik perusahaan, dan lamaran masuk. Lanjutkan form profil, buat/ubah/tutup lowongan, lihat CV pelamar yang melamar, dan ubah status lamaran. Endpoint: `/api/company/profile`, `/api/company/jobs`, `/api/company/jobs/{id}/applications`, `/api/company/applications/{id}/status`.
3. **Admin minimum:** halaman antrean verifikasi perusahaan dan aksi setujui/tolak. Ini diperlukan jika aturan produk tetap mensyaratkan perusahaan terverifikasi sebelum lowongannya tampil. Moderasi user/job dan audit log bisa menyusul. Akun admin harus dibuat lewat seed/CLI backend; registrasi publik tetap hanya menerima `applicant` atau `company`.

Untuk tiap halaman privat, cek sesi dan role di PHP untuk tampilan/redirect, lalu **cek ulang role dan ownership di backend** pada setiap endpoint. Jangan percaya role, `company_id`, atau `user_id` dari form. Backend harus tetap menolak 401/403 meski URL API dipanggil langsung.

## Titik sambung kode

| File/fungsi | Dipakai untuk | Contoh |
| --- | --- | --- |
| `app/includes/api.php` → `api_request()` | GET dan request JSON ke backend; meneruskan cookie sesi | `api_request('GET', '/applicant/profile')` |
| `app/includes/api.php` → `api_post_with_csrf()` | POST JSON dengan token CSRF backend | `api_post_with_csrf('/jobs/12/applications', ['cover_letter' => $text])` |
| `app/includes/layout.php` → `get_current_user_data()` | Baca user dari helper backend atau `GET /auth/me`; `null` berarti guest | `$user = get_current_user_data();` |
| `app/includes/layout.php` → `render_header()` / `render_footer()` | Bungkus HTML halaman dengan navbar/footer | `render_header(['title' => 'Dashboard']); ... render_footer();` |
| `app/includes/layout.php` → `e()` | Escape data sebelum masuk teks/atribut HTML | `<?= e($user['name']) ?>` |
| `app/includes/layout.php` → `public_form_token()` | Token untuk setiap form POST HTML ke PHP | `<input type="hidden" name="form_token" value="<?= e(public_form_token()) ?>">` |
| `app/includes/job-view.php` → `valid_job_summary()` / `render_job_card()` | Validasi dan render kartu lowongan dari API | `if (valid_job_summary($job)) render_job_card($job);` |
| `public/auth-action.php` | Contoh action PHP untuk POST form, validasi, panggil API, flash, lalu redirect 303 | `action="/auth-action.php?intent=login"` |

`api_request()` hanya menerima body array untuk JSON. **Upload CV perlu fungsi multipart terpisah** yang mengirim file tanpa mengubahnya menjadi JSON. PATCH/DELETE butuh token CSRF backend juga; pola tokennya ada di `api_post_with_csrf()`, tetapi helper khusus untuk kedua metode itu belum dibuat.

## Cara jalan dan uji

Ikuti [README](../README.md) untuk build Tailwind, set `JOBNEST_API_BASE_URL`, dan menjalankan server. Uji tanpa backend asli:

```powershell
php tests/public-ui.php
node tests/http-integration.mjs
```

Mock API hanya fixture untuk memastikan alur HTML → PHP → API, sesi, dan escaping. Sebelum mengerjakan modul baru, sepakati payload dan respons di [kontrak API](api-contract.md), lalu buat tes 401, 403, 422, CSRF, dan akses antar pemilik data untuk endpoint privat.

## Opsional: alur dua coding agent

`jobnest_orchestrator.py` meminta Codex membuat brief dan mereview hasil, sementara Antigravity/Gemini mengerjakan perubahan. Jalankan hanya pada feature branch yang bersih setelah CLI `codex` dan `agy` terpasang:

```powershell
py -3 jobnest_orchestrator.py --task "Buat dashboard pelamar sesuai docs/api-contract.md"
```

Script ini tidak otomatis commit, push, atau merge. Ia akan menolak repo dengan perubahan lain yang belum disimpan; commit atau stash pekerjaan yang sedang berjalan sebelum memakainya.
