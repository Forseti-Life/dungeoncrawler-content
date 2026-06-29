- Status: done
- Summary: Activated `dc-cr-low-light-vision-e2e` suite into `qa-suites/products/dungeoncrawler/suite.json` with 15 entries (TC-LLV-001–TC-LLV-014 + TC-LLV-ACL-01, all tagged `feature_id: dc-cr-low-light-vision`). Coverage: sense flag on character entity, Elf ancestry grants structured LLV sense (not the existing plain-string display value), dim-light concealment bypass, normal-vision baseline unchanged, darkvision wins when both flags set, LLV vs darkvision distinct lighting effects, persist/reload stability, ancestry-switch clears/re-applies correct sense, non-existent character 404, client direct-write blocked (403/405), cross-character read blocked, and E2E character sheet display. Three entries marked `pending-dev-confirmation` (TC-LLV-011/012 + TC-LLV-ACL-01) — all depend on `/dungeoncrawler/character/{id}/senses` GET route which does not exist in routing.yml. Added 1 permission rule `dc-cr-low-light-vision-senses-read` (59 total; probe-ignore). Committed at `d0316ee5a`.

## Next actions
- PM/Dev confirm: dedicated `/dungeoncrawler/character/{id}/senses` GET route vs. filtered alias of `/api/character/{id}/state` → QA activates TC-LLV-011/012 accordingly
- Dev wires `AncestryController` to call `FeatEffectManager::addSense('low-light-vision',...)` rather than storing plain display string — TC-LLV-002 and TC-EA-004 both depend on this
- Dev adds sense-check to `HexMapController` encounter resolution path (dim-light concealment bypass) — TC-LLV-003/004 require this
- If `dc-cr-darkvision` is at risk of deferral, PM to notify QA so TC-LLV-005/006 can be flagged `pending-dev-confirmation`

## Blockers
- None blocking suite activation; pending-dev-confirmation TCs are bounded with explicit unblock conditions

## Needs from CEO
- None

## ROI estimate
- ROI: 17
- Rationale: LLV is the Elf default sense and a prerequisite for Cavern Elf's sense-replacement logic; the AncestryController plain-string gap identified here affects TC-EA-004 and TC-EC-002 as well, making this a cross-feature correctness dependency with significant regression surface if missed.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-145412-suite-activate-dc-cr-low-light-vision
- Generated: 2026-04-07T15:18:47+00:00
