import http from 'k6/http';
import { check } from 'k6';

export const BASE = __ENV.BASE_URL || 'http://localhost';

/** Login via formulário do Laravel usando o cookie XSRF-TOKEN (mesmo fluxo do axios). */
export function login(email, password = 'password') {
    const jar = http.cookieJar();
    http.get(`${BASE}/login`);
    const token = decodeURIComponent(jar.cookiesForURL(BASE)['XSRF-TOKEN'][0]);

    const res = http.post(`${BASE}/login`, { email, password }, {
        headers: { 'X-XSRF-TOKEN': token, Accept: 'text/html' },
        redirects: 0,
        tags: { name: 'POST /login' },
    });
    check(res, { 'login redireciona': (r) => r.status === 302 });

    // versão dos assets do Inertia (sem ela o servidor responde 409 pedindo reload)
    const page = http.get(`${BASE}/dashboard`, { tags: { name: 'GET /dashboard (html)' } });
    const match = /&quot;version&quot;:&quot;([a-f0-9]+)&quot;|"version":"([a-f0-9]+)"/.exec(page.body);
    version = match ? match[1] || match[2] : '';
}

let version = '';

/** Requisição Inertia (JSON), como o front faz ao navegar entre telas. */
export function inertia(path, name) {
    const res = http.get(`${BASE}${path}`, {
        headers: { 'X-Inertia': 'true', 'X-Inertia-Version': version, 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html, application/xhtml+xml' },
        tags: { name },
    });
    check(res, { [`${name} 200`]: (r) => r.status === 200 && r.headers['X-Inertia'] === 'true' });
    return res;
}
