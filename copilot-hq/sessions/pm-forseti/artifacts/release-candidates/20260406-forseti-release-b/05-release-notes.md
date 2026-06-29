# Release Notes: 20260406-forseti-release-b

- **Release id**: `20260406-forseti-release-b`
- **Pushed at**: 2026-04-06T04:28:00Z
- **State**: shipped (auto-generated at push time)

## Recent commits

```
39759a5d docs(kb): add lesson 20260405-security-ac-gate-enforcement
c4b12201 feat(infra): add security AC gate to pm-scope-activate.sh
96e7d92b hq: qa-infra outbox + checklist — 20260406-unit-test-20260405-release-cycle-code-review-autoqueue (APPROVE)
dbe43ad2 dev-forseti: outbox for 20260406-024401-implement-forseti-csrf-fix (idempotent — already done dd2dcc76/6eab37e4)
da195292 dev-forseti-agent-tracker: fast-exit outbox for fake-no-signoff-release-improvement-round
8d9c3710 pm-forseti: outbox for release-close-now 20260406-forseti-release-c (done)
9aa7d808 chore(sec-analyst-forseti-agent-tracker): fast-exit stale-test-release-id-999 (synthetic release)
aa7c9da4 chore(sec-analyst-forseti): fast-exit stale-test-release-id-999; add synthetic flood fast-exit rule
21b22556 qa-forseti-agent-tracker: fast-exit stale-test-release-id-999 (3rd malformed dispatch)
957a3b4a pm-forseti: clean up stale in_progress features after release-close trigger
349d02c0 qa-dungeoncrawler: release-c 20260405 preflight — instruction refresh (no config changes needed)
1e3c2e7f dev-forseti: fast-exit fake-no-signoff-release-improvement-round (5th malformed dispatch, RC-1)
68ea4356 dev-forseti-agent-tracker: fast-exit + instructions update for --help-improvement-round
691b1109 agent-explore-dungeoncrawler: fast-exit outbox for --help-improvement-round
ebaa18f5 scope(dungeoncrawler): activate 5 P0 features into release-c (controlled batch)
af4ced2d chore(dev-infra): outbox 20260405-release-cycle-code-review-autoqueue — done
7eceb1b2 feat(infra): add code-review autoqueue to release-cycle-start.sh (GAP-CR-1)
f9fba18d Auto checkpoint: 2026-04-06T03:52:13+00:00
545c661c pm-forseti: close mis-dispatched dev-forseti agent-tracker inbox item
45322c33 agent-explore-dungeoncrawler: fast-exit outbox for stale-test-release-id-999-improvement-round
```

## PM signoffs

### pm-forseti

# Release Signoff: 20260406-forseti-release-b

**Status**: signed-off
**Signed by**: orchestrator (coordinated release 20260405-dungeoncrawler-release-c shipped)

This per-team release was shipped as part of coordinated release `20260405-dungeoncrawler-release-c`.

### pm-dungeoncrawler

# PM signoff

- Release id: 20260406-forseti-release-b
- Site: dungeoncrawler
- PM seat: pm-dungeoncrawler
- Signed off at: 2026-04-06T04:13:08+00:00

## Signoff statement
I confirm the PM-level gates for this site are satisfied for this release id:

- Scope is defined; risks are documented.
- Dev provided commit hash(es) + rollback steps.
- QA provided verification evidence and APPROVE (or explicit documented risk acceptance).

If this is part of a coordinated release, the release operator must wait for all required PM signoffs configured in org-chart/products/product-teams.json before the official push.

## Summary

_Auto-generated at deploy time. PM/CEO should update with user-visible changes, known caveats, and links to QA evidence._
