# Many Faced God - External Server Deployment Guide

This guide explains how to deploy the Many Faced God application on an external server using Docker.

## Prerequisites

- Docker and Docker Compose installed on the server
- Domain name (optional, but recommended)
- SSH access to the server
- Basic understanding of Docker and Linux

## Quick Start

### IMPORTANT: Docker Build Process

The Dockerfile automatically installs all Composer dependencies during image build with proper Git configuration.
This means:

1. ✅ Git is configured to trust the working directory (prevents permission issues)
2. ✅ Dependencies are installed into the Docker image
3. ✅ No runtime composer install is needed
4. ✅ Containers start immediately without dependency resolution
5. ✅ No "Missing vendor/autoload.php" or "Class not found" errors

**The `docker-compose build` command must be run on the deployment server!**

This ensures:
- All Laravel/Illuminate packages are available
- No Git permission errors during Composer operations
- Consistent behavior across all environments

### 1. Clone the Repository

```bash
git clone <repository-url> many-faced-god
cd many-faced-god
```

### 2. Build the Docker Image (IMPORTANT!)

This step MUST be done on the deployment server to install dependencies:

```bash
docker-compose build
```

This will:
- Copy application files into the image
- Run `composer install --no-dev --optimize-autoloader`
- Set up proper file permissions
- Create the vendor/ directory

### 3. Configure Environment Variables

Copy the example environment file and modify it for your server:

```bash
cp .env.example .env
```

Edit `.env` with your server-specific settings:

```env
# Application Settings
APP_NAME="Many Faced God"
APP_ENV=production          # Set to production on external server
APP_DEBUG=false             # NEVER set to true in production
APP_URL=https://yourdomain.com   # Your actual domain

# Database Configuration
DB_HOST=db                  # Use 'db' for Docker Compose setup
DB_PORT=3306
DB_DATABASE=many_faced_god
DB_USERNAME=mfg_user
DB_PASSWORD=<strong-random-password>

# Session Configuration
SESSION_DRIVER=file         # Or database/redis for scaling
SESSION_DOMAIN=yourdomain.com  # Your domain

# Logging
LOG_LEVEL=info             # Use 'info' or higher in production
```

### 3. Generate Application Key ⚠️ CRITICAL

**IMPORTANT**: The application WILL NOT work without a valid APP_KEY!

Laravel requires a 32-byte (256-bit) encryption key for AES-256-CBC cipher. Generate it with:

```bash
docker-compose exec -T app php artisan key:generate --no-ansi
```

**What is APP_KEY?**
- Required for all encryption operations (sessions, cookies, tokens, encrypted fields)
- Must be exactly 32 bytes for AES-256-CBC cipher
- Automatically prefixed with `base64:` when generated
- Format: `APP_KEY=base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX=`
- Should NEVER be shared or committed to version control

**Validation:**

Verify the key is set correctly:

```bash
# Check APP_KEY exists
grep "^APP_KEY=" .env

# Verify it's loaded in the application
docker-compose exec -T app php artisan tinker --execute="echo 'Key: ' . config('app.key');"
```

Expected output: `base64:` followed by 44 characters (not empty!)

**Note**: If using automated CI/CD (like Woodpecker), the pipeline will automatically generate the APP_KEY if missing.

### 4. Run Database Migrations

Initialize the database:

```bash
docker-compose exec -T app php artisan migrate --force
```

### 5. Run Database Seeders (Optional)

Populate with sample data:

```bash
docker-compose exec -T app php artisan db:seed --force
```

### 6. Build and Start Containers

```bash
docker-compose build      # ← CRITICAL: Installs composer dependencies
docker-compose up -d
```

### 7. Verify Installation

Check that all containers are running:

```bash
docker-compose ps
```

Access the application:

- **Web**: `http://your-server-ip:8765` or `https://yourdomain.com`
- **Database**: `your-server-ip:33306`

## Production Deployment Checklist

### Security

- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_ENV=production`
- [ ] Use strong, unique passwords for `DB_PASSWORD` and `MYSQL_ROOT_PASSWORD`
- [ ] Use `https://` in `APP_URL`
- [ ] Configure SSL/TLS certificate (nginx reverse proxy recommended)
- [ ] Restrict database port (33306) to trusted IPs only
- [ ] Set strong `SESSION_DOMAIN`

