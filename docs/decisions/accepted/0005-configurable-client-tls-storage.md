# ADR: Configurable client TLS storage location

- ID: ADR-0005
- Status: superseded
- Superseded by: ADR-0006
- Date: 2026-09-29
- Extends: ADR-0004

## Context

ADR-0004 hardcoded the client TLS storage to the `local` disk under `service-tls/`. Operators who keep private keys on a dedicated persisted volume, on an encrypted disk, or outside the default layout had no way to relocate them without patching code.

## Decision

Superseded by ADR-0006 (fixed local disk; the disk is pinned to `local` because mTLS requires real local filesystem paths).

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
