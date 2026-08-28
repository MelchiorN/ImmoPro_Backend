FROM php:8.3-apache

# Installer les dépendances système nécessaires à Laravel/Composer
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Activer mod_rewrite Apache
RUN a2enmod rewrite

# Répertoire de travail
WORKDIR /var/www/html

# Copier le projet
COPY . .

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Installer les dépendances Laravel
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissions Laravel
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Apache doit servir le dossier public de Laravel
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' \
    /etc/apache2/sites-available/000-default.conf

# Autoriser l'accès au dossier public
RUN printf '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' > /etc/apache2/conf-available/laravel.conf

RUN a2enconf laravel

EXPOSE 80

CMD ["apache2-foreground"]