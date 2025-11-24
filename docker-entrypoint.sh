#!/bin/bash
set -e

# Create config.php from config.example.php if it doesn't exist
if [ ! -f /var/www/html/config.php ]; then
    echo "Creating config.php from config.example.php..."
    cp /var/www/html/config.example.php /var/www/html/config.php
    
    # Set Docker-friendly defaults using environment variables or defaults
    DB_HOST=${DB_HOST:-db}
    DB_NAME=${DB_NAME:-staff_management}
    DB_USER=${DB_USER:-staff_user}
    DB_PASS=${DB_PASS:-staff_password}
    
    # Auto-detect BASE_URL from Render environment or use provided/default
    if [ -n "$RENDER_EXTERNAL_URL" ]; then
        BASE_URL=${BASE_URL:-$RENDER_EXTERNAL_URL}
    elif [ -n "$BASE_URL" ]; then
        BASE_URL=$BASE_URL
    else
        BASE_URL=${BASE_URL:-http://localhost}
    fi
    
    # Update config.php with Docker values
    sed -i "s/define('DB_HOST', 'localhost');/define('DB_HOST', '${DB_HOST}');/" /var/www/html/config.php
    sed -i "s/define('DB_NAME', 'your_database_name');/define('DB_NAME', '${DB_NAME}');/" /var/www/html/config.php
    sed -i "s/define('DB_USER', 'your_database_user');/define('DB_USER', '${DB_USER}');/" /var/www/html/config.php
    sed -i "s/define('DB_PASS', 'your_database_password');/define('DB_PASS', '${DB_PASS}');/" /var/www/html/config.php
    sed -i "s|define('BASE_URL', 'https://yourdomain.com/staff2');|define('BASE_URL', '${BASE_URL}');|" /var/www/html/config.php
    
    echo "config.php created with Docker defaults"
fi

# Ensure directories exist and have proper permissions
mkdir -p /var/www/html/uploads /var/www/html/logs /var/www/html/storage /var/www/html/exports
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 777 /var/www/html/uploads
chmod -R 777 /var/www/html/logs
chmod -R 777 /var/www/html/storage
chmod -R 777 /var/www/html/exports

# Execute the original command
exec "$@"

