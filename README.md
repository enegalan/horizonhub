<p align="center">
  <img src="public/logo.svg" width="300" alt="Horizon Hub" />
</p>
# Horizon Hub

Centralized dashboard for monitoring Laravel Horizon jobs across multiple services. Provides real-time metrics, job management, and alerting.

<p align="center">
  <img src="art/dashboard.png" width="600" alt="Horizon Hub Jobs page" />
</p>

## Features

- **Parity with Horizon** per service: view jobs, failed jobs, queues
- **Centralized monitoring** across unlimited services
- **Live UI** via server-sent events (SSE)
- **Alerting** for failures and performance
- **HTTP-based integration**: configure each service's Horizon HTTP API endpoint in Horizon Hub

## Requirements

- PHP 8.2+
- Laravel 12
- Composer
- Node.js and npm
- MySQL 8 or SQLite
- Redis

## Quick start

```bash
git clone https://github.com/enegalan/horizonhub.git
cd horizonhub
cp .env.example .env
# Edit .env: set APP_KEY (generate with `php artisan key:generate`), DB_*, REDIS_*
docker compose up -d
docker compose exec hub php artisan key:generate
docker compose exec hub php artisan migrate --force
```

## Configuration

- **Database**: `DB_*` in `.env` (MySQL or SQLite)
- **Redis**: `REDIS_*` when using Redis for cache/sessions
- **Alerts**: configure SMTP (`MAIL_*`) and/or Slack webhooks in alert rules

## Testing and code style

```bash
composer test
```

Formatting (Laravel Pint):

```bash
./vendor/bin/pint
```

## License

MIT
