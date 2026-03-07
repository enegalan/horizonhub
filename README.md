# Horizon Hub

Centralized dashboard for monitoring Laravel Horizon jobs across multiple services. Provides real-time metrics, job management (retry, delete, pause queues), and alerting (email and Slack).

## Features

- **Parity with Horizon** per service: view jobs, failed jobs, queues
- **Centralized monitoring** across unlimited services
- **Real-time updates** via Laravel Reverb (WebSockets)
- **Alerting** for failures and performance (email, Slack)
- **Agent-based architecture**: install the Agent on each Laravel service to push events to the Hub

## Requirements

- PHP 8.0+
- Laravel 12 (or 11)
- MySQL 8 or SQLite
- Redis (for Reverb broadcasting)

## Quick start (Docker)

```bash
git clone https://github.com/enegalan/horizonhub.git
cd horizonhub
cp .env.example .env
# Edit .env: set APP_KEY (generate with `php artisan key:generate`), DB_*, REDIS_*, REVERB_*
docker compose up -d
docker compose exec hub php artisan key:generate
docker compose exec hub php artisan migrate --force
```

Open http://localhost (redirects to the Horizon dashboard at `/horizon`). The UI is Livewire-based and does not require authentication. Register a service under **Services**, then install the Agent on your Laravel apps.

## Hub configuration

- **Database**: `DB_*` in `.env` (MySQL or SQLite)
- **Redis**: `REDIS_*` for cache and Reverb
- **Reverb**: `REVERB_*` and `BROADCAST_CONNECTION=reverb` for real-time dashboard
- **Alerts**: configure SMTP (`MAIL_*`) and/or Slack webhooks in alert rules

## Service registration

1. In the Hub UI, go to **Services**.
2. Click **Register service**: enter a name and the service **Base URL** (e.g. `https://my-app.example.com`).
3. Copy the generated **API key** (shown once).
4. On the Laravel service, install the Agent and set `HORIZON_HUB_URL`, `HORIZON_HUB_API_KEY`, and `HORIZON_HUB_SERVICE_NAME` in `.env`.

## Agent installation

On each Laravel service that runs Horizon:

```bash
composer require horizonhub/agent
php artisan horizonhub:install
```

In `.env`:

```
HORIZON_HUB_URL=https://your-hub.example.com
HORIZON_HUB_API_KEY=<key-from-hub>
HORIZON_HUB_SERVICE_NAME=my-service
```

Ensure the service’s `base_url` is reachable by the Hub (for retry/delete/pause/resume actions).

See [examples/README.md](examples/README.md) for demo apps that push events to the Hub.

## License

MIT
