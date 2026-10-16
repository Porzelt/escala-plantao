/** Smoke test rápido usado no CI: poucos usuários, mesmas rotas do load.js. */
import { sleep } from 'k6';
import { login, inertia } from './lib.js';

export const options = {
    vus: Number(__ENV.VUS || 5),
    duration: __ENV.DURATION || '20s',
    thresholds: { http_req_failed: ['rate<0.01'], checks: ['rate>0.99'] },
};

let logged = false;

export default function () {
    if (!logged) {
        login(`membro1.${__VU}@escala.test`);
        logged = true;
    }
    inertia('/dashboard', 'GET /dashboard');
    inertia('/escalas', 'GET /escalas');
    inertia('/trocas', 'GET /trocas');
    sleep(1);
}
