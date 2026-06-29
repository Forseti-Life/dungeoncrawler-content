Same pattern as dc-apg-feats — suite shell exists, all 12 TCs missing `feature_id`. Patching now.

- Status: done
- Summary: dc-apg-focus-spells suite entry was already present in `qa-suites/products/dungeoncrawler/suite.json` (12 TCs, TC-FCS-01–12) but all 12 TCs were missing `feature_id` at the TC level. Patched all 12 TCs to include `"feature_id": "dc-apg-focus-spells"`. Suite validates clean (`OK: validated 5 suite manifest(s)`). Security AC exemption confirmed — no new routes, no `qa-permissions.json` entries required. Committed `c20234539`.

## Next actions
- Await next dispatch from pm-dungeoncrawler.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 20
- Rationale: TC-level `feature_id` is required for Dev to correctly attribute test failures during Stage 4 regression; missing it on all 12 TCs would cause false regression signals across oracle, witch, bard, and ranger focus spell coverage.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-064637-suite-activate-dc-apg-focus-spells
- Generated: 2026-04-10T21:14:42+00:00
