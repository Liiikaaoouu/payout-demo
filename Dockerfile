FROM php:8.3-fpm-alpine

# pdo_pgsql для PostgreSQL, rdkafka для Kafka
RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        zip \
        git \
        librdkafka-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql opcache \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www/html

COPY . .

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
