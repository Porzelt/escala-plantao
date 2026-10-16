# ADR 0005 — Auditoria por trait com retenção via `model:prune`

**Status:** aceita

## Decisão
- Trait `Auditable` nos models críticos grava `created/updated/deleted` com **antes/depois só dos campos alterados**,
  usuário, unidade e IP.
- Campos sensíveis (`password`, `remember_token` e os `$hidden` do model) nunca são gravados.
- `AuditLog` usa `MassPrunable`: `php artisan model:prune` roda diariamente pelo scheduler e remove
  registros mais antigos que `AUDIT_RETENTION_DAYS` (padrão 365).
- O log herda o isolamento por unidade (gestor só lê a auditoria da própria unidade).

## Alternativas
Pacotes como `owen-it/laravel-auditing` resolvem o mesmo; a implementação própria tem ~60 linhas,
fica explícita para revisão e evita dependência num ponto sensível.

## Limitação conhecida
Inserções em massa (`insert()` na geração da escala) não disparam eventos de model — a geração é
auditada pela atualização da escala (`seed`) e pode ser reproduzida a partir da semente.
