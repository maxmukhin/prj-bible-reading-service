FROM dunglas/frankenphp:1.1-php8.3-alpine AS base

RUN install-php-extensions \
    pdo_sqlite \
    opcache \
    apcu \
    intl \
    zip

ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ==========================================
# STAGE 2: Среда разработки (Development)
# ==========================================
FROM base AS dev

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
ENV APP_ENV=dev

# Создаем папку внутри контейнера и даем права серверу
RUN mkdir -p /app/data && chown -R www-data:www-data /app/data

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# ==========================================
# STAGE 3: Среда продакшена (Production)
# ==========================================
FROM base AS prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/production.ini $PHP_INI_DIR/conf.d/99-production.ini

COPY . /app

# На продакшене также выставляем права на папки кэша и данных
RUN mkdir -p /app/var /app/data && chown -R www-data:www-data /app/var /app/data

RUN composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction

ENV FRANKENPHP_CONFIG="worker ./public/index.php"
ENV APP_ENV=prod

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

