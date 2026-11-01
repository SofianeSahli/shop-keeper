FROM php:8.4.27-fpm-alpine

ARG HOST_UID=1000
ARG HOST_GID=1000

ENV HOME=/tmp

COPY --from=composer:2.11.1 /usr/bin/composer /usr/local/bin/composer
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions bcmath intl opcache pcntl pdo_mysql redis zip
RUN mkdir -p /var/www/html/vendor && chown -R "$HOST_UID:$HOST_GID" /var/www/html

COPY shop-keeper.ini "$PHP_INI_DIR/conf.d/shop-keeper.ini"

WORKDIR /var/www/html
