# syntax=docker/dockerfile:1.7

ARG PHP_IMAGE=php:8.4.11-cli-bookworm@sha256:7088ccae38951afa5e6d49fefdd78cbc2045e3891a6cdc52c3831560c97fe055
ARG NODE_IMAGE=node:22.20.0-bookworm-slim@sha256:b21fe589dfbe5cc39365d0544b9be3f1f33f55f3c86c87a76ff65a02f8f5848e
ARG COMPOSER_IMAGE=composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c

FROM ${COMPOSER_IMAGE} AS composer-bin

FROM ${PHP_IMAGE} AS php-extensions

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        pcntl \
        pdo_mysql \
        zip; \
    pecl install redis-6.3.0; \
    docker-php-ext-enable redis

FROM ${PHP_IMAGE} AS php-runtime

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libfreetype6 \
        libicu72 \
        libjpeg62-turbo \
        libpng16-16 \
        libzip4; \
    rm -rf /var/lib/apt/lists/*

COPY --from=php-extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-extensions /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

WORKDIR /var/www/html

FROM php-runtime AS composer-production

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip; \
    rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer/cache \
    composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts

COPY artisan ./
COPY app/ ./app/
COPY bootstrap/ ./bootstrap/
COPY config/ ./config/
COPY database/ ./database/
COPY public/ ./public/
COPY resources/ ./resources/
COPY routes/ ./routes/
COPY storage/ ./storage/

RUN set -eux; \
    composer dump-autoload \
        --no-dev \
        --no-interaction \
        --classmap-authoritative \
        --no-scripts; \
    php artisan package:discover --ansi; \
    php artisan filament:upgrade

FROM ${NODE_IMAGE} AS frontend-build

WORKDIR /build

COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci

COPY vite.config.js ./
COPY resources/ ./resources/
COPY --from=composer-production /var/www/html/vendor/ ./vendor/
COPY --from=composer-production /var/www/html/storage/ ./storage/
RUN npm run build

# This target keeps development dependencies available only for the containerized
# Laravel test suite. Build explicitly with: docker build --target test ...
FROM composer-production AS test

COPY --from=frontend-build /build/public/build/ ./public/build/
COPY phpunit.xml ./
COPY tests/ ./tests/

RUN --mount=type=cache,target=/tmp/composer/cache \
    composer install \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts \
    && php artisan package:discover --ansi \
    && touch .env \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data:www-data

CMD ["php", "artisan", "test"]

FROM php-runtime AS application

LABEL org.opencontainers.image.title="Hoaxlin Laravel application" \
      org.opencontainers.image.version="local" \
      org.opencontainers.image.description="Reproducible local Laravel 12 HTTP and queue runtime"

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

COPY --from=composer-production --chown=www-data:www-data /var/www/html/ /var/www/html/
COPY --from=frontend-build --chown=www-data:www-data /build/public/build/ /var/www/html/public/build/

RUN set -eux; \
    mkdir -p \
        storage/app/private/submissions \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    test -f public/build/manifest.json

USER www-data:www-data

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
