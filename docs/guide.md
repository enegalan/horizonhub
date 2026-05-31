# Horizon Hub — User Guide

This guide explains how to use Horizon Hub day to day: navigation, monitoring, connecting services, alerting, and common workflows.

For what Horizon Hub is and how it integrates technically, see [horizonhub.md](horizonhub.md). For installation, see the [README](../README.md).

## Getting started

### Opening the app

- Visit Horizon Hub web app (e.g. `http://localhost`). The root path `/` redirects to **`/horizon`**, the main dashboard.
- `/dashboard` also redirects to the same place.

### Layout

- **Sidebar** (left):
  - Dashboard
  - Jobs
  - Queues
  - Services
  - Metrics
  - Alerts
  - Providers
- **Header toolbar** (top right):
  - **Hot reload** (circular arrows): when on, the page receives live updates without a full browser reload.
  - **Theme**:
    - Light
    - Dark
    - System appearance
- **Form drawer**: creating or editing Services, Alerts, and Providers opens a slide-over panel instead of leaving the list page. Closing the drawer returns you to the index you came from.

### Recommended setup order

1. Register **Services** (Horizon app endpoints) and verify connectivity.
2. Create notification **Providers** (Slack or email channels).
3. Define **Alerts** and attach providers.
4. Use **Dashboard**, **Jobs**, **Queues**, and **Metrics** for ongoing monitoring.

---

## Dashboard

**Path:** `/horizon`

The dashboard is the default landing page and the best place for a quick health check across all connected services.

### KPI cards

- **Jobs (last minute / hour)** — recent throughput across enabled services.
- **Failed jobs (7 days)** — failure volume in the rolling window.
- **Services online** — how many registered services are currently reachable.

### Panels

| Panel            | What it shows                                  | Typical next step                               |
|------------------|------------------------------------------------|-------------------------------------------------|
| Service health   | Status per service (online, stand-by, offline) | Open **Services** for detail or connection test |
| Recent alerts    | Latest alert activity                          | Open **Alerts** for rules or delivery logs      |
| Current workload | Queue backlog summary                          | Open **Queues** for pending jobs                |

Use the dashboard during incidents to see whether the problem is global (many services) or isolated (one service offline or one queue growing).

---

## Connecting services

**Path:** `/horizon/services`

Services are the foundation of Horizon Hub. Every monitoring view, metric, and alert depends on correctly registered Horizon endpoints.

### Register a service

1. Open **Services** and choose **Add service**.
2. Fill in **Connection details**:

| Field | Purpose                                                                                                                                                           |
|-----------------------------|---------------------------------------------------------------------------------------------------------------------------------------------|
| **Name**                    | Unique label shown across Horizon Hub (e.g. `billing-api`).                                                                                 |
| **Base URL**                | Internal URL Horizon Hub uses to call the Horizon HTTP API. Must be reachable from Horizon Hub server. Trailing slashes are normalized.     |
| **Public URL** (optional)   | URL used when you open the native Horizon dashboard in a browser. If empty, Base URL is used.                                               |
| **Tags**                    | Optional labels for filtering Jobs, Queues, and Metrics.                                                                                    |
| **HTTP headers** (optional) | Name/value pairs sent with API requests (e.g. API keys, basic auth). Reserved names such as `host` and `connection` cannot be set manually. |

1. Save the service.

### After registration

| Action              | When to use it                                                                      |
|---------------------|-------------------------------------------------------------------------------------|
| **Test connection** | Confirms Horizon Hub can reach Horizon's API endpoint.                              |
| **Toggle enabled**  | Disable polling without deleting the service (useful during maintenance).           |
| **Open Horizon**    | Opens the remote Horizon Dashboard UI in a new tab using the configured Public URL. |
| **Edit**            | Update URLs, tags, or headers.                                                      |
| **Delete**          | Removes the service from Horizon Hub (remote Horizon is unaffected).                |

### Service detail page

Open a service card to see a **single-service** view:

- Stats and supervisor health for that app only.
- Workload table for its queues.
- Job lists (processing, completed, failed).

### Service status meanings

| Status       | Meaning                                                                                                                     |
|--------------|-----------------------------------------------------------------------------------------------------------------------------|
| **Online**   | Recent successful contact with Horizon API.                                                                                 |
| **Stand-by** | No recent events within the stale window (`stale_service_minutes`, default 5 minutes); supervisors may be treated as stale. |
| **Offline**  | No signal within the dead window (`dead_service_minutes`, default 15 minutes) or connectivity failure.                      |

---

## Notification providers

**Path:** `/horizon/providers`

Providers define **where** alert notifications go. Create providers **before** or while configuring alerts.

### Slack provider

