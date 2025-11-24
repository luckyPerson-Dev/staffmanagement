#!/bin/bash
set -e

# Create or update config.php from config.example.php
if [ ! -f /var/www/html/config.php ]; then
    echo "Creating config.php from config.example.php..."
    cp /var/www/html/config.example.php /var/www/html/config.php
else
    echo "config.php already exists, will update database settings..."
fi

# Parse DATABASE_URL if provided (PostgreSQL connection string from Render)
if [ -n "$DATABASE_URL" ]; then
    echo "Found DATABASE_URL, parsing..."
    # Parse postgresql://user:password@host:port/database
    # Remove postgresql:// prefix
    DB_URL=$(echo "$DATABASE_URL" | sed 's|^postgresql://||')
    # Extract user (everything before first :)
    DB_USER=$(echo "$DB_URL" | cut -d':' -f1)
    # Extract password and host (everything after first :, before @)
    DB_PASS_AND_HOST=$(echo "$DB_URL" | cut -d':' -f2-)
    DB_PASS=$(echo "$DB_PASS_AND_HOST" | cut -d'@' -f1)
    # Extract host and database (everything after @)
    DB_HOST_AND_DB=$(echo "$DB_PASS_AND_HOST" | cut -d'@' -f2)
    # Extract host (before /, and remove port if present)
    DB_HOST=$(echo "$DB_HOST_AND_DB" | cut -d'/' -f1 | cut -d':' -f1)
    # Extract database name (after /)
    DB_NAME=$(echo "$DB_HOST_AND_DB" | cut -d'/' -f2)
    echo "Parsed DATABASE_URL: Host=$DB_HOST, Database=$DB_NAME, User=$DB_USER"
fi

# Use individual environment variables if set (they override DATABASE_URL parsing)
if [ -n "$DB_HOST" ]; then
    echo "Using DB_HOST from environment: $DB_HOST"
else
    DB_HOST=${DB_HOST:-db}
    echo "Using default DB_HOST: $DB_HOST"
fi

if [ -n "$DB_NAME" ]; then
    echo "Using DB_NAME from environment: $DB_NAME"
else
    DB_NAME=${DB_NAME:-staff_management}
fi

if [ -n "$DB_USER" ]; then
    echo "Using DB_USER from environment: $DB_USER"
else
    DB_USER=${DB_USER:-staff_user}
fi

if [ -n "$DB_PASS" ]; then
    echo "Using DB_PASS from environment (length: ${#DB_PASS})"
else
    DB_PASS=${DB_PASS:-staff_password}
fi

echo "Final database config: Host=$DB_HOST, Database=$DB_NAME, User=$DB_USER"

# Auto-detect BASE_URL from Render environment or use provided/default
if [ -n "$RENDER_EXTERNAL_URL" ]; then
    BASE_URL=${BASE_URL:-$RENDER_EXTERNAL_URL}
elif [ -n "$BASE_URL" ]; then
    BASE_URL=$BASE_URL
else
    BASE_URL=${BASE_URL:-http://localhost}
fi

# Update config.php with Docker values (handle both new and existing config.php)
# Escape special characters for sed
DB_HOST_ESCAPED=$(echo "$DB_HOST" | sed 's/[[\.*^$()+?{|]/\\&/g')
DB_NAME_ESCAPED=$(echo "$DB_NAME" | sed 's/[[\.*^$()+?{|]/\\&/g')
DB_USER_ESCAPED=$(echo "$DB_USER" | sed 's/[[\.*^$()+?{|]/\\&/g')
DB_PASS_ESCAPED=$(echo "$DB_PASS" | sed 's/[[\.*^$()+?{|]/\\&/g')
BASE_URL_ESCAPED=$(echo "$BASE_URL" | sed 's/[[\.*^$()+?{|]/\\&/g')

# Update DB_HOST (match any existing value)
sed -i "s/define('DB_HOST', '[^']*');/define('DB_HOST', '${DB_HOST_ESCAPED}');/" /var/www/html/config.php || \
    sed -i "s|define('DB_HOST', \"[^\"]*\");|define('DB_HOST', '${DB_HOST_ESCAPED}');|" /var/www/html/config.php || \
    echo "define('DB_HOST', '${DB_HOST_ESCAPED}');" >> /var/www/html/config.php

# Update DB_NAME
sed -i "s/define('DB_NAME', '[^']*');/define('DB_NAME', '${DB_NAME_ESCAPED}');/" /var/www/html/config.php || \
    sed -i "s|define('DB_NAME', \"[^\"]*\");|define('DB_NAME', '${DB_NAME_ESCAPED}');|" /var/www/html/config.php

# Update DB_USER
sed -i "s/define('DB_USER', '[^']*');/define('DB_USER', '${DB_USER_ESCAPED}');/" /var/www/html/config.php || \
    sed -i "s|define('DB_USER', \"[^\"]*\");|define('DB_USER', '${DB_USER_ESCAPED}');|" /var/www/html/config.php

# Update DB_PASS
sed -i "s/define('DB_PASS', '[^']*');/define('DB_PASS', '${DB_PASS_ESCAPED}');/" /var/www/html/config.php || \
    sed -i "s|define('DB_PASS', \"[^\"]*\");|define('DB_PASS', '${DB_PASS_ESCAPED}');|" /var/www/html/config.php

# Update BASE_URL
sed -i "s|define('BASE_URL', '[^']*');|define('BASE_URL', '${BASE_URL_ESCAPED}');|" /var/www/html/config.php || \
    sed -i "s|define('BASE_URL', \"[^\"]*\");|define('BASE_URL', '${BASE_URL_ESCAPED}');|" /var/www/html/config.php

echo "config.php updated with database settings"

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

