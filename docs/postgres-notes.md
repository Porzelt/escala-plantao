# MariaDB x PostgreSQL — diferenças encontradas na prática

Registro do que apareceu ao rodar o Escala Plantão (Laravel 12) em **PostgreSQL 17** além do
**MariaDB 11**. Cada item foi reproduzido diretamente no banco (comandos abaixo) e está coberto por teste
em `tests/Feature/DatabasePortabilityTest.php`. Data: 19/10/2026.

## Primeira execução no Postgres
- `migrate:fresh --seed`: **passou sem alteração**.
- PHPUnit: **2 falhas em 71 testes** (itens 1 e 2). Depois das correções: **75/75 nos dois bancos**.

## O que quebrou (ou estava errado sem ninguém ver)

### 1. Comparar coluna `date` com texto que não é data (bug que o MariaDB escondia)
A validação de "já existe escala neste mês" usava `Rule::unique('schedules')`, que gerava
`WHERE month = '2026-12'` (o texto do campo `<input type="month">`).

```sql
-- MariaDB: converte em silêncio, só emite warning e nunca encontra nada
SELECT count(*) FROM schedules WHERE month = '2026-12';   -- 0
-- Warning 1292: Truncated incorrect datetime value: '2026-12'

-- PostgreSQL: erro
SELECT count(*) FROM schedules WHERE month = '2026-12';
-- ERROR: invalid input syntax for type date: "2026-12"
```
**Efeito no MariaDB:** a validação nunca detectava duplicata; o erro só aparecia depois, no índice
único, como **HTTP 500**. **Correção:** normalizar para `2026-12-01` e checar com `whereDate()`.

### 2. Funções de data específicas: `DAYOFWEEK()`
```sql
-- PostgreSQL
SELECT dayofweek(date '2026-11-01');          -- ERROR: function dayofweek(date) does not exist
SELECT extract(dow from date '2026-11-01');   -- 0  (domingo = 0; no MariaDB DAYOFWEEK dá domingo = 1)
```
Além do nome, a **numeração muda** (Postgres 0–6, MariaDB 1–7). **Correção:** agrupar por data no banco
(`GROUP BY date`, portável) e calcular o dia da semana em PHP com Carbon.

### 3. `LIKE` diferencia maiúsculas no Postgres
```sql
-- MariaDB (collation utf8mb4_unicode_ci)
SELECT 'Fulana' LIKE '%fulana%';                               -- 1
-- PostgreSQL
SELECT 'Fulana' LIKE '%fulana%', 'Fulana' ILIKE '%fulana%';    -- f | t
```
Não quebrou teste nenhum (a suíte não buscava com outra caixa); seria um bug silencioso na busca de membros.
**Correção:** `whereLike()` / `orWhereLike()` do Laravel, que gera `LIKE` no MariaDB e `ILIKE` no Postgres.

## Diferenças que não quebraram, mas é bom saber

| Tema | MariaDB | PostgreSQL | Impacto aqui |
|---|---|---|---|
| `->after('coluna')` na migration | respeita a posição | **ignora** (coluna vai para o fim) | nenhum: ordem física não importa |
| `json` | `longtext` + `CHECK (json_valid())` | tipo `json` nativo (`jsonb` disponível) | cast `array` do Eloquent igual; UTF-8 preservado (testado) |
| `boolean` | `tinyint(1)` | `boolean` real | o cast `boolean` do Eloquent esconde a diferença |
| Ordem aleatória | `RAND()` | `RANDOM()` | `inRandomOrder()` já abstrai |
| `SELECT ... FOR UPDATE` | suportado | suportado (+ `SKIP LOCKED`) | `lockForUpdate()` na troca de plantão funciona igual (testado) |
| `GROUP BY` estrito | depende do `sql_mode` | sempre estrito | as consultas já agrupavam corretamente |
| `INSERT IGNORE` / `ON DUPLICATE KEY` | sintaxe própria | `ON CONFLICT` | não usados; o caminho portável é `upsert()` / `insertOrIgnore()` |
| IDs explícitos | `AUTO_INCREMENT` acompanha | a sequence **não** avança sozinha | não inserimos IDs manualmente |

## Desempenho (k6, 450 usuários simultâneos, mesma máquina, 19/10/2026)

| Banco | Requisições | Vazão | Erros | p50 | p90 | p95 |
|---|---:|---:|---:|---:|---:|---:|
| MariaDB 11 | 43.224 | 202 req/s | 0,00% | 1,01 s | 1,70 s | 1,93 s |
| PostgreSQL 17 | 44.067 | 206 req/s | 0,00% | 1,05 s | 1,61 s | 1,86 s |

A diferença fica dentro da variação entre execuções: o gargalo é o servidor PHP de desenvolvimento, não o banco.
As duas rodadas ocorreram com a stack completa no ar (2 apps + 2 bancos). Por isso o p95 do MariaDB ficou
acima da medição isolada de 16/10 (1,48 s). Resumos brutos: `tests/k6/results/load-450-{mariadb,pgsql}.json`.

## Para entrevista: o resumo em uma frase
"Portar para Postgres me mostrou que o MariaDB aceitava em silêncio uma comparação inválida de data, que
virava erro 500. O banco estrito achou um bug real, e a matriz no CI garante que ele não volta."
