# =============================================================================
# Development Stage
# =============================================================================
FROM php:8.2-fpm AS development

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    zip \
    unzip \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js 20.x
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory
COPY . .

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]

# =============================================================================
# Production Stage
# =============================================================================
FROM php:8.2-fpm-alpine AS production

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/cache/apk/*

# Copy Composer from development stage
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY --chown=www-data:www-data . .

# Install PHP dependencies (production only)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy production configs
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# Create supervisor config for nginx + php-fpm
RUN mkdir -p /etc/supervisor.d
COPY <<EOF /etc/supervisor.d/supervisord.ini
[supervisord]
nodaemon=true
user=root

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Cache Laravel config/routes/views
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Expose port 80
EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor.d/supervisord.ini"]
