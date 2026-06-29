# Test Plan: dc-cr-gm-tools

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-GMT-01-05)
- Suites: playwright (GM prep, encounter budgeting, access control)
- Security: GM-only prep or generation routes require authenticated GM/admin access and must not be exposed to anonymous or standard player roles.

---

## TC-GMT-01 — GM prep surface availability and scope
- Description: GM prep tooling exposes encounter budget guidance by party level/size and threat category (Trivial, Low, Moderate, Severe, Extreme).
- Suite: playwright/gm-prep
- Expected: GM prep tooling exposes encounter budget guidance by party level/size and threat category (Trivial, Low, Moderate, Severe, Extreme).
- AC: Happy Path-1

## TC-GMT-02 — Primary guidance and generation behavior
- Description: GM prep references include environment/terrain guidance, NPC stat-block structure, and loot-by-level lookup data required for session preparation.
- Suite: playwright/gm-prep
- Expected: GM prep references include environment/terrain guidance, NPC stat-block structure, and loot-by-level lookup data required for session preparation.; AI GM or GM-facing prep flows can retrieve the budgeting and reward data without requiring players to perform manual rules math.
- AC: Happy Path-2, Happy Path-3

## TC-GMT-03 — Data consumption by GM or AI GM flows
- Description: AI GM or GM-facing prep flows can retrieve the budgeting and reward data without requiring players to perform manual rules math.
- Suite: playwright/gm-prep
- Expected: AI GM or GM-facing prep flows can retrieve the budgeting and reward data without requiring players to perform manual rules math.; The feature contract identifies which outputs are read-only guidance and which are GM-triggered generation actions.
- AC: Happy Path-3, Happy Path-4

## TC-GMT-04 — Edge-case table and visibility handling
- Description: Encounter-budget calculations respond predictably when party size or level is outside the normal table range.
- Suite: playwright/gm-prep
- Expected: Encounter-budget calculations respond predictably when party size or level is outside the normal table range.; Loot generation handles missing or incomplete tables with an explicit validation/reporting path instead of producing nonsense rewards.; Player-facing surfaces do not expose GM-only prep details that would spoil encounters or hidden NPC data.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-GMT-05 — Validation errors and GM-only access control
- Description: Invalid party size, level, or threat-category inputs return a validation error rather than generating misleading encounter budgets.
- Suite: playwright/gm-prep
- Expected: Invalid party size, level, or threat-category inputs return a validation error rather than generating misleading encounter budgets.; GM-only routes return 403 for non-GM roles.
- AC: Failure Modes-1, Failure Modes-2
