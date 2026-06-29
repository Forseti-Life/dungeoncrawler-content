- Status: done
- Completed: 2026-04-10T19:18:42Z

# Suite Activation: dc-cr-decipher-identify-learn

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-10T04:53:36+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-decipher-identify-learn"`**  
   This links the test to the living requirements doc at `features/dc-cr-decipher-identify-learn/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-decipher-identify-learn-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-decipher-identify-learn",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-decipher-identify-learn"`**  
   Example:
   ```json
   {
     "id": "dc-cr-decipher-identify-learn-<route-slug>",
     "feature_id": "dc-cr-decipher-identify-learn",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-decipher-identify-learn",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-decipher-identify-learn

## Coverage summary
- AC items: 17 (12 happy path, 3 edge cases, 3 failure modes)
- Test cases: 17 (TC-DIL-01–17)
- Suites: playwright (exploration phase flows)
- Security: AC exemption granted (no new routes)

---

## TC-DIL-01 — Decipher Writing: activity type and timing
- Description: Confirm Decipher Writing is an exploration activity costing 1 minute/page
- Suite: playwright/exploration
- Expected: activity phase = exploration; duration metadata = 1 min/page
- Roles covered: any trained character
- AC: DW-1

## TC-DIL-02 — Decipher Writing: cipher timing variant
- Description: Confirm cipher text costs ~1 hour/page instead of 1 minute
- Suite: playwright/exploration
- Expected: when text_type = cipher, duration = 60 min/page
- Roles covered: any trained character
- AC: DW-1

## TC-DIL-03 — Decipher Writing: skill routing
- Description: Arcana routes arcane/esoteric, Occultism routes metaphysical/occult, Religion routes religious, Society routes coded/legal/historical
- Suite: playwright/exploration
- Expected: each text type maps to exactly one primary skill; mismatch returns error or +5 DC (PM to confirm which)
- Roles covered: trained caster + trained knowledge character
- AC: DW-2
- PM NOTE: AC doesn't specify penalty for wrong skill. Does Decipher Writing have a wrong-skill penalty (like Identify Magic's +5), or does it simply not allow wrong-skill attempts? Clarification needed before automating this assertion.

## TC-DIL-04 — Decipher Writing: language literacy gate
- Description: Character without the text's language cannot attempt; Society allows GM-discretion override
- Suite: playwright/exploration
- Expected: literacy_check = fails if character.languages does not include text.language; GM override flag enables attempt
- Roles covered: literate and illiterate characters
- AC: DW-3

## TC-DIL-05 — Decipher Writing: Crit Success
- Description: Roll beats DC by 10+ → full meaning revealed
- Suite: playwright/exploration
- Expected: result.outcome = full_meaning; all text content returned
- Roles covered: trained character
- AC: DW-4

## TC-DIL-06 — Decipher Writing: Success
- Description: Roll meets DC → true meaning (coded text = general summary only)
- Suite: playwright/exploration
- Expected: result.outcome = true_meaning; coded text returns summary not full content
- Roles covered: trained character
- AC: DW-4

## TC-DIL-07 — Decipher Writing: Failure + retry penalty
- Description: Roll fails → blocked from retry with –2 circumstance penalty to same text
- Suite: playwright/exploration
- Expected: status = blocked_retry; penalty_flag = –2_circumstance stored against text_id
- Roles covered: trained character
- AC: DW-4 + Edge-1

## TC-DIL-08 — Decipher Writing: Crit Failure (secret)
- Description: Roll fails by 10+ → false interpretation shown; system marks internally as false; player cannot distinguish from success
- Suite: playwright/exploration
- Expected: player-visible result looks like success; internal field result.is_false = true
- Roles covered: trained character
- AC: DW-4, Failure-1

## TC-DIL-09 — Identify Magic: activity type and timing
- Description: Confirm Identify Magic is an exploration activity costing 10 minutes
- Suite: playwright/exploration
- Expected: phase = exploration; duration = 10 min
- AC: IM-1

## TC-DIL-10 — Identify Magic: skill routing by tradition
- Description: Arcana → arcane, Nature → primal, Occultism → occult, Religion → divine
- Suite: playwright/exploration
- Expected: each tradition item maps to correct skill; wrong-tradition skill triggers +5 DC penalty
- AC: IM-2, IM-3

## TC-DIL-11 — Identify Magic: degrees of success
- Description: Crit Success = full ID + bonus fact; Success = full ID; Failure = 1-day block same item; Crit Fail = false ID (secret)
- Suite: playwright/exploration
- Expected: four distinct outcome branches; Failure sets item.identify_blocked_until; CritFail sets result.is_false = true
- AC: IM-4, Failure-2

## TC-DIL-12 — Identify Magic: 1-day block is item-specific
- Description: After Failure on item A, item B can still be attempted immediately
- Suite: playwright/exploration
- Expected: identify_blocked_until is stored per item_id, not globally
- AC: Edge-2

## TC-DIL-13 — Identify Magic: pre-existing spell effects require this action
- Description: Active spell effects on a creature/object cannot be identified via Recall Knowledge; must use Identify Magic
- Suite: playwright/exploration
- Expected: recall_knowledge on active_spell_effect returns error or insufficient_info; identify_magic returns result
- AC: IM-5

## TC-DIL-14 — Learn a Spell: activity type, timing, and material cost
- Description: Exploration activity, 1 hour; material cost = spell_level × 10 gp, consumed on attempt
- Suite: playwright/exploration
- Expected: duration = 60 min; materials deducted from character.gold immediately on start
- AC: LAS-1, LAS-2

## TC-DIL-15 — Learn a Spell: degrees of success
- Description: Crit Success = learn + refund half cost; Success = learn, full cost consumed; Failure = not learned, NO cost consumed; Crit Failure = not learned + materials lost
- Suite: playwright/exploration
- Expected: four branches — CritSuccess refunds 50%; Success no refund; Failure restores cost; CritFail no restoration
- AC: LAS-4

## TC-DIL-16 — Learn a Spell: spellcasting class feature gate
- Description: Character without a spellcasting class feature cannot attempt Learn a Spell
- Suite: playwright/exploration
- Expected: attempt blocked; error = requires_spellcasting_class_feature
- AC: LAS-5, Edge-3

## TC-DIL-17 — Learn a Spell: Failure does NOT consume materials
- Description: On Failure (not Crit Fail), materials are returned (not consumed)
- Suite: playwright/exploration
- Expected: character.gold after Failure = character.gold before attempt (only gp deducted then restored)
- AC: Failure-3

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-decipher-identify-learn

## Gap analysis reference
- DB sections: core/ch04 General Skill Actions (REQs 1574–1590 — Decipher Writing, Identify Magic, Learn a Spell; already in_progress via dc-cr-skill-system)
- Depends on: dc-cr-skill-system ✓, dc-cr-dc-rarity-spell-adjustment, dc-cr-spellcasting

---

## Happy Path

### Decipher Writing [Exploration, Secret, Trained]
- [ ] `[NEW]` Decipher Writing is an exploration activity (1 min/page; ~1 hr/page for ciphers); requires Trained in applicable skill.
- [ ] `[NEW]` Skills: Arcana (arcane/esoteric texts), Occultism (metaphysical/occult texts), Religion (religious texts), Society (coded/legal/historical texts).
- [ ] `[NEW]` Character must be able to read the language; Society may allow attempt in unfamiliar language at GM discretion.
- [ ] `[NEW]` Degrees: Crit Success = full meaning; Success = true meaning (coded text = general summary); Failure = blocked + –2 circumstance penalty to retry same text; Crit Failure = false interpretation (player believes they succeeded).

### Identify Magic [Exploration, Trained]
- [ ] `[NEW]` Identify Magic is an exploration activity (10 minutes); requires Trained in applicable tradition skill.
- [ ] `[NEW]` Skills: Arcana (arcane), Nature (primal), Occultism (occult), Religion (divine).
- [ ] `[NEW]` DC = standard DC for the item/effect's level; +5 DC penalty if using wrong tradition skill.
- [ ] `[NEW]` Degrees: Crit Success = full ID + one bonus fact; Success = full identification; Failure = blocked for 1 day (cannot retry same item); Crit Failure = false identification (secret trait — player sees false result).
- [ ] `[NEW]` Pre-existing spell effects require this action (cannot use Recall Knowledge to identify active effects).

### Learn a Spell [Exploration, Trained]
- [ ] `[NEW]` Learn a Spell is an exploration activity (1 hour); requires Trained in applicable tradition skill.
- [ ] `[NEW]` Material cost = spell level × 10 gp; consumed on attempt (regardless of outcome).
- [ ] `[NEW]` DC = standard DC for the spell's level (from dc-cr-dc-rarity-spell-adjustment).
- [ ] `[NEW]` Degrees: Crit Success = learn spell + refund half material cost; Success = learn spell, full cost consumed; Failure = spell not learned, no cost (cost not consumed on failure); Crit Failure = spell not learned + materials lost.
- [ ] `[NEW]` Character must have an appropriate spellcasting class feature and the spell must be on their tradition's list.

---

## Edge Cases
- [ ] `[NEW]` Decipher Writing retry after Failure: –2 penalty persists for same text until fully deciphered.
- [ ] `[NEW]` Identify Magic 1-day block: same item only; another item can be attempted immediately.
- [ ] `[NEW]` Learn a Spell when character lacks a spellcasting class feature: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Decipher Writing Crit Fail: player sees a result (not an error); system marks internally as false.
- [ ] `[TEST-ONLY]` Identify Magic Crit Fail: false ID presented as true; player cannot distinguish it from success.
- [ ] `[TEST-ONLY]` Learn a Spell Failure: NO materials consumed (only Crit Fail destroys them).

## Security acceptance criteria
- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing exploration phase handlers
- Agent: qa-dungeoncrawler
- Status: pending
