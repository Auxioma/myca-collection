FROM php:8.5-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
        libicu-dev libxslt1-dev libzip-dev libsodium-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl pdo_mysql soap sockets xsl zip ftp \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN { echo 'memory_limit=4G'; echo 'max_execution_time=1800'; echo 'zlib.output_compression=On'; \
      echo 'realpath_cache_size=10M'; echo 'opcache.memory_consumption=512'; \
      echo 'opcache.max_accelerated_files=60000'; } > /usr/local/etc/php/conf.d/magento.ini

WORKDIR /var/www/html
