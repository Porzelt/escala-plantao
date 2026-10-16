/**
 * Teste de carga: membros e gestores navegando no sistema ao mesmo tempo.
 *
 *   docker run --rm -i --network=host grafana/k6 run -e BASE_URL=http://localhost - < tests/k6/load.js
 *   (ou: k6 run tests/k6/load.js)
 *
 * Perfil: rampa até VUS usuários simultâneos, platô e descida.
 */
import { sleep, group } from 'k6';
import { login, inertia } from './lib.js';

const VUS = Number(__ENV.VUS || 450);

export const options = {
    scenarios: {
        navegacao: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: __ENV.RAMP || '1m', target: VUS },
                { duration: __ENV.DURATION || '3m', target: VUS },
                { duration: '30s', target: 0 },
            ],
            gracefulRampDown: '10s',
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],          // < 1% de erro
        http_req_duration: ['p(95)<1500'],       // p95 abaixo de 1,5 s
        checks: ['rate>0.99'],
    },
};

// 50 membros + 3 gestores criados pelo seeder
const accounts = [
    ...['gestor1', 'gestor2', 'gestor3'].map((u) => `${u}@escala.test`),
    ...Array.from({ length: 21 }, (_, i) => `membro1.${i + 1}@escala.test`),
    ...Array.from({ length: 15 }, (_, i) => `membro2.${i + 1}@escala.test`),
    ...Array.from({ length: 11 }, (_, i) => `membro3.${i + 1}@escala.test`),
];

let logged = false;

export default function () {
    if (!logged) {
        login(accounts[(__VU - 1) % accounts.length]);
        logged = true;
    }

    group('navegação típica', () => {
        inertia('/dashboard', 'GET /dashboard');
        sleep(1);
        inertia('/escalas', 'GET /escalas');
        sleep(1);
        inertia('/trocas', 'GET /trocas');
    });

    sleep(Math.random() * 2 + 1);
}
