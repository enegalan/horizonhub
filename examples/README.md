# Demo Laravel apps for Horizon Hub

Example Laravel apps with Horizon and the Horizon Hub Agent, used to test the hub with real job events (processed, failed, multiple queues) in development. The hub UI is Livewire-based at `/horizon` (root `/` redirects there); no authentication is required.

## Overview

- **demo-app**: One Laravel app codebase run as three containers (`demo-app-1`, `demo-app-2`, `demo-app-3`), each with its own Redis and a distinct service name in the hub (Demo Orders, Demo Notifications, Demo Reports).
- **Jobs**: `ProcessOrder` (default queue), `SendNotification` (notifications queue), `GenerateReport` (reports queue, always fails). A scheduled task dispatches these every minute so events keep flowing.
- **Hub registration**: The hub must have the three demo services registered (name, api_key, base_url). Use `DemoServicesSeeder` when starting with the demo profile.

## Requirements

- Docker and Docker Compose
- From the **repository root** (parent of `examples/`)

## Local development (demo-app without Docker)

The demo-app depends on the Horizon Hub Agent via a path repository (`../../packages/horizon-hub-agent`). From the repo root run:

```bash
cd examples/demo-app && composer install
```

So that the path resolves, Composer must be run from `examples/demo-app` (the agent is at `../../packages/horizon-hub-agent` from there). The Docker build uses the repo root as context and copies the agent to `/var/packages/horizon-hub-agent` so the same path works inside the container.

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

4. You can use the hub UI to retry or delete failed jobs and to pause/resume queues; the hub will call the demo apps at `http://demo-app-{1,2,3}:80` (inside the Docker network).

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
docker exec horizon-hub php artisan db:seed --class=DemoServicesSeeder --force
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

## Troubleshooting: jobs don't appear in the hub UI

The hub only shows jobs when the demo-app **agent** sends events to the hub (JobProcessed, JobFailed, JobProcessing). If no jobs appear:

1. **"Failed to connect to hub" in demo-app logs**  
   The demo-app container cannot reach the hub at `HORIZON_HUB_URL` (e.g. `http://hub`). Ensure:
   - The **hub** container is running (`docker compose --profile demo ps`).
   - Hub and demo-apps were started with the **same** `docker compose --profile demo up` so they share the `horizonhub` network and the hostname `hub` resolves to the hub container.
   - If you start demo-apps in another way (e.g. different compose or host), set `HORIZON_HUB_URL` to a URL reachable from the demo-app (e.g. host gateway IP or hub’s published port).

2. **"Invalid API key" or 401**  
   The hub has no service with the demo-app’s API key. Start the hub with `DEMO_SERVICES=1` or run:  
   `docker exec horizon-hub php artisan db:seed --class=DemoServicesSeeder --force`

3. **"HORIZON_HUB_URL or HORIZON_HUB_API_KEY not set"**  
   The agent skips sending events. Set these env vars in the demo-app (in docker-compose they are set per service).

## Stopping

```bash
docker compose --profile demo down
```

To remove demo app images as well:

```bash
docker compose --profile demo down
docker rmi horizonhub-demo-app-1 horizonhub-demo-app-2 horizonhub-demo-app-3 2>/dev/null || true
```
