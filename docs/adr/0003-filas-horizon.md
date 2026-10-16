# ADR 0003 — Exportações em fila Redis supervisionada pelo Horizon

**Status:** aceita

## Contexto
Gerar PDF/XLSX de uma escala com centenas de linhas leva segundos e consome memória; feito na
requisição, segura um worker PHP-FPM e estoura timeout sob carga.

## Decisão
- O endpoint cria um registro `schedule_exports` e despacha `ExportScheduleJob` na fila **`exports`**, responde **202**.
- O job processa em lotes (`chunk(200)`) e grava `progress` (5% → 90% → 100%).
- O front faz polling leve (`GET /exportacoes/{id}`) e baixa o arquivo ao concluir.
- Horizon supervisiona as filas `default` e `exports` (3 tentativas, timeout 300 s, `failed()` marca o erro).
- Rota de exportação com `throttle:10,1`.

## Alternativas consideradas
Broadcasting via WebSocket (Reverb) — melhor UX, mas adiciona um serviço. Polling a cada 800 ms é
suficiente para um job de poucos segundos e não precisa de infraestrutura extra.
