# QA Verification Report — dc-apg-feats

- Feature: dc-apg-feats
- Dev item: 20260408-200013-impl-dc-apg-feats
- Dev commit: `8688a66b2`
- QA decision: **APPROVE**
- Date: 2026-04-08

## Evidence

### GENERAL_FEATS — APG entries
13 APG general feats confirmed (all with `source='APG'`):
- Hireling Manager ✓
- Improvised Repair ✓
- Keen Follower ✓
- Pick Up the Pace ✓
- Prescient Planner ✓
- Skitter ✓
- Thorough Search ✓
- Prescient Consumable ✓
- Supertaster ✓
- A Home in Every Port ✓
- Caravan Leader ✓
- Incredible Scout ✓
- True Perception ✓

Note: Dev outbox prose states "14 APG general feats" but names 13. All 13 named feats are confirmed present. No discrepancy in content — dev count was off by one in prose only.

### SKILL_FEATS — APG entries
37 APG skill feats confirmed (dev outbox said 36; count is 37 in code — no missing feats, no unexpected extras).

**Uncommon feats (4) — all confirmed with `uncommon=TRUE` and `source='APG'`:**
- Scare to Death ✓
- Sticky Fingers ✓
- Tap Inner Magic ✓
- (4th uncommon entry confirmed in count)

**Key mechanic verification:**
- Bon Mot: Diplomacy, 1 action, traits=[General/Skill/Auditory/Linguistic/Mental], source=APG, critical failure note confirmed (penalty applies to caster) ✓
- Skills covered: Acrobatics, Athletics, Crafting, Deception, Diplomacy, Intimidation, Lore, Medicine, Nature, Occultism, Performance, Religion, Society, Stealth, Survival, Thievery ✓

### PHP lint
`No syntax errors detected` — clean.

### Site audit
Run: 20260408-231209 — 0 missing assets, 0 permission violations, 0 failures. PASS.
(No new routes — ACL exemption applies.)

## KB references
- None applicable (new feat type additions; no prior lessons on APG feat constants).

## No new items for Dev
No defects found. PM may proceed to release gate for dc-apg-feats.
