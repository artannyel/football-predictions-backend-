FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    libpq-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    dos2unix

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Copy configs
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/start.sh /usr/local/bin/start.sh

# Fix line endings (CRLF -> LF) for Windows users
RUN dos2unix /usr/local/bin/start.sh
RUN dos2unix /etc/supervisor/conf.d/supervisord.conf

# Create log directories for supervisor
RUN mkdir -p /var/log/supervisor

# Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod +x /usr/local/bin/start.sh

# Create storage link
RUN php artisan storage:link

# Expose port 80
EXPOSE 80

# Start via script
CMD ["/usr/local/bin/start.sh"]
