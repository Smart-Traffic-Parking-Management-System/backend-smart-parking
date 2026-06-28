FROM php:8.2-cli

RUN apt-get update && apt-get install -y unzip git \
    && docker-php-ext-install pdo_mysql sockets

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-sockets

COPY . .

EXPOSE 8001

CMD ["php", "-S", "0.0.0.0:8001", "-t", "public"]