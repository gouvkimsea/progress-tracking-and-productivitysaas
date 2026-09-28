# Use official PHP 8.2 with Apache
FROM php:8.2-apache

# Install system dependencies & SQLite/MySQL extensions
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    && a2enmod rewrite headers expires deflate \
    && rm -rf /var/lib/apt/lists/*

# Set recommended PHP production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/expose_php = On/expose_php = Off/' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/display_errors = On/display_errors = Off/' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 12M/' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/post_max_size = 8M/post_max_size = 16M/' "$PHP_INI_DIR/php.ini"

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Set file permissions for Apache user
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# Expose standard web port
EXPOSE 80

# Run Apache in foreground
CMD ["apache2-foreground"]
