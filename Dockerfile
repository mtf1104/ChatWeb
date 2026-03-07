FROM php:8.2-apache

<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
<<<<<<< HEAD
>>>>>>> d29338ca9923b2ea4787a6c3a57794de7aa472ed
>>>>>>> 5ed5c40d4aa0fcb9150670b1fa92d6e3bf320f04
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

<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
=======
RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/

>>>>>>> 89446c25d01d74d0e83a07ca43017d64c635dd8d
>>>>>>> d29338ca9923b2ea4787a6c3a57794de7aa472ed
>>>>>>> 5ed5c40d4aa0fcb9150670b1fa92d6e3bf320f04
EXPOSE 80