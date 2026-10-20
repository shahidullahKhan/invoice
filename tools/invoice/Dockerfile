# Local runtime for the invoice tool: PHP 8.3 with the extensions dompdf / PhpSpreadsheet need.
FROM php:8.3-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install gd zip \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
EXPOSE 8080
# Code is mounted by docker-compose.yml so edits show up without rebuilding
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app/webroot"]
