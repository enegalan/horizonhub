# Horizon Hub

Centralized dashboard for monitoring Laravel Horizon jobs across multiple services. Provides real-time metrics, job management, and alerting.

## Features

- **Parity with Horizon** per service: view jobs, failed jobs, queues
- **Centralized monitoring** across unlimited services
- **Live UI refresh** via server-sent events (SSE)
- **Alerting** for failures and performance (email, Slack)
- **HTTP-based integration**: configure each service's Horizon HTTP API endpoint in Horizon Hub

## Requirements

- PHP 8.2+
- Laravel 12
- MySQL 8 or SQLite
- Redis

## Quick start (Docker)

```bash
git clone https://github.com/enegalan/horizonhub.git
cd horizonhub
cp .env.example .env
# Edit .env: set APP_KEY (generate with `php artisan key:generate`), DB_*, REDIS_*
docker compose up -d
docker compose exec hub php artisan key:generate
docker compose exec hub php artisan migrate --force
```

Open http://localhost (redirects to the Horizon dashboard at `/horizon`). Register a service under **Services** and configure its base URL so Horizon Hub can reach its Horizon HTTP API.

## Hub configuration

- **Database**: `DB_*` in `.env` (MySQL or SQLite)
- **Redis**: `REDIS_*` when using Redis for cache/sessions
- **Alerts**: configure SMTP (`MAIL_*`) and/or Slack webhooks in alert rules

## Service registration

1. In the Hub UI, go to **Services**.
2. Click **Register service**: enter a name and the service **Base URL** (e.g. `https://my-app.example.com`).
3. Copy the generated **API key** (shown once).
4. On the Laravel service, ensure Laravel Horizon is installed and its HTTP API is reachable from Horizon Hub (for example, `/horizon/api`).
See [examples/README.md](examples/README.md) for demo apps that push events to the Hub.

## License

MIT
