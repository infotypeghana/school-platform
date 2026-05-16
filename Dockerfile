# ──────────────────────────────────────────────────────────────────────────────
# SchoolMS Ghana — Production Dockerfile
# Multi-stage build: composer deps + asset compilation, then lean PHP-FPM image
# ──────────────────────────────────────────────────────────────────────────────

# ── Stage 1: PHP/Composer dependencies ──────────────────────────────────────
FROM php:8.4-fpm-alpine AS vendor

WORKDIR /app

# System libraries required by PHP extensions
RUN apk add --no-cache \
    git \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    mysql-client

# PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        pcntl \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ── Stage 2: Node / Vite asset compilation ────────────────────────────────────
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --prefer-offline

COPY . .
RUN npm run build

# ── Stage 3: Production image ─────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS production

LABEL maintainer="SchoolMS Ghana <support@schoolms.com.gh>"
LABEL org.opencontainers.image.title="SchoolMS Ghana"
LABEL org.opencontainers.image.description="Multi-tenant school management platform"

WORKDIR /var/www/html

# System libs
RUN apk add --no-cache \
    libpng \
    libjpeg-turbo \
    libwebp \
    freetype \
    libzip \
    oniguruma \
    icu-libs \
    mysql-client \
    gzip

# PHP extensions (same set as vendor stage)
COPY --from=vendor /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=vendor /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# Re-install ext list (alpine already has libs via COPY; the ini files are present)
RUN docker-php-ext-install -j$(nproc) \
    bcmath exif gd intl mbstring opcache pdo_mysql pcntl zip 2>/dev/null || true

# PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Application code
COPY --chown=www-data:www-data . .

# Composer vendor from stage 1
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor

# Compiled frontend assets from stage 2
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Writable directories
RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Remove dev-only files
RUN rm -rf \
    tests \
    .github \
    docker \
    node_modules \
    phpstan.neon \
    phpunit.xml \
    .env.example \
    README.md

USER www-data

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD php-fpm -t || exit 1
