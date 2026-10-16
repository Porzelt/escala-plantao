# ADR 0001 — Monólito Laravel + Inertia.js + Vue 3

**Status:** aceita

## Contexto
Equipe pequena, sistema interno, telas ricas (grade de escala, barra de progresso) e necessidade de
autorização forte no servidor.

## Decisão
Monólito Laravel com Inertia.js e Vue 3 (Composition API). Sem API REST pública separada:
o Inertia entrega props já autorizadas pelo backend, e as rotas JSON existem só onde o front
precisa de polling (progresso de exportação).

## Consequências
- Um deploy, um repositório, uma camada de autorização (policies) — menos superfície de erro.
- Roteamento e sessão do Laravel (CSRF, cookies HttpOnly) sem gerenciar tokens no browser.
- Se surgir app mobile, expõe-se uma API Sanctum reaproveitando Services e Policies.
