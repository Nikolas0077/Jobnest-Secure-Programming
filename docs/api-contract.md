# API contract

## 4.1 Konvensi global
Base URL: /api. Transport HTTPS untuk deployment; JSON UTF-8 untuk request/response kecuali multipart upload dan download file. Auth: PHP session cookie; tidak memakai JWT. Frontend wajib menyertakan credentials/session cookie secara same-origin. Semua POST/PATCH/DELETE mengirim token CSRF, misalnya header X-CSRF-Token dari endpoint GET /api/auth/csrf.
Success: {"success":true,"data":{...},"message":"OK"}. Error: {"success":false,"error":{"code":"VALIDATION_ERROR","message":"Invalid input","fields":{"email":"Invalid email"}}}. Status umum: 200 OK, 201 Created, 204 No Content, 400 Bad Request, 401 Unauthenticated, 403 Forbidden, 404 Not Found, 409 Conflict, 413 Payload Too Large, 422 Validation, 429 Rate Limited. Jangan kirim stack trace atau path server.

## 4.2 Authentication endpoints
- GET /api/auth/csrf — guest/user — 200 {data:{csrf_token:string}}.
- POST /api/auth/register — guest — {name,email,password,role:'applicant'|'company'} → 201 {data:{user:{id,name,email,role}}}; role admin ditolak.
- POST /api/auth/login — guest — {email,password} → 200 {data:{user:{id,name,email,role,is_active,email_verified}}}; error generik 401.
- GET /api/auth/me — session — 200 {data:{user:{...}}}; 401 jika belum login.
- POST /api/auth/logout — session + CSRF — 204.
- PATCH /api/auth/password — session + CSRF — {current_password,new_password} → 200.
- P1: POST /api/auth/forgot-password; POST /api/auth/reset-password; POST /api/auth/verify-email (bergantung layanan email).

## 4.3 Public jobs endpoints
- GET /api/jobs?q=&location=&category=&salary_min=&salary_max=&sort=newest|salary_asc|salary_desc&page=1&per_page=10 → 200 {data:{items:[{id,title,company_name,location,category,salary_min,salary_max,deadline}],pagination:{page,per_page,total,total_pages}}}. Server whitelist sort, page max, dan filter hanya open+verified.
- GET /api/jobs/{job_id} → 200 {data:{id,title,description,location,category,salary_min,salary_max,deadline,company:{id,name,website}}}. ID integer positif; 404 bila unavailable.

## 4.4 Applicant endpoints
- GET /api/applicant/profile → 200 {data:{name,phone,address,education,skills,cv_available,photo_url}}.
- PATCH /api/applicant/profile — CSRF — {phone,address,education,skills} → 200.
- POST /api/applicant/cv — multipart form-data field 'cv' (PDF max 2MB) + CSRF → 201 {data:{cv_available:true}}. Simpan private; jangan expose cv_path.
- POST /api/jobs/{job_id}/applications — CSRF — {cover_letter} memakai CV tersimpan → 201 {data:{id,job_id,status:'submitted'}}; 409 duplicate, 422 job closed/expired/no CV.
- GET /api/applicant/applications?page=1 → 200 {data:{items:[{id,job_id,job_title,status,created_at}],pagination:{...}}}.
- P1: GET/POST/DELETE /api/applicant/saved-jobs[/{job_id}].

## 4.5 Company endpoints
- GET /api/company/profile → 200 {data:{id,company_name,description,website,is_verified}}.
- PATCH /api/company/profile — CSRF — {company_name,description,website} → 200.
- GET /api/company/jobs → 200 {data:{items:[{id,title,status,deadline}]}}.
- POST /api/company/jobs — verified company + CSRF — {title,description,location,category,salary_min,salary_max,deadline} → 201 {data:{id,status:'open'}}.
- PATCH /api/company/jobs/{job_id} — owner + CSRF — editable fields/status → 200.
- DELETE /api/company/jobs/{job_id} — owner + CSRF → 204 (opsional soft delete).
- GET /api/company/jobs/{job_id}/applications — owner — 200 {data:{items:[{id,applicant_name,status,created_at}]}}.
- PATCH /api/company/applications/{application_id}/status — owner + CSRF — {status:'reviewed'|'accepted'|'rejected'} → 200.
- GET /api/company/applications/{application_id}/cv — owner — application/pdf attachment; no user-selected file path.

## 4.6 Admin endpoints
- GET /api/admin/companies?verified=pending|approved|rejected → 200 {data:{items:[...]}}.
- PATCH /api/admin/companies/{company_id}/verification — admin + CSRF — {is_verified:boolean} → 200.
- PATCH /api/admin/users/{user_id}/status — admin + CSRF — {is_active:boolean} → 200; 403 untuk self-disable.
- DELETE /api/admin/jobs/{job_id} — admin + CSRF → 204.
- GET /api/admin/logs?page=1 → 200 {data:{items:[{id,user_id,action,ip_address,created_at}],pagination:{...}}}; no secrets.

## 4.7 Data contract & ownership
Tabel: users(id,name,email,password_hash,role,is_active,email_verified_at), applicant_profiles(user_id,...,cv_path), companies(id,user_id,company_name,is_verified,...), job_posts(id,company_id,...,status,deadline), applications(id,job_post_id,applicant_id,cv_path,cover_letter,status,created_at), saved_jobs(user_id,job_post_id), activity_logs(...). UNIQUE applications(job_post_id,applicant_id), saved_jobs(user_id,job_post_id). Foreign key wajib. Role diambil dari session, bukan request body. Company ownership harus di-resolve melalui companies.user_id=session.user.id.

## 4.8 Example end-to-end contract
1) Admin PATCH verification company. 2) Company POST job. 3) Guest GET jobs dan detail. 4) Applicant upload CV lalu POST application. 5) Company GET applications lalu GET protected CV, PATCH status. 6) Applicant GET own applications. Pada tiap langkah, uji 401 (no session), 403 (wrong role/owner), 422 (invalid inputs), dan CSRF rejection untuk write requests.
