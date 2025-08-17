# Use official PHP image with Apache
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mysqli \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache \
    sockets

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure Apache for the application
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/stellar-dominion.ini

# Create log directories with proper permissions
RUN mkdir -p /var/log/stellar-dominion \
    && chown -R www-data:www-data /var/log/stellar-dominion \
    && chmod -R 755 /var/log/stellar-dominion

# Create application log directory as fallback
RUN mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/logs \
    && chmod -R 755 /var/www/html/logs

# Create uploads directory with proper permissions
RUN mkdir -p /var/www/html/public/uploads/avatars \
    && chown -R www-data:www-data /var/www/html/public/uploads \
    && chmod -R 755 /var/www/html/public/uploads

# Set proper permissions for web directory
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Switch to www-data user for Composer operations
USER www-data

# Copy application files
COPY --chown=www-data:www-data Stellar-Dominion/ /var/www/html/

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Switch back to root for final setup
USER root

# Expose port 80
EXPOSE 80

# Use entrypoint script to handle permissions and start Apache
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
