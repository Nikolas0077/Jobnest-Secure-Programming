# Public UI API contract (provisional)

This contract fixes the fields used by the public PHP views. The API handlers are a separate backend task. All responses are JSON; dates use `YYYY-MM-DD` and money uses integer IDR values. A failed request returns `{"error":"Safe message"}` with an appropriate 4xx/5xx status.

## Jobs

- `GET /api/jobs?q=&location=&category=&page=1` returns `{"jobs":[Job],"pagination":{"page":1,"total_pages":1}}`. Filters are optional; the server lists only open jobs from verified companies.
- `GET /api/jobs/{id}` returns `{"job":Job}` or 404.
- `Job`: `id` (integer), `title`, `company_name`, `location`, `category`, `description` (plain text), `salary_min` and `salary_max` (nullable integers), `deadline` (nullable date). Treat every string as untrusted when rendering.

## Auth

Form requests use `application/x-www-form-urlencoded` and include `csrf_token` from the PHP session. The backend must validate the token; the browser must not supply its own trusted role or user ID.

- `POST /api/auth/register`: `name`, `email`, `password`, `password_confirmation`, `role` (`applicant` or `company` only). Returns `{"redirect":"/login.php"}` on success.
- `POST /api/auth/login`: `email`, `password`, `csrf_token`. Returns `{"redirect":"/applicant/dashboard.php"}` or the authorized role's dashboard on success. Failure uses the same message for unknown email and wrong password.
- `POST /api/auth/logout`: `csrf_token`. Returns `{"redirect":"/"}`.

The frontend accepts only same-origin relative paths from `redirect`. The forgot-password page is UI only until a reset API and mail delivery flow are defined; it must not claim that an email was sent.
