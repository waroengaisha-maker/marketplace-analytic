# Production deployment

Architecture:

Internet :443 -> Caddy -> Laravel image (Nginx + PHP-FPM)
                              |-> MySQL
                              |-> Redis

The production stack is separate from the development `compose.yaml`.

## Required .env.production

Do not commit this file.

Set at minimum:

```
APP_NAME=Marketplace Analytics
APP_ENV=production
APP_DEBUG=false
APP_KEY=<strong existing production key>
APP_URL=https://your-domain.example
APP_DOMAIN=your-domain.example
TRUSTED_HOSTS=your-domain.example
TRUSTED_PROXIES=
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=marketplace_analytics
DB_USERNAME=marketplace
DB_PASSWORD=<strong unique password>
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

Also set the Shopee credentials and the remaining application settings from `.env.example`.

Keep the existing production `APP_KEY` when deploying a new image. Never generate a new key during routine deployments.

## First deployment

1. Point DNS at the VPS.
2. Allow TCP 80/443 (UDP 443 is optional for HTTP/3).
3. Create `.env.production`.
4. Start the stack:

```bash
docker compose -f compose.production.yaml up -d --build
```

5. Run migrations:

```bash
docker compose -f compose.production.yaml exec laravel.test php artisan migrate --force
```

6. Optimize:

```bash
docker compose -f compose.production.yaml exec laravel.test php artisan optimize
```

7. Verify:

```bash
docker compose -f compose.production.yaml ps
curl -fsS https://your-domain.example/up
```

Caddy obtains and renews the HTTPS certificate automatically.

## Deploy a new version

```bash
docker compose -f compose.production.yaml up -d --build --no-deps laravel.test queue
docker compose -f compose.production.yaml exec laravel.test php artisan migrate --force
docker compose -f compose.production.yaml exec laravel.test php artisan optimize
docker compose -f compose.production.yaml exec laravel.test php artisan queue:restart
```

The queue worker is long-lived and must be restarted after code changes.

## Backup

Create a MySQL backup:

```bash
./deploy/backup-mysql.sh
```

Copy backups off the VPS. Docker volumes are persistence, not backups.

Restore:

```bash
./deploy/restore-mysql.sh backups/mysql-YYYYMMDD-HHMMSS.sql.gz
```

Test restores on a separate database/server periodically.

## Important

Do **not** run `docker compose down -v` in production unless you intentionally want to delete persistent volumes.

Do not expose MySQL, Redis, or Adminer to the public Internet.
