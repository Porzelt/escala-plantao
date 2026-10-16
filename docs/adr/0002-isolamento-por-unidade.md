# ADR 0002 — Isolamento de dados por unidade com Global Scope + Policies

**Status:** aceita

## Contexto
Cada unidade (equipe) só pode ver e alterar os próprios dados. Esquecer um `where unit_id = ?`
em uma única consulta vaza informação entre unidades.

## Decisão
Defesa em camadas:
1. **Global scope** (`BelongsToUnit`) em todo model com `unit_id`: o filtro é aplicado por padrão,
   inclusive no route model binding (registro de outra unidade → 404, não 403, para não revelar existência).
2. **Preenchimento automático** de `unit_id` no `creating` para não-admins (ignora o que vier do request).
3. **Policies** verificam `sameUnitAs()` em toda ação de escrita.
4. **Middleware `module:<modulo>,<nivel>`** barra o acesso ao módulo inteiro conforme a matriz
   de `config/rbac.php`.

O admin é a única exceção explícita. Jobs e comandos rodam sem usuário e usam `withoutGlobalScopes()`
de forma deliberada e visível.

## Consequências
- Testes de feature cobrem cada vetor (listagem, binding, criação, alteração).
- Tabelas filhas sem `unit_id` (plantões, trocas) herdam o isolamento pela relação com a escala/solicitante.
