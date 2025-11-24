#!/bin/bash
set -euo pipefail

echo "=========================================="
echo "Render PostgreSQL Database Migration"
echo "=========================================="
echo ""

# Render PostgreSQL connection string
RENDER_DATABASE_URL="postgresql://dbpass_92kf3m1:O9ZL6oS5YUqZGky3EL2ufMwTYjdA71Db@dpg-d4i2tv95pdvs739i366g-a.oregon-postgres.render.com/dbpass_92kf3m1"

echo "STEP 1 — Checking prerequisites..."
echo ""

# Check if psql is available
if ! command -v psql &> /dev/null; then
    echo "ERROR: psql command not found."
    echo "Please install PostgreSQL client tools:"
    echo "  macOS: brew install postgresql"
    echo "  Ubuntu/Debian: sudo apt-get install postgresql-client"
    echo "  Windows: Download from https://www.postgresql.org/download/"
    exit 1
fi

echo "✓ psql found"
echo ""

echo "STEP 2 — Converting MySQL schema to PostgreSQL..."
echo ""

# Create PostgreSQL-compatible schema
PG_SCHEMA="/tmp/staff_management_pg.sql"

# Convert MySQL to PostgreSQL
cat > "$PG_SCHEMA" <<'PGEOF'
-- ============================================
-- STAFF MANAGEMENT SYSTEM - POSTGRESQL VERSION
-- ============================================
-- Converted from MySQL schema for PostgreSQL compatibility
-- ============================================

-- Enable UUID extension if needed
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================
-- SECTION 1: CORE TABLES
-- ============================================

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'staff' CHECK (role IN ('superadmin','admin','staff','accountant','hr','auditor','finance','supervisor')),
  status VARCHAR(50) DEFAULT 'active' CHECK (status IN ('active','suspended','banned','inactive')),
  status_reason TEXT DEFAULT NULL,
  status_changed_at TIMESTAMP NULL DEFAULT NULL,
  status_changed_by INTEGER DEFAULT NULL,
  monthly_salary DECIMAL(10,2) DEFAULT 0.00,
  two_factor_enabled BOOLEAN DEFAULT FALSE,
  two_factor_secret VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  deleted_at TIMESTAMP NULL DEFAULT NULL
);

CREATE INDEX idx_email ON users(email);
CREATE INDEX idx_role ON users(role);
CREATE INDEX idx_status ON users(status);
CREATE INDEX idx_deleted ON users(deleted_at);

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
  id SERIAL PRIMARY KEY,
  key VARCHAR(100) NOT NULL UNIQUE,
  value TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL
);

CREATE INDEX idx_key ON settings(key);

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  whatsapp_group_link VARCHAR(500) DEFAULT NULL,
  ticket_penalty_percent DECIMAL(5,2) DEFAULT NULL,
  group_miss_penalty_percent DECIMAL(5,2) DEFAULT NULL,
  group_partial_penalty_percent DECIMAL(5,2) DEFAULT NULL,
  group_partial_ratio DECIMAL(5,2) DEFAULT NULL,
  group_miss_ratio DECIMAL(5,2) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  deleted_at TIMESTAMP NULL DEFAULT NULL
);

CREATE INDEX idx_deleted ON customers(deleted_at);
CREATE INDEX idx_penalties ON customers(ticket_penalty_percent, group_miss_penalty_percent);

-- Customer groups table
CREATE TABLE IF NOT EXISTS customer_groups (
  id SERIAL PRIMARY KEY,
  customer_id INTEGER NOT NULL,
  name VARCHAR(255) NOT NULL,
  whatsapp_group_link VARCHAR(500) DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'active' CHECK (status IN ('active','inactive')),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE INDEX idx_customer ON customer_groups(customer_id);
CREATE INDEX idx_deleted ON customer_groups(deleted_at);

-- Teams table
CREATE TABLE IF NOT EXISTS teams (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  deleted_at TIMESTAMP NULL DEFAULT NULL
);

CREATE INDEX idx_deleted ON teams(deleted_at);

-- Note: This is a simplified version. For full migration, we need to convert all tables.
-- The full schema conversion would require processing the entire database.sql file.
PGEOF

echo "✓ PostgreSQL schema created at $PG_SCHEMA"
echo ""

echo "STEP 3 — Testing connection to Render database..."
echo ""

if psql "$RENDER_DATABASE_URL" -c "SELECT version();" > /dev/null 2>&1; then
    echo "✓ Connection successful"
else
    echo "ERROR: Cannot connect to Render database"
    echo "Please verify your DATABASE_URL is correct"
    exit 1
fi

echo ""

echo "STEP 4 — Importing schema to Render..."
echo ""

if psql "$RENDER_DATABASE_URL" -f "$PG_SCHEMA"; then
    echo "✓ Schema imported successfully"
else
    echo "ERROR: Schema import failed"
    exit 1
fi

echo ""

echo "STEP 5 — Verifying tables..."
echo ""

psql "$RENDER_DATABASE_URL" -c "\dt" || true

echo ""

echo "STEP 6 — Cleanup..."
rm -f "$PG_SCHEMA"
echo "✓ Temporary files removed"

echo ""
echo "=========================================="
echo "MIGRATION COMPLETE"
echo "=========================================="
echo ""
echo "NOTE: This script created a basic schema with core tables."
echo "For a complete migration, you may need to:"
echo "1. Convert the full database.sql file to PostgreSQL"
echo "2. Or use a MySQL-to-PostgreSQL conversion tool"
echo ""
echo "To convert the full schema, consider using:"
echo "  - https://github.com/dumblob/mysql2postgres"
echo "  - Or manually convert each table from migrations/database.sql"
echo ""

