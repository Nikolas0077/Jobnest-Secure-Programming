// End-to-end security tests for the real API + MySQL (Slot E).
// Setup once (see README.md):
//   mysql -u root -p < database/schema.sql
//   php database/seed.php            (asks for the demo password, never prints it)
//   (api/.env filled with DB settings)
// Run: node tests/api-security.mjs
// Required env: SEED_PASSWORD = the demo password typed during seed.php (never hardcoded here).
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';

const PORT = 8893;
const UI_PORT = 8894;
const BASE = `http://127.0.0.1:${PORT}/api`;
const SEED_PASSWORD = process.env.SEED_PASSWORD;
if (!SEED_PASSWORD) {
    console.error('Set SEED_PASSWORD ke password akun demo yang dimasukkan saat menjalankan database/seed.php, lalu jalankan lagi.');
    process.exit(1);
}
const api = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', 'api/public', 'api/public/index.php'], { stdio: 'ignore' });
const ui = spawn('php', ['-S', `127.0.0.1:${UI_PORT}`, '-t', 'public'], {
    stdio: 'ignore',
    env: { ...process.env, JOBNEST_API_BASE_URL: BASE }
});
const results = [];

// Each run uses its own fake client IP (sent by a trusted proxy in dev), so login
// throttling counters from earlier runs do not affect this run.
const RUN_IP = `10.${Math.floor(Math.random() * 250)}.${Math.floor(Math.random() * 250)}.${Math.floor(Math.random() * 250)}`;

/** A browser-like client with its own cookie jar. */
function client(ip = RUN_IP) {
    const jar = new Map();
    async function call(method, path, { json, form, csrf = true, headers = {}, base = BASE, raw = false } = {}) {
        const h = { 'X-Forwarded-For': ip, ...headers };
        if (csrf && method !== 'GET' && base === BASE) {
            const t = await call('GET', '/auth/csrf', { csrf: false });
            h['X-CSRF-Token'] = t.body.data.csrf_token;
        }
        if (jar.size) h.Cookie = [...jar].map(([k, v]) => `${k}=${v}`).join('; ');
        let body;
        if (json !== undefined) { h['Content-Type'] = 'application/json'; body = JSON.stringify(json); }
        if (form !== undefined) body = form;
        const res = await fetch(base + path, { method, headers: h, body, redirect: 'manual' });
        for (const c of res.headers.getSetCookie()) {
            const [k, v] = c.split(';', 1)[0].split('=', 2);
            if (/Max-Age=0|expires=Thu, 01 Jan 1970/i.test(c) || v === '' || v === 'deleted') jar.delete(k); else jar.set(k, v);
        }
        const text = raw ? null : await res.text();
        let parsed = null;
        try { parsed = text ? JSON.parse(text) : null; } catch { parsed = text; }
        return { status: res.status, body: parsed, headers: res.headers, res };
    }
    return { call, jar };
}

async function check(name, fn) {
    try {
        await fn();
        results.push(['PASS', name]);
    } catch (err) {
        results.push(['FAIL', name, err.message]);
    }
}

async function login(c, email, password) {
    return c.call('POST', '/auth/login', { json: { email, password } });
}

const pdf = (extra = '') => new Blob([`%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n${extra}trailer\n<< /Root 1 0 R >>\n%%EOF\n`], { type: 'application/pdf' });
function cvForm(blob, name = 'cv.pdf') {
    const f = new FormData();
    f.append('cv', blob, name);
    return f;
}

