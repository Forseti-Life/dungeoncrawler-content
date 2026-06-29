# Gate 2 Consolidation — forseti-release-g

Write a consolidated Gate 2 APPROVE artifact for release `20260409-forseti-release-g`.

All 5 release-g features have been individually verified and approved:

1. `forseti-jobhunter-cover-letter-display` — APPROVE (dev commit `24ae748a2`, QA commit `faaf2eb53`)
2. `forseti-jobhunter-interview-prep` — APPROVE (dev commit `a7d7accc8`, QA commit `ba499bbba`)
3. `forseti-jobhunter-saved-search` — APPROVE (dev commits `2f2658355` + `62c441f56`, QA commit `d55426161`)
4. `forseti-ai-conversation-export` — APPROVE (QA outbox `20260409-unit-test-20260409-impl-forseti-ai-conversation-export.md`, QA commit `4f97b1c5c`)
5. `forseti-ai-conversation-history-browser` — APPROVE (suite activation commit `8138af4a7`, evidence in export outbox `4f97b1c5c`)

Site audit `20260409-171353` confirmed: 0 failures, 0 permission violations, 0 config drift.
Code review for release-g: no findings (`sessions/agent-code-review/outbox/20260409-code-review-forseti.life-20260409-forseti-release-g.md`).

## Required output

Write the file `sessions/qa-forseti/outbox/20260409-gate2-approve-20260409-forseti-release-g.md`.

The file MUST contain both strings:
- `20260409-forseti-release-g`
- `APPROVE`

Format: follow the same structure as `sessions/qa-forseti/outbox/20260409-gate2-approve-20260409-forseti-release-f.md`.

Commit the artifact and include the commit hash in your outbox.

## Acceptance criteria
- File `sessions/qa-forseti/outbox/20260409-gate2-approve-20260409-forseti-release-g.md` exists and is committed
- File contains both `20260409-forseti-release-g` and `APPROVE`
- `bash scripts/release-signoff.sh forseti 20260409-forseti-release-g` exits 0 after QA artifact is in place

## KB reference
- Prior pattern: `sessions/qa-forseti/outbox/20260409-gate2-approve-20260409-forseti-release-f.md`
