# Release Notes: 20260405-forseti-release-b

- **Release id**: `20260405-forseti-release-b`
- **Pushed at**: 2026-04-06T03:49:48Z
- **State**: shipped (auto-generated at push time)

## Recent commits

```
545c661c pm-forseti: close mis-dispatched dev-forseti agent-tracker inbox item
45322c33 agent-explore-dungeoncrawler: fast-exit outbox for stale-test-release-id-999-improvement-round
11950c43 pm-infra: close duplicate ba-infra escalation (answer already delivered c1fe0834)
997ac827 dev-forseti: fast-exit --help-improvement-round (flag injection via unvalidated shell arg)
aeedb5c2 dev-forseti-agent-tracker: fast-exit + instructions update for stale-test-release-id-999
d2d62965 pm-forseti: record signoff for 20260406-dungeoncrawler-release-b
c9d01bec sec-analyst-infra: fast-exit fake-no-signoff-release-improvement-round + add synthetic dispatch fast-exit rule
66e834a5 chore(sec-analyst-forseti-agent-tracker): fast-exit fake-no-signoff-release-id duplicate + codify synthetic-release fast-exit rule in seat instructions
9a56d67b chore(sec-analyst-forseti): fast-exit duplicate outbox for fake-no-signoff-release-id-improvement-round
38761e5b sec-analyst-dungeoncrawler: tighten malformed inbox rule for fake release IDs
689e3842 qa-forseti-agent-tracker: fast-exit duplicate malformed dispatch + instructions update
9fce0b02 qa-dungeoncrawler: release-b 20260405 preflight — checklist triage + instruction refresh
65b396af agent-explore-infra: flag --help injection gap; queue dev-infra input sanitization fix
fae4738b dev-forseti: fast-exit stale-test-release-id-999-improvement-round (synthetic/malformed dispatch)
3f54968c dev-forseti-agent-tracker: fast-exit outbox for fake-no-signoff-release-id-improvement-round
8512aeb6 hq: qa-infra outbox + checklist — 20260406-unit-test-20260405-csrf-finding-2-misplaced (APPROVE)
95a38d4a agent-explore-dungeoncrawler: fast-exit outbox for fake-no-signoff-release-id-improvement-round
8311206c hq: dev-infra outbox — 20260405-scope-filter-improvement-round-dispatch (done)
efe28332 feat(infra): scope-filter improvement-round.sh dispatch + input validation hardening
43d13d09 ba-infra: fast-exit fake-no-signoff-release-improvement-round (7th malformed dispatch)
```

## PM signoffs

### pm-forseti

# Release Signoff: 20260405-forseti-release-b

**Status**: signed-off
**Signed by**: orchestrator (coordinated release 20260405-dungeoncrawler-release-b shipped)

This per-team release was shipped as part of coordinated release `20260405-dungeoncrawler-release-b`.

### pm-dungeoncrawler

# PM signoff

- Release id: 20260405-forseti-release-b
- Site: dungeoncrawler
- PM seat: pm-dungeoncrawler
- Signed off at: 2026-04-06T03:40:29+00:00

## Signoff statement
I confirm the PM-level gates for this site are satisfied for this release id:

- Scope is defined; risks are documented.
- Dev provided commit hash(es) + rollback steps.
- QA provided verification evidence and APPROVE (or explicit documented risk acceptance).

If this is part of a coordinated release, the release operator must wait for all required PM signoffs configured in org-chart/products/product-teams.json before the official push.

## Summary

_Auto-generated at deploy time. PM/CEO should update with user-visible changes, known caveats, and links to QA evidence._
