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
