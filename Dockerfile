FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql
RUN a2dismod mpm_event || true \
    && a2dismod mpm_worker || true \
    && a2enmod mpm_prefork rewrite

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p /var/www/html/exports \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
