import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';

const api = spawn('php', ['-S', '127.0.0.1:8891', 'tests/mock-api.php'], { stdio: 'ignore' });
const ui = spawn('php', ['-S', '127.0.0.1:8892', '-t', 'public'], {
    stdio: 'ignore',
    env: { ...process.env, JOBNEST_API_BASE_URL: 'http://127.0.0.1:8891/api' }
});
const cookies = new Map();
const base = 'http://127.0.0.1:8892';

async function request(path, options = {}) {
    const headers = { ...options.headers };
    if (cookies.size) headers.Cookie = [...cookies].map(([name, value]) => `${name}=${value}`).join('; ');
    const response = await fetch(base + path, { ...options, headers, redirect: 'manual' });
    for (const cookie of response.headers.getSetCookie()) {
        const [name, value] = cookie.split(';', 1)[0].split('=', 2);
        cookies.set(name, value);
    }
    return response;
}

try {
    await delay(1000);
    const loginHtml = await (await request('/login.php')).text();
    const token = loginHtml.match(/name="form_token" value="([^"]+)"/)?.[1];
    assert.ok(token, 'Login form token missing');
    const post = await request('/auth-action.php?intent=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ form_token: token, email: 'test@example.com', password: 'secret123' })
    });
    assert.equal(post.status, 303, await post.text());
    assert.equal(post.headers.get('location'), '/index.php');
    const homeHtml = await (await request('/index.php')).text();
    assert.match(homeHtml, /Test User/);
    const jobsHtml = await (await request('/jobs/index.php')).text();
    assert.match(jobsHtml, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
    assert.doesNotMatch(jobsHtml, /<script>alert\(1\)<\/script>/);
    const detailHtml = await (await request('/jobs/detail.php?id=1')).text();
    assert.match(detailHtml, /&lt;img src=x onerror=alert\(1\)&gt;/);
    const logout = await request('/auth-action.php?intent=logout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ form_token: token })
    });
    assert.equal(logout.status, 303);
    const signedOutHtml = await (await request('/index.php')).text();
    assert.doesNotMatch(signedOutHtml, /Test User/);
    const registerHtml = await (await request('/register.php')).text();
    const registerToken = registerHtml.match(/name="form_token" value="([^"]+)"/)?.[1];
    assert.ok(registerToken, 'Registration form token missing');
    const register = await request('/auth-action.php?intent=register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ form_token: registerToken, name: 'A Test', email: 'a@example.com', password: 'Secret123!', password_confirmation: 'Secret123!', role: 'applicant' })
    });
    assert.equal(register.status, 303);
    assert.equal(register.headers.get('location'), '/login.php');
    console.log('HTTP auth forms, session, jobs, detail, and XSS checks passed.');
} finally {
    ui.kill();
    api.kill();
}
