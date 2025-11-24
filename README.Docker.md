# Docker Setup for Staff Management System

## Quick Start

### Using Docker Compose (Recommended)

1. **Create your config.php file:**
   ```bash
   cp config.example.php config.php
   ```
   Then edit `config.php` and update the database settings:
   ```php
   define('DB_HOST', 'db');  // Use 'db' as hostname in Docker Compose
   define('DB_NAME', 'staff_management');
   define('DB_USER', 'staff_user');
   define('DB_PASS', 'staff_password');
   ```

2. **Start the containers:**
   ```bash
   docker-compose up -d
   ```

3. **Access the application:**
   - Web: http://localhost:8080
   - Database: localhost:3306

### Using Docker Only

1. **Build the image:**
   ```bash
   docker build -t staff-management .
   ```

2. **Run the container:**
   ```bash
   docker run -d \
     -p 8080:80 \
     -v $(pwd)/config.php:/var/www/html/config.php \
     -v $(pwd)/uploads:/var/www/html/uploads \
     -v $(pwd)/logs:/var/www/html/logs \
     --name staff-management \
     staff-management
   ```

## Database Setup

The database will be automatically initialized with the schema from `migrations/database.sql` when using Docker Compose.

## Environment Variables

You can customize the database connection by editing `docker-compose.yml`:

```yaml
environment:
  MYSQL_ROOT_PASSWORD: your_root_password
  MYSQL_DATABASE: staff_management
  MYSQL_USER: your_user
  MYSQL_PASSWORD: your_password
```

## Volumes

The following directories are persisted as volumes:
- `uploads/` - User uploaded files
- `logs/` - Application logs
- `storage/` - Storage files
- `exports/` - Exported files

## Troubleshooting

### Check logs:
```bash
docker-compose logs web
docker-compose logs db
```

### Access container shell:
```bash
docker-compose exec web bash
```

### Restart services:
```bash
docker-compose restart
```

### Stop and remove containers:
```bash
docker-compose down
```

