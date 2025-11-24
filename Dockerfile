# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mysqli \
    gd \
    zip \
    intl \
    mbstring \
    opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure PHP
RUN echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "session.gc_maxlifetime = 1800" >> /usr/local/etc/php/conf.d/uploads.ini

# Set proper permissions for uploads and logs directories
RUN mkdir -p /var/www/html/uploads /var/www/html/logs /var/www/html/storage /var/www/html/exports \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create entrypoint script inline
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
# Create config.php from config.example.php if it doesn'\''t exist\n\
if [ ! -f /var/www/html/config.php ]; then\n\
    echo "Creating config.php from config.example.php..."\n\
    cp /var/www/html/config.example.php /var/www/html/config.php\n\
    \n\
    # Set Docker-friendly defaults using environment variables or defaults\n\
    DB_HOST=${DB_HOST:-db}\n\
    DB_NAME=${DB_NAME:-staff_management}\n\
    DB_USER=${DB_USER:-staff_user}\n\
    DB_PASS=${DB_PASS:-staff_password}\n\
    BASE_URL=${BASE_URL:-http://localhost}\n\
    \n\
    # Update config.php with Docker values\n\
    sed -i "s/define('\''DB_HOST'\'', '\''localhost'\'');/define('\''DB_HOST'\'', '\''${DB_HOST}'\'');/" /var/www/html/config.php\n\
    sed -i "s/define('\''DB_NAME'\'', '\''your_database_name'\'');/define('\''DB_NAME'\'', '\''${DB_NAME}'\'');/" /var/www/html/config.php\n\
    sed -i "s/define('\''DB_USER'\'', '\''your_database_user'\'');/define('\''DB_USER'\'', '\''${DB_USER}'\'');/" /var/www/html/config.php\n\
    sed -i "s/define('\''DB_PASS'\'', '\''your_database_password'\'');/define('\''DB_PASS'\'', '\''${DB_PASS}'\'');/" /var/www/html/config.php\n\
    sed -i "s|define('\''BASE_URL'\'', '\''https://yourdomain.com/staff2'\'');|define('\''BASE_URL'\'', '\''${BASE_URL}'\'');|" /var/www/html/config.php\n\
    \n\
    echo "config.php created with Docker defaults"\n\
fi\n\
\n\
# Ensure directories exist and have proper permissions\n\
mkdir -p /var/www/html/uploads /var/www/html/logs /var/www/html/storage /var/www/html/exports\n\
chown -R www-data:www-data /var/www/html\n\
chmod -R 755 /var/www/html\n\
chmod -R 777 /var/www/html/uploads\n\
chmod -R 777 /var/www/html/logs\n\
chmod -R 777 /var/www/html/storage\n\
chmod -R 777 /var/www/html/exports\n\
\n\
# Execute the original command\n\
exec "$@"' > /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

# Copy application files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure Apache
RUN echo '<VirtualHost *:80>\n\
    ServerAdmin webmaster@localhost\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Expose port 80
EXPOSE 80

# Use entrypoint script
ENTRYPOINT ["docker-entrypoint.sh"]

# Start Apache
CMD ["apache2-foreground"]

