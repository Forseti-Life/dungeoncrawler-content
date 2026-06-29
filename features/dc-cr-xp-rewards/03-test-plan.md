# Test Plan: dc-cr-xp-rewards

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-XPR-01-05)
- Suites: playwright (reward ledger, level-up threshold, access control)
- Security: XP award writes require authenticated GM/system access; players can read progress but cannot mint XP for themselves.

---

## TC-XPR-01 — Milestone availability and slot gating
- Description: Characters can earn XP from encounters, hazards, and story milestones using the same progression ledger.
- Suite: playwright/progression
- Expected: Characters can earn XP from encounters, hazards, and story milestones using the same progression ledger.
- AC: Happy Path-1

## TC-XPR-02 — Primary progression rule application
- Description: Reaching the configured level-up threshold (default 1,000 XP) triggers the character-leveling workflow instead of leaving XP in an unresolved state.
- Suite: playwright/progression
- Expected: Reaching the configured level-up threshold (default 1,000 XP) triggers the character-leveling workflow instead of leaving XP in an unresolved state.; XP tracking remains aligned with the consolidated `dc-cr-xp-award-system` rules for award sources and threshold handling.
- AC: Happy Path-2, Happy Path-3

## TC-XPR-03 — Persistence and recalculation across level changes
- Description: XP tracking remains aligned with the consolidated `dc-cr-xp-award-system` rules for award sources and threshold handling.
- Suite: playwright/progression
- Expected: XP tracking remains aligned with the consolidated `dc-cr-xp-award-system` rules for award sources and threshold handling.; Party or campaign reward flows can identify which characters received XP and what source generated the reward.
- AC: Happy Path-3, Happy Path-4

## TC-XPR-04 — Edge-case rebuild and empty-option handling
- Description: XP progress across multiple rewards accumulates correctly until the threshold is crossed.
- Suite: playwright/progression
- Expected: XP progress across multiple rewards accumulates correctly until the threshold is crossed.; Rewards of 0 XP (for example trivial events under the broader system) are handled explicitly rather than silently ignored.; Characters already behind or ahead in XP state still level through the same validated threshold logic.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-XPR-05 — Validation, ownership, and invalid input handling
- Description: Invalid XP amounts or unknown reward-source types return validation errors.
- Suite: playwright/progression
- Expected: Invalid XP amounts or unknown reward-source types return validation errors.; Awarding XP to a character outside the active party/campaign context is blocked.
- AC: Failure Modes-1, Failure Modes-2
