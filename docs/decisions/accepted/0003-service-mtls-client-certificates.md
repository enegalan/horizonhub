# ADR: Per-service mTLS client certificates

- ID: ADR-0003
- Status: superseded
- Superseded by: ADR-0004
- Date: 2026-09-23

## Context

Some Horizon upstreams require mutual TLS (client certificates) during the TLS handshake. Horizon Hub previously only supported HTTP headers, which cannot satisfy mTLS. Operators need both PEM (cert + key) and PKCS#12 (.p12) formats without converting certificates.

## Decision

Support optional per-service client TLS configuration stored as filesystem paths (plus an encrypted passphrase) on the `services` table. Supported modes are PEM (`tls_client_cert_path` + `tls_client_key_path`) and PKCS#12 (`tls_client_cert_path` pointing at the `.p12` file). Certificate file contents are not uploaded or stored in the database; they must be mounted where the Hub process can read them. `HorizonClientHttpService` applies the matching Guzzle options on every outbound Horizon HTTP call.

## Rationale

- Paths keep secrets out of the database and match container/volume workflows.
- Supporting both PEM and PKCS#12 avoids forcing operators to convert formats.
- Applying options in the shared HTTP client covers API, dashboard-session, and retry flows.

## Consequences

- Operators must mount readable cert/key/p12 files into the Hub runtime and configure absolute paths per service.
- PKCS#12 uses `CURLOPT_SSLCERTTYPE=P12` via Guzzle curl options.
- Passphrases are encrypted at rest; blank passphrase on update keeps the existing value.

## Reopen triggers

This decision can be revisited only if at least one condition is met:

- A secure certificate-upload flow is approved with storage, rotation, and access controls.
- Platform constraints make path-based client certs non-viable (e.g. serverless without mounted filesystems).
