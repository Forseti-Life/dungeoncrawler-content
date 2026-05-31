# Test Plan: dc-apg-class-witch

## Coverage summary
- AC items: ~30 (class record, patron, familiar, spell learning, hexes, lessons, integration, edge cases)
- Test cases: 18 (TC-WCH-01–18)
- Suites: playwright (character creation, encounter, downtime)
- Security: AC exemption granted (no new routes)

---

## TC-WCH-01 — Class record and saves
- Description: HP 6+Con/level; key ability Int; Trained Fortitude/Reflex; Expert Will; no armor proficiency; trained unarmored only
- Suite: playwright/character-creation
- Expected: stats correct; armor_proficiency = unarmored only; key_ability = Int
- AC: Fundamentals-1–4

## TC-WCH-02 — Patron theme: determines tradition, skill, hex cantrip, granted spell
- Description: Patron theme selected at L1; cannot change; grants tradition, patron skill (trained), hex cantrip, familiar's first spell
- Suite: playwright/character-creation
- Expected: 7 themes available; after selection, theme locked; patron_skill granted as trained; tradition = theme's tradition
- AC: Patron-1–2

## TC-WCH-03 — Familiar: mandatory class feature
- Description: Witch must have a familiar; cannot opt out; familiar holds all spells
- Suite: playwright/character-creation
- Expected: familiar created automatically at character creation; cannot be dismissed; all spells stored in familiar
- AC: Familiar-1

## TC-WCH-04 — Familiar: bonus abilities at L1/6/12/18
- Description: Familiar gains one extra ability at L1, L6, L12, and L18
- Suite: playwright/character-creation
- Expected: extra familiar ability unlocked at each milestone level; correct count total
- AC: Familiar-2

## TC-WCH-05 — Familiar death: spells preserved, replacement at next prep
- Description: Familiar death does NOT erase known spells; new familiar with same spells restored at next daily prep
- Suite: playwright/encounter
- Expected: familiar.death → spells_list unchanged; new familiar = same spell list at next daily_prep
- AC: Familiar-3–4

## TC-WCH-06 — Witch prepared spellcasting via familiar commune
- Description: Witch uses prepared (not spontaneous) spellcasting; requires communing with familiar to prepare
- Suite: playwright/character-creation
- Expected: spellcasting_type = prepared; preparation UI shows "commune with familiar" step; no spontaneous casting
- AC: Familiar-3

## TC-WCH-07 — Spell repertoire: starting count and per-level growth
- Description: Familiar starts with 10 cantrips + 5 1st-level + 1 patron spell; +2 player-chosen spells per level-up
- Suite: playwright/character-creation
- Expected: initial familiar.spells count = 16; on each level-up: +2 player selections from tradition spell list
- AC: Repertoire-1–2

## TC-WCH-08 — Familiar learns from scrolls and between familiars
- Description: Familiar can absorb scrolls (1 hr each); two witch familiars can share spells via Learn a Spell; direct preparation from another familiar blocked
- Suite: playwright/downtime
- Expected: scroll absorption available (1 hr, consumes scroll); familiar_to_familiar transfer uses Learn a Spell (cost + both present); direct prep from foreign familiar blocked
- AC: Repertoire-3–5

## TC-WCH-09 — Hexes: cost Focus Point; one per turn; focus pool starts at 1
- Description: Regular hexes cost 1 FP; hex cantrips cost 0 FP; only one hex per turn (any type); focus pool = 1 FP
- Suite: playwright/encounter
- Expected: hex.cost = 1 FP; hex_cantrip.cost = 0; second hex attempt same turn blocked; initial focus_pool = 1
- AC: Hexes-1–4

## TC-WCH-10 — Hex cantrips: auto-heighten, no FP, one-per-turn
- Description: Hex cantrips auto-heighten to half witch level rounded up; still count as "hex used this turn"
- Suite: playwright/character-creation
- Expected: hex_cantrip.effective_level = ceil(level/2); using hex_cantrip blocks second hex that turn
- AC: Hexes-5–7

## TC-WCH-11 — Hex cantrips are separate from prepared cantrips
- Description: Hex cantrips do not occupy prepared cantrip slots
- Suite: playwright/character-creation
- Expected: character.cantrip_slots unaffected by hex cantrips; hex cantrips in separate pool
- AC: Hexes-8

## TC-WCH-12 — Refocus: 10 minutes communing with familiar = +1 FP
- Description: Refocus for witch = 10 min commune with familiar; restores 1 FP
- Suite: playwright/encounter
- Expected: refocus.activity = commune_with_familiar; duration = 10 min; fp_restored = 1
- AC: Hexes-3

## TC-WCH-13 — Witch Lessons: tiered feat mechanism
- Description: Each lesson grants one hex + one familiar spell; tiered L2/L6/L10; Basic/Greater/Major tiers
- Suite: playwright/character-creation
- Expected: lesson feats gated by tier requirements; each lesson adds 1 hex to focus options and 1 spell to familiar
- AC: Lessons-1–2

## TC-WCH-14 — Basic Lessons catalog
- Description: 5 Basic lessons (Dreams, Elements, Life, Protection, Vengeance) each grant specific hex + spell
- Suite: playwright/character-creation
- Expected: each Basic lesson grants correct hex + spell pair; accessible at L2+
- AC: Lessons-3

## TC-WCH-15 — Greater and Major Lessons catalog
- Description: Greater Lessons (Mischief, Shadow, Snow) at L6+; Major Lessons (Death, Renewal) at L10+
- Suite: playwright/character-creation
- Expected: Greater lessons blocked before L6; Major lessons blocked before L10; correct hex/spell pairs
- AC: Lessons-4–5

## TC-WCH-16 — Notable hexes: Cackle, Evil Eye, Phase Familiar
- Description: Cackle extends active hex duration +1 round; Evil Eye: 0 FP, –2 status, ends on Will save; Phase Familiar: incorporeal briefly negating one damage source
- Suite: playwright/encounter
- Expected: Cackle extends current hex (not a second hex cast); Evil Eye status penalty removed on target Will success; Phase Familiar limited to 1 damage negation
- AC: NotableHexes-1–3, Edge-1–2

## TC-WCH-17 — Integration: familiar spell list grows by 2 per level-up
- Description: After each level gained, familiar.spells grows by exactly 2 player-chosen spells
- Suite: playwright/character-creation
- Expected: level_up event triggers familiar spell selection UI; +2 spells added
- AC: Integration-3

## TC-WCH-18 — Edge: Cackle on a hex cantrip (valid extension, not second hex)
- Description: Using Cackle to extend a sustained hex cantrip is valid; Cackle does not count as casting a second hex
- Suite: playwright/encounter
- Expected: Cackle extends hex_cantrip duration; one_hex_per_turn counter = 2 (cantrip + Cackle) is allowed since Cackle extends, does not cast
- AC: Edge-3
