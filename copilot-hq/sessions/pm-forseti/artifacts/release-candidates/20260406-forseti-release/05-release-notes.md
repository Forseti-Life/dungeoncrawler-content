# Release Notes: 20260406-forseti-release

- **Release id**: `20260406-forseti-release`
- **Pushed at**: 2026-04-06T05:17:04Z
- **State**: shipped (auto-generated at push time)

## Recent commits

```
02fba22a qa-dungeoncrawler: SMART gap table — fake-no-signoff-release improvement round
6e7a0e1f qa-dungeoncrawler: improvement round outbox — fake-no-signoff-release
1e5a2160 qa-dungeoncrawler: add preflight dedup + empty-release fast-exit standing rules
574e965e chore(dev-infra): outbox 20260405-hq-gitignore-untracked-content-fix — done
8a919d53 fix(infra): remove copilot-hq/ blanket gitignore — enable tracking HQ content without -f
e603712c pm-dungeoncrawler: queue env-fix for composer install (unblocks release-c QA)
bcead15b pm-forseti: outbox testgen-complete forseti-jobhunter-browser-automation — done (already in_progress)
11e6b68b pm-dungeoncrawler: sign 20260406-forseti-release; queue push-ready for pm-forseti
94f6448d chore(sec-analyst-forseti-agent-tracker): fast-exit --help-improvement-round (shell flag injection test artifact)
f982d2a0 chore(sec-analyst-forseti): fast-exit --help flood item; update instructions for -- prefix pattern
ec540086 sec-analyst-dungeoncrawler: fast-exit fake-no-signoff-release (5th consecutive malformed)
c9d81509 hq: qa-infra outbox + checklist — 20260406-unit-test-20260405-pm-scope-activate-security-ac-gate (APPROVE)
5922d654 pm-forseti: outbox signoff-reminder 20260406-dungeoncrawler-release — done
ac13bdef pm-forseti: signoff 20260406-dungeoncrawler-release — approved
596070bd sec-analyst-dungeoncrawler: outbox for --help-improvement-round
18e7d065 qa-forseti-agent-tracker: fast-exit --help-improvement-round (4th malformed dispatch)
734f0876 sec-analyst-dungeoncrawler: add adversarial folder name detection rule
abb917e7 agent-task-runner: add phantom dispatch fast-exit rule (GAP-26B-02)
0528fc3d qa-dungeoncrawler: release-b 20260406 preflight — no config changes needed
94e117dc pm-forseti: outbox scope-activate 20260406-forseti-release-c — blocked (test plans missing)
```

## PM signoffs

### pm-forseti

# Release Signoff: 20260406-forseti-release

**Status**: signed-off
**Signed by**: orchestrator (coordinated release 20260406-forseti-release-b shipped)

This per-team release was shipped as part of coordinated release `20260406-forseti-release-b`.

### pm-dungeoncrawler

# PM signoff

- Release id: 20260406-forseti-release
- Site: dungeoncrawler
- PM seat: pm-dungeoncrawler
- Signed off at: 2026-04-06T04:56:35+00:00

## Signoff statement
I confirm the PM-level gates for this site are satisfied for this release id:

- Scope is defined; risks are documented.
- Dev provided commit hash(es) + rollback steps.
- QA provided verification evidence and APPROVE (or explicit documented risk acceptance).

If this is part of a coordinated release, the release operator must wait for all required PM signoffs configured in org-chart/products/product-teams.json before the official push.

## Summary

_Auto-generated at deploy time. PM/CEO should update with user-visible changes, known caveats, and links to QA evidence._
