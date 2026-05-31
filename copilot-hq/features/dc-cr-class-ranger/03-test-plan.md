# Test Plan: dc-cr-class-ranger

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Ranger Class — identity/stats, Hunt Prey, Hunter's Edge (Flurry/Precision/Outwit), feat progression, edge cases
**KB reference:** none found (first Ranger class feature)

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Ranger class business logic, stat derivation, Hunt Prey, Hunter's Edge mechanics, feat/boost gating |
| `role-url-audit` | HTTP role audit | ACL regression — no new Ranger-specific routes; existing character routes only |

---

## Test Cases

### TC-RNG-01 — Ranger class selectable at character creation
- **Suite:** module-test-suite
- **Description:** Ranger appears in the class selection list during character creation.
- **Expected:** Ranger is a valid selectable class in the character creation flow.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-02 — Key ability: STR selection
- **Suite:** module-test-suite
- **Description:** Player selects STR as key ability boost for Ranger at level 1; character sheet reflects STR key ability.
- **Expected:** Key ability = STR; STR mod applied to class DC and relevant attack rolls per class rules.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-03 — Key ability: DEX selection
- **Suite:** module-test-suite
- **Description:** Player selects DEX as key ability boost for Ranger at level 1; character sheet reflects DEX key ability.
- **Expected:** Key ability = DEX; DEX mod applied to class DC and relevant attack rolls per class rules.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-04 — HP per level: 10 + CON modifier
- **Suite:** module-test-suite
- **Description:** At each level-up, Ranger gains exactly 10 + CON modifier HP.
- **Expected:** HP delta per level = 10 + CON mod (minimum 1 per level if CON is negative).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-05 — Initial proficiency: Expert Perception
- **Suite:** module-test-suite
- **Description:** Ranger starts with Expert rank in Perception at level 1.
- **Expected:** Perception proficiency = Expert (not Trained) at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-06 — Initial proficiencies: saves (Trained Fort/Reflex/Will)
- **Suite:** module-test-suite
- **Description:** Ranger starts with Trained in all three saves.
- **Expected:** Fortitude = Trained, Reflex = Trained, Will = Trained at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-07 — Initial proficiencies: weapons (Trained simple + martial)
- **Suite:** module-test-suite
- **Description:** Ranger starts Trained in simple and martial weapons.
- **Expected:** Simple weapon proficiency = Trained; martial weapon proficiency = Trained at level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-08 — Initial proficiencies: armor (Trained light + medium)
- **Suite:** module-test-suite
- **Description:** Ranger starts Trained in light and medium armor.
- **Expected:** Light armor = Trained; medium armor = Trained at level 1; heavy armor = Untrained.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-09 — Hunt Prey: designates one creature as prey
- **Suite:** module-test-suite
- **Description:** Using Hunt Prey (1 action) on a creature marks it as hunted prey on the character state.
- **Expected:** Target creature flagged as hunted prey; prey-conditional effects apply to that creature.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-10 — Hunt Prey: only one prey at a time (no Double Prey)
- **Suite:** module-test-suite
- **Description:** Without Double Prey feat, using Hunt Prey on a second creature removes the first prey designation.
- **Expected:** After Hunt Prey on creature B, creature A is no longer prey; only one prey exists.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-11 — Hunt Prey: replacing prey removes previous
- **Suite:** module-test-suite
- **Description:** Failure mode validation — previous prey designation removed when new prey hunted.
- **Expected:** Old prey benefits (MAP reduction, precision bonus, Outwit bonuses) no longer apply to previous target.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-12 — Hunter's Edge selection at level 1
- **Suite:** module-test-suite
- **Description:** At level 1, player is required to choose exactly one Hunter's Edge from {Flurry, Precision, Outwit}.
- **Expected:** Exactly one Hunter's Edge subclass recorded on character; no character advances to level 2 without a selection.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-13 — Flurry: reduced MAP vs hunted prey (non-agile)
- **Suite:** module-test-suite
- **Description:** With Flurry edge, second and third attacks against hunted prey use –3/–6 MAP instead of –5/–10.
- **Expected:** Attack 2 vs prey = –3 MAP; attack 3 vs prey = –6 MAP (non-agile weapon).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-14 — Flurry: reduced MAP vs hunted prey (agile weapon)
- **Suite:** module-test-suite
- **Description:** With Flurry edge and agile weapon, MAP vs hunted prey = –2/–4.
- **Expected:** Attack 2 vs prey = –2 MAP; attack 3 vs prey = –4 MAP (agile weapon).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-15 — Flurry: normal MAP vs non-prey targets
- **Suite:** module-test-suite
- **Description:** Flurry MAP reduction does not apply when attacking a creature that is NOT the hunted prey.
- **Expected:** MAP vs non-prey = standard –5/–10 (or –4/–8 agile).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-16 — Flurry MAP floor: cannot reduce below minimum values
- **Suite:** module-test-suite
- **Description:** Failure mode — Flurry MAP cannot be further reduced by other effects below –2/–4 (agile) or –3/–6 (non-agile) specifically because Flurry is a floor rule.
- **Expected:** MAP with Flurry = max(flurry_value, other_reductions); does not stack below flurry values.
- **Notes to PM:** Confirm stacking interaction rule with other MAP-reduction effects (e.g., Agile + Flurry). If Agile applies first then Flurry is a separate cap, exact stacking order needs dev confirmation.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (stacking rule note flagged)

