# ADR: Agent removal

- ID: ADR-0001
- Status: accepted
- Date: 2026-03-09

## Context

Horizon Hub currently relies on an agent-oriented integration path to normalize service communication and metrics collection behavior across environments.

The proposal considered removing this layer to simplify the architecture and operate only with direct service-to-hub communication.

## Decision

Replace the agent layer with an API-based layer.

## Rationale

- The agent currently provides a compatibility boundary that reduces per-service divergence.
- Direct-only integration would shift complexity into hub services and increase contract drift risk.
- Migration effort and regression surface are high compared to expected short-term benefits.

## Consequences

- Agent-related code remains in scope for maintenance.
- Improvement work should target contract hardening and observability, not structural removal.
- New proposals must preserve compatibility with current integrations.

## Reopen triggers

This decision can be revisited only if at least one condition is met:

- A replacement design is approved with rollout phases, compatibility strategy, and rollback plan.
- A security or platform constraint makes the current model non-viable.