- **Type:** Slack.
- **Webhook URL:** Slack incoming webhook for the target channel.
- Horizon Hub POSTs alert payloads to that URL when a rule fires.

### Discord provider

- **Type:** Discord.
- **Webhook URL:** Discord channel webhook (Channel settings → Integrations → Webhooks).
- Horizon Hub POSTs embed payloads to that URL when a rule fires.

### Email provider

- **Type:** Email.
- **Recipients:** one or more addresses (comma-separated or multiple entries depending on the form).
- Requires Horizon Hub's mail transport to be configured (`MAIL_*` in the `.env`) at deploy time.

---

## Alerts

**Path:** `/horizon/alerts`

Alerts watch enabled services and send notifications through attached providers when conditions match.

### Create an alert

1. Ensure at least one **enabled service** and one **provider** exist.
2. Open **Alerts** → **Add alert**.
3. Configure:

| Area                         | Description                                                                                                         |
|------------------------------|---------------------------------------------------------------------------------------------------------------------|
| **Name**                     | Descriptive title for operators.                                                                                    |
| **Services**                 | Which services this rule applies to (multi-select).                                                                 |
| **Rule type**                | Condition to evaluate (see table below).                                                                            |
| **Threshold**                | Type-specific limits (`count`, `seconds`, `minutes`).                                                               |
| **Queue / job patterns**     | Optional narrowing for failure, latency, and queue-blocked rules (OR rows for queue patterns; job name substrings). |
| **Providers**                | One or more notification providers.                                                                                 |
| **Email interval (minutes)** | Minimum time between sends for this alert; `0` means notify on every evaluation that still matches.                 |
| **Enabled**                  | Master switch for the rule.                                                                                         |

### Rule types

| Name                            | What it detects                                                             |
|---------------------------------|-----------------------------------------------------------------------------|
| **Failure count in window**     | At least *N* failures in the last *M* minutes (optional queue/job filters). |
| **Avg execution time exceeded** | Average runtime above *S* seconds in the last *M* minutes.                  |
| **Queue blocked**               | Queue has had no progress for *M* minutes.                                  |
| **Worker offline**              | Worker absent for *M* minutes.                                              |
| **Supervisor offline**          | Supervisor absent for *M* minutes.                                          |
| **Horizon offline**             | Horizon API unreachable or not running for the service for *M* minutes.     |

Defaults for counts, seconds, and minutes come from Horizon Hub configuration (`horizonhub.alerts` in `config/horizonhub.php`) when you do not override them in the form.

### Alert index actions

| Action             | Effect                                                                       |
|--------------------|------------------------------------------------------------------------------|
| **Toggle enabled** | Turn rule on/off without deleting.                                           |
| **Evaluate**       | Run this alert immediately.                                                  |
| **Evaluate all**   | Queue evaluation for every enabled alert; progress is polled until complete. |
| **Edit / Delete**  | Change rule or remove it.                                                    |

### Alert detail page

Open an alert to see:

- Rule summary and linked services/providers.
- Send charts (24 hours, 7 days, 30 days).
- **Delivery log** — history of notifications with filters.
- **Retry** on a failed delivery attempts send again.

### Background evaluation

Horizon Hub runs `hh:evaluate-alerts` **every minute** via the Laravel scheduler. Manual evaluation is useful after changing thresholds or testing a new rule.

Pending events may be batched for a single notification according to `pending_ttl_minutes` in configuration.

---

## Jobs

**Path:** `/horizon/jobs`

The Jobs page aggregates work across all enabled services.

### Sections

Three collapsible blocks:

1. **Processing** — jobs currently running or pending pickup.
2. **Completed** — recently finished jobs.
3. **Failed** — jobs that exhausted retries or errored.

### Filters

- **Services** — multiselect which backends to include.
- **Tags** — limit to services carrying selected tags.
- **Search** — text match on queue name, job name, or job UUID.

### Job detail

**Path:** `/horizon/jobs/{job}`

Shows payload, exception stack (for failures), metadata, retry history, and contextual links.

### Actions

| Action              | Description                                                                          |
|---------------------|--------------------------------------------------------------------------------------|
| **Retry**           | Re-queue a single failed job via the remote Horizon API.                             |
| **Batch retry**     | From the failed section, open the batch modal to retry multiple failed jobs at once. |
| **Open in Horizon** | Deep link to the job in the native Horizon dashboard for that service.               |

---

## Queues

**Path:** `/horizon/queues`

Shows **pending** jobs grouped by queue across services.

- **Stats** — number of queues and total pending jobs in view.
- **Filters** — same service/tag concepts as Jobs where applicable.
- Use this page when backlog grows but you need queue-level detail rather than individual job UUIDs.