### Performance

- [ ] Set `LOG_LEVEL=info` or higher
- [ ] Change `CACHE_STORE` from `file` to `redis` or `memcached`
- [ ] Change `SESSION_DRIVER` from `file` to `database` or `redis`
- [ ] Change `QUEUE_CONNECTION` from `sync` to `database`
- [ ] Configure appropriate `LOG_CHANNEL` with log rotation

### Backup & Monitoring

- [ ] Set up automated database backups
- [ ] Configure application logging/monitoring
- [ ] Monitor disk space for `mfg-db-data` volume
- [ ] Set up health checks for containers

## Environment Variables Reference

### Core Settings

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_NAME` | Many Faced God | Application name |
| `APP_ENV` | local | Environment (local/production) |
| `APP_DEBUG` | false | Debug mode (NEVER true in production) |
| `APP_URL` | http://localhost:8765 | Full URL of your application |
| `APP_KEY` | (empty) | Encryption key (auto-generated) |

### Database Settings

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_CONNECTION` | mysql | Database type |
| `DB_HOST` | db | Database host (use 'db' for Docker) |
| `DB_PORT` | 3306 | Database port (internal) |
| `DB_DATABASE` | many_faced_god | Database name |
| `DB_USERNAME` | mfg_user | Database user |
| `DB_PASSWORD` | secret | Database password (CHANGE IN PRODUCTION) |

### Session & Cache

| Variable | Default | Description |
|----------|---------|-------------|
| `SESSION_DRIVER` | file | Session storage (file/database/redis) |
| `SESSION_DOMAIN` | null | Session domain cookie |
| `CACHE_STORE` | file | Cache backend (file/database/redis) |

## Docker Compose Configuration

### Services

- **app**: PHP-FPM application container
- **webserver**: Nginx web server (port 8765)
- **db**: MySQL 8.0 database (port 33306)

### Ports

- **8765**: Web interface (HTTP)
- **33306**: MySQL database (external access)
- **9000**: PHP-FPM (internal only)

### Volumes

- **mfg-db-data**: Persistent database storage
- **./** : Application code (mounted from host)

## Advanced Configuration

### SSL/TLS with Reverse Proxy

For production with SSL, use an nginx reverse proxy in front of the Docker setup:

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    location / {
        proxy_pass http://localhost:8765;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}
```

### Using Redis for Caching/Sessions

Add to `docker-compose.yml`:

```yaml
redis:
  image: redis:7-alpine
  container_name: mfg-redis
  networks:
    - mfg-network
  command: redis-server --appendonly yes
  volumes:
    - mfg-redis-data:/data
```

Update `.env`:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=redis
```

### Database Backups

```bash
# Manual backup
docker-compose exec db mysqldump -u mfg_user -p many_faced_god > backup.sql

# Automated daily backup
0 2 * * * docker-compose -f /path/to/docker-compose.yml exec -T db mysqldump -u mfg_user -p many_faced_god > /backups/db-$(date +\%Y\%m\%d).sql
```

## Troubleshooting

### Encryption Error: "Unsupported cipher or incorrect key length"

**Symptom**: Application shows 500 error with "Unsupported cipher or incorrect key length" in logs.

**Cause**: Missing or improperly formatted APP_KEY in production `.env` file.

**Solution**:

```bash
# 1. Check if APP_KEY exists
grep "^APP_KEY=" .env

# 2. Generate a new key
docker-compose run --rm app php artisan key:generate --show --no-ansi > /tmp/newkey.txt
NEW_KEY=$(cat /tmp/newkey.txt | tr -d '\n\r')

# 3. Update .env on host
sed -i "s|^APP_KEY=.*|APP_KEY=$NEW_KEY|" .env

# 4. Copy into container (if using named volumes)
docker cp .env $(docker-compose ps -q app):/var/www/html/.env

# 5. Clear config cache and restart
docker-compose exec app php artisan config:clear
docker-compose restart app

# 6. Verify
docker-compose exec app php artisan tinker --execute="echo 'Key: ' . config('app.key');"
```

**Common Issues:**
- ❌ Empty `APP_KEY=` in `.env`
- ❌ Missing `base64:` prefix
- ❌ ANSI color codes in key (use `--no-ansi` flag)
- ❌ Key not copied into app container when using named volumes

**Prevention**: The CI/CD pipeline now automatically validates and generates APP_KEY if missing.

### Cache Configuration Error: "Database file at path does not exist"

**Symptom**: Cache operations fail with SQLite database errors.

**Solution**: Update cache driver in `.env`:

```bash
# Update to file-based cache
sed -i 's/^CACHE_STORE=.*/CACHE_STORE=file/' .env
sed -i 's/^CACHE_DRIVER=.*/CACHE_DRIVER=file/' .env

