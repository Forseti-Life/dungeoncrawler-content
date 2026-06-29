- Status: done
- Summary: Activated the `dc-cr-dwarf-heritage-ancient-blooded` test suite into `qa-suites/products/dungeoncrawler/suite.json` with 15 test cases (TC-HAB-001 through TC-HAB-015, all tagged `feature_id: dc-cr-dwarf-heritage-ancient-blooded`). This is the first concrete heritage implementation — TC-HAB-001/002 serve as templates for all future heritage suites. Coverage: heritage availability for Dwarf (TC-HAB-001), reaction grant on selection (TC-HAB-002), reaction prompt before magical save (TC-HAB-003), +1 circumstance bonus application (TC-HAB-004), bonus persistence across turn (TC-HAB-005), bonus expiry at end of turn (TC-HAB-006), once-per-turn reaction consumption (TC-HAB-007), non-Dwarf exclusion (TC-HAB-008/012), spent-reaction rejection (TC-HAB-009/013), circumstance stacking rule (TC-HAB-010), non-magical trigger exclusion (TC-HAB-011), heritage lock post-creation (TC-HAB-014), and reaction authorization (TC-HAB-015). Four TCs are `pending-dev-confirmation` (TC-HAB-003/005/006/010/011/015) pending resolution of 5 PM clarification items (CQ-001 through CQ-005). Added 1 permission rule to `qa-permissions.json` (56 total): `dc-cr-dwarf-heritage-ancient-blooded-reaction` (ignore-all; reaction route path TBD). Suite validator: OK. Committed at `be592d5d1`.

## Next actions
- PM resolves CQ-001 (reaction prompt API surface), CQ-002 (turn tracking), CQ-003 (stacking location), CQ-004 (magical source_type), CQ-005 (GM role) → Dev documents in implementation notes → QA updates pending-dev-confirmation TCs to active
- Dev confirms reaction trigger route path → update `dc-cr-dwarf-heritage-ancient-blooded-reaction` path_regex in qa-permissions.json
- Dev notes this is the first heritage implementation — heritage entity schema should be documented in implementation notes for reuse across future heritages

## Blockers
- None blocking suite activation; 5 PM clarification items (CQ-001–CQ-005) must be resolved before pending TCs can be finalized and executed

## Needs from CEO
- N/A

## ROI estimate
- ROI: 20
- Rationale: First-of-kind heritage implementation; establishing the test pattern here prevents rework on all subsequent heritage features. The 5 open CQs are documentation gaps, not blockers — suite is activated and ready for Dev implementation.
