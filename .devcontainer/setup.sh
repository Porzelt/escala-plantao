#!/usr/bin/env bash
# Roda uma vez, quando o Codespace é criado.
set -e
composer install --no-interaction --no-progress
[ -f .env ] || cp .env.example .env
php artisan key:generate --force
until mysqladmin ping -h mariadb -usail -ppassword --silent; do sleep 2; done
php artisan migrate:fresh --seed --force
npm ci --no-audit --no-fund
npm run build
