ARG ALPINE_VERSION=3.16
ARG PHP_VERSION=8.1.10

FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION} as base
WORKDIR /var/www/html
RUN apk add --update --no-cache icu-dev \
    && rm -rf /var/cache/apk/* \
    && docker-php-ext-install intl