#!/bin/bash
set -e

echo "=========================================="
echo "Import Database Schema to Render"
echo "=========================================="
echo ""

# Render PostgreSQL connection string
RENDER_DATABASE_URL="postgresql://dbpass_92kf3m1:O9ZL6oS5YUqZGky3EL2ufMwTYjdA71Db@dpg-d4i2tv95pdvs739i366g-a.oregon-postgres.render.com/dbpass_92kf3m1"

SCHEMA_FILE="migrations/database_postgresql.sql"

# Check if psql is available
if ! command -v psql &> /dev/null; then
    echo "ERROR: psql command not found."
    echo "Please install PostgreSQL client tools:"
    echo "  macOS: brew install postgresql"
    echo "  Ubuntu/Debian: sudo apt-get install postgresql-client"
    echo "  Windows: Download from https://www.postgresql.org/download/"
    exit 1
fi

# Check if schema file exists
if [ ! -f "$SCHEMA_FILE" ]; then
    echo "ERROR: Schema file not found: $SCHEMA_FILE"
    exit 1
fi

echo "STEP 1 — Testing connection to Render database..."
if psql "$RENDER_DATABASE_URL" -c "SELECT version();" > /dev/null 2>&1; then
    echo "✓ Connection successful"
else
    echo "ERROR: Cannot connect to Render database"
    echo "Please verify your DATABASE_URL is correct"
    exit 1
fi

echo ""
echo "STEP 2 — Importing schema to Render..."
echo "This may take a few moments..."
echo ""

if psql "$RENDER_DATABASE_URL" -f "$SCHEMA_FILE"; then
    echo ""
    echo "✓ Schema imported successfully!"
else
    echo ""
    echo "ERROR: Schema import failed"
    echo "Check the error messages above"
    exit 1
fi

echo ""
echo "STEP 3 — Verifying tables..."
echo ""
psql "$RENDER_DATABASE_URL" -c "\dt" | head -20

echo ""
echo "STEP 4 — Checking default settings..."
echo ""
psql "$RENDER_DATABASE_URL" -c "SELECT key, value FROM settings LIMIT 5;"

echo ""
echo "=========================================="
echo "IMPORT COMPLETE!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Create a superadmin account via the website"
echo "2. Configure system settings"
echo "3. Add users, customers, and teams"
echo ""
echo "Your website should now be able to connect to the database!"
echo ""

