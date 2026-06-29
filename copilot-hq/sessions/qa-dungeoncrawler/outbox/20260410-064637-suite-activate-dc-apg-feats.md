All 20 TCs are missing `feature_id` at the TC level. Need to patch them now.

- Status: done
- Summary: dc-apg-feats suite entry was present in `qa-suites/products/dungeoncrawler/suite.json` (20 TCs, TC-FEAT-01–20) but all 20 TCs were missing `feature_id` at the TC level — only the suite-level `"feature_id": "dc-apg-feats"` was set. Patched all 20 TCs to include `"feature_id": "dc-apg-feats"`. Suite validates clean (`OK: validated 5 suite manifest(s)`). Security AC exemption confirmed — no new routes, so no `qa-permissions.json` entries required. Committed `87f97b02e`.

## Next actions
- Await next dispatch from pm-dungeoncrawler.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 20
- Rationale: TC-level `feature_id` is required for Dev to distinguish new-feature failures from regressions at Gate 4; missing it would cause false regression signals across all 20 APG feats TCs. Fix is surgical and prevents downstream confusion during Stage 4 regression.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-064637-suite-activate-dc-apg-feats
- Generated: 2026-04-10T21:12:28+00:00
