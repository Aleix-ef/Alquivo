// Local HTTP security regression. Does not create messages, accounts or attachments.
// Run after docker compose up -d --build: node tools/check-support-chat.mjs
import assert from 'node:assert/strict';

const base = 'http://localhost:8080';
const cookies = new Map();
async function request(path, { method = 'GET', body, csrf = true, foreign = false } = {}) {
  const headers = {
    Accept: 'application/json',
    Referer: foreign ? 'https://untrusted.invalid/' : `${base}/`,
    Cookie: [...cookies].map(([key, value]) => `${key}=${value}`).join('; '),
  };
  if (foreign || method !== 'GET') headers.Origin = foreign ? 'https://untrusted.invalid' : base;
  if (csrf && cookies.has('XSRF-TOKEN')) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookies.get('XSRF-TOKEN'));
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  const response = await fetch(base + path, {
    method, headers, body: body === undefined ? undefined : JSON.stringify(body),
    redirect: 'manual', signal: AbortSignal.timeout(15000),
  });
  if (!foreign) for (const cookie of response.headers.getSetCookie()) {
    const pair = cookie.split(';')[0];
    const index = pair.indexOf('=');
    cookies.set(pair.slice(0, index), pair.slice(index + 1));
  }
  return response;
}

const inbox = await request('/support/inbox');
assert.equal(inbox.status, 200, 'Private SPA route can be loaded directly');
assert.match(inbox.headers.get('x-robots-tag') ?? '', /noindex/);
assert.equal((await request('/sanctum/csrf-cookie')).status, 204);
const endpoint = '/api/v1/public/support/chat/conversations';
const list = await request(endpoint);
assert.equal(list.status, 200);
assert.match(list.headers.get('cache-control') ?? '', /no-store/);
assert.deepEqual((await list.json()).conversations, [], 'A fresh visitor sees no conversations');
assert.equal((await request(`${endpoint}/00000000-0000-4000-8000-000000000001`)).status, 404);
assert.equal((await request(endpoint, { method: 'POST', body: {}, csrf: false })).status, 419, 'Writes require CSRF');
assert.equal((await request(endpoint, { method: 'POST', body: {} })).status, 422, 'Invalid messages are rejected');
assert.equal((await request(endpoint, { foreign: true })).status, 419, 'Foreign origins cannot reuse the guest session');
for (const path of ['/api/v1/support/chat/conversations', '/api/v1/support/team/conversations']) {
  assert.equal((await request(path)).status, 401, 'Account and team conversations require login');
}
assert.deepEqual((await (await request(endpoint)).json()).conversations, [], 'Rejected writes did not create a conversation');
console.log('PASS: guest session, CSRF, origin isolation, private routes, no-store and noindex. No support content created.');
