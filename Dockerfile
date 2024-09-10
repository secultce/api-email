FROM php:fpm-alpine3.20

COPY . /var/www/html
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
    bash \
    curl \
    git \
    nano \
    libpng-dev \
    libpq \
    libzip-dev \
    zlib-dev \
    linux-headers \
    postgresql-dev \
    # Extensões do PHP
    && docker-php-ext-install \
    gd \
    zip \
    pdo_pgsql \
    sockets \
    # Composer Install
    && composer install \
    && chown -R www-data:www-data /var/www/html \
    # Limpeza dos pacotes
    && docker-php-source delete

EXPOSE 9000