# Render Deployment Setup Guide

## Environment Variables

Set these in your Render dashboard (Settings → Environment):

### Option 1: Using DATABASE_URL (Recommended for PostgreSQL)
- `DATABASE_URL` = `postgresql://dbpass_92kf3m1:O9ZL6oS5YUqZGky3EL2ufMwTYjdA71Db@dpg-d4i2tv95pdvs739i366g-a.oregon-postgres.render.com/dbpass_92kf3m1`
- `BASE_URL` = `https://staffmanagement-bx2z.onrender.com`

### Option 2: Using Individual Database Variables
- `BASE_URL` = `https://staffmanagement-bx2z.onrender.com`
- `DB_HOST` = `dpg-d4i2tv95pdvs739i366g-a.oregon-postgres.render.com`
- `DB_NAME` = `dbpass_92kf3m1`
- `DB_USER` = `dbpass_92kf3m1`
- `DB_PASS` = `O9ZL6oS5YUqZGky3EL2ufMwTYjdA71Db`

### Optional:
- `PORT` = (Automatically set by Render, don't override)
- `APP_ENV` = `production`

## Render Service Configuration

1. **Build Command:** (Leave empty - Docker will handle it)
2. **Start Command:** (Leave empty - Docker CMD will handle it)
3. **Dockerfile Path:** `Dockerfile` (or leave empty if in root)

## Database Setup

If using Render PostgreSQL:
- Use the **Internal Database URL** for `DB_HOST`
- Format: `your-db-name.onrender.com` or the internal hostname provided

## Troubleshooting 404 Errors

1. **Check BASE_URL:** Must match your Render service URL exactly
2. **Check logs:** Render Dashboard → Logs tab
3. **Verify files:** Ensure all files are in the repository
4. **Check Apache:** Verify Apache is running (should see in logs)

## Manual Database Migration

After first deployment, you may need to run the database migration:
- The migration SQL is in `migrations/database.sql`
- Import it via Render's database dashboard or via command line

