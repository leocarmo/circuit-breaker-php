FROM phpswoole/swoole:4.8-php8.1-alpine

RUN apk add --no-cache $PHPIZE_DEPS bash \
    && pecl install redis pcov \
    && docker-php-ext-enable redis \
    && echo 'extension=pcov.so' > /usr/local/etc/php/conf.d/pcov.ini

RUN curl --insecure https://getcomposer.org/composer-stable.phar -o /usr/bin/composer && \
    chmod +x /usr/bin/composer

WORKDIR /app

COPY ./composer.* /app

RUN composer install --prefer-dist

COPY . /app
