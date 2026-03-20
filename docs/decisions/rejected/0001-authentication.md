# IDEA-0001: Add authentication to Horizon Hub

- Status: rejected
- Date: 2026-03-09

## Proposal

Add built-in user authentication to Horizon Hub.

## Reason for rejection

- Current deployment model assumes trusted internal access and network-level controls.
- Adding app-level authentication now increases operational complexity and support surface.
- The team prioritizes metrics reliability and service integrations over auth feature work.

## Reopen conditions

- Horizon Hub becomes exposed outside trusted internal networks.
- A compliance or customer requirement mandates application-level access control.

## Alternatives kept

- Keep network-level protections (VPN, private networks, reverse-proxy restrictions).
- Reassess lightweight authentication only when an explicit security requirement appears.
