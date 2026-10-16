#!/usr/bin/env bash
# Sobe o app (porta 8000) e os workers do Horizon em segundo plano.
pkill -f "artisan serve" || true
pkill -f "artisan horizon" || true
nohup php artisan horizon > storage/logs/horizon.out 2>&1 &
nohup env PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.out 2>&1 &
echo "App em http://localhost:8000 — entre com gestor1@escala.test / password"
