#!/bin/bash
set -e

echo "=== Docker Entrypoint Script Starting ==="
echo "Environment variables check:"
echo "  DATABASE_URL: ${DATABASE_URL:+SET (hidden)}"
echo "  DB_HOST: ${DB_HOST:-NOT SET}"
echo "  DB_NAME: ${DB_NAME:-NOT SET}"
echo "  DB_USER: ${DB_USER:-NOT SET}"
echo "  DB_PASS: ${DB_PASS:+SET (hidden)}"
echo "  BASE_URL: ${BASE_URL:-NOT SET}"
echo "  RENDER_EXTERNAL_URL: ${RENDER_EXTERNAL_URL:-NOT SET}"
echo ""

# Create or update config.php from config.example.php
if [ ! -f /var/www/html/config.php ]; then
    echo "Creating config.php from config.example.php..."
    cp /var/www/html/config.example.php /var/www/html/config.php
else
    echo "config.php already exists, will update database settings..."
fi

# Priority: Individual DB_* variables > DATABASE_URL > defaults
# Check if individual variables are set first (they take priority)
if [ -n "$DB_HOST" ] && [ -n "$DB_NAME" ] && [ -n "$DB_USER" ] && [ -n "$DB_PASS" ]; then
    echo "Using individual DB_* environment variables"
    echo "DB_HOST=$DB_HOST, DB_NAME=$DB_NAME, DB_USER=$DB_USER"
elif [ -n "$DATABASE_URL" ]; then
    echo "Found DATABASE_URL, parsing..."
    # Parse postgresql://user:password@host:port/database
    # Remove postgresql:// prefix
    DB_URL=$(echo "$DATABASE_URL" | sed 's|^postgresql://||')
    # Extract user (everything before first :)
    DB_USER_PARSED=$(echo "$DB_URL" | cut -d':' -f1)
    # Extract password and host (everything after first :, before @)
    DB_PASS_AND_HOST=$(echo "$DB_URL" | cut -d':' -f2-)
    DB_PASS_PARSED=$(echo "$DB_PASS_AND_HOST" | cut -d'@' -f1)
    # Extract host and database (everything after @)
    DB_HOST_AND_DB=$(echo "$DB_PASS_AND_HOST" | cut -d'@' -f2)
    # Extract host (before /, and remove port if present)
    DB_HOST_PARSED=$(echo "$DB_HOST_AND_DB" | cut -d'/' -f1 | cut -d':' -f1)
    # Extract database name (after /)
    DB_NAME_PARSED=$(echo "$DB_HOST_AND_DB" | cut -d'/' -f2)
    
    # Use parsed values, but allow individual vars to override
    DB_HOST=${DB_HOST:-$DB_HOST_PARSED}
    DB_NAME=${DB_NAME:-$DB_NAME_PARSED}
    DB_USER=${DB_USER:-$DB_USER_PARSED}
    DB_PASS=${DB_PASS:-$DB_PASS_PARSED}
    
    echo "Parsed DATABASE_URL: Host=$DB_HOST, Database=$DB_NAME, User=$DB_USER"
else
    echo "No DATABASE_URL or individual DB_* variables found, using defaults"
    DB_HOST=${DB_HOST:-db}
    DB_NAME=${DB_NAME:-staff_management}
    DB_USER=${DB_USER:-staff_user}
    DB_PASS=${DB_PASS:-staff_password}
fi

# Final validation and defaults
DB_HOST=${DB_HOST:-db}
DB_NAME=${DB_NAME:-staff_management}
DB_USER=${DB_USER:-staff_user}
DB_PASS=${DB_PASS:-staff_password}

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

# Update config.php - use perl for more reliable replacement
perl -i -pe "
    s/define\('DB_HOST',\s*'[^']*'\);/define('DB_HOST', '${DB_HOST_ESCAPED}');/g;
    s/define\(\"DB_HOST\",\s*\"[^\"]*\"\);/define('DB_HOST', '${DB_HOST_ESCAPED}');/g;
    s/define\('DB_NAME',\s*'[^']*'\);/define('DB_NAME', '${DB_NAME_ESCAPED}');/g;
    s/define\(\"DB_NAME\",\s*\"[^\"]*\"\);/define('DB_NAME', '${DB_NAME_ESCAPED}');/g;
    s/define\('DB_USER',\s*'[^']*'\);/define('DB_USER', '${DB_USER_ESCAPED}');/g;
    s/define\(\"DB_USER\",\s*\"[^\"]*\"\);/define('DB_USER', '${DB_USER_ESCAPED}');/g;
    s/define\('DB_PASS',\s*'[^']*'\);/define('DB_PASS', '${DB_PASS_ESCAPED}');/g;
    s/define\(\"DB_PASS\",\s*\"[^\"]*\"\);/define('DB_PASS', '${DB_PASS_ESCAPED}');/g;
    s|define\('BASE_URL',\s*'[^']*'\);|define('BASE_URL', '${BASE_URL_ESCAPED}');|g;
    s|define\(\"BASE_URL\",\s*\"[^\"]*\"\);|define('BASE_URL', '${BASE_URL_ESCAPED}');|g;
" /var/www/html/config.php 2>/dev/null || {
    # Fallback: use sed with simpler patterns
    sed -i "s|define('DB_HOST', '[^']*');|define('DB_HOST', '${DB_HOST_ESCAPED}');|g" /var/www/html/config.php
    sed -i "s|define('DB_NAME', '[^']*');|define('DB_NAME', '${DB_NAME_ESCAPED}');|g" /var/www/html/config.php
    sed -i "s|define('DB_USER', '[^']*');|define('DB_USER', '${DB_USER_ESCAPED}');|g" /var/www/html/config.php
    sed -i "s|define('DB_PASS', '[^']*');|define('DB_PASS', '${DB_PASS_ESCAPED}');|g" /var/www/html/config.php
    sed -i "s|define('BASE_URL', '[^']*');|define('BASE_URL', '${BASE_URL_ESCAPED}');|g" /var/www/html/config.php
}

echo "config.php updated with database settings"
echo ""
echo "=== Verifying config.php updates ==="
grep -E "define\('(DB_HOST|DB_NAME|DB_USER|DB_PASS|BASE_URL)'" /var/www/html/config.php | head -5 || echo "Warning: Could not verify config.php updates"
echo ""

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

