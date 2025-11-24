#!/bin/bash
# Convert MySQL database.sql to PostgreSQL format
# This script converts the MySQL schema to PostgreSQL-compatible SQL

set -e

INPUT_FILE="migrations/database.sql"
OUTPUT_FILE="migrations/database_postgresql.sql"

if [ ! -f "$INPUT_FILE" ]; then
    echo "ERROR: $INPUT_FILE not found"
    exit 1
fi

echo "Converting MySQL schema to PostgreSQL..."
echo "Input: $INPUT_FILE"
echo "Output: $OUTPUT_FILE"
echo ""

# Start conversion
cat > "$OUTPUT_FILE" <<'HEADER'
-- ============================================
-- STAFF MANAGEMENT SYSTEM - POSTGRESQL VERSION
-- ============================================
-- Converted from MySQL schema
-- ============================================

-- Disable foreign key checks temporarily (PostgreSQL doesn't support this, but we'll handle it)
-- Note: PostgreSQL requires proper ordering of CREATE TABLE statements

HEADER

# Convert the file
sed -E '
    # Remove MySQL-specific statements
    /^SET SQL_MODE/d
    /^SET time_zone/d
    /^SET FOREIGN_KEY_CHECKS/d
    
    # Convert AUTO_INCREMENT to SERIAL
    s/AUTO_INCREMENT/SERIAL/g
    
    # Convert backticks to nothing (PostgreSQL doesn't use them)
    s/`//g
    
    # Convert ENGINE=InnoDB to nothing
    s/ENGINE=InnoDB[^;]*//
    
    # Convert DEFAULT CHARSET to nothing
    s/DEFAULT CHARSET=[^;]*//
    
    # Convert COLLATE to nothing (or handle appropriately)
    s/COLLATE=[^;]*//
    
    # Convert tinyint(1) to BOOLEAN
    s/tinyint\(1\)/BOOLEAN/g
    
    # Convert int(11) to INTEGER
    s/int\(11\)/INTEGER/g
    
    # Convert enum to VARCHAR with CHECK constraint (simplified - needs manual review)
    s/enum\([^)]+\)/VARCHAR(50)/g
    
    # Convert ON UPDATE CURRENT_TIMESTAMP (PostgreSQL uses triggers)
    s/ON UPDATE CURRENT_TIMESTAMP//
    
    # Convert KEY to CREATE INDEX (this needs to be handled separately)
    # For now, just remove KEY lines - we'll add indexes separately
    /^\s*KEY /d
    
    # Convert UNIQUE KEY
    /^\s*UNIQUE KEY /d
' "$INPUT_FILE" >> "$OUTPUT_FILE"

echo "✓ Conversion complete"
echo ""
echo "NOTE: This is an automated conversion. You should:"
echo "1. Review $OUTPUT_FILE for any issues"
echo "2. Manually fix enum types (convert to VARCHAR with CHECK constraints)"
echo "3. Add proper indexes (KEY statements were removed)"
echo "4. Test the schema on a PostgreSQL database"
echo ""
echo "To import to Render:"
echo "  psql \"\$DATABASE_URL\" -f $OUTPUT_FILE"

