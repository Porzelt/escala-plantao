FROM mcr.microsoft.com/devcontainers/php:1-8.4-bookworm

RUN rm -f /etc/apt/sources.list.d/yarn.list \n    && apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libzip-dev libicu-dev mariadb-client \
    && docker-php-ext-install pdo_mysql gd zip intl pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
