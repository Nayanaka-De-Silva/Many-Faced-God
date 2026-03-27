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
MYSQL_ROOT_PASSWORD=<strong-random-root-password>

# Session Configuration
SESSION_DRIVER=file         # Or database/redis for scaling
SESSION_DOMAIN=yourdomain.com  # Your domain

# Logging
LOG_LEVEL=info             # Use 'info' or higher in production
```

### 3. Generate Application Key

Generate a fresh application key:

```bash
docker-compose exec -T app php artisan key:generate
```

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
| `MYSQL_ROOT_PASSWORD` | secret-root | MySQL root password required by the database container |

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

### Update Application

```bash
# Pull latest code
git pull origin main

# Recreate containers from the freshly built images and wait for services
docker compose -p many-faced-god -f ./docker-compose.prod.yml \
  up --force-recreate -d --remove-orphans --wait --wait-timeout 120

# Wait briefly for app -> db connectivity, then run migrations
docker compose -p many-faced-god -f ./docker-compose.prod.yml \
  exec -T app sh -lc '
  i=0
  echo "Checking app -> db connectivity before running migrations..."
  until mysqladmin ping -h"${DB_HOST:-db}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent; do
    if [ "$i" -ge 10 ]; then
      echo "ERROR: database is not reachable from app after 30s" >&2
      exit 1
    fi
    i=$((i+1))
    echo "Waiting for app -> db readiness ($i/10)..."
    sleep 3
  done
  php artisan migrate --force
  '
```

For image-based production deploys, avoid `docker compose restart` as the primary rollout command. Restarting existing containers does not load newly built images, so it can leave the previous application version running even after a successful image build.

If you automate deployment through a CI/CD system such as Woodpecker, prefer `docker compose up --wait` when your Compose version supports it and your database service has a health check configured. In this project, keep a small follow-up readiness loop around migrations as well, because the DB health check only proves MySQL is responding inside the DB container itself. The extra loop confirms the app container can actually reach `db:3306` before `php artisan migrate --force` runs.

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
