# AGENTS

## Scope

These instructions apply to the whole repository.

## Tech stack

- PHP 8.4+
- Laravel 12
- PHPUnit 12
- Laravel Pint

## Development rules

- Keep all code and comments in English.
- Follow existing project formatting and naming conventions.
- Use spaces for indentation, never tabs.
- Do not introduce unrelated refactors.
- Preserve existing behavior unless the task explicitly asks to change it.
- Prefer Laravel helpers and framework conventions over custom utilities.
- Use dependency injection instead of static access where possible.

## Laravel conventions

- Keep controllers thin and move business logic to services/support classes.
- Validate request input with Form Requests.
- Use Eloquent and query builder patterns already used in the codebase.
- Handle errors explicitly and log actionable context.
- Avoid hardcoded values; use config files/constants when appropriate.

## Testing expectations

- Add or update unit/feature tests for behavioral changes.
- Keep tests focused and deterministic.
- Prefer Mockery patterns already present in `tests/Unit`.

## Useful commands

```bash
composer test
php artisan test
./vendor/bin/phpunit
./vendor/bin/pint
```

## Change boundaries

- Do not edit environment/secrets files.
- Do not remove unrelated code.
- Keep modifications minimal and task-focused.
