// Local HTTP regression: GET requests carry Referer, but no Origin, like the SPA.
// Run after docker compose up -d --build: node tools/check-session.mjs
import assert from 'node:assert/strict';

const base = 'http://localhost:8080';
const cookies = new Map();
async function request(path, { method = 'GET', body, referer = `${base}/dashboard`, anonymous = false } = {}) {
  const headers = { Accept: 'application/json', Referer: referer };
  if (method !== 'GET') headers.Origin = base;
  if (!anonymous) {
    headers.Cookie = [...cookies].map(([key, value]) => `${key}=${value}`).join('; ');
    if (cookies.has('XSRF-TOKEN')) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookies.get('XSRF-TOKEN'));
  }
  if (body) headers['Content-Type'] = 'application/json';
  const response = await fetch(base + path, { method, headers, body: body ? JSON.stringify(body) : undefined, redirect: 'manual', signal: AbortSignal.timeout(15000) });
  if (!anonymous) for (const cookie of response.headers.getSetCookie()) {
    const pair = cookie.split(';')[0];
    const index = pair.indexOf('=');
    cookies.set(pair.slice(0, index), pair.slice(index + 1));
  }
  return response;
}

const page = await request('/login', { anonymous: true });
assert.equal(page.status, 200);
assert.equal(page.headers.get('referrer-policy'), 'same-origin', 'The SPA must preserve its same-origin Referer');
const html = await page.text();
assert.match(html, /data-theme="dark"/);
assert.equal([...html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)].some((match) => !/\bsrc=/.test(match[1]) && match[2].trim()), false, 'No inline executable scripts under the CSP');
assert.equal((await request('/api/v1/auth/me', { anonymous: true })).status, 401);
assert.equal((await request('/sanctum/csrf-cookie')).status, 204);
assert.equal((await request('/api/v1/auth/login', { method: 'POST', body: { email: 'demo@alquivo.test', password: 'demo12345' } })).status, 200);
try {
  for (const path of ['/api/v1/auth/me', '/api/v1/dashboard', '/api/v1/properties', '/api/v1/assistant/conversations', '/api/v1/support']) {
    assert.equal((await request(path)).status, 200, `${path} must retain the session without an Origin header`);
  }
  const features = await (await request('/api/v1/public/config', { anonymous: true })).json();
  assert.equal((await request('/api/v1/fiscality')).status, features.fiscality ? 200 : 404);
  assert.equal(features.support_email, 'soporte@alquivo.com');
  if (!features.billing_enabled) {
    const plans = await (await request('/api/v1/plans')).json();
    assert.equal(plans.billing_enabled, false);
    assert.equal(plans.plans.founder.price_monthly, 6.99);
    assert.equal(plans.plans.founder.checkout_available, false);
    assert.equal((await request('/api/v1/billing/checkout', { method: 'POST', body: { plan: 'founder', period: 'monthly' } })).status, 403);
    assert.equal((await request('/api/v1/billing/portal', { method: 'POST' })).status, 403);
    console.log('PASS: precios visibles, checkout y portal bloqueados durante validación.');
  }
  assert.equal((await request('/api/v1/auth/me', { referer: 'https://untrusted.invalid/' })).status, 401);
  console.log('PASS: login → dashboard → datos → restauración de sesión; GET sin Origin y origen ajeno rechazado.');
} finally {
  assert.equal((await request('/api/v1/auth/logout', { method: 'POST' })).status, 204);
}
assert.equal((await request('/api/v1/auth/me')).status, 401);
console.log('PASS: cierre de sesión. Sin consultas a OpenAI ni modificaciones de cartera.');
