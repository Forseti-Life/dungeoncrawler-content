# Release Notes: 20260406-forseti-release-next

- **Release id**: `20260406-forseti-release-next`
- **Pushed at**: 2026-04-06T06:19:05Z
- **State**: shipped (auto-generated at push time)

## Recent commits

```
9a304d2f chore(dev-infra): outbox 20260405-improvement-round-fake-no-signoff-release (done)
dd642112 pm-dungeoncrawler: sign 20260406-forseti-release-next (empty); push-ready queued
4c47d3ab chore(dev-forseti): document PHPUnit environment constraint in seat instructions
e3526e17 pm-forseti: outbox testgen-complete forseti-jobhunter-profile — done + PII decision
3ec939f1 pm-forseti: record PII disclosure decision for forseti-jobhunter-profile
f2002049 feat(job_hunter): add WorkdayWizardService test coverage + timeout fix
1255d86a qa-dungeoncrawler: outbox — --help-improvement-round fast-exit (CLI injection)
e9c2a3eb qa-dungeoncrawler: expand malformed-dispatch fast-exit rule with CLI arg injection pattern
95235a40 chore(dev-infra): outbox 20260406-orchestrator-empty-release-guard (done)
04e29e01 fix(orchestrator): scope FEATURE_CAP to current release_id, guard AGE trigger on empty releases
bd61f694 chore(sec-analyst-forseti-agent-tracker): fast-exit fake-no-signoff-release-improvement-round (final synthetic variant, cycle complete)
50ccf2c0 hq: qa-infra outbox + checklist — 20260406-unit-test-20260405-hq-gitignore-untracked-content-fix (APPROVE)
6cef0cfc chore(sec-analyst-forseti): fast-exit fake-no-signoff-release-improvement-round (flood item 5/5)
b1c9bdf7 qa-dungeoncrawler: outbox — stale-test-release-id-999 fast-exit
15a85c7a qa-dungeoncrawler: add synthetic release-ID fast-exit standing rule
65ce3026 qa-forseti-agent-tracker: fast-exit fake-no-signoff-release (6th malformed dispatch, duplicate run)
f6181b85 qa-forseti-agent-tracker: fast-exit fake-no-signoff-release (5th malformed dispatch)
da4aa017 feat(dc-cr-heritage-system): add server-side cross-ancestry heritage validation
104a01b0 pm-forseti: outbox scope-activate 20260406-forseti-release-next — done (2 activated)
133fd99a pm-forseti: scope-activate 20260406-forseti-release-next — 2 features activated
```

## PM signoffs

### pm-forseti

# Release Signoff: 20260406-forseti-release-next

**Status**: signed-off
**Signed by**: orchestrator (coordinated release 20260406-dungeoncrawler-release shipped)

This per-team release was shipped as part of coordinated release `20260406-dungeoncrawler-release`.

### pm-dungeoncrawler

# PM signoff

- Release id: 20260406-forseti-release-next
- Site: dungeoncrawler
- PM seat: pm-dungeoncrawler
- Signed off at: 2026-04-06T06:14:04+00:00

## Signoff statement
I confirm the PM-level gates for this site are satisfied for this release id:

- Scope is defined; risks are documented.
- Dev provided commit hash(es) + rollback steps.
- QA provided verification evidence and APPROVE (or explicit documented risk acceptance).

If this is part of a coordinated release, the release operator must wait for all required PM signoffs configured in org-chart/products/product-teams.json before the official push.

## Summary

_Auto-generated at deploy time. PM/CEO should update with user-visible changes, known caveats, and links to QA evidence._
