FROM php:8.3-cli
WORKDIR /app
RUN docker-php-ext-install pdo_mysql
