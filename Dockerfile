FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    sqlite3 libsqlite3-dev \
    git unzip \
    libzip-dev zlib1g-dev \
  && docker-php-ext-install pdo pdo_sqlite zip \
  && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
  && composer install --no-interaction --prefer-dist

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
