FROM php:8.2-apache

# Install dependencies for MariaDB and MongoDB
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    curl \
    && docker-php-ext-install pdo pdo_mysql mysqli

# Install MongoDB extension
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer

# Copy composer files first
COPY composer.json /var/www/html/composer.json

# Install MongoDB PHP library
RUN cd /var/www/html && composer install --no-dev

# Copy ALL app files AFTER composer install
COPY . /var/www/html/

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set permissions
RUN chown -R www-data:www-data /var/www/html
