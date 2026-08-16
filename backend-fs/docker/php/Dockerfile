FROM php:8.3-fpm

# System libraries needed to build the Drupal PHP extensions, plus ffmpeg
# (used by the fashion_video module to assemble the final video: muxing the
# D-ID talking clip with the background song, Ken Burns montage, etc.).
#
# opcache's jit/zend_jit.c is a huge generated file that is extremely slow
# to compile at -O2, and that alone was blowing past the deploy workflow's
# SSH command timeout on the droplet. JIT itself is off at runtime by
# default (opcache.jit_buffer_size=0, not set in drupal-opcache.ini below)
# and gives negligible benefit to a request/response PHP-FPM app like
# Drupal anyway, but building it with --disable-opcache-jit produces a
# broken opcache.so on PHP 8.3 (undefined symbol: jit_globals), so instead
# just build zend_jit.c at -O0 — still compiled in, but far faster — while
# leaving every other extension at the image's normal -O2.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libwebp-dev libxml2-dev libonig-dev libicu-dev \
        ffmpeg \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo pdo_mysql zip intl bcmath \
    && CFLAGS="-fpic -fpie -O0 -D_LARGEFILE_SOURCE -D_FILE_OFFSET_BITS=64" \
        docker-php-ext-install -j"$(nproc)" opcache \
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

# Ensure the bind-mounted runtime-writable dirs (public files + private
# fallback) are owned by www-data on start, so PHP-FPM can create the
# directories Drupal writes into. See the script for details.
COPY docker/php/entrypoint.sh /usr/local/bin/fs-entrypoint.sh
RUN chmod +x /usr/local/bin/fs-entrypoint.sh
ENTRYPOINT ["/usr/local/bin/fs-entrypoint.sh"]
CMD ["php-fpm"]