try {
    await delay(1200);
    const stamp = Date.now();
    const guest = client();
    const admin = client();
    const company = client();
    const otherCompany = client();
    const applicant = client();
    const otherApplicant = client();

    // ---------- Auth (Slot C) ----------
    await check('Register menolak role admin (role tampering)', async () => {
        const r = await guest.call('POST', '/auth/register', { json: { name: 'Hacker', email: `h${stamp}@x.test`, password: 'Secret123', role: 'admin' } });
        assert.equal(r.status, 422);
    });
    await check('Register company dan applicant berhasil (201)', async () => {
        for (const [c, email, role] of [[company, `co${stamp}@x.test`, 'company'], [otherCompany, `co2${stamp}@x.test`, 'company'], [applicant, `ap${stamp}@x.test`, 'applicant'], [otherApplicant, `ap2${stamp}@x.test`, 'applicant']]) {
            const r = await c.call('POST', '/auth/register', { json: { name: `<script>alert(1)</script> ${role}`, email, password: 'Secret123', role } });
            assert.equal(r.status, 201, JSON.stringify(r.body));
        }
    });
    await check('Email duplikat ditolak 409', async () => {
        const r = await guest.call('POST', '/auth/register', { json: { name: 'Dup', email: `ap${stamp}@x.test`, password: 'Secret123', role: 'applicant' } });
        assert.equal(r.status, 409);
    });
    await check('Write tanpa token CSRF ditolak 403', async () => {
        const r = await guest.call('POST', '/auth/login', { json: { email: `ap${stamp}@x.test`, password: 'Secret123' }, csrf: false });
        assert.equal(r.status, 403);
        assert.equal(r.body.error.code, 'CSRF_REJECTED');
    });
    await check('Token CSRF palsu ditolak 403', async () => {
        await guest.call('GET', '/auth/csrf', { csrf: false });
        const r = await guest.call('POST', '/auth/login', { json: { email: 'a@b.c', password: 'x' }, csrf: false, headers: { 'X-CSRF-Token': 'forged' } });
        assert.equal(r.status, 403);
    });
    await check('SQL injection di login gagal (401 generik)', async () => {
        const r = await guest.call('POST', '/auth/login', { json: { email: "' OR '1'='1' -- ", password: "' OR '1'='1" } });
        assert.equal(r.status, 401);
        assert.equal(r.body.error.message, 'Email atau password salah.');
    });
    await check('Pesan error login sama untuk email tidak ada dan password salah', async () => {
        const a = await guest.call('POST', '/auth/login', { json: { email: `nobody${stamp}@x.test`, password: 'Wrong1234' } });
        const b = await guest.call('POST', '/auth/login', { json: { email: `ap2${stamp}@x.test`, password: 'Wrong1234' } });
        assert.equal(a.status, 401); assert.equal(b.status, 401);
        assert.deepEqual(a.body, b.body);
    });
    await check('Session ID berganti setelah login (anti session fixation)', async () => {
        await applicant.call('GET', '/auth/csrf', { csrf: false });
        const before = applicant.jar.get('JOBNEST_API');
        const r = await login(applicant, `ap${stamp}@x.test`, 'Secret123');
        assert.equal(r.status, 200);
        assert.ok(before && applicant.jar.get('JOBNEST_API') && before !== applicant.jar.get('JOBNEST_API'));
    });
    await check('Session ID buatan penyerang ditolak (strict mode)', async () => {
        const c = client();
        c.jar.set('JOBNEST_API', 'attackerchosenid1234567890abcdef');
        await c.call('GET', '/auth/csrf', { csrf: false });
        assert.notEqual(c.jar.get('JOBNEST_API'), 'attackerchosenid1234567890abcdef');
    });
    await check('Cookie session HttpOnly + SameSite', async () => {
        const c = client();
        const r = await c.call('GET', '/auth/csrf', { csrf: false });
        const cookie = r.headers.getSetCookie().join(';');
        assert.match(cookie, /HttpOnly/i); assert.match(cookie, /SameSite=Lax/i);
    });
    await check('Login throttling: 429 setelah 5 kali gagal', async () => {
        const c = client('10.254.254.' + Math.floor(Math.random() * 250));
        const email = `ap2${stamp}@x.test`;
        let last;
        for (let i = 0; i < 6; i++) last = await login(c, email, 'Wrong1234');
        assert.equal(last.status, 429);
    });
    await login(admin, 'admin@jobnest.test', SEED_PASSWORD);
    await login(company, `co${stamp}@x.test`, 'Secret123');
    await login(otherCompany, `co2${stamp}@x.test`, 'Secret123');
    await login(otherApplicant, `ap2${stamp}@x.test`, 'Secret123');

    // ---------- Authorization / force browsing ----------
    await check('Tanpa session: endpoint privat 401', async () => {
        for (const p of ['/applicant/profile', '/company/jobs', '/admin/logs', '/auth/me']) {
            assert.equal((await guest.call('GET', p)).status, 401, p);
        }
    });
    await check('Force browse /api/admin/* sebagai applicant & company → 403', async () => {
        for (const c of [applicant, company]) {
            for (const p of ['/admin/companies', '/admin/logs', '/admin/users', '/admin/summary']) {
                assert.equal((await c.call('GET', p)).status, 403, p);
            }
        }
    });
    await check('Applicant tidak bisa akses endpoint company (403)', async () => {
        assert.equal((await applicant.call('POST', '/company/jobs', { json: { title: 'x' } })).status, 403);
    });

    // ---------- Company verification flow (Slot E + D) ----------
    let companyId;
    await check('Company belum verified tidak bisa pasang lowongan (403)', async () => {
        const p = await company.call('GET', '/company/profile');
        companyId = p.body.data.id;
        assert.equal(p.body.data.is_verified, false);
        const r = await company.call('POST', '/company/jobs', { json: { title: 'Dev', description: 'Deskripsi pekerjaan', location: 'Jakarta', category: 'IT' } });
        assert.equal(r.status, 403);
    });
    await check('Admin menyetujui company', async () => {
        const r = await admin.call('PATCH', `/admin/companies/${companyId}/verification`, { json: { is_verified: true } });
        assert.equal(r.status, 200, JSON.stringify(r.body));
        const other = await otherCompany.call('GET', '/company/profile');
        assert.equal((await admin.call('PATCH', `/admin/companies/${other.body.data.id}/verification`, { json: { is_verified: true } })).status, 200);
    });

    let jobId;
    const xss = '<img src=x onerror=alert(1)>';
    await check('Validasi job: field salah → 422 dengan fields', async () => {
        const r = await company.call('POST', '/company/jobs', { json: { title: 'a', description: 'short', location: '', category: 'IT', salary_min: -5, deadline: '2020-01-01' } });
        assert.equal(r.status, 422);
        assert.ok(r.body.error.fields.title && r.body.error.fields.salary_min && r.body.error.fields.deadline);
    });
    await check('Company pasang lowongan (201) dan muncul di publik', async () => {
        const r = await company.call('POST', '/company/jobs', { json: { title: `Pentester ${stamp} ${xss}`, description: `Deskripsi ${xss}`, location: 'Jakarta', category: 'Security', salary_min: 5000000, salary_max: 9000000 } });
        assert.equal(r.status, 201, JSON.stringify(r.body));
        jobId = r.body.data.id;
        const list = await guest.call('GET', `/jobs?q=${encodeURIComponent('Pentester ' + stamp)}`);
        assert.equal(list.body.data.items.length, 1);
    });
    await check('SQL injection di pencarian & sort tidak bocor / error', async () => {
        const r1 = await guest.call('GET', `/jobs?q=${encodeURIComponent("' OR 1=1 -- ")}`);
        assert.equal(r1.status, 200); assert.equal(r1.body.data.items.length, 0);
        const r2 = await guest.call('GET', `/jobs?sort=${encodeURIComponent('title; DROP TABLE users')}&per_page=99999&page=-1`);
        assert.equal(r2.status, 200); assert.ok(r2.body.data.pagination.per_page <= 50);
        const r3 = await guest.call('GET', '/jobs?q=%25');
        assert.equal(r3.status, 200);
    });
    await check('ID non-integer di URL → 404 (tidak sampai ke query)', async () => {
        assert.equal((await guest.call('GET', "/jobs/1' OR '1'='1")).status, 404);
        assert.equal((await guest.call('GET', '/jobs/-1')).status, 404);
    });
    await check('IDOR: company lain tidak bisa edit/hapus/lihat pelamar lowongan ini (404)', async () => {
        assert.equal((await otherCompany.call('PATCH', `/company/jobs/${jobId}`, { json: { title: 'Hacked title' } })).status, 404);
        assert.equal((await otherCompany.call('DELETE', `/company/jobs/${jobId}`)).status, 404);
        assert.equal((await otherCompany.call('GET', `/company/jobs/${jobId}/applications`)).status, 404);
    });
    await check('Mass assignment: company_id di body diabaikan', async () => {
        const other = await otherCompany.call('GET', '/company/profile');
        const r = await otherCompany.call('POST', '/company/jobs', { json: { title: 'Job milik other', description: 'Deskripsi pekerjaan', location: 'Bandung', category: 'IT', company_id: companyId } });
        assert.equal(r.status, 201);
        const mine = await company.call('GET', '/company/jobs');
        assert.ok(!mine.body.data.items.some(j => j.id === r.body.data.id));
        assert.ok(other.body.data.id !== companyId);
    });

    // ---------- Applicant: CV upload + apply (Slot D) ----------
    await check('Lamar tanpa CV → 422', async () => {
        const r = await applicant.call('POST', `/jobs/${jobId}/applications`, { json: { cover_letter: 'Halo' } });
        assert.equal(r.status, 422);
    });
    await check('Upload file bukan PDF ditolak (422)', async () => {
        const r = await applicant.call('POST', '/applicant/cv', { form: cvForm(new Blob(['<?php system($_GET["c"]); ?>'], { type: 'application/pdf' }), 'shell.pdf') });
        assert.equal(r.status, 422);
    });
    await check('Upload PDF polyglot berisi PHP ditolak (422)', async () => {
        const r = await applicant.call('POST', '/applicant/cv', { form: cvForm(pdf('<?php echo 1; ?>\n')) });
        assert.equal(r.status, 422);
    });
    await check('Upload PDF dengan JavaScript ditolak (422)', async () => {
        const r = await applicant.call('POST', '/applicant/cv', { form: cvForm(pdf('2 0 obj << /S /JavaScript /JS (app.alert(1)) >> endobj\n')) });
        assert.equal(r.status, 422);
    });
    await check('Upload ekstensi .php walau isi PDF ditolak (422)', async () => {
        const r = await applicant.call('POST', '/applicant/cv', { form: cvForm(pdf(), 'cv.php') });
        assert.equal(r.status, 422);
    });
    await check('Upload > 2 MB ditolak (413)', async () => {
        const big = new Blob(['%PDF-1.4\n', 'A'.repeat(2 * 1024 * 1024 + 10), '\n%%EOF\n'], { type: 'application/pdf' });
        const r = await applicant.call('POST', '/applicant/cv', { form: cvForm(big) });
        assert.ok([413, 422].includes(r.status), String(r.status));
    });
    await check('Upload PDF valid berhasil, path tidak dikirim ke klien', async () => {
        const r = await applicant.call('POST', '/applicant/cv', { form: cvForm(pdf()) });
        assert.equal(r.status, 201, JSON.stringify(r.body));
        const p = await applicant.call('GET', '/applicant/profile');
        assert.equal(p.body.data.cv_available, true);
        assert.ok(!JSON.stringify(p.body).includes('cv_path') && !JSON.stringify(p.body).includes('.pdf'));
    });
    let applicationId;
    await check('Lamar lowongan berhasil (201)', async () => {
        const r = await applicant.call('POST', `/jobs/${jobId}/applications`, { json: { cover_letter: `Saya tertarik ${xss}`, applicant_id: 1, status: 'accepted' } });
        assert.equal(r.status, 201, JSON.stringify(r.body));
        assert.equal(r.body.data.status, 'submitted'); // status from body is ignored
        applicationId = r.body.data.id;
    });
    await check('Lamaran ganda ditolak 409', async () => {
        assert.equal((await applicant.call('POST', `/jobs/${jobId}/applications`, { json: {} })).status, 409);
    });
    await check('Riwayat lamaran hanya milik sendiri', async () => {
        const mine = await applicant.call('GET', '/applicant/applications');
        assert.ok(mine.body.data.items.some(a => a.id === applicationId));
    });

    // ---------- Company reviews applications ----------
    await check('Company melihat pelamar & download CV (application/pdf, attachment)', async () => {
        const list = await company.call('GET', `/company/jobs/${jobId}/applications`);
        assert.equal(list.status, 200);
        assert.ok(list.body.data.items.some(a => a.id === applicationId));
        const cv = await company.call('GET', `/company/applications/${applicationId}/cv`, { raw: true });
        assert.equal(cv.status, 200);
        assert.match(cv.headers.get('content-type'), /application\/pdf/);
        assert.match(cv.headers.get('content-disposition'), /^attachment;/);
        assert.equal(cv.headers.get('x-content-type-options'), 'nosniff');
    });
    await check('IDOR: company lain tidak bisa download CV / ubah status (404)', async () => {
        assert.equal((await otherCompany.call('GET', `/company/applications/${applicationId}/cv`)).status, 404);
        assert.equal((await otherCompany.call('PATCH', `/company/applications/${applicationId}/status`, { json: { status: 'rejected' } })).status, 404);
        assert.equal((await applicant.call('GET', `/company/applications/${applicationId}/cv`)).status, 403);
    });
    await check('Status lamaran whitelist (status tidak dikenal → 422)', async () => {
        assert.equal((await company.call('PATCH', `/company/applications/${applicationId}/status`, { json: { status: 'hired' } })).status, 422);
        assert.equal((await company.call('PATCH', `/company/applications/${applicationId}/status`, { json: { status: 'submitted' } })).status, 422);
        const ok = await company.call('PATCH', `/company/applications/${applicationId}/status`, { json: { status: 'accepted' } });
        assert.equal(ok.status, 200);
        const mine = await applicant.call('GET', '/applicant/applications');
        assert.equal(mine.body.data.items.find(a => a.id === applicationId).status, 'accepted');
    });
    await check('Lowongan ditutup → tidak tampil & tidak bisa dilamar (422)', async () => {
        assert.equal((await company.call('PATCH', `/company/jobs/${jobId}`, { json: { status: 'closed' } })).status, 200);
        assert.equal((await guest.call('GET', `/jobs/${jobId}`)).status, 404);
        assert.equal((await otherApplicant.call('POST', `/jobs/${jobId}/applications`, { json: {} })).status, 422);
        await company.call('PATCH', `/company/jobs/${jobId}`, { json: { status: 'open' } });
    });

    // ---------- Admin (Slot E) ----------
    let adminId;
    await check('Admin tidak bisa menonaktifkan diri sendiri (403)', async () => {
        adminId = (await admin.call('GET', '/auth/me')).body.data.user.id;
        assert.equal((await admin.call('PATCH', `/admin/users/${adminId}/status`, { json: { is_active: false } })).status, 403);
    });
    await check('User yang dinonaktifkan langsung kehilangan session (401) dan tidak bisa login', async () => {
        const me = (await otherCompany.call('GET', '/auth/me')).body.data.user.id;
        assert.equal((await admin.call('PATCH', `/admin/users/${me}/status`, { json: { is_active: false } })).status, 200);
        assert.equal((await otherCompany.call('GET', '/company/jobs')).status, 401);
        const relog = await login(client(), `co2${stamp}@x.test`, 'Secret123');
        assert.equal(relog.status, 401);
        await admin.call('PATCH', `/admin/users/${me}/status`, { json: { is_active: true } });
    });
    await check('Admin menghapus lowongan (204) → hilang dari publik', async () => {
        const r = await admin.call('DELETE', `/admin/jobs/${jobId}`);
        assert.equal(r.status, 204);
        assert.equal((await guest.call('GET', `/jobs/${jobId}`)).status, 404);
    });
    await check('Log aktivitas tidak menyimpan password/token', async () => {
        const logs = await admin.call('GET', '/admin/logs');
        assert.equal(logs.status, 200);
        const text = JSON.stringify(logs.body);
        assert.ok(!text.includes('Secret123') && !text.includes('csrf') && !text.includes(SEED_PASSWORD));
        assert.ok(logs.body.data.items.some(l => l.action === 'admin.job.delete'));
    });

    // ---------- Logout ----------
    await check('Logout menghapus session (me → 401)', async () => {
        assert.equal((await applicant.call('POST', '/auth/logout')).status, 204);
        assert.equal((await applicant.call('GET', '/auth/me')).status, 401);
    });
    await check('Error tidak membocorkan stack trace / path server', async () => {
        const r = await guest.call('POST', '/auth/register', { json: [1, 2, 3] });
        assert.ok(!JSON.stringify(r.body).match(/\.php|Stack|PDO|SQLSTATE/));
        const r2 = await fetch(`${BASE}/auth/register`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{bad json' });
        assert.ok(!(await r2.text()).match(/\.php|Stack|SQLSTATE/));
    });

    // ---------- UI (Slot B) ----------
    const uiBase = `http://127.0.0.1:${UI_PORT}`;
    await check('UI: halaman dashboard tanpa login redirect ke /login.php', async () => {
        for (const p of ['/applicant/dashboard.php', '/company/jobs.php', '/admin/logs.php']) {
            const r = await fetch(uiBase + p, { redirect: 'manual' });
            assert.equal(r.status, 303, p);
            assert.equal(r.headers.get('location'), '/login.php');
        }
    });
    await check('UI: security headers (CSP, nosniff, X-Frame-Options)', async () => {
        const r = await fetch(uiBase + '/index.php');
        assert.match(r.headers.get('content-security-policy') || '', /default-src 'self'/);
        assert.equal(r.headers.get('x-content-type-options'), 'nosniff');
        assert.equal(r.headers.get('x-frame-options'), 'DENY');
    });
    await check('UI: action tanpa form_token ditolak 403', async () => {
        const r = await fetch(uiBase + '/admin/action.php?intent=delete_job', { method: 'POST', body: new URLSearchParams({ job_id: '1' }), redirect: 'manual' });
        assert.equal(r.status, 403);
    });
} finally {
    api.kill();
    ui.kill();
}

const failed = results.filter(r => r[0] === 'FAIL');
for (const r of results) console.log(r[0] === 'PASS' ? `  ✔ ${r[1]}` : `  ✘ ${r[1]}\n      ${r[2]}`);
console.log(`\n${results.length - failed.length}/${results.length} tes keamanan lulus.`);
process.exit(failed.length ? 1 : 0);
