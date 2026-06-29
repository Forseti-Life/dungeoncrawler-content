# Test Plan: dc-cr-fey-fellowship

## Coverage summary
- AC items: 10 (7 happy path, 2 edge cases, 2 failure modes)
- Test cases: 8 (TC-FEY-01–08)
- Suites: playwright (character creation, encounter/social flows)
- Security: AC exemption granted (no new routes)

---

## TC-FEY-01 — Feat selectable for Gnome at level 1
- Description: Fey Fellowship appears in level 1 gnome ancestry feat list
- Suite: playwright/character-creation
- Expected: feat_pool[gnome][level_1] includes fey-fellowship
- AC: Availability

## TC-FEY-02 — +2 Perception vs fey
- Description: Perception checks against fey entities get +2 circumstance bonus
- Suite: playwright/encounter
- Expected: perception_check.circumstance_bonus = 2 when target.traits includes fey
- AC: Combat Bonuses-1, Combat Bonuses-3

## TC-FEY-03 — +2 saving throws vs fey
- Description: All saves against fey creatures get +2 circumstance bonus
- Suite: playwright/encounter
- Expected: save.circumstance_bonus = 2 when source.traits includes fey
- AC: Combat Bonuses-2, Combat Bonuses-3

## TC-FEY-04 — Bonuses do not apply to non-fey
- Description: +2 bonuses are not granted against non-fey entities in same encounter
- Suite: playwright/encounter
- Expected: perception_check.circumstance_bonus = 0 and save.circumstance_bonus = 0 vs non-fey targets
- AC: Edge Case-1

## TC-FEY-05 — Immediate Diplomacy with –5 penalty
- Description: In a social encounter with fey, immediate Make an Impression costs 1 action with –5 penalty
- Suite: playwright/social
- Expected: diplomacy_check.penalty = -5; timing = immediate (1 action); available when fey present
- AC: Immediate Social-1, Immediate Social-2

## TC-FEY-06 — Failed immediate check allows retry
- Description: If immediate check fails, normal 1-minute retry allowed with no further penalty
- Suite: playwright/social
- Expected: after failed immediate check, retry_available = true; retry.penalty = 0 (no additional penalty from this feat)
- AC: Immediate Social-3, Failure Modes-1

## TC-FEY-07 — Glad-Hand waives penalty vs fey
- Description: With Glad-Hand feat and fey target, –5 penalty is waived
- Suite: playwright/social
- Expected: diplomacy_check.penalty = 0 when character has glad-hand and target.traits includes fey
- AC: Glad-Hand Interaction

## TC-FEY-08 — Glad-Hand waiver restricted to fey targets
- Description: Glad-Hand waiver does not apply to non-fey targets
- Suite: playwright/social
- Expected: diplomacy_check.penalty = -5 when character has glad-hand but target.traits does not include fey
- AC: Failure Modes-2
