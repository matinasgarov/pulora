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
RUN mkdir -p /data

# Blade compilation is pure source-to-source — it reads resources/views and
# writes storage/framework/views, and touches neither the environment nor the
# database. It took three seconds of every single cold start, which mattered a
# great deal: Fly's proxy gives the app about eight seconds to start answering
# before it gives up and retries with a backoff, so a boot that overran turned
# a ten-second start into a twenty-four-second one. Doing it here costs those
# seconds once per deploy instead of once per wake-up.
#
# config:cache and route:cache deliberately stay in the entrypoint: both bake
# environment values into the cache, and the environment at build time is not
# the environment the container runs in.
RUN php artisan view:cache \
 && php artisan storage:link --force

# After view:cache and storage:link, not before: both run as root and would
# otherwise leave root-owned files for php-fpm to trip over.
#
# Doing it here is what keeps it out of the entrypoint. Recursing over storage/
# means walking the 72 product photographs, which cost five seconds of every
# wake-up — the largest remaining item once migrate and seed were gone, and
# enough on its own to blow the proxy's eight-second patience. None of it
# changes at runtime, so it belongs in the image.
RUN chown -R www-data:www-data storage bootstrap/cache /data

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
