# ==============================================================================
# GTA CLIP AI - Core PHP + MySQL Production Web Container
# Base Image: Official PHP 8.2 with Apache Web Server
# ==============================================================================
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    zip \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Configure & Install PHP Extensions required by GTA Clip AI
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        curl \
        mbstring \
        zip \
        gd \
        opcache

# Configure PHP settings for heavy media SaaS workloads
RUN { \
        echo 'upload_max_filesize = 500M'; \
        echo 'post_max_size = 500M'; \
        echo 'memory_limit = 512M'; \
        echo 'max_execution_time = 300'; \
        echo 'max_input_time = 300'; \
        echo 'date.timezone = UTC'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.use_strict_mode = 1'; \
    } > /usr/local/etc/php/conf.d/gtaclip-custom.ini

# Configure Apache: Enable rewrite and headers modules
RUN a2enmod rewrite headers

# Copy VirtualHost configuration with DirectoryIndex index.php
RUN { \
        echo '<VirtualHost *:80>'; \
        echo '    ServerAdmin webmaster@localhost'; \
        echo '    DocumentRoot /var/www/html'; \
        echo '    DirectoryIndex index.php index.html'; \
        echo '    <Directory /var/www/html>'; \
        echo '        Options -Indexes +FollowSymLinks'; \
        echo '        AllowOverride All'; \
        echo '        Require all granted'; \
        echo '    </Directory>'; \
        echo '    ErrorLog ${APACHE_LOG_DIR}/error.log'; \
        echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined'; \
        echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files (excluding storage and .git via .dockerignore)
COPY . /var/www/html/

# Create persistent storage directories with proper permissions
RUN mkdir -p /var/www/html/storage/uploads \
             /var/www/html/storage/audio \
             /var/www/html/storage/subtitles \
             /var/www/html/storage/clips \
             /var/www/html/storage/thumbnails \
             /var/www/html/backups \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/backups

EXPOSE 80

CMD ["apache2-foreground"]
