FROM php:8.4-fpm
RUN apt-get update && \
    apt-get install -y --force-yes --no-install-recommends \
        libmemcached-dev \
        libzip-dev \
        libz-dev \
        libpq-dev \
        libjpeg-dev \
        libpng-dev \
        libfreetype6-dev \
        libssl-dev \
        openssh-server \
        libmagickwand-dev \
        git \
        cron \
        nano \
        libxml2-dev \
        libreadline-dev \
        libgmp-dev \
        mariadb-client \
        unzip \
        supervisor \
    && docker-php-ext-install soap exif pcntl zip pdo_mysql pdo_pgsql bcmath intl gmp sockets \
    && pecl install redis && docker-php-ext-enable redis \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && docker-php-ext-install gd \
    && docker-php-ext-configure gd --with-freetype --with-jpeg

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . /var/www/html
COPY ./docker/prod/php-fpm/supervisord.conf /etc/supervisord.conf
COPY ./docker/prod/php-fpm/published.sh /etc/published.sh
COPY ./docker/prod/php-fpm/works.sh /etc/works.sh
COPY ./docker/prod/php-fpm/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN composer install --no-dev --optimize-autoloader
RUN chown -R www-data:www-data /var/www && chmod -R 755 /var/www/html/storage
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]