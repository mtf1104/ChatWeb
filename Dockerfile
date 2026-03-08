FROM php:8.2-apache

# instalar dependencias necesarias
RUN apt-get update && apt-get install -y \
    git \
    unzip

# instalar composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# copiar archivos del proyecto
COPY . /var/www/html/

WORKDIR /var/www/html

# instalar dependencias de composer
RUN composer install

EXPOSE 80