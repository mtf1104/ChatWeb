FROM php:8.2-apache

# instalar extensiones necesarias para MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    unzip

# instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# copiar archivos del proyecto
COPY . /var/www/html/

WORKDIR /var/www/html

# instalar dependencias de composer
RUN composer install

EXPOSE 80