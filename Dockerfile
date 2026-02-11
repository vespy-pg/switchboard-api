# Użyj obrazu PHP 8.3 z FPM
FROM php:8.3-fpm

# Zaktualizuj system i zainstaluj zależności
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    libmemcached-dev \
    zlib1g-dev \
    curl \
    zip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache \
    && pecl install memcached-3.2.0 \
    && docker-php-ext-enable memcached

# Instalacja Composera
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# Ustaw katalog roboczy
WORKDIR /var/www/symfony

# Kopiuj pliki projektu
COPY . /var/www/symfony

# Instalacja zależności Symfony
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Kopiuj i ustaw uprawnienia dla entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Ustawienia uprawnień
RUN chown -R www-data:www-data /var/www/symfony \
    && chmod -R 775 /var/www/symfony/var

# Expose port for PHP-FPM
EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
