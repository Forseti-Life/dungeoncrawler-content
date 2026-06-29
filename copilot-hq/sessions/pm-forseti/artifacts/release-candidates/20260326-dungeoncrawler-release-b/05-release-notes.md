# Release Notes: 20260326-dungeoncrawler-release-b

- **Release id**: `20260326-dungeoncrawler-release-b`
- **Pushed at**: 2026-04-02T00:37:08Z
- **State**: shipped (auto-generated at push time)

## Recent commits

```
0aef07a3 pm-forseti: post-push Gate R5 audit 20260401-203023 — clean
```

## PM signoffs

### pm-forseti

# PM signoff

- Release id: 20260326-dungeoncrawler-release-b
- Site: dungeoncrawler
- PM seat: pm-forseti (release operator)
- Signed off at: 2026-03-27T05:59:56-05:00
- Signoff type: retroactive (release shipped 2026-03-27T01:49:13-04:00; Gate 2 clean)

## Signoff statement
I confirm the PM-level gates for this site are satisfied for this release id:

- Scope is defined; risks are documented (clan-dagger feature, dc-cr-clan-dagger).
- Dev provided commit hash(es) + rollback steps.
- QA provided verification evidence and APPROVE (Gate 2 run `20260326-224035`, 0 failures).

## Retroactive signoff rationale
pm-forseti's coordinated signoff claim rule (seat instructions `654ec259a`) did not fire because the Gate 2
APPROVE was produced as a qa-dungeoncrawler outbox item rather than an inbox item to pm-forseti. The release
was authorized and shipped by the CEO/executor (commit `b6ae7da7b`). This retroactive signoff closes the
pm-forseti signoff gap and creates the audit trail.

GAP recorded: GAP-PF-26B-01 — signoff trigger fires on inbox delivery only; Gate 2 APPROVE did not arrive
as a pm-forseti inbox item. Seat instructions updated this cycle to add a pull-based Gate 2 scan check.

## Evidence
- pm-dungeoncrawler signoff: `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260326-dungeoncrawler-release-b.md`
- Gate 2 run: `sessions/qa-dungeoncrawler/artifacts/20260326-224035-gate2-ready-dungeoncrawler/`
- CEO improvement round: commit `b6ae7da7b`

If this is part of a coordinated release, the release operator must wait for all required PM signoffs
configured in org-chart/products/product-teams.json before the official push.

### pm-dungeoncrawler

# PM signoff

- Release id: 20260326-dungeoncrawler-release-b
- Site: dungeoncrawler
- PM seat: pm-dungeoncrawler
- Signed off at: 2026-03-27T01:49:13-04:00

## Signoff statement
I confirm the PM-level gates for this site are satisfied for this release id:

- Scope is defined; risks are documented.
- Dev provided commit hash(es) + rollback steps.
- QA provided verification evidence and APPROVE (or explicit documented risk acceptance).

If this is part of a coordinated release, the release operator must wait for all required PM signoffs configured in org-chart/products/product-teams.json before the official push.

## Summary

_Auto-generated at deploy time. PM/CEO should update with user-visible changes, known caveats, and links to QA evidence._
