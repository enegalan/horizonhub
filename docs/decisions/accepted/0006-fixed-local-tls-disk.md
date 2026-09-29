# ADR: Fixed local disk for client TLS storage

- ID: ADR-0006
- Status: accepted
- Date: 2026-09-29
- Supersedes: ADR-0005

## Context

ADR-0005 made the client TLS disk configurable through `HORIZON_HUB_TLS_DISK`. The stored paths are not opaque object keys: `PathBuilder::absolutePath()` resolves them with `Storage::disk($disk)->path()` and the result is handed to Guzzle as `cert` / `ssl_key` (see `HorizonClientHttpService`), to an `openssl pkcs12` subprocess, and to `is_readable()`.

Only three disks are defined in `config/filesystems.php`, and none of the alternatives is valid:

- `s3` has no local filesystem path, so `path()` returns a prefixed key; the `openssl` call and the Guzzle TLS options then fail at request time with a misleading `PKCS#12 file is missing or not readable`.
- `public` is a local disk, but its root is `storage/app/public` and it exposes a `url`, so private keys would become reachable over HTTP once `storage:link` is in place.

The value was therefore a trap: a plausible-looking `HORIZON_HUB_TLS_DISK=s3` silently breaks mTLS for every service that uses it.

## Decision

Pin the client TLS disk to `local` in `config/horizonhub.php` and drop the `HORIZON_HUB_TLS_DISK` env var. `ServiceTlsClientStorage::disk()` keeps reading `horizonhub.tls.disk`, so the storage layout, the per-service `{root}/{service_id}/` directories, PKCS#12 extraction, and everything else in ADR-0004 are unchanged. `HORIZON_HUB_TLS_ROOT` stays configurable.

## Rationale

- mTLS requires real local paths, so a remote disk can only ever be a misconfiguration.
- The `local` disk already roots at `storage/app/private` with no public URL, which is where private keys belong.
- ADR-0005's motivating cases survive without the var: a dedicated or encrypted volume is a matter of persisting `storage/app/private` (already a named volume in the compose stack), and relocating within that disk is what `HORIZON_HUB_TLS_ROOT` is for.
- One env var fewer, and no way to configure a state that fails at runtime.

## Consequences

- `HORIZON_HUB_TLS_DISK` is ignored if it is still present in an existing `.env`; no migration is needed because the previous default was already `local`.
- Operators who pointed `HORIZON_HUB_TLS_DISK` at another disk silently lose their certificates and must move the files into `storage/app/private` and re-upload through the service form.
- `storage/app/private` must persist across deploys (unchanged from ADR-0004).
- Config caching keeps taking effect at container start, not per request.

## Reopen triggers

This decision can be revisited only if at least one condition is met:

- Guzzle and `openssl` gain a way to consume client certificates without a local filesystem path.
- Operators need a filesystem-backed disk other than `local` (for example a second `local` entry mounted at a different root) to hold client keys.
