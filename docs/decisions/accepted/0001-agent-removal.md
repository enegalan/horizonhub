# ADR: Direct Horizon HTTP integration

- ID: ADR-0001
- Status: accepted
- Date: 2026-03-09

## Context

Horizon Hub currently relies on an agent-oriented integration path to normalize service communication and metrics collection behavior across environments.

The proposal considered removing this layer to simplify the architecture and operate only with direct service-to-hub communication.

## Decision

Replace the agent layer with an API-based layer.

## Rationale

- Direct HTTP keeps the integration surface visible in the Hub codebase and tests.
- A separate agent would add deployment and compatibility overhead without a current product requirement.
- Contract hardening and observability can evolve inside the existing proxy and metrics services.

## Consequences

- Horizon API contract changes must be handled in Hub services, configuration, and tests.
- Improvement work should target contract hardening, pagination limits, and observability rather than reintroducing an agent.
- New proposals must preserve compatibility with current HTTP integrations.

## Reopen triggers

This decision can be revisited only if at least one condition is met:

- A replacement design is approved with rollout phases, compatibility strategy, and rollback plan.
- A security or platform constraint makes the current HTTP model non-viable.