# Ensure cache directories exist
docker-compose exec app mkdir -p storage/framework/cache/data
docker-compose exec app chown -R www-data:www-data storage/framework
docker-compose exec app chmod -R 775 storage/framework

# Restart
docker-compose restart app
```

### Application not accessible

1. Check containers are running: `docker-compose ps`
2. Check logs: `docker-compose logs webserver`
3. Verify port mapping: `netstat -tlnp | grep 8765`

### Database connection errors

1. Check database is running: `docker-compose ps`
2. Verify credentials in `.env`
3. Check logs: `docker-compose logs db`

### Permission issues

```bash
# Fix ownership
docker-compose exec -T app chown -R www-data:www-data /var/www/html/storage
docker-compose exec -T app chmod -R 775 /var/www/html/storage
```

### Cache/Session issues

Clear all caches:

```bash
docker-compose exec -T app php artisan cache:clear
docker-compose exec -T app php artisan config:clear
docker-compose exec -T app php artisan view:clear
```

## Maintenance

### APP_KEY Rotation (When Needed)

**When to rotate:**
- Security breach or suspected key exposure
- Moving from development to production
- Compliance requirements

**IMPORTANT**: Rotating APP_KEY will invalidate:
- All encrypted session data (users will be logged out)
- All encrypted database fields
- All signed/encrypted cookies
- Password reset tokens

**Rotation Procedure**:

```bash
# 1. Back up current key
grep "^APP_KEY=" .env > .env.key.backup

# 2. Generate new key
docker-compose run --rm app php artisan key:generate --show --no-ansi

# 3. Update .env with new key
# (Copy the generated key and update manually)

# 4. If you have encrypted database fields, decrypt with old key first
# APP_KEY=<old-key> php artisan migrate:decrypt
# APP_KEY=<new-key> php artisan migrate:encrypt

# 5. Copy .env into container and restart
docker cp .env $(docker-compose ps -q app):/var/www/html/.env
docker-compose exec app php artisan config:clear
docker-compose restart app

# 6. Verify
docker-compose exec app php artisan tinker --execute="echo config('app.key');"
```

**Post-Rotation Checklist**:
- [ ] All users are logged out (expected)
- [ ] New sessions work correctly
- [ ] Encrypted fields are accessible
- [ ] No encryption errors in logs

### Verification Commands

After any deployment or maintenance, run these commands to verify everything is working:

```bash
# 1. Check APP_KEY is set and properly formatted
docker-compose exec app php artisan tinker --execute="echo 'Key: ' . config('app.key');"

# 2. Verify cipher configuration
docker-compose exec app php artisan tinker --execute="echo 'Cipher: ' . config('app.cipher');"

# 3. Test encryption works
docker-compose exec app php artisan tinker --execute="echo encrypt('test');"

# 4. Check container health
docker-compose ps

# 5. View recent logs for errors
docker-compose logs app --tail=50 | grep -i error
```

### Update Application

```bash
# Pull latest code
git pull origin main

# Run migrations
docker-compose exec -T app php artisan migrate --force

# Restart containers
docker-compose restart app webserver
```

### View Logs

```bash
# Application logs
docker-compose logs -f app

# Nginx logs
docker-compose logs -f webserver

# Database logs
docker-compose logs -f db
```

### Monitor Disk Space

```bash
# Check volume sizes
docker volume ls
docker volume inspect many-faced-god_mfg-db-data

# Check disk usage
docker system df
```

## Support

For issues or questions about deployment, refer to:
- Laravel Documentation: https://laravel.com/docs
- Docker Documentation: https://docs.docker.com/
- Docker Compose Documentation: https://docs.docker.com/compose/

---

**Last Updated**: 2024
**Version**: 1.0
