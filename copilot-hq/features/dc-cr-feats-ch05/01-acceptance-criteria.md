# Acceptance Criteria: dc-cr-feats-ch05

## Gap analysis reference
- DB sections: core/ch05/Chapter Overview (5 reqs), core/ch05/Key Feat Mechanic Notes (17 reqs), core/ch05/Non-Skill General Feats Table (2 reqs)
- Depends on: dc-cr-general-feats, dc-cr-skill-feats, dc-cr-character-leveling

---

## Happy Path

### Feat Category System
- [ ] `[NEW]` System supports a general feat category accessible to all characters regardless of class or ancestry.
- [ ] `[NEW]` General feat slots granted at levels 3, 7, 11, 15, 19.
- [ ] `[NEW]` Skill feats are a subcategory of general feats; granted at level 2 and every 2 levels thereafter.
- [ ] `[NEW]` Skill feat slot requires a feat with the `skill` trait; non-skill general feats cannot fill skill feat slots.
- [ ] `[NEW]` Feat level = minimum character level at which a character could meet the feat's proficiency prerequisite.

### Repeatable Feats
- [ ] `[NEW]` Repeatable feats (Armor Proficiency, Weapon Proficiency) track progression; subsequent selections must respect prior grants.
- [ ] `[NEW]` Each non-skill general feat is implementable as a character option at its listed level.

### Assurance [Fortune Feat]
- [ ] `[NEW]` Assurance produces a fixed result = 10 + proficiency bonus; no other bonuses, penalties, or modifiers apply.
- [ ] `[NEW]` Can be selected once per skill; each selection (per skill) is tracked independently.

### Recognize Spell [Reaction]
- [ ] `[NEW]` Recognize Spell is a reaction with no action cost; requires awareness of casting.
- [ ] `[NEW]` Auto-identify thresholds (common spells only): Trained ≤ level 2, Expert ≤ level 4, Master ≤ level 6, Legendary ≤ level 10.
- [ ] `[NEW]` Crit Success: +1 circumstance bonus to save or AC vs that spell.
- [ ] `[NEW]` Crit Failure: false identification (player sees wrong spell name/effect).

### Trick Magic Item [1 action, Manipulate]
- [ ] `[NEW]` Trick Magic Item requires Trained in the appropriate tradition skill and knowledge of the item's function.
- [ ] `[NEW]` Spell attack/DC falls back to level-based proficiency + highest mental ability score.
- [ ] `[NEW]` Crit Failure: locked out of using the item until next daily preparations.

### Battle Medicine [1 action, Manipulate]
- [ ] `[NEW]` Battle Medicine requires healer's tools and Trained Medicine; uses same DC/HP table as Treat Wounds.
- [ ] `[NEW]` Does NOT remove the wounded condition (distinct from Treat Wounds which may clear dying-adjacent states).
- [ ] `[NEW]` Per-character 1-day immunity after receiving Battle Medicine; does not block other healers from using Treat Wounds on same target.

### Specialty Crafting
- [ ] `[NEW]` Specialty Crafting grants +1 circumstance bonus to relevant Craft checks; at Master proficiency, bonus increases to +2.
- [ ] `[NEW]` GM-adjudicated partial applicability for items spanning multiple specialties (system flags for manual review).

### Virtuosic Performer
- [ ] `[NEW]` Virtuosic Performer grants +1 circumstance bonus to chosen performance type; at Master proficiency, +2.

---

## Edge Cases
- [ ] `[NEW]` Skill feat slot at level 2 filled with non-skill feat: blocked.
- [ ] `[NEW]` Assurance selected twice for same skill: blocked (one selection per skill).
- [ ] `[NEW]` Battle Medicine on target already treated today: blocked for same healer.

## Failure Modes
- [ ] `[TEST-ONLY]` Assurance modifiers/penalties: silently ignored (not added to result).
- [ ] `[TEST-ONLY]` Recognize Spell Crit Fail: returns false info, not an error.
- [ ] `[TEST-ONLY]` Trick Magic Item Crit Fail: locked out per-item per-day (not permanently).

## Security acceptance criteria
- Security AC exemption: game-mechanic feat system logic; no new routes or user-facing input beyond existing character creation and leveling forms