### TC-RNG-17 — Precision: +1d8 on first hit per round vs prey (level 1–10)
- **Suite:** module-test-suite
- **Description:** With Precision edge, the first successful Strike against hunted prey per round adds +1d8 precision damage.
- **Expected:** Hit 1 vs prey = base damage + 1d8 precision; verified via damage breakdown.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-18 — Precision: bonus applies only to first hit per round
- **Suite:** module-test-suite
- **Description:** Second and subsequent hits vs hunted prey in the same round do NOT receive the +1d8 bonus.
- **Expected:** Hit 2+ vs prey = base damage only (no precision bonus).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-19 — Precision: scales to 2d8 at level 11, 3d8 at level 19
- **Suite:** module-test-suite
- **Description:** Precision damage bonus increases at levels 11 and 19.
- **Expected:** Level 1–10 = +1d8; level 11–18 = +2d8; level 19–20 = +3d8.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-20 — Outwit: +2 circumstance to Deception/Intimidation/Stealth/Recall Knowledge vs prey
- **Suite:** module-test-suite
- **Description:** With Outwit edge, skill checks against hunted prey get +2 circumstance bonus on Deception, Intimidation, Stealth, and Recall Knowledge.
- **Expected:** Each listed skill check vs prey = roll + 2 circumstance in addition to normal modifiers.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-21 — Outwit: +1 circumstance to AC vs prey's attacks
- **Suite:** module-test-suite
- **Description:** With Outwit edge, character's AC gains +1 circumstance bonus against attacks made by the hunted prey.
- **Expected:** AC vs hunted prey attacks = base AC + 1 circumstance; AC vs other attackers = base AC (no bonus).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-22 — Outwit: bonuses only vs designated prey
- **Suite:** module-test-suite
- **Description:** Outwit skill and AC bonuses do not apply when the target/attacker is not the hunted prey.
- **Expected:** No circumstance bonus to Deception/Intimidation/Stealth/RC or AC when interacting with non-prey.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-23 — Feat progression: class feat at level 1 and every even level
- **Suite:** module-test-suite
- **Description:** Ranger receives class feats at levels 1, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20.
- **Expected:** Class feat slots available at each of those levels; no extra class feat at odd levels beyond level 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-24 — Feat progression: general feats at levels 3, 7, 11, 15, 19
- **Suite:** module-test-suite
- **Description:** Ranger receives general feat slots at levels 3, 7, 11, 15, 19 only.
- **Expected:** General feat slots at exactly those levels.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-25 — Feat progression: skill feats every even level
- **Suite:** module-test-suite
- **Description:** Ranger receives skill feat slots at levels 2, 4, 6, 8, 10, 12, 14, 16, 18, 20.
- **Expected:** Skill feat slot at each even level.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-26 — Feat progression: ancestry feats at levels 5, 9, 13, 17
- **Suite:** module-test-suite
- **Description:** Ranger receives ancestry feat slots at levels 5, 9, 13, 17 only.
- **Expected:** Ancestry feat slots at exactly those levels.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-27 — Ability boosts at levels 5, 10, 15, 20
- **Suite:** module-test-suite
- **Description:** Ranger receives four ability boosts at levels 5, 10, 15, 20.
- **Expected:** Four ability boost choices available at each of those levels; none at other levels (standard class schedule).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-28 — Level-gated features do not appear before required level
- **Suite:** module-test-suite
- **Description:** Features gated to higher levels are not accessible or displayed at lower levels.
- **Expected:** Level-gated class abilities (e.g., Precision scaling, advanced feats) unavailable below their minimum level.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-29 — Double Prey feat: two simultaneous prey designations
- **Suite:** module-test-suite
- **Description:** With Double Prey feat taken, using Hunt Prey allows two creatures to be simultaneously designated as prey.
- **Expected:** Two prey slots populated; benefits of Hunter's Edge apply to both; third Hunt Prey replaces oldest.
- **Notes to PM:** Confirm "oldest replaced" vs "player chooses" when a third Hunt Prey is used with Double Prey — clarification needed for full TC parameterization.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (third-prey replacement rule flagged for PM)

