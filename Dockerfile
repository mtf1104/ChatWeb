FROM php:8.2-apache

<<<<<<< HEAD
# instalar dependencias necesarias
=======
<<<<<<< HEAD
# instalar dependencias necesarias
=======
# instalar extensiones necesarias para MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# instalar dependencias del sistema
>>>>>>> b5d1ebfe3118f031f3a423118975c03ba9937a21
>>>>>>> a5edebe6bf55c9136a9cc4b18191684e862c633b
RUN apt-get update && apt-get install -y \
    git \
    unzip

<<<<<<< HEAD
# instalar composer
=======
<<<<<<< HEAD
# instalar composer
=======
# instalar Composer
>>>>>>> b5d1ebfe3118f031f3a423118975c03ba9937a21
>>>>>>> a5edebe6bf55c9136a9cc4b18191684e862c633b
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# copiar archivos del proyecto
COPY . /var/www/html/

WORKDIR /var/www/html

# instalar dependencias de composer
RUN composer install

EXPOSE 80