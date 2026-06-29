# Test Plan Design: dc-apg-equipment

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:54:16+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-apg-equipment/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-apg-equipment "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

# Acceptance Criteria: APG Equipment and Magic Items

## Feature: dc-apg-equipment
## Source: PF2E Advanced Player's Guide, Chapter 5 & 6

---

## New Weapons and Adventuring Gear

- [ ] `Sword Cane`: looks like a mundane cane while sheathed; social inspection does not automatically identify it as a weapon (requires exceptional check)
- [ ] `Bola`: thrown ranged weapon, no reload; on successful hit may attempt to Trip the target
- [ ] `Daikyu`: bow with mounted firing restriction — can only fire left-side when mounted
- [ ] All new weapons use standard weapon mechanics from Core Rulebook (damage dice, traits, proficiency)
- [ ] `Detective's Kit`: +1 item bonus on investigation/examination skill checks (Recall Knowledge, Seek, and similar)
- [ ] `Dueling Cape`: Interact action required to deploy; while deployed grants +1 item bonus to AC and +1 to Feint checks
- [ ] `Net` (two modes):
  - Rope-attached mode: extends Grapple range to 10 feet
  - Thrown mode: ranged attack; on critical hit, immobilizes target
  - Net imposes flat-footed and –10 ft Speed penalty; Escape DC 16; adjacent ally can remove with Interact action

---

## Alchemical Items (APG Additions)

- [ ] `Blight Bomb`: deals poison damage + persistent poison damage + splash damage (three-component bomb)
- [ ] `Dread Ampoule`: hit imposes Enfeebled 2 until start of thrower's next turn; has fear/emotion traits
- [ ] `Crystal Shards`: splash creates crystals acting as caltrops on floor, or climbing handholds on vertical surfaces (context-dependent effect)
- [ ] `Focus Cathartic`: attempts to counteract Confused or Stupefied (one condition per use; uses counteract rules); counteract modifier scales: +6 (L2), +8 (L4), +19 (L12), +28 (L18)
- [ ] `Sinew-Shock Serum`: attempts to counteract Clumsy or Enfeebled (one condition per use; same counteract scaling)
- [ ] `Olfactory Obfuscator`: suppresses scent-based detection; grants concealment vs. creatures using precise scent
- [ ] `Leadenleg`: reduces target's Speed (per-item entry reduction); Fortitude save resists
- [ ] `Cerulean Scourge`: high-level 3-stage affliction poison with escalating damage (stage 1/2/3 effects stored per item entry)
- [ ] `Timeless Salts`: prevent corpse decay for 1 week; extends viable magical revival window
- [ ] `Universal Solvent`: auto-counteracts sovereign glue; uses counteract check vs. other adhesives
- [ ] `Forensic Dye`: creates tracking mark on target; improves Seek/Track checks against marked target

---

## Consumable Magic Items (APG Additions)

- [ ] `Candle of Revealing`: removes invisible condition from creatures in area (not full visibility — concealed, not observed)
- [ ] `Dust of Corpse Animation`: creates one temporary undead minion (1-minute duration; maximum 4 total minions including this one at any time)
- [ ] `Potion of Retaliation`: damage type must be specified when crafted; deals that type in a damaging aura when holder is hit
- [ ] `Terrifying Ammunition`: on failed save, target cannot reduce Frightened below 1 until spending a concentrate action
- [ ] `Oil of Unlife`: applies negative healing to undead target (heals undead; does not harm them); follows standard potion application rules

---

## Permanent Magic Items (APG Additions)

- [ ] `Glamorous Buckler`: grants Feint bonus while shield is raised; daily activation on successful Feint causes dazzled condition on target
- [ ] `Victory Plate`: tracks creature kills of level ≥ plate level; records heraldic information; activated to grant resistance based on a trait of slain creatures
- [ ] `Rope of Climbing`: animates on activation; follows commands: stop, fasten, detach, knot, unknot
- [ ] `Slates of Distant Letters`: must be crafted as a pair; one slate breaking shatters both; 25 words per activation; maximum 1/hour use per slate
- [ ] `Four-Ways Dogslicer`: 3 property rune slots that can be swapped via 1-action Interact; activation costs 1d6 damage of the newly-activated type
- [ ] `Infiltrator's Accessory`: concealment property handled by social context rules (not magical detection evasion)
- [ ] `Winged Rune`: grants timed Fly Speed; frequency 1/hour; duration 5 minutes; dismissable early
- [ ] `Wand of Overflowing Life`: each activation grants caster a free 1-action heal targeting themselves (bonus effect on top of standard wand spell)
- [ ] `Wand of the Snowfields`: cone of cold adds environmental difficult terrain to affected area
- [ ] `Urn of Ashes`: reaction protecting against Doomed condition; finite protection (urn has its own doomed accumulation); only one doomed reduction per night's rest
- [ ] `Rod of Cancellation`: on counteract success, target magical effect or item is permanently canceled; cooldown 2d6 hours after activation

---

## APG Snares (Equipment)

- [ ] `Engulfing Snare`: creates cage structure; trapped creature gains immobilized condition; escape requires Escape check (DC 31) or destroying cage (Hardness 5, HP 30)
- [ ] `Flare Snare`: signal device — no damage; emits bright light; useful for scouting and alarm configurations

---

## Integration Checks

- [ ] New weapons appear in weapon selection with correct damage dice, traits, and proficiency grouping
- [ ] Sword Cane's concealed identity: social checks to identify it use standard Investigation/Perception rules
- [ ] Bola Trip attempt uses standard Trip rules after successful hit
- [ ] Alchemical bomb items (Blight Bomb, Dread Ampoule, Crystal Shards) integrate with existing bomb/splash system
- [ ] Counteract modifiers on Focus Cathartic/Sinew-Shock Serum stored per item tier (not hardcoded)
- [ ] APG snares appear in the snare selector alongside Core Rulebook snares
- [ ] Victory Plate kill tracking persists across sessions

## Edge Cases

- [ ] Dust of Corpse Animation 4-minion cap: attempting to create a 5th minion fails or destroys oldest
- [ ] Terrifying Ammunition failure state: Frightened reduction specifically blocked (normal recovery via mental checks still works for other conditions)
- [ ] Winged Rune dismissal: fly speed removed immediately; falling rules apply normally if in the air
- [ ] Rod of Cancellation: permanent cancellation — not temporary or counteractable (GM adjudication flag for edge cases)
- Agent: qa-dungeoncrawler
- Status: pending
