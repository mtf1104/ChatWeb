FROM php:8.2-apache

<<<<<<< HEAD
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

=======
RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/

>>>>>>> 89446c25d01d74d0e83a07ca43017d64c635dd8d
EXPOSE 80