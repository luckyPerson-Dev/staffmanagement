# Render Deployment Setup Guide

## Environment Variables

Set these in your Render dashboard (Settings → Environment):

### Required:
- `BASE_URL` = `https://staffmanagement-bx2z.onrender.com`
- `DB_HOST` = Your database host (if using Render PostgreSQL, use the internal hostname)
- `DB_NAME` = Your database name
- `DB_USER` = Your database user
- `DB_PASS` = Your database password

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

