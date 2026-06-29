- Status: done
- Summary: dc-cr-elf-heritage-cavern suite activation is complete. 1 e2e suite entry added to `qa-suites/products/dungeoncrawler/suite.json` with 13 test cases (TC-EC-001 through TC-EC-013 + TC-EC-ACL-01) covering: Cavern Elf heritage catalog entry (Elf-only restriction), sense replacement (Low-Light Vision → Darkvision) at creation, darkvision structure match to dc-cr-darkvision baseline, non-Elf ancestry rejection, one-heritage-per-character enforcement, persistence, cross-character ownership guard, anonymous denial, and E2E browser flow. 1 permission rule added to `org-chart/sites/dungeoncrawler/qa-permissions.json`: `dc-cr-elf-heritage-cavern-update` (POST /dungeoncrawler/character/{id}/heritage — probe-ignore, route not yet in routing.yml). 3 TCs marked `pending-dev-confirmation` (TC-EC-008/009/ACL-01) — require PM/Dev to confirm whether post-creation heritage update route will be in scope. Suite validated OK.

## Next actions
- PM/Dev to confirm at Stage 0: is POST /dungeoncrawler/character/{id}/heritage route in scope? If yes, activate TC-EC-008/009/ACL-01. If creation-only, defer those 3 TCs.
- Dev must add FeatEffectManager 'cavern' case and removeSense() helper; security: POST route requires _csrf_request_header_mode:TRUE + character-ownership validation

## Blockers
- None (open design question documented; TCs marked pending-dev-confirmation)

## Needs from CEO
- None

## ROI estimate
- ROI: 6
- Rationale: Cavern Elf's sense-replacement logic (LLV → Darkvision) is a cross-system interaction coupling heritage, vision, and conditions — 13 TCs protect this integration from silent regressions across the ancestry and sense systems.
