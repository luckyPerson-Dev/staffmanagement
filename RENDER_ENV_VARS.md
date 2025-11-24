# Render Environment Variables - Quick Setup

## ⚠️ IMPORTANT: Set these in Render Dashboard

Go to: **Render Dashboard → Your Service → Environment → Add Environment Variable**

## Required Environment Variables

Copy and paste these EXACT values:

### Option 1: Using DATABASE_URL (Easiest - Recommended)
```
DATABASE_URL=postgresql://dbpass_92kf3m1:O9ZL6oS5YUqZGky3EL2ufMwTYjdA71Db@dpg-d4i2tv95pdvs739i366g-a.oregon-postgres.render.com/dbpass_92kf3m1
BASE_URL=https://staffmanagement-bx2z.onrender.com
```

### Option 2: Using Individual Variables (If Option 1 doesn't work)
```
BASE_URL=https://staffmanagement-bx2z.onrender.com
DB_HOST=dpg-d4i2tv95pdvs739i366g-a.oregon-postgres.render.com
DB_NAME=dbpass_92kf3m1
DB_USER=dbpass_92kf3m1
DB_PASS=O9ZL6oS5YUqZGky3EL2ufMwTYjdA71Db
```

## Steps to Add:

1. Go to Render Dashboard
2. Click on your service (staffmanagement)
3. Click "Environment" in the left sidebar
4. Click "Add Environment Variable"
5. Add each variable one by one:
   - Key: `DATABASE_URL`
   - Value: `postgresql://dbpass_92kf3m1:O9ZL6oS5YUqZGky3EL2ufMwTYjdA71Db@dpg-d4i2tv95pdvs739i366g-a.oregon-postgres.render.com/dbpass_92kf3m1`
6. Click "Save Changes"
7. Repeat for `BASE_URL`
8. **Redeploy** your service (Manual Deploy → Deploy latest commit)

## Verify:

After redeploy, check the logs. You should see:
```
Parsed DATABASE_URL: Host=dpg-d4i2tv95pdvs739i366g-a.oregon-postgres.render.com, Database=dbpass_92kf3m1, User=dbpass_92kf3m1
Final database config: Host=dpg-d4i2tv95pdvs739i366g-a.oregon-postgres.render.com, Database=dbpass_92kf3m1, User=dbpass_92kf3m1
config.php updated with database settings
```

If you see "Using default DB_HOST: db" in the logs, the environment variables are NOT being read correctly.

