FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libsqlite3-dev libonig-dev \
    && docker-php-ext-install pdo_sqlite mbstring && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY . .
RUN mkdir -p storage && chown -R www-data:www-data storage
COPY docker/php.ini /usr/local/etc/php/conf.d/thesisflow.ini
USER www-data
EXPOSE 8080
CMD ["sh", "-c", "php bin/seed.php && php -S 0.0.0.0:8080 -t public public/router.php"]
