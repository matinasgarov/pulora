# Pulora — public preview image.
#
# Everything the container needs is in the repo: composer dependencies are
# installed at build, the Vite bundle in public/build is committed, and the
# product photographs come from database/demo/card-holders. So the build needs
# neither Node nor the 60MB of source photography.
FROM php:8.4-fpm-alpine AS base

# sqlite-dev is a build dependency, not a runtime one: PHP 8 dropped the
# bundled libsqlite, so pdo_sqlite compiles against the system headers. The
# `sqlite` package below only carries the CLI and the shared library, which is
# why the build failed here first — "Package 'sqlite3' not found".
RUN apk add --no-cache nginx supervisor sqlite libpng libjpeg-turbo freetype icu libzip \
 && apk add --no-cache --virtual .build libpng-dev libjpeg-turbo-dev freetype-dev icu-dev sqlite-dev libzip-dev \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install -j"$(nproc)" gd intl pdo_sqlite zip opcache \
 && apk del .build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first, so a code change does not reinstall the vendor tree.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev

# The database lives on the mounted volume so it survives a redeploy; this is
# only the directory it will be mounted over.
RUN mkdir -p /data \
 && chown -R www-data:www-data storage bootstrap/cache /data

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
