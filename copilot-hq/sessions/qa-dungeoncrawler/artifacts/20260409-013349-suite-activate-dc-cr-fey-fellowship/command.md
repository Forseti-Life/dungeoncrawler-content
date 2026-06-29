# Suite Activation: dc-cr-fey-fellowship

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-09T01:33:49+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-fey-fellowship"`**  
   This links the test to the living requirements doc at `features/dc-cr-fey-fellowship/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-fey-fellowship-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-fey-fellowship",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-fey-fellowship"`**  
   Example:
   ```json
   {
     "id": "dc-cr-fey-fellowship-<route-slug>",
     "feature_id": "dc-cr-fey-fellowship",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-fey-fellowship",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-fey-fellowship

## Gap analysis reference
- DB sections: core/ch02 (Gnome Ancestry Feats)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-encounter-rules (creature-type tags), social encounter / Diplomacy subsystem

---

## Happy Path

### Availability
- [ ] `[NEW]` Fey Fellowship is a Gnome ancestry feat (level 1); selectable at character creation and at level 1 ancestry feat slots.

### Combat/Perception Bonuses vs. Fey
- [ ] `[NEW]` +2 circumstance bonus to Perception checks against fey creatures (detects/locates fey).
- [ ] `[NEW]` +2 circumstance bonus to all saving throws against fey creatures.
- [ ] `[NEW]` Bonuses apply only when the opposing entity has the fey creature-type tag.

### Immediate Social Diplomacy
- [ ] `[NEW]` In a social encounter involving a fey creature, the character may attempt a Diplomacy check to Make an Impression immediately (1 action), without the usual 1-minute conversation requirement.
- [ ] `[NEW]` The immediate check incurs a –5 penalty.
- [ ] `[NEW]` If the immediate check fails, the character may still attempt the normal 1-minute conversation retry with no further penalty.

### Glad-Hand Interaction
- [ ] `[NEW]` If the character has the Glad-Hand skill feat AND the target is a fey creature, the –5 penalty on the immediate Diplomacy check is waived.

---

## Edge Cases
- [ ] `[NEW]` The circumstance bonuses are against "fey creatures" (fey trait on encounter entity) — not against non-fey targets in the same encounter.
- [ ] `[NEW]` Multiple circumstance bonuses vs. fey do not stack; only the highest applies.

## Failure Modes
- [ ] `[TEST-ONLY]` The –5 penalty is on the IMMEDIATE attempt only; the 1-minute retry has no penalty from this feat.
- [ ] `[TEST-ONLY]` Glad-Hand waiver applies only when target is fey; non-fey targets with Glad-Hand still incur the –5.

## Security acceptance criteria
- Security AC exemption: game-mechanic ancestry feat; no new routes or user-facing input
