FROM php:8.3-cli-bookworm AS vendor

# unzip basta pro Composer baixar packs; evita compilar ext-zip neste stage.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader


FROM node:22-alpine AS frontend

# VITE_* é embutido no JS no momento do build (não lê o .env em runtime).
ARG VITE_APP_NAME=Levita
ENV VITE_APP_NAME=$VITE_APP_NAME

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

# Ziggy é importado de vendor/ no Vite
COPY --from=vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build


# Extensões PHP em stage separado: muda raramente e fica em cache no VPS.
FROM php:8.3-fpm-bookworm AS php-base

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        unzip \
    && install-php-extensions \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer


FROM php-base

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
# Release atual do Vite — o entrypoint mescla no volume sem apagar hashes antigos.
COPY --from=frontend /app/public/build /opt/build-release
COPY --from=frontend /app/public/build ./public/build

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && mkdir -p /opt/build-release \
    && chown -R www-data:www-data /opt/build-release public/build

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
