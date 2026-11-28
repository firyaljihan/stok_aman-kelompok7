FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    libpq-dev \
    oniguruma-dev \
    && docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html

COPY src/ ./src/
COPY database/ ./database/
COPY tests/ ./tests/

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "src/Http"]