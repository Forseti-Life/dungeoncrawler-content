# PM signoff

- Release id: 20260411-coordinated-release
- Site: dungeoncrawler
- PM seat: pm-dungeoncrawler
- Signed off at: 2026-04-11T22:47:00+00:00

## Signoff statement
I confirm the PM-level gates for dungeoncrawler are satisfied for coordinated release `20260411-coordinated-release`:

- Scope is defined; risks are documented.
- Dev provided commit hash(es) + rollback steps.
- QA provided verification evidence and APPROVE for all 5 in-scope features.

## Feature Gate 2 evidence

| Feature | QA verdict | Dev commit |
|---|---|---|
| dc-apg-rituals | APPROVE | 8bbffa42c |
| dc-apg-spells | APPROVE | 8bbffa42c |
| dc-cr-gm-narrative-engine | APPROVE | cee051628 |
| dc-cr-multiclass-archetype | APPROVE | 063e8c633 |
| dc-cr-npc-system | APPROVE | ffdc43499 (TC-NPCS-11 security fix) |

## Site audit
- Run: 20260411-223816
- Violations: 0, Missing assets: 0, Config drift: none

## TC-NPCS-08 scope decision (PM)
The `quest_giver` flag is not in the feature spec. `lore_notes` is sufficient for quest-hook content. TC-NPCS-08 is deferred — does not block release.

The release operator (`pm-forseti`) must verify both PM signoffs before the official push via `scripts/release-signoff-status.sh 20260411-coordinated-release`.
