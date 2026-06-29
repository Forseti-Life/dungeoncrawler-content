# Test Plan: dc-cr-familiar

## Coverage summary
- AC items: ~16 (content type, daily ability selection, familiar vs. companion distinction, touch spell delivery, class-specific rules)
- Test cases: 10 (TC-FAM-01–10)
- Suites: playwright (character creation, encounter, downtime)
- Security: Daily ability selection is server-validated

---

## TC-FAM-01 — Familiar content type: HP = 5 × level, default land speed 25 ft
- Description: Familiar stores familiar_id, character_id, familiar_type, HP, speed, ability list; HP = 5 × character_level
- Suite: playwright/character-creation
- Expected: familiar.HP = 5 × level; familiar.speed = 25 (land default); HP updates on level-up
- AC: AC-001

## TC-FAM-02 — Daily ability selection up to class-granted maximum
- Description: Each day, character selects familiar abilities up to max; max = 2 base + 1 per relevant class feat
- Suite: playwright/character-creation
- Expected: ability selector shows count up to current max; exceeding max blocked; selections reset each day
- AC: AC-002

## TC-FAM-03 — Familiar ability catalog and prerequisites enforced
- Description: Available abilities: Amphibious, Climber, Darkvision, Fast Movement, Flier, Skilled, Speech, Spellcasting, Tough, others; prerequisites enforced (e.g., Flier requires wings)
- Suite: playwright/character-creation
- Expected: Flier blocked if familiar_has_wings = false; all listed abilities appear in selector; prereq check fires
- AC: AC-002

## TC-FAM-04 — Familiar has no combat stats (no attack/damage)
- Description: Familiar content type has no attack_bonus or damage_entries
- Suite: playwright/character-creation
- Expected: familiar record lacks attack/damage fields; UI does not show attack option for familiar
- AC: AC-003

## TC-FAM-05 — Familiar dies at 0 HP; replaced via 1-week downtime
- Description: Familiar dies at 0 HP (not unconscious like animal companion); replaced with 1 week downtime ritual
- Suite: playwright/encounter
- Expected: familiar.HP = 0 → familiar.state = dead; replacement = 1 week downtime ritual
- AC: AC-003

## TC-FAM-06 — Familiar delivers touch spells
- Description: Familiar can deliver touch-range spells as its action within reach
- Suite: playwright/encounter
- Expected: touch spell + familiar in range → familiar_delivery option offered; resolution = same as caster touching
- AC: AC-004

## TC-FAM-07 — Wizard Arcane Bond: standard familiar rules
- Description: Wizard with Arcane Bond follows standard familiar rules
- Suite: playwright/character-creation
- Expected: Arcane Bond grants familiar with standard ability selection; no deviation from base rules
- AC: AC-005

## TC-FAM-08 — Witch familiar: mandatory, stores prepared spells
- Description: Witch familiar is required (not optional); stores witch's prepared spells as patron's vessel
- Suite: playwright/character-creation
- Expected: witch character must have familiar; familiar.spell_storage = witch's prepared spells; familiar cannot be dismissed
- AC: AC-005

## TC-FAM-09 — Daily ability count: server-validated against allowed maximum
- Description: Server validates daily ability selection count against class-granted max; exceeding max rejected
- Suite: playwright/character-creation
- Expected: API/server rejects ability selection count > max; no client-side bypass possible
- AC: Security AC

## TC-FAM-10 — Familiar Spellcasting ability: stores 1 spell slot in familiar
- Description: Familiar with Spellcasting ability can store and deliver 1 spell slot for caster
- Suite: playwright/encounter
- Expected: Spellcasting ability adds 1 stored slot to familiar; slot usable via familiar delivery
- AC: AC-002 (ability catalog)
