FROM php:8.3-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    default-mysql-client \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Git to trust this directory (fixes permission issues)
RUN git config --global --add safe.directory /var/www/html

# Copy application files
COPY . /var/www/html/

# Install PHP dependencies (before changing ownership)
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Generate Laravel application key (if not already set)
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Set permissions (do this last, after all composer operations)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
