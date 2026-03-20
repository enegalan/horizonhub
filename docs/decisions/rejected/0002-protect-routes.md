# IDEA-0002: Protect Horizon Hub routes

- Status: rejected
- Date: 2026-03-20

## Proposal

Apply application-level route protection to Horizon Hub (for example Laravel middleware, signed URLs, or session-based gates) so HTTP endpoints are not reachable without explicit in-app credentials or tokens.

## Reason for rejection

- Route-level protection presupposes authentication or equivalent issuance and lifecycle, which is out of scope under the current product and deployment assumptions (see [DECISION-0001](0001-authentication.md)).
- Trusted-internal access and network-level controls are the agreed control plane; adding per-route checks duplicates policy without a defined identity model.
- Operational cost (secrets rotation, user support, failure modes on misconfiguration) outweighs benefit while Horizon Hub remains on trusted networks only.

## Reopen conditions

- Horizon Hub is reachable from untrusted or broader networks than today’s trusted-internal model.
- A documented compliance, customer, or incident-driven requirement mandates application-level access control on specific routes or APIs.
- DECISION-0001 is superseded or explicitly reopened and an identity or token model is adopted.

## Alternatives kept

- Rely on network-level and infrastructure controls (private networks, VPN, reverse-proxy allowlists, mTLS where appropriate) as the primary gate.
- Defer any route middleware or auth gates until reopen conditions for application-level access control are met; then design protection coherently with the chosen auth approach.
