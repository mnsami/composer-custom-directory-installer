FROM php:8.3-cli-alpine

WORKDIR /app

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache zip libzip libzip-dev linux-headers \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS

RUN docker-php-ext-install zip \
    && docker-php-ext-enable zip \
    && apk del --no-cache .build-deps
