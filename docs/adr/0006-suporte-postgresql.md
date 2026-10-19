# ADR 0006 — Suporte a PostgreSQL: decisões e diferenças

**Status:** aceita

## Contexto
O sistema de produção que este projeto espelha roda em MariaDB, mas muitas empresas usam PostgreSQL.
Queremos que o código seja **portável de verdade**, comprovado por testes, e não só "deve funcionar".

## Decisão
- **MariaDB continua o padrão** (compose, `.env.example`, CI de carga). PostgreSQL 17 é o segundo driver suportado.
- `docker compose --profile pgsql up -d` sobe o Postgres **e uma segunda instância do app** em `:8080`,
  com prefixos próprios de cache, Redis e Horizon. Dá para comparar os dois bancos lado a lado.
- **Sem SQL específico de banco no código da aplicação.** Onde havia (`DAYOFWEEK`), a agregação passou a ser
  feita por data no banco e convertida em PHP. Buscas usam `whereLike()` (o Laravel gera `ILIKE` no Postgres).
- Validações que comparam datas recebem **valores já normalizados** (`Y-m-01`), nunca o texto do formulário.
- **CI em matriz** `db: [mariadb, pgsql]`: a suíte inteira roda nos dois bancos em todo PR.
- Testes de regressão de portabilidade em `tests/Feature/DatabasePortabilityTest.php`.

## Consequências
- Qualquer `selectRaw`/`whereRaw` novo precisa passar nos dois bancos no CI, o que pega o problema cedo.
- Postgres é mais estrito: um bug que o MariaDB escondia apareceu já na primeira execução
  (ver [docs/postgres-notes.md](../postgres-notes.md)).
- Desempenho equivalente no teste de carga: o gargalo é a camada PHP, não o banco.
