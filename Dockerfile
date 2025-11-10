FROM php:8.4-fpm

ENV TZ=America/Fortaleza

# Copia o código da aplicação para o contêiner
COPY . /var/www/html

# Instala o Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    nano \
    curl \
    supervisor \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    unzip \
    zip \
    libxml2-dev \
    libpq-dev \
    libicu-dev \
    ca-certificates \
    xz-utils \
    gnupg \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath opcache intl zip sockets \
    && /usr/bin/composer install \
    && rm -rf /var/lib/apt/lists/* \
    && chown -R www-data:www-data /var/www/html

COPY ./docker/production/supervisor/conf.d/* /etc/supervisor/conf.d/
COPY ./docker/production/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]

EXPOSE 9000
