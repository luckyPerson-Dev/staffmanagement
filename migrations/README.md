# Database Installation Guide

## Quick Start

This folder contains the **complete database schema** for the Staff Management System.

### Single File Installation

**File: `database.sql`**

This is the **ONLY** database file you need to run. It contains all tables, indexes, foreign keys, and default settings.

### Installation Steps

1. **Create Database** (if not exists):
   ```sql
   CREATE DATABASE staff_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE staff_management;
   ```

2. **Import Database File**:
   - **Via phpMyAdmin**: 
     - Select your database
     - Click "Import" tab
     - Choose `database.sql` file
     - Click "Go"
   
   - **Via MySQL Command Line**:
     ```bash
     mysql -u your_username -p staff_management < database.sql
     ```

3. **Create Superadmin Account**:
   - Use the installation wizard in the web interface, OR
   - Manually insert via SQL (see below)

### Manual Superadmin Creation

```sql
INSERT INTO `users` (
    `name`,
    `email`,
    `password`,
    `role`,
    `status`,
    `monthly_salary`,
    `created_at`
) VALUES (
    'Super Admin',
    'admin@example.com',
    '$2y$12$YOUR_HASHED_PASSWORD_HERE',
    'superadmin',
    'active',
    0.00,
    UTC_TIMESTAMP()
);
```

**Note**: Replace `$2y$12$YOUR_HASHED_PASSWORD_HERE` with a password hash generated using PHP's `password_hash()` function.

### What's Included

The `database.sql` file includes:

✅ **Core Tables**:
- Users, Settings, Customers, Teams, Team Members

✅ **Progress & Attendance**:
- Daily Progress (with missed day & overtime support)
- Attendance (with QR code support)
- Customer Groups

✅ **Financial Management**:
- Advances & Advance Auto-Deductions
- Salary History
- Payroll Run Log
- Profit Fund System
- Monthly & Staff Tickets
- Bonuses, Loans, Expenses

✅ **System Features**:
- Audit Logs
- Notifications
- Messages & Attachments
- Documents
- Support Tickets

✅ **Security**:
- User IP Restrictions
- Remember Tokens
- Two-Factor Authentication Support

✅ **Permissions & RBAC**:
- Permissions Table
- Role Permissions

✅ **Analytics & AI**:
- Analytics Cache
- AI Insights

✅ **Team Management**:
- Team Objectives/KPIs

✅ **Bulk Operations**:
- Bulk Payments

✅ **Default Settings**:
- All required system settings pre-configured

### Database Requirements

- **MySQL**: 5.7+ or **MariaDB**: 10.2+
- **Charset**: utf8mb4 (for full Unicode support)
- **Engine**: InnoDB (for transaction support)

### Verification

After installation, verify by checking:

```sql
-- Check all tables exist
SHOW TABLES;

-- Check settings are loaded
SELECT COUNT(*) FROM settings;

-- Check permissions are loaded
SELECT COUNT(*) FROM permissions;
```

### Troubleshooting

**Error: Foreign key constraint fails**
- Make sure you're running the entire file in order
- Check that all tables are created before foreign keys are added

**Error: Table already exists**
- The file uses `CREATE TABLE IF NOT EXISTS` - safe to run multiple times
- If you need a fresh start, drop the database and recreate

**Error: Unknown column**
- Make sure you're using the latest `database.sql` file
- All columns are included in the main file

### Support

For issues or questions, check the main project README or contact support.

---

**Last Updated**: 2025
**Version**: 2.0

