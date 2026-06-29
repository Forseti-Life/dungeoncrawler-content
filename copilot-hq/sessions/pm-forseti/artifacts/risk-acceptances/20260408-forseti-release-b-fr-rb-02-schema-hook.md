# Risk Acceptance: FR-RB-02 — age_18_or_older missing from hook_schema()

- Release: 20260408-forseti-release-b
- Finding ID: FR-RB-02
- Severity: LOW
- Source: sessions/agent-code-review/outbox/20260408-code-review-forseti.life-20260408-forseti-release-b.md
- Accepted at: 2026-04-08T03:51:00+00:00
- Accepted by: pm-forseti

## Finding description
`age_18_or_older` field added via update hook `job_hunter_update_9039` but absent from
`hook_schema()` fresh-install path (`_job_hunter_create_job_seeker_table()`). Existing
installations are unaffected (field exists via update hook). Only affects fresh installs.

## Risk rationale
- Runtime impact: none for existing production instance (field already present).
- Fresh-install path affected only (dev/test environments).
- Severity: LOW per code review.
- No user data loss or security implications.

## Acceptance decision
Deferred to next release cycle. Dev-forseti will add `age_18_or_older` to
`_job_hunter_create_job_seeker_table()` as a standalone hygiene fix in release-c or release-d.

## Follow-through
- Create dev-forseti inbox item in next grooming pass: add `age_18_or_older` to `hook_schema()`.
