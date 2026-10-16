#!/usr/bin/env bash
# Sobe o app (porta 8000) e os workers do Horizon em segundo plano.
pkill -f "artisan serve" || true
pkill -f "artisan horizon" || true
nohup php artisan horizon > storage/logs/horizon.out 2>&1 &
nohup env PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.out 2>&1 &
# No Codespaces a porta nasce privada (401 fora da sessão); para demo com dados fictícios, deixamos pública.
if [ -n "$CODESPACE_NAME" ] && command -v gh > /dev/null; then
    (for i in $(seq 1 30); do
        gh codespace ports visibility 8000:public -c "$CODESPACE_NAME" > /dev/null 2>&1 && break
        sleep 2
    done) &
fi

echo "App em http://localhost:8000 â€” entre com gestor1@escala.test / password"
