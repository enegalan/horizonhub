<p align="center">
  <img src="art/logo.png" width="360" alt="Horizon Hub" />
</p>

# Introduction

Centralized dashboard for monitoring Laravel Horizon jobs across multiple services. Provides real-time metrics, job management, and alerting.

<p align="center">
  <img src="art/dashboard.png" width="600" alt="Horizon Hub Jobs page" />
</p>

## Features

- **Everything in one place**: monitor all your Horizon services from a single dashboard
- **HTTP-based integration**: configure each service's Horizon HTTP API endpoint in Horizon Hub
- **Act fast under pressure**: retry, inspect, and manage jobs without switching tools
- **Stay informed proactively**: receive timely alerts when reliability or performance drops

## Requirements

- PHP 8.4+
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
