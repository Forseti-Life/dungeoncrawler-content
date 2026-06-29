- Status: done
- Summary: Activated the `dc-cr-darkvision` test suite into `qa-suites/products/dungeoncrawler/suite.json` with 15 test cases (TC-DV-01 through TC-DV-15, all tagged `feature_id: dc-cr-darkvision`). Coverage includes: sense entity structure and effects (TC-DV-01/02), Low-Light Vision distinction (TC-DV-03), Dwarf ancestry senses reference (TC-DV-04), multi-ancestry reference without duplication (TC-DV-05), encounter visibility Concealed bypass (TC-DV-06/07), baseline behavior preserved for characters without darkvision (TC-DV-08), non-darkness Concealed not suppressed (TC-DV-09), bright-light no-op (TC-DV-10), null-senses crash guard (TC-DV-11), ancestry-swap integrity (TC-DV-12), character sheet API (TC-DV-13), and 2 ACL role-url-audit entries (TC-DV-14/15). TC-DV-15 is marked `pending-dev-confirmation` — the sense entity admin UI route path is TBD. Added 2 permission rules to `qa-permissions.json` (55 total): `dc-cr-darkvision-sense-catalog` (ignore until public catalog path confirmed) and `dc-cr-darkvision-admin-ui` (placeholder — path_regex must be updated when Dev confirms route). Suite validator: OK. Committed at `6cf6060eb`.

## Next actions
- Dev confirms sense entity admin route path → update `dc-cr-darkvision-admin-ui` path_regex in qa-permissions.json and set TC-DV-15 to `active`
- Dev confirms whether `AncestryController` plain-string `vision` field (L71) is migrated or coexists with structured senses array (regression risk #3)
- Dev confirms Low-Light Vision sense entity is also created (needed for TC-DV-03)

## Blockers
- None blocking suite activation; two open items require Dev follow-up (admin route path, AncestryController migration path)

## Needs from CEO
- N/A

## ROI estimate
- ROI: 22
- Rationale: Darkvision is a foundational sense system that integrates with encounter visibility (ConditionManager) and ancestry data; missing test coverage would leave the release gate blind to ConditionManager coupling regressions.