---

## Metrics

**Path:** `/horizon/metrics`

Analytics view with service and tag filters.

### KPIs

- Jobs per minute and per hour.
- Failed jobs (7 days).
- Failure rate (24 hours).

### Charts

- Jobs per hour.
- Failure rate percentage.
- Job runtime scatter plot.
- Queue wait times (top queues).

### Tables

- **Current workload** — queues with pending counts.
- **Supervisors** — supervisor processes across services.

Use Metrics for trend analysis; use Dashboard and Jobs for immediate incident triage.

---

## Live updates (hot reload)

When **hot reload** is enabled in the header:

- Horizon Hub opens an SSE connection to a stream URL that mirrors the current page.
- Changed fragments arrive as Turbo Streams and patch the DOM.
- If updates stop (network blip), the client reconnects with backoff.

Turn hot reload off if you prefer a static page or need to reduce background traffic.

---

## Recommended workflows

### Onboard a new Laravel application

1. Confirm Horizon is installed and the HTTP API is reachable from the Horizon Hub host (`/horizon/api/stats` or your configured paths).
2. **Services** → add service with correct Base URL and Public URL.
3. **Test connection** until status is online.
4. Add **tags** if you will filter Jobs/Metrics by team or product.
5. Optionally add **Providers** and **Alerts** for that service ID.

### Respond to a failed job

1. **Dashboard** or **Jobs** → open **Failed**.
2. Filter by service or search by UUID.
3. Open job detail → read exception and payload.
4. **Retry** from Horizon Hub, or **Open in Horizon** if you need the full Horizon Dashboard UI.
5. For many failures, use **Batch retry** from the Jobs index.

### Respond to “Horizon offline” or “service offline”

1. **Services** → find the affected service → **Test connection**.
2. Verify the remote app: Horizon process, Redis, network from Horizon Hub to `base_url`.
3. Check optional **HTTP headers** if the API requires auth.
4. On the service detail page, review supervisors and workload after connectivity returns.

### Reduce alert noise

- Increase **Email interval** on chatty rules.
- Tighten **queue** or **job** patterns so only relevant failures match.
- Disable non-essential alerts with **Toggle enabled** instead of deleting (preserves history).
- Use **Evaluate** after changes to confirm behavior before waiting for the scheduler.

---

## Troubleshooting

| Symptom                              | Things to check                                                                                                                                                 |
|--------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Test connection fails**            | Base URL reachable from Horizon Hub; correct path prefix; firewall; TLS; custom headers; remote Horizon auth (401/403/419 HTTP codes).                          |
| **No jobs or metrics for a service** | Service **enabled**; status online; Base URL correct; Scheduler and queue workers running on Horizon Hub itself.                                                |
| **Service stuck offline**            | Remote Horizon down; wrong URL; prolonged API errors; wait for next `hh:mark-stale-services-offline` cycle or test connection after fix.                        |
| **Alerts never fire**                | Alert and service both **enabled**; rule thresholds realistic; providers attached; Scheduler running `hh:evaluate-alerts`; mail config for email providers.     |
| **Slack works, email does not**      | Horizon Hub `MAIL_*` and mailer logs; recipient addresses on provider.                                                                                          |
| **Hot reload not updating**          | Toggle on in header; browser console/network for SSE errors; try manual refresh once.                                                                           |

---

## Appendix: route map

| Path                                 | Purpose                           |
|--------------------------------------|-----------------------------------|
| `/horizon`                           | Dashboard                         |
| `/horizon/jobs`                      | Jobs index                        |
| `/horizon/jobs/{job}`                | Job detail                        |
| `/horizon/jobs/failed`               | Failed jobs list (batch retry UI) |
| `/horizon/queues`                    | Queues                            |
| `/horizon/services`                  | Services index                    |
| `/horizon/services/create`           | Create service                    |
| `/horizon/services/{service}`        | Service detail                    |
| `/horizon/services/{service}/edit`   | Edit service                      |
| `/horizon/metrics`                   | Metrics                           |
| `/horizon/alerts`                    | Alerts index                      |
| `/horizon/alerts/create`             | Create alert                      |
| `/horizon/alerts/{alert}`            | Alert detail                      |
| `/horizon/alerts/{alert}/edit`       | Edit alert                        |
| `/horizon/providers`                 | Providers index                   |
| `/horizon/providers/create`          | Create provider                   |
| `/horizon/providers/{provider}/edit` | Edit provider                     |

---

## See also

- [horizonhub.md](horizonhub.md) — product overview, architecture, agent FAQ
- [README.md](../README.md) — requirements, quick start, environment variables
- [decisions/](decisions/) — architecture decisions affecting deployment and security
