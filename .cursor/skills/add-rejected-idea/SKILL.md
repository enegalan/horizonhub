---
name: add-rejected-idea
description: Add a new rejected-idea file under docs/decisions/rejected using the repository naming and section format. Use when the user asks to register, document, or record a rejected proposal.
---

# Add Rejected Idea

## Goal

Create one new markdown file in `docs/decisions/rejected/` per rejected idea.

## Steps

1. List files matching `docs/decisions/rejected/*.md`.
2. Parse the highest numeric id from filenames: `(\d{4})-*.md`.
3. Allocate the next id with zero padding to 4 digits.
4. Build filename `XXXX-<kebab-case-title>.md` from the short title.
5. Create the file using the required structure below.
6. Keep `- Status: rejected`.
7. Set `- Date:` to today in `YYYY-MM-DD`.
8. Write concrete rejection reasons, reopen conditions, and at least one alternative in `## Alternatives kept`.

## Required file structure

```markdown
# IDEA-XXXX: <short title>

- Status: rejected
- Date: YYYY-MM-DD

## Proposal

<one paragraph>

## Reason for rejection

- <reason-1>
- <reason-2>

## Reopen conditions

- <condition-1>
- <condition-2>

## Alternatives kept

- <alternative-1>
```

## Guardrails

- Do not edit existing `*.md` files unless the user explicitly asks.
- Do not place accepted or superseded decisions in `docs/decisions/rejected/`.
- Keep reasons and reopen triggers testable and specific.
- If the user asks to reopen a rejected topic, require one explicit trigger (user request, documented reopen trigger, or new hard constraint).
