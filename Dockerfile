FROM php:8.3-cli-alpine
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN apk update \
    && apk add zip libzip libzip-dev \
    --update linux-headers

RUN docker-php-ext-install zip \
    && docker-php-ext-enable zip
