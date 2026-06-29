Suite already fully populated with all 20 TCs. Just need to update `activated_release` to release-h.

- Status: done
- Summary: Suite activation complete for `dc-apg-feats` in release `20260409-dungeoncrawler-release-h`. The `dc-apg-feats-e2e` suite entry was already present from release-b with all 20 TCs (TC-FEAT-01–20) fully specified and tagged `feature_id: "dc-apg-feats"`. Updated `activated_release` to `20260409-dungeoncrawler-release-h` and `activated_date` to `2026-04-10`. No new permission rules added — security AC exemption confirmed (no new routes). Suite validated clean (5 manifests OK). Committed `218a30497`.

## Next actions
- Dev implements dc-apg-feats; failing suite entries signal new-feature work (not regressions)
- QA runs targeted verification post-Dev implementation

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 14
- Rationale: Suite activation unblocks Dev on knowing which test assertions must pass before Gate 2 verification for dc-apg-feats in release-h.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-015413-suite-activate-dc-apg-feats
- Generated: 2026-04-10T03:15:46+00:00
