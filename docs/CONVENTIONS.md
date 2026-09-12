# Horizon Hub — Conventions

Repository-specific conventions that expand on the rules in [AGENTS.md](../AGENTS.md). When in doubt, the root [AGENTS.md](../AGENTS.md) takes precedence.

## Language and formatting

- Formatting is enforced by **Laravel Pint** (`pint.json`): Laravel preset, ordered class elements, ordered imports, single quotes, trailing commas in multiline constructs, left-aligned PHPDoc. Check with `./vendor/bin/pint --test`.
- Indentation and encoding follow `.editorconfig` (4 spaces for PHP/Blade, 2 for JS/YAML); Markdown is exempt from trailing-whitespace trimming.

## PHP and Laravel conventions

### Layering

- Business logic lives in `app/Services/*` (domain services) and `app/Support/*` (reusable helpers/readers); keep controllers thin and Form Requests under `app/Http/Requests/Horizon/` (see [AGENTS.md](../AGENTS.md) for the general rules).
- Use the established **strategy patterns** for extension points: `AlertRuleStrategy` with `AlertRuleStrategyRegistry` for alert rules, `AlertNotifier` for notifiers.
- Follow the dependency-injection rule from [AGENTS.md](../AGENTS.md) with one existing exception: `HorizonClientHttpService::call()` / `HorizonClientApiService` are deliberately static-style facades for the HTTP proxy — follow them rather than refactoring.

### Models

- `app/Models` uses Eloquent idioms already present: `Attribute::make` accessors, local query scopes (`enabled()`, `disabled()`, `matchingTags()`), JSON casts for structured columns (`service_ids`, `tags`, `threshold`, `config`), and `HasFactory` with factories under `database/factories`.
- Keep model events minimal; the only model hook is a `saving` event on `Service`.

### Enums

- Domain enums live in `app/Enums/`, back `string`, and reuse the `HasOptions` trait (`label()`, `labels()`, `options()`, `values()`) instead of hardcoded string literals.
- **Reading cast attributes** returns the enum instance, so compare with `===` against the enum case (no `->value`), e.g. `$service->status === ServiceStatus::Online`. For scalar collections (un-cast primitives, `->pluck()`/`->values()` results) compare with the backing value instead (`ServiceStatus::Online->value`); a backed enum case is a separate object and never equals its backing string.
- **Writing model attributes** that have an enum cast: always pass the backing value explicitly (`ServiceStatus::Online->value`), including `create()`/`update()`/`fill()`/`forceFill()`. The cast tolerates the raw enum, but the codebase standard is `->value` for clarity and consistency.
- **Query builder / raw SQL** (`Model::where(...)->update()`, `where`, `whereIn`, `DB::`): casts are not applied, so you must bind the backing value (`ServiceStatus::Online->value`).
- **Plain structures** (array keys, JSON/SSE payloads, Blade/JS transport, factory definitions): enums have no implicit string conversion and cannot be array keys, so emit `->value` (or `->label()` for display labels).
- Fallback strategies that map to no concrete type return `null` from their `type()` (e.g. `NullRule::type()`), never a real enum case.

### Migrations

- Named with date + 6-digit sequence (e.g. `2026_03_25_000110_migrate_alert_service_id_to_service_ids.php`).
- Anonymous classes with readable `up()` / `down()`.
- Data migrations (normalization, backfill) are allowed and follow the same convention.
- Prefer additive migrations; destructive schema changes require review.

### Error handling

- Log actionable context without exposing secrets or internal details to the browser.
- Respect the proxy's HTTP semantics: `401`/`403`/`419` skip the failure cooldown; `429`/`502`/`503`/`504` may be retried on GET (see the [HORIZONHUB.md FAQ](HORIZONHUB.md#what-http-statuses-does-the-proxy-treat-specially)).

## Frontend conventions

- Blade + Alpine.js + Turbo; Tailwind CSS v3 with the CSS-variable theme in `resources/css/app.css`.
- ES modules under `resources/js/`: reusable behavior in `components/`, page logic in `horizon/`, low-level utilities in `lib/`.
- Wire ECharts to `window.echarts` rather than importing it directly.
- Vite inputs are declared in `vite.config.js`; run `npm run lint` (ESLint) before committing.

## Testing conventions

- PHPUnit 12; unit tests (`tests/Unit`) cover services, strategies, calculators, helpers, and models; feature tests (`tests/Feature`) cover pages and actions.
- Doubles (Mockery), determinism, and coverage expectations follow [AGENTS.md](../AGENTS.md); mirror the patterns already present in `tests/Unit` and `tests/Feature`.

## Static analysis

- **PHPStan (Larastan) at level 5**, paths `app` and `tests` (`phpstan.neon`). Run `./vendor/bin/phpstan` before committing.
