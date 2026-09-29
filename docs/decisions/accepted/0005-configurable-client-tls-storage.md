# ADR: Configurable client TLS storage location

- ID: ADR-0005
- Status: accepted
- Date: 2026-09-29
- Extends: ADR-0004

## Context

ADR-0004 hardcoded the client TLS storage to the `local` disk under `service-tls/`. Operators who keep private keys on a dedicated persisted volume, on an encrypted disk, or outside the default layout had no way to relocate them without patching code.

## Decision

Read the disk and root directory from `horizonhub.tls.disk` and `horizonhub.tls.root` (env `HORIZON_HUB_TLS_DISK` / `HORIZON_HUB_TLS_ROOT`), defaulting to the ADR-0004 values (`local` and `service-tls`). `ServiceTlsClientStorage::disk()` and `::root()` replace the former `DISK` and `DIRECTORY` constants; the per-service `{root}/{service_id}/` layout and everything else in ADR-0004 are unchanged.

## Rationale

- The ADR-0004 defaults stay the default, so existing installs need no migration.
- Relocating private keys becomes an env change instead of a code change or a rebuild.
- Keeps the storage location with the other operational keys in `config/horizonhub.php`.

## Consequences

- Changing `HORIZON_HUB_TLS_ROOT` orphans the relative paths already persisted in `services.tls_client_cert_path` and `services.tls_client_key_path`; those services must re-upload their certificates.
- Changing `HORIZON_HUB_TLS_DISK` moves every certificate, including files already extracted from PKCS#12 bundles.
- Both values are read from cached config, so they take effect at container start, not per request.

## Reopen triggers

This decision can be revisited only if this condition is met:

- Operators need a distinct TLS storage location per service rather than one global location.
