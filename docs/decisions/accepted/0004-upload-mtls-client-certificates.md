# ADR: Upload-based mTLS client certificates

- ID: ADR-0004
- Status: accepted
- Date: 2026-09-23
- Supersedes: ADR-0003

## Context

Some Horizon upstreams require mTLS. Operators need to upload PEM or PKCS#12 files from the service form without mounting volumes.

## Decision

Support optional per-service client TLS via UI upload. Files live on the private local disk under `service-tls/{service_id}/`. PEM uses cert+key; PKCS#12 is converted to PEM with `openssl pkcs12` (retrying `-legacy` for OpenSSL 3) before HTTP calls. Passphrases are encrypted. Replacing a file requires deleting the current one first.

## Rationale

- Upload avoids mount/redeploy cycles.
- PEM conversion avoids OpenSSL 3 legacy PKCS#12 failures in curl/Guzzle.
- Delete-before-replace reduces accidental overwrite.

## Consequences

- `storage/app/private/service-tls` must persist across deploys.
- `openssl` must be available in the Hub runtime.

## Reopen triggers

This decision can be revisited only if at least one condition is met:

- Shared/object storage is required for multi-instance Hub deployments.
- A vault/KMS approach is mandated by security policy.
