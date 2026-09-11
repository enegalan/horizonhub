# Horizon Hub — Development

Guide for setting up a local development environment and working on Horizon Hub. For architecture details see [ARCHITECTURE.md](ARCHITECTURE.md); for coding conventions see [CONVENTIONS.md](CONVENTIONS.md).

## Prerequisites

- PHP 8.4+
- Composer
- Node.js and npm (Laravel Vite build)
- MySQL 8.0 or SQLite
- Redis (optional locally; used for cache/sessions in Docker)

> Note: `laravel/horizon` is **not** required here. Horizon Hub is a monitoring client that calls each remote service's existing Horizon HTTP API.

## Local setup

```bash
git clone https://github.com/enegalan/horizonhub.git
cd horizonhub
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Database — the default `.env.example` uses SQLite:

```bash
touch database/database.sqlite
php artisan migrate
```

For MySQL, set `DB_CONNECTION=mysql`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env` before migrating.

Build frontend assets (with watch in a terminal):

```bash
npm run dev        # watch mode
npm run build      # production build
```

## Running the application

```bash
php artisan serve
```

Open http://localhost:8000 — `/` redirects to `/horizon`.

Background processes the scheduler needs (for alerts and service staleness):

```bash
php artisan schedule:work
php artisan queue:work
```

The `schedule:work` process runs the commands registered in `routes/console.php`; see [ARCHITECTURE.md — Scheduler](ARCHITECTURE.md#scheduler). In Docker both run automatically via the entrypoint.

## Verifying changes

Before committing, run the standard checks listed in [AGENTS.md](../AGENTS.md) (`composer test`, `./vendor/bin/pint`, `./vendor/bin/phpstan`, `npm run lint`). The custom `hh:*` artisan commands are documented in [ARCHITECTURE.md — Scheduler](ARCHITECTURE.md#scheduler).

## Working with real Horizon services

Register each service under **Services** (`/horizon/services`) with `base_url`, optional `public_url` and `headers`, then use **Test connection**. The integration model is described in [HORIZONHUB.md — Integration requirements](HORIZONHUB.md#integration-requirements), with the UI steps in [GUIDE.md — Connecting services](GUIDE.md#connecting-services).

## Testing workflows

```bash
php artisan test --filter=ServiceControllerTest
```

Doubles, environment details, and expectations follow [CONVENTIONS.md — Testing conventions](CONVENTIONS.md#testing-conventions) and [AGENTS.md](../AGENTS.md).

## Adding a feature

1. Add a migration (see [CONVENTIONS.md — Migrations](CONVENTIONS.md#migrations)) and/or a Form Request under `app/Http/Requests/Horizon/`.
2. Put business logic in a service (`app/Services/`) or helper (`app/Support/`) and keep the controller thin, per [CONVENTIONS.md](CONVENTIONS.md).
3. Add a Blade partial under `resources/views/horizon/` and wire any Alpine/JS module under `resources/js/`.
4. For live updates, mirror the stream route in `routes/streams.php` and reuse/extend a `Builds*Streams` trait (see [ARCHITECTURE.md — Stream (SSE) path](ARCHITECTURE.md#stream-sse-path)).
5. Add unit and feature tests, then run the verification checks above.
6. Document user-facing changes in [GUIDE.md](GUIDE.md) and record notable trade-offs as ADRs under `docs/decisions/`.

## Docker / compose

Full stack (hub + MySQL + Redis):

```bash
docker compose up -d
```

Then follow the key/migrate steps from the [README.md](../README.md) quick start. Hub runs on port 80; the image and entrypoint behavior are described in [ARCHITECTURE.md — Deployment](ARCHITECTURE.md#deployment).

## Demo environment

`demo/` contains a separate stack with the hub plus three fake Horizon apps (`demo-app-*` on ports 8081–8083). Useful for end-to-end testing of monitoring, metrics, and alerts without real workloads:

```bash
cd demo
docker compose up -d
```

## CI

`npm run build`, lint (pint = `--test`, phpstan, eslint), `composer validate --strict`, and `php artisan test` run on every push to `main` and on PRs (`.github/workflows/ci.yml`). Run the same commands locally before pushing.

## Troubleshooting

- **Jobs/queues show nothing**: confirm the service is enabled and `base_url` is reachable from the Horizon Hub host; use **Test connection**.
- **Service stuck offline**: check `HORIZON_HUB_STALE_SERVICE_MINUTES` / `HORIZON_HUB_DEAD_SERVICE_MINUTES` and that the scheduler runs.
- **401/403 on job retry**: add the required `headers` to the service; Horizon Hub does not authenticate on its own.
- **CSS/JS changes not showing**: ensure `npm run dev` or `npm run build` produced `public/build` assets.
- **Tests fail on MySQL but pass on SQLite**: keep the test suite SQLite-only; the Docker stack uses MySQL by design.

## Related documents

- [README.md](../README.md) — install and quick start
- [ARCHITECTURE.md](ARCHITECTURE.md) — internal architecture and data flow
- [CONVENTIONS.md](CONVENTIONS.md) — coding and testing conventions
- [GUIDE.md](GUIDE.md) — end-user manual
- [AGENTS.md](../AGENTS.md) — repository instructions for agents