### TC-RNG-30 — Hunted Shot: both attacks count for MAP; once per round
- **Suite:** module-test-suite
- **Description:** Hunted Shot makes 2 ranged Strikes; both count for MAP accumulation; ability is usable only once per round.
- **Expected:** After Hunted Shot, MAP = –10 (two strikes taken); second use of Hunted Shot same round blocked.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-31 — Warden's Boon: ally gains prey benefits for one turn only
- **Suite:** module-test-suite
- **Description:** Warden's Boon grants one ally the Hunter's Edge benefits vs the Ranger's hunted prey for their next turn only; benefit does not persist to subsequent turns.
- **Expected:** Ally has prey benefits on their immediate next turn; benefits absent on ally's following turn without re-application.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RNG-32 — ACL regression: no new routes introduced by Ranger
- **Suite:** role-url-audit
- **Description:** Ranger class implementation adds no new HTTP routes; existing character creation/leveling routes remain accessible per their existing ACL.
- **Expected:** HTTP 200 for authenticated player on existing character routes; HTTP 403 for anonymous on auth-required routes (no change from baseline).
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Deferred dependency summary

| TC | Dependency | Reason deferred |
|---|---|---|
| None | — | All 32 TCs are immediately activatable at Stage 0. |

No TCs in this plan depend on unshipped features. Ranger has no spellcasting, no animal companion, and no conditions dependencies beyond standard MAP/hit tracking already covered by `dc-cr-character-class`.

---

## Notes to PM

1. **TC-RNG-16 (Flurry MAP floor):** Stacking order of Flurry + Agile weapon MAP reductions needs explicit dev decision — does Agile apply first and Flurry is a separate floor, or do they combine? This affects the exact MAP assertion values.
2. **TC-RNG-29 (Double Prey replacement):** When a third Hunt Prey is used with Double Prey feat, confirm which prey is replaced (oldest, or player-selected). Needed to fully parameterize TC-RNG-29 edge assertion.
3. **Hunted Shot / Warden's Boon (TC-RNG-30, TC-RNG-31):** These are feat-level ACs; they are written as TCs based on the AC text but will need feat implementation to be in scope before Stage 0 activation.
