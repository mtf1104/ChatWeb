FROM php:8.2-apache

# 1. Instalar dependencias del sistema necesarias para Composer, Git y conexiones seguras
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    libssl-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql zip

# 2. Habilitar mod_rewrite de Apache (muy útil para proyectos PHP)
RUN a2enmod rewrite

# 3. Instalar Composer desde la imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Establecer el directorio de trabajo
WORKDIR /var/www/html

# 5. Copiar el código del proyecto al contenedor
COPY . /var/www/html/

# 6. Dar permisos correctos a la carpeta para que Apache pueda trabajar
RUN chown -R www-data:www-data /var/www/html

# 7. Instalar dependencias de Composer (PHPMailer, etc.)
# Usamos --no-interaction para que el proceso sea automático
RUN composer install --no-interaction --optimize-autoloader

# 8. Exponer el puerto 80
EXPOSE 80

# El comando por defecto ya es iniciar apache en primer plano
CMD ["apache2-foreground"]