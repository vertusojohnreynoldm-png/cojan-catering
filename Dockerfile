FROM php:8.2-cli
RUN apt-get update && apt-get install -y git curl zip unzip libpng-dev libxml2-dev libzip-dev && docker-php-ext-install pdo pdo_mysql gd zip bcmath mbstring xml
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader
EXPOSE 8080
CMD php artisan migrate --force && php artisan storage:link && php artisan config:cache && php artisan route:cache && php -S 0.0.0.0:\$PORT -t public