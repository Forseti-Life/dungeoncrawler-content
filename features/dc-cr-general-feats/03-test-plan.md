# Test Plan: dc-cr-general-feats

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-GFE-01-05)
- Suites: playwright (feat progression, build validation, retraining)
- Security: Security AC exemption: feat-catalog and character-build scope only; no new routes or input surfaces beyond existing feat assignment handlers.

---

## TC-GFE-01 — Milestone availability and slot gating
- Description: General feat slots open at levels 3, 7, 11, 15, and 19 and are distinct from class, ancestry, and skill feat slots.
- Suite: playwright/feat-progression
- Expected: General feat slots open at levels 3, 7, 11, 15, and 19 and are distinct from class, ancestry, and skill feat slots.
- AC: Happy Path-1

## TC-GFE-02 — Primary progression rule application
- Description: The general-feat catalog includes the chapter's core cross-class options (for example Armor Proficiency, Shield Block, Toughness, and Incredible Initiative) with the metadata needed for the picker.
- Suite: playwright/feat-progression
- Expected: The general-feat catalog includes the chapter's core cross-class options (for example Armor Proficiency, Shield Block, Toughness, and Incredible Initiative) with the metadata needed for the picker.; The feat picker only offers general feats whose prerequisites are satisfied by the current character build.
- AC: Happy Path-2, Happy Path-3

## TC-GFE-03 — Persistence and recalculation across level changes
- Description: The feat picker only offers general feats whose prerequisites are satisfied by the current character build.
- Suite: playwright/feat-progression
- Expected: The feat picker only offers general feats whose prerequisites are satisfied by the current character build.; Taking a general feat applies its listed modifier, action, or rules flag to the character state in a testable way.
- AC: Happy Path-3, Happy Path-4

## TC-GFE-04 — Edge-case rebuild and empty-option handling
- Description: A feat available from multiple sources is still tracked in the correct feat pool and not duplicated across slot types.
- Suite: playwright/feat-progression
- Expected: A feat available from multiple sources is still tracked in the correct feat pool and not duplicated across slot types.; Leveling without an eligible general feat choice leaves the slot open rather than auto-assigning an invalid feat.; Retraining recalculates downstream prerequisites for other feat selections.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-GFE-05 — Validation, ownership, and invalid input handling
- Description: General feats cannot be selected in ancestry-feat or class-feat slots.
- Suite: playwright/feat-progression
- Expected: General feats cannot be selected in ancestry-feat or class-feat slots.; Submitting a feat without meeting its prerequisites returns a validation error instead of corrupting the build.
- AC: Failure Modes-1, Failure Modes-2
