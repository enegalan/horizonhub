---
name: add-accepted-decision
description: Add a new accepted ADR file under docs/decisions/accepted with the repository ADR structure. Use when the user asks to document a taken decision, architecture decision, or accepted proposal.
---

# Add Accepted Decision

## Goal

Create a new ADR markdown file in `docs/decisions/accepted/` for an accepted decision.

## Steps

1. Read all `*.md` files in `docs/decisions/accepted/` and identify existing ADR ids (`XXXX`).
2. Pick the next id with zero padding to 4 digits.
3. Build a kebab-case filename from the decision title (for example `queue-scaling.md`).
4. Create the new file in `docs/decisions/accepted/` with the exact section order below.
5. Set `- Status: accepted`.
6. Set `- Date:` to today in `YYYY-MM-DD`.
7. Keep rationale and consequences concrete and verifiable.
8. Add explicit reopen triggers.

## Required file template

```markdown
# ADR: <short title>

- ID: ADR-XXXX
- Status: accepted
- Date: YYYY-MM-DD

## Context

<problem and constraints>

## Decision

<final decision statement>

## Rationale

- <reason-1>
- <reason-2>

## Consequences

- <impact-1>
- <impact-2>

## Reopen triggers

This decision can be revisited only if at least one condition is met:

- <trigger-1>
- <trigger-2>
```

## Guardrails

- Do not overwrite existing ADR files.
- Do not mark as accepted if the user says the decision is rejected or superseded.
- Keep compatibility with existing accepted decisions unless the user explicitly asks to re-evaluate them.
- If a proposal conflicts with any `docs/decisions/rejected/*.md` record, stop and ask for explicit reopen justification before writing.
