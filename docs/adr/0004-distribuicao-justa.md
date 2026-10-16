# ADR 0004 — Algoritmo guloso de distribuição justa com sorteio reprodutível

**Status:** aceita

## Contexto
A escala precisa ser **justa** (ninguém concentra noturnos/fins de semana), **válida**
(descanso mínimo, indisponibilidades) e **auditável** (gestor precisa explicar por que alguém foi escalado).

## Decisão
`App\Domain\Scheduling\FairShiftDistributor` — classe pura, sem Eloquent:

1. Vagas em ordem cronológica.
2. Cada vaga vai para o elegível com **menor carga ponderada** (noturno 1,5; fim de semana ×1,5).
3. Empates → sorteio com `Random\Randomizer(Mt19937($seed))`. A semente é salva na escala:
   **mesma semente = mesma escala**, o que torna o sorteio verificável.
4. Elegibilidade: sem indisponibilidade, sem sobreposição, descanso mínimo (12 h) antes e depois.
5. Vaga sem candidato vai para `unfilled` — o algoritmo nunca viola regra para "fechar" a escala.
6. Carga do mês anterior entra como carga inicial (normalizada pelo mínimo).

## Por que não um solver (ILP/CP-SAT)?
Para equipes de 10–50 pessoas o guloso fica a ≤ 1 peso do ótimo de equilíbrio (coberto por teste),
roda em milissegundos em PHP puro e é explicável em uma frase. Um solver entra se surgirem
restrições "macias" conflitantes (preferências individuais com prioridade).

## Consequências
Algoritmo coberto por testes unitários rápidos (sem banco), integração coberta por testes de feature.
