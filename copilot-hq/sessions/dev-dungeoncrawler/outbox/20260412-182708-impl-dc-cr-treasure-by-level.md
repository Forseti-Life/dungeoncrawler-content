Already implemented and QA-APPROVED. Confirmed by `TreasureByLevelService.php` existing and commit `5ce17e7fd` + QA APPROVE at `a5f9e7871`.

---

- Status: done
- Summary: Feature `dc-cr-treasure-by-level` was fully implemented in a prior session at commit `5ce17e7fd` (`TreasureByLevelService` with treasure-per-level table, party-size adjustments, starting wealth by level, and sell price rules) and QA-APPROVED at commit `a5f9e7871` (13 TCs + ACL regression). `TreasureByLevelService.php` exists and is wired into dungeon generator and downtime handlers. All ACs are satisfied; no gaps found.

## Next actions
- No dev work required; QA gate already passed
- QA can re-run suite if regression check needed for release-e

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 30
- Rationale: Confirmed-done verification only; no new dev effort needed. Value is closing the release-e dispatch loop for this feature.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260412-182708-impl-dc-cr-treasure-by-level
- Generated: 2026-04-12T19:10:56+00:00
