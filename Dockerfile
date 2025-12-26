FROM php:8.4-fpm

# Dependencias del sistema + extensiones PHP necesarias para Laravel + Postgres
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiamos composer primero para aprovechar cache
COPY composer.json composer.lock ./

# Instala vendor (sin scripts al inicio para evitar fallos si faltan env/keys)
RUN composer install --no-interaction --prefer-dist --no-dev --no-scripts

# Copiamos el resto del proyecto
COPY . .

# Permisos típicos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
