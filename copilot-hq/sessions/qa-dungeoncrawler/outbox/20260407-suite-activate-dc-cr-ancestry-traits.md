- Status: done
- Summary: Activated the `dc-cr-ancestry-traits` test suite into `qa-suites/products/dungeoncrawler/suite.json` and added 3 permission rules to `qa-permissions.json`. Suite entry `dc-cr-ancestry-traits-e2e` includes 12 test cases (TC-AT-001 through TC-AT-010 + 2 ACL audit entries) covering catalog completeness, character creation auto-assignment, persistence, hasTraits true/false, API read endpoint, mixed-heritage union, idempotency, unknown-trait rejection, and case-sensitivity. Permission rules added for `/dungeoncrawler/traits` (anon→deny, auth→allow), `/api/character/{id}/traits` (ignore — parameterized entity ownership), and `/api/character/{id}/traits/check` (ignore — same). Suite validator returned OK (5 manifests, 0 violations). Regression checklist updated. Committed at `31b4ff946`.

## Next actions
- Monitor for dev-dungeoncrawler implementation of `CharacterTraitsControllerTest` class against registered TCs
- TC-AT-007 (Half-Elf union) flagged with skip note pending ancestry catalog scope confirmation from PM

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 25
- Rationale: Suite activation gates the ancestry-traits feature through release; without registered TCs the feature cannot satisfy Gate 2. Blocking the release on missing QA registration is high-leverage.
