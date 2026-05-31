# Agent Communication & Handoffs

This defines how agents talk to each other while maintaining checks-and-balances.

## Core mechanism: artifacts-as-messages
Agents communicate by producing and consuming **standard artifacts**:
- PM: Problem Statement, Acceptance Criteria, Risk Assessment, Release Notes
- Dev: Implementation Notes + code changes
- QA: Test Plan + Verification Report (APPROVE/BLOCK)

Artifacts live under:
- `sessions/<agent-id>/inbox/` (incoming requests)
- `sessions/<agent-id>/outbox/` (outgoing requests)
- `sessions/<agent-id>/artifacts/` (owned outputs)

## Message types
### 1) Work Request (PM -> Dev)
Required attachments:
- `templates/00-problem-statement.md`
- `templates/01-acceptance-criteria.md`
- `templates/06-risk-assessment.md` (initial)

### 2) QA Request (Dev -> QA)
Required attachments:
- `templates/02-implementation-notes.md`
- link/diff reference

### 3) Release Request (QA -> PM)
Required attachments:
- `templates/04-verification-report.md` (APPROVE)
- QA notes on risk/regression

### 4) Passthrough Request (PM -> PM)
Use:
- `runbooks/passthrough-request.md`

## Filenames / conventions
- Create a single folder per work item:
  `sessions/<agent-id>/(inbox|outbox)/YYYYMMDD-<short-topic>/`
- Inside the folder, include the required templates + any supporting notes.

## Escalation
- Conflicts go to CEO first.
- CEO either resolves (document decision) or escalates to the human owner.

## Instruction change proposals
Any agent may propose improvements to a target repo's `instructions.md` by submitting:
- `templates/instructions-change-proposal.md`
into `knowledgebase/proposals/`.

CEO/human owner reviews before applying changes in the target repo.

## Human command -> PM request
CEO converts human commands into PM inbox work requests.
See: runbooks/command-intake.md
