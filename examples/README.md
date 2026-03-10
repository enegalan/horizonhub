# Demo Laravel apps for Horizon Hub

Example Laravel apps with Horizon, used to test the hub with real job events (processed, failed, multiple queues) in development.

## Overview

- **demo-app**: One Laravel app codebase run as three containers (`demo-app-1`, `demo-app-2`, `demo-app-3`), each with its own Redis and a distinct service name in the hub (Demo Orders, Demo Notifications, Demo Reports).
- **Jobs**: `ProcessOrder` (default queue), `SendNotification` (notifications queue), `GenerateReport` (reports queue, always fails). A scheduled task dispatches these every minute so events keep flowing.
- **Hub registration**: The hub must have the three demo services registered (name, api_key, base_url). Use `DemoServicesSeeder` when starting with the demo profile.

## Requirements

- Docker and Docker Compose
- From the **repository root** (parent of `examples/`)

## Run hub with demo apps

1. Start the hub and all demo containers (and run the demo seeder on the hub):

   ```bash
   DEMO_SERVICES=1 docker compose --profile demo up -d
   ```

   `DEMO_SERVICES=1` makes the hub run `DemoServicesSeeder` on startup so the three demo services are created with the expected api_key and base_url. Omit it if the services are already in the database.

2. Wait for the hub and demo apps to be up (migrations, Horizon and scheduler running). Open the hub at http://localhost (port 80); you will be redirected to the Horizon dashboard at `/horizon`.

3. In the hub UI you should see:
   - **Services**: Demo Orders, Demo Notifications, Demo Reports (online after they push events).
   - **Jobs**: Processing/processed jobs on default and notifications; failed jobs from the reports queue.
   - **Queues**: default, notifications, reports per service.

4. You can use the hub UI to retry failed jobs; the hub will call the demo apps' Horizon HTTP APIs at `http://demo-app-{1,2,3}:80` (inside the Docker network).

## Run only the hub (no demo apps)

```bash
docker compose up -d
```

## Run a single demo app (e.g. for debugging)

From the repo root (use the `demo` profile so demo services are available):

```bash
docker compose --profile demo up -d mysql redis hub reverb redis-demo-1 demo-app-1
```

Then seed the demo service for app 1 once (if not already present):

```bash
docker exec horizonhub php artisan db:seed --class=DemoServicesSeeder --force
```

Or register "Demo Orders" manually in the hub UI with base URL `http://demo-app-1:80` and the api_key from `examples/demo-app/.env.example` (`demo-service-1-api-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`).

## API keys and base URLs

The seeder and demo app env use fixed 64-character api_keys so the agent and hub match:

| Service             | API key (64 chars)                                              | base_url              |
|---------------------|-----------------------------------------------------------------|-----------------------|
| Demo Orders         | `demo-service-1-api-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`     | http://demo-app-1:80  |
| Demo Notifications  | `demo-service-2-api-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`     | http://demo-app-2:80  |
| Demo Reports        | `demo-service-3-api-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`     | http://demo-app-3:80  |

These values are in `database/seeders/DemoServicesSeeder.php` and `examples/demo-app/.env.example`.

## Stopping

```bash
docker compose --profile demo down
```

To remove demo app images as well:

```bash
docker compose --profile demo down
docker rmi horizonhub-demo-app-1 horizonhub-demo-app-2 horizonhub-demo-app-3 2>/dev/null || true
```
