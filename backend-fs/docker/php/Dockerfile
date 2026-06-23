FROM php:8.3-fpm

# System libraries needed to build the Drupal PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libwebp-dev libxml2-dev libonig-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        gd opcache pdo pdo_mysql zip intl bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (so you can `composer install` against the bind-mounted code on deploy)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# OPcache — validate_timestamps=1 so a `git pull` is picked up without
# force-recreating the php container (your deploy workflow only runs
# `git pull && docker compose up -d`, which won't restart php on a code-only change).
RUN { \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'realpath_cache_size=4096K'; \
        echo 'realpath_cache_ttl=600'; \
    } > /usr/local/etc/php/conf.d/drupal-opcache.ini

# Runtime limits — keep upload sizes in sync with nginx client_max_body_size
RUN { \
        echo 'memory_limit=256M'; \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=64M'; \
        echo 'max_execution_time=60'; \
    } > /usr/local/etc/php/conf.d/drupal-runtime.ini

WORKDIR /var/www/html
