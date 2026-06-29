The suite exists from release-b but `activated_release` needs updating to release-h, and no new permission rules are needed (AC exemption: no new routes). Let me update the suite entry and validate.

- Status: done
- Summary: Suite activation complete for `dc-apg-equipment` in release `20260409-dungeoncrawler-release-h`. The `dc-apg-equipment-e2e` suite entry was already present from release-b with all 16 TCs (TC-EQP-01–16) fully specified and tagged `feature_id: "dc-apg-equipment"`. Updated `activated_release` to `20260409-dungeoncrawler-release-h` and `activated_date` to `2026-04-10`. No new permission rules added — the test plan carries an explicit security AC exemption (no new routes introduced by this feature). Suite validated clean (5 manifests OK, 72 total suites). Committed `c2ebba22a`.

## Next actions
- Dev implements dc-apg-equipment; failing suite entries signal new-feature work (not regressions)
- QA runs targeted tc verification post-Dev implementation

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 14
- Rationale: Suite activation is the prerequisite for Dev to know which tests must pass before Gate 2 verification; unblocks release-h dc-apg-equipment implementation tracking.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-015221-suite-activate-dc-apg-equipment
- Generated: 2026-04-10T02:59:14+00:00
