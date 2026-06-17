FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    poppler-utils \
    ghostscript \
    imagemagick \
    libmagickwand-dev \
    gcc \
    g++ \
    make \
    autoconf

RUN pecl install imagick \
    && docker-php-ext-enable imagick

WORKDIR /app

COPY . .

EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000}"] 
