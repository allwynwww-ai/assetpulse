# syntax=docker/dockerfile:1.7
# AssetPulse production image (Render web service, also works anywhere Docker runs).
# nginx + php-fpm in one container, listening on $PORT (Render sets it; default 10000).

############################
# 1) PHP dependencies (no dev). Platform checks are done in the final stage.
############################
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist --ignore-platform-reqs

############################
# 2) Front-end assets (Vite + Tailwind). Tailwind scans Blade views, app/ classes
#    and Laravel's pagination views, so copy those in before building.
############################
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY app ./app
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

############################
# 3) Runtime
############################
FROM php:8.4-fpm-bookworm AS app

# install-php-extensions pulls the right -dev libs, builds, then cleans up
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN apt-get update \
 && apt-get install -y --no-install-recommends nginx gettext-base ca-certificates \
 && install-php-extensions pdo_pgsql pgsql pdo_mysql gd intl zip bcmath opcache exif pcntl \
 && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-assetpulse.ini"
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-assetpulse.conf
COPY docker/nginx.conf.template /etc/nginx/templates/assetpulse.conf.template
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --no-interaction \
 && composer check-platform-reqs --no-dev \
 && rm -f .env public/hot public/storage \
 && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=10000

EXPOSE 10000
# php-fpm base image sets STOPSIGNAL SIGQUIT; the entrypoint traps TERM (what Render sends) and stops both processes gracefully
STOPSIGNAL SIGTERM
ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["serve"]
