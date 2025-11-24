#!/bin/bash
set -e

# Create config.php from config.example.php if it doesn't exist
if [ ! -f /var/www/html/config.php ]; then
    echo "Creating config.php from config.example.php..."
    cp /var/www/html/config.example.php /var/www/html/config.php
    
    # Parse DATABASE_URL if provided (PostgreSQL connection string from Render)
    if [ -n "$DATABASE_URL" ]; then
        # Parse postgresql://user:password@host:port/database
        DB_URL=$(echo "$DATABASE_URL" | sed 's|postgresql://||')
        DB_USER=$(echo "$DB_URL" | cut -d':' -f1)
        DB_PASS_AND_HOST=$(echo "$DB_URL" | cut -d':' -f2-)
        DB_PASS=$(echo "$DB_PASS_AND_HOST" | cut -d'@' -f1)
        DB_HOST_AND_DB=$(echo "$DB_PASS_AND_HOST" | cut -d'@' -f2)
        DB_HOST=$(echo "$DB_HOST_AND_DB" | cut -d'/' -f1 | cut -d':' -f1)
        DB_NAME=$(echo "$DB_HOST_AND_DB" | cut -d'/' -f2)
        echo "Parsed DATABASE_URL: Host=$DB_HOST, Database=$DB_NAME, User=$DB_USER"
    else
        # Set Docker-friendly defaults using environment variables or defaults
        DB_HOST=${DB_HOST:-db}
        DB_NAME=${DB_NAME:-staff_management}
        DB_USER=${DB_USER:-staff_user}
        DB_PASS=${DB_PASS:-staff_password}
    fi
    
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
    # Escape special characters in password for sed
    DB_PASS_ESCAPED=$(echo "$DB_PASS" | sed 's/[[\.*^$()+?{|]/\\&/g')
    sed -i "s/define('DB_PASS', 'your_database_password');/define('DB_PASS', '${DB_PASS_ESCAPED}');/" /var/www/html/config.php
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

