FROM php:8.2-apache

# Install dependencies for MariaDB and MongoDB
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    && docker-php-ext-install pdo pdo_mysql mysqli

# Install MongoDB extension
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy app files to container
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html