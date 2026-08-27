FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
        libpq-dev \
        unzip \
        git \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --prefer-dist --no-progress --no-dev

EXPOSE 8000

CMD ["sh", "-c", "test -f .env || cp .env.example .env; php artisan key:generate --force; php artisan migrate --force; php artisan serve --host=0.0.0.0 --port=8000"]