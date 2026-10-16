# Testes de carga (k6)

| Script | Uso |
|---|---|
| `smoke.js` | 5 usuários por 20 s — roda no CI a cada push no `main` |
| `load.js` | rampa até `VUS` usuários simultâneos navegando (painel → escalas → trocas) |
| `lib.js` | login real pelo formulário (cookie `XSRF-TOKEN`) e requisições Inertia com `X-Inertia-Version` |

Cada usuário virtual faz login com uma conta diferente do seeder, então o teste passa por
sessão no Redis, middleware de módulo, global scope por unidade e cache do painel.

## Como rodar

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan optimize   # lembre de `optimize:clear` antes de rodar o PHPUnit

# sem instalar o k6: imagem oficial
docker run --rm -i --network=host -v "$PWD/tests/k6:/scripts" grafana/k6 \
  run -e BASE_URL=http://localhost -e VUS=450 -e RAMP=1m -e DURATION=2m /scripts/load.js
```

Variáveis: `BASE_URL`, `VUS` (padrão 450), `RAMP` (1m), `DURATION` (3m).

## Resultados medidos (16/10/2026)

Ambiente: **notebook de desenvolvimento**, não servidor — Windows 11 + Docker Desktop (WSL2),
AMD Ryzen 7 5700X (8 núcleos), 16 GB RAM. Laravel Sail com o servidor embutido do PHP 8.4
(`PHP_CLI_SERVER_WORKERS=16`), MariaDB 11 e Redis no mesmo host, `artisan optimize` ligado.

| Cenário | Requisições | Vazão | Erros | p50 | p90 | p95 |
|---|---:|---:|---:|---:|---:|---:|
| 150 usuários simultâneos (30 s rampa + 1 min) | 16.878 | 149 req/s | **0,00%** | 54 ms | 308 ms | 490 ms |
| **450 usuários simultâneos** (1 min rampa + 2 min) | **56.232** | **263 req/s** | **0,00%** | 519 ms | 1,38 s | **1,48 s** |

Todos os thresholds passaram (`http_req_failed < 1%`, `p(95) < 1,5 s`, `checks > 99%`).
Os resumos brutos do k6 estão em [`results/`](results/).

### Leitura dos números
- Zero erro com 450 sessões autenticadas ao mesmo tempo, num servidor de desenvolvimento.
- O gargalo é o servidor embutido do PHP com bind mount do Windows — em produção (Nginx + PHP-FPM
  em Linux, ou Octane) a latência cai bastante; os números aqui são um piso, não um teto.
- O painel usa `Cache::remember` por unidade (60 s) no Redis: os agregados não são recalculados a cada acesso.
