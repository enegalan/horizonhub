# Comparisons

Horizon Hub is a **centralized dashboard** for monitoring multiple [Laravel Horizon](https://laravel.com/docs/horizon) instances across different Laravel applications. It does not replace Horizon on each app; it reads each app's existing Horizon HTTP API and unifies jobs, queues, workers, and alerts.

## At a glance

| Feature                                      | Horizon Hub                         | Laravel Horizon (built-in) | Laravel Pulse        | Laravel Nightwatch            | Prometheus + Grafana                                   |
| -------------------------------------------- | ----------------------------------- | -------------------------- | -------------------- | ----------------------------- | ------------------------------------------------------ |
| **Multi-app / multi-service monitoring**     | Yes                                 | No (one per-app)           | Limited / app-scoped | APM-focused, per-app          | Possible but requires custom exporters/instrumentation |
| **Uses existing Horizon API**                | Yes                                 | N/A                        | No                   | No                            | No                                                     |
| **Works without shared Redis**               | Yes                                 | N/A                        | Depends              | Depends                       | Yes                                                    |
| **Centralized alerts across apps**           | Yes                                 | Per-app only               | Limited              | Has alerting but APM-centric  | Yes                                                    |
| **Retry/inspect/forget jobs from one place** | Yes (proxied to each service's API) | Yes (per app)              | Limited              | Limited                       | No                                                     |
| **Self-hosted, Docker ready**                | Yes                                 | Yes                        | Yes                  | SaaS + self-host options vary | Yes                                                    |

## When to choose what

- **Choose Horizon Hub** if you run 2+ Laravel apps with Horizon and want one dashboard to monitor them all, with unified alerts and the ability to retry jobs without switching tabs.
- **Choose Laravel Horizon** if you only have one app or don't need cross-app monitoring.
- **Choose Laravel Pulse** if you need broader app health (requests, jobs, slow queries, exceptions) in addition to queues.
- **Choose Laravel Nightwatch** if you need full APM/tracing and don't mind a more observability-focused tool.
- **Choose Prometheus + Grafana** if you already have metrics infrastructure and want to build custom dashboards/alerts.

## Notes

- Horizon Hub is not a queue worker and doesn't process jobs. Each Laravel app still runs Horizon independently.
- Integration requires each remote app to have Horizon's HTTP API reachable from Horizon Hub.
- For details on setup, see the [README](README.md) and [documentation](docs/HORIZONHUB.md).
