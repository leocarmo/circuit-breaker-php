FROM php:8.0-cli-alpine

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis swoole pcov \
    && docker-php-ext-enable redis swoole \
    && echo 'extension=pcov.so' > /usr/local/etc/php/conf.d/pcov.ini

RUN curl --insecure https://getcomposer.org/composer-stable.phar -o /usr/bin/composer && \
    chmod +x /usr/bin/composer

WORKDIR /app

COPY ./composer.* /app

RUN composer install --prefer-dist

COPY . /app
