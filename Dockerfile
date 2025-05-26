FROM php:8.3-cli-alpine

WORKDIR /app

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache zip libzip \
    --virtual .build-deps $PHPIZE_DEPS libzip-dev linux-headers \
    && docker-php-ext-install zip \
    && docker-php-ext-enable zip \
    && apk del --no-cache .build-deps
