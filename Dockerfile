# PHP version — pinned to an exact patch release so a deploy can never pick
# up a stale cached 8.3 base image. 8.3.36 is the first 8.3 release fixing
# CVE-2026-109618 / CVE-2026-109619 (unauthenticated RCE, 21 Oct 2026).
# To take a newer security release, just raise this number (never lower).
ARG PHP_VERSION=8.3.36
FROM php:${PHP_VERSION}-cli

# Security floor: refuse to build on anything older than 8.3.36, whatever the
# tag above resolves to — and print the exact version into the Railway build
# log so it can be checked there.
RUN php -r 'if (version_compare(PHP_VERSION, "8.3.36", "<")) { fwrite(STDERR, "PHP " . PHP_VERSION . " is vulnerable to CVE-2026-109618/109619 - need 8.3.36 or newer\n"); exit(1); } echo "PHP security floor OK: " . PHP_VERSION . PHP_EOL;' \
    && php -v

# Don't advertise the PHP version to the public: without this every
# response carries an "X-Powered-By: PHP/8.3.x" header. (The admin
# dashboard shows the version to logged-in admins instead.)
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/zz-security.ini

RUN apt-get update && apt-get install -y git curl zip unzip libpng-dev libxml2-dev libzip-dev libonig-dev && docker-php-ext-install pdo pdo_mysql gd zip bcmath mbstring xml
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader
EXPOSE 8080
CMD php artisan migrate --force && php artisan storage:link && php artisan config:cache && php artisan route:cache && php -S 0.0.0.0:\$PORT -t public
