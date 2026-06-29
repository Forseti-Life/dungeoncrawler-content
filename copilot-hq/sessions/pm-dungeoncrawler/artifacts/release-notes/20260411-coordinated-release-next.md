# Release Notes: 20260411-coordinated-release-next (DungeonCrawler)

**Release date:** 2026-04-12
**Release ID:** 20260411-coordinated-release-next
**Site:** dungeoncrawler.forseti.life
**PM:** pm-dungeoncrawler

## Features shipped (1)

### dc-cr-gnome-heritage-chameleon — Gnome Heritage: Chameleon Gnome
- **Module:** dungeoncrawler_content
- **Dev commit:** `aa5cee3f1`
- **QA commit:** `9ac8f7826`
- **Summary:** Full implementation of the Chameleon Gnome heritage mechanics in `CharacterManager.php`:
  - +2 circumstance bonus to all Stealth checks when terrain coloration-tag matches character coloration-tag (lost immediately on significant terrain change)
  - 1-action minor color shift to enable the bonus in current terrain
  - Dramatic full-body color change as an up-to-1-hour downtime activity
  - Structured `special` sub-array with non-stacking note for machine-readable rule enforcement
  - 8 TCs in `dc-cr-gnome-heritage-chameleon-e2e` suite (all `required_for_release: true`)

## Features deferred (9)
The following features were activated but deferred — no dev implementation commit + QA APPROVE available for this cycle. They return to `Status: ready` for the next release.

| Feature ID | Reason |
|---|---|
| dc-cr-downtime-mode | Dev done (`96f4ddb18`) but QA unit-test APPROVE not yet issued |
| dc-cr-feats-ch05 | No dev impl this cycle |
| dc-cr-hazards | No dev impl this cycle |
| dc-cr-magic-ch11 | No dev impl this cycle |
| dc-cr-rest-watch-starvation | No dev impl this cycle |
| dc-cr-skills-society-create-forgery | No dev impl this cycle |
| dc-cr-skills-survival-track-direction | No dev impl this cycle |
| dc-cr-snares | No dev impl this cycle |
| dc-cr-spells-ch07 | No dev impl this cycle |

**Note on dc-cr-downtime-mode:** Dev implementation is complete at `96f4ddb18`. QA suite is activated at `f3542560b`. QA unit-test inbox item exists at `sessions/qa-dungeoncrawler/inbox/20260412-unit-test-20260411-235513-impl-dc-cr-downtime-mode` — should be highest priority for activation in next release cycle.

## Gate status
- Gate 1b (Code review): Site audit PASS (0 violations, confirmed at prior `20260411-231245` run)
- Gate 2 (QA APPROVE): `dc-cr-gnome-heritage-chameleon` — QA APPROVE at `9ac8f7826`

## KB reference
- No new lessons identified; standard release-close-now flow followed
