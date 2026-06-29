- Status: done
- Summary: Activated the `dc-cr-character-leveling` test suite by enhancing the existing `dc-cr-character-leveling-e2e` entry in `qa-suites/products/dungeoncrawler/suite.json` with 17 test cases (TC-LV-001 through TC-LV-015 + 2 ACL audit entries). The entry already existed from a prior Stage 0 activation but lacked individual TC records; all 17 TCs have been added with `feature_id: dc-cr-character-leveling` tags. Coverage includes: level-up trigger (TC-LV-001), advancement table response (TC-LV-002), class feature auto-apply (TC-LV-003), ability boosts at level 5 (TC-LV-004), skill increase (TC-LV-005), feat selection (TC-LV-006), idempotency (TC-LV-007), persistence (TC-LV-008), max-level rejection (TC-LV-009), milestone enforcement (TC-LV-010), skip-level rejection (TC-LV-011), no-choice feature auto-apply (TC-LV-012), structured error on invalid char (TC-LV-013), missing advancement data handling (TC-LV-014), concurrency guard (TC-LV-015), and 2 ACL role-url-audit entries. Added 2 permission rules to `qa-permissions.json` (53 total): player routes (probe-ignore; `_character_access:TRUE` — parameterized) and admin routes (probe-ignore; `administer dungeoncrawler content` — POST-only). Suite validator: OK. Committed at `f21ae702d`.

## Next actions
- Dev implements `CharacterLevelingControllerTest` against registered TCs
- TC-LV-015 (concurrency) may be deferred post-release if test infra not ready — flag to PM if Dev encounters that constraint
- TC-LV-002 response shape (pending_choices vs separate GET endpoint) needs Dev confirmation before writing test fixture

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 25
- Rationale: Character leveling is a core gameplay loop; suite activation gates the feature through Gate 2 and prevents a regression gap at the final pre-ship checkpoint.
