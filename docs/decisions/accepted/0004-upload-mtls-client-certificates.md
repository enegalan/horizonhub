# ADR: Upload-based mTLS client certificates

- ID: ADR-0004
- Status: accepted
- Date: 2026-09-23
- Supersedes: ADR-0003

## Context

ADR-0003 required operators to mount certificate files and configure absolute paths. That forces a redeploy or volume change for every new upstream that needs mTLS. Operators need to upload PEM or PKCS#12 files from the service form instead.

## Decision

Accept certificate uploads from the service create/edit UI. Store files on the private local disk under `service-tls/{service_id}/`, keep relative paths (and an encrypted passphrase) on the `services` table, and resolve absolute paths at request time via `ServiceTlsClientStorage`. PEM and PKCS#12 remain supported. Clearing the TLS mode or deleting the service removes the stored files.

For PKCS#12, Horizon Hub extracts PEM sidecars with `openssl pkcs12` (retrying with `-legacy` on OpenSSL 3) and uses those for outbound HTTP. This avoids curl/Guzzle failing on legacy PKCS#12 ciphers (`error:0308010C:digital envelope routines::unsupported`).

## Rationale

- UI upload avoids mount/redeploy cycles when onboarding mTLS upstreams.
- Private disk storage keeps files out of the public web root.
- Relative DB paths remain portable across environments that share the same storage layout.

## Consequences

- Operators upload cert/key or `.p12` from the service form; no manual path entry.
- Horizon Hub storage must persist `storage/app/private/service-tls` across deploys.
- Replacing files on edit is optional when a certificate is already on file.

## Reopen triggers

This decision can be revisited only if at least one condition is met:

- Shared/object storage (e.g. S3) is required for multi-instance Hub deployments.
- A different secret-management approach (vault, KMS) is mandated by security policy.
