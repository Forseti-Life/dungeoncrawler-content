# Test Plan: dc-apg-class-swashbuckler

## Coverage summary
- AC items: ~28 (class record, panache, styles, precise strike, finishers, key abilities, integration, edge cases)
- Test cases: 16 (TC-SWS-01–16)
- Suites: playwright (character creation, encounter)
- Security: AC exemption granted (no new routes)

---

## TC-SWS-01 — Class record and starting proficiencies
- Description: HP 10+Con/level; key ability Dex; Trained Fortitude (upgrades L3); Expert Reflex/Will; trained simple+martial weapons, light armor; Swashbuckler class DC tracked
- Suite: playwright/character-creation
- Expected: stats correct; fortitude upgrades at L3; class_dc tracked at trained
- AC: Fundamentals-1–4

## TC-SWS-02 — Panache: binary state and speed bonus
- Description: Panache is binary (in/out); grants +5-ft status speed bonus; persists until encounter ends or Finisher used
- Suite: playwright/encounter
- Expected: panache = true → speed_bonus = +5 status; panache lost immediately when Finisher performed (before outcome)
- AC: Panache-1–3, Panache-7

## TC-SWS-03 — Panache: check bonus while active
- Description: Panache grants +1 circumstance to checks that would earn panache per selected style
- Suite: playwright/encounter
- Expected: while panache active, style skill check bonus = +1 circumstance
- AC: Panache-4

## TC-SWS-04 — Panache: earned by style's associated skill check success
- Description: Panache earned by succeeding at the style-specific skill check
- Suite: playwright/encounter
- Expected: style skill check success → panache = true; fail → no change
- AC: Panache-5

## TC-SWS-05 — Panache: GM award for very hard non-standard actions
- Description: GM may award panache for particularly daring non-standard actions vs Very Hard DC
- Suite: playwright/encounter
- Expected: GM-award panache mechanism exists (admin tool or GM confirmation); no auto-grant
- AC: Panache-6

## TC-SWS-06 — Styles: correct skill routing and feat grants
- Description: Battledancer (Fascinating Performance + Performance vs. Will), Braggart (Demoralize), Fencer (Feint/Create Diversion), Gymnast (Grapple/Shove/Trip), Wit (Bon Mot + Bon Mot feat)
- Suite: playwright/character-creation
- Expected: each style grants Trained in its skill; correct panache trigger linked; Battledancer grants Fascinating Performance; Wit grants Bon Mot
- AC: Styles-1–5

## TC-SWS-07 — Precise Strike: non-Finisher flat precision bonus
- Description: With panache + qualifying weapon: flat precision +2/+3/+4/+5/+6 at L1/5/9/13/17
- Suite: playwright/encounter
- Expected: non-finisher strike with panache → precision_bonus = flat per level table; requires agile or finesse weapon
- AC: PreciseStrike-1–2

## TC-SWS-08 — Precise Strike: Finisher precision dice
- Description: Finisher strike with panache: 2d6/3d6/4d6/5d6/6d6 precision dice at L1/5/9/13/17
- Suite: playwright/encounter
- Expected: finisher strike → precision_dice = table value; panache consumed before outcome
- AC: PreciseStrike-2

## TC-SWS-09 — Finisher: requires panache and blocks subsequent attack actions
- Description: Finisher requires panache; no additional attack-trait actions that turn after Finisher
- Suite: playwright/encounter
- Expected: Finisher blocked if panache = false; after Finisher, further attack actions that turn = blocked
- AC: Finisher-1–3, Edge-1

## TC-SWS-10 — Finisher: partial damage on failure (not crit fail)
- Description: Some Finishers have a Failure effect (partial damage); Crit Failure does NOT trigger failure effect
- Suite: playwright/encounter
- Expected: finisher.failure → half_damage (flat value); finisher.crit_failure → no failure effect triggered
- AC: Finisher-4

## TC-SWS-11 — Confident Finisher (base L1 Finisher)
- Description: 1-action; success = full Finisher precise strike; failure = half (flat value, not rolled)
- Suite: playwright/encounter
- Expected: success: full precision dice; failure: flat_half_value (no dice); action_cost = 1
- AC: Finisher-5, Edge-2

## TC-SWS-12 — Opportune Riposte (L3 reaction)
- Description: Triggers on foe's critical failure on Strike against swashbuckler; effect: melee Strike or Disarm
- Suite: playwright/encounter
- Expected: on foe_crit_fail_strike → reaction available; player chooses Strike or Disarm
- AC: Riposte-1

## TC-SWS-13 — Vivacious Speed (L3): speed bonus scaling
- Description: Speed bonus with panache: +10/+15/+20/+25/+30 ft at L3/7/11/15/19; without panache: half, rounded to nearest 5 ft
- Suite: playwright/character-creation
- Expected: at L3 with panache: speed_bonus = +10; without panache: +5; scales at later levels
- AC: VSpeed-1–2, Edge-3

## TC-SWS-14 — Exemplary Finisher (L9): style-specific effect on hit
- Description: Activates on Finisher Strike hit; applies style-specific effect
- Suite: playwright/encounter
- Expected: Exemplary Finisher fires only on hit (not miss or failure); effect matches selected style
- AC: ExFin-1, Edge-4

## TC-SWS-15 — Integration: panache state display and auto-toggle on Finisher
- Description: Panache state displayed prominently in encounter UI; toggles automatically when Finisher used
- Suite: playwright/encounter
- Expected: panache indicator visible; Finisher action removes panache immediately (before resolution)
- AC: Integration-1

## TC-SWS-16 — Integration: correct skill for panache generation per style
- Description: Each style's panache trigger uses the correct skill in the encounter phase
- Suite: playwright/encounter
- Expected: Performance (Battledancer), Intimidation (Braggart), Deception (Fencer), Athletics (Gymnast), Diplomacy (Wit) — each style uses only its associated skill for panache generation
- AC: Integration-2
