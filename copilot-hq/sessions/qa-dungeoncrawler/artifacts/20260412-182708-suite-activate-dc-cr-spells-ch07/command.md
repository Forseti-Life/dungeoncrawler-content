- Status: done
- Completed: 2026-04-12T22:54:46Z

# Suite Activation: dc-cr-spells-ch07

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:27:08+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-spells-ch07"`**  
   This links the test to the living requirements doc at `features/dc-cr-spells-ch07/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-spells-ch07-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-spells-ch07",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-spells-ch07"`**  
   Example:
   ```json
   {
     "id": "dc-cr-spells-ch07-<route-slug>",
     "feature_id": "dc-cr-spells-ch07",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-spells-ch07",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-spells-ch07 — Chapter 7 Spells

**QA owner:** qa-dungeoncrawler
**Feature:** dc-cr-spells-ch07
**Depends on:** dc-cr-spellcasting, dc-cr-focus-spells, dc-cr-rituals
**KB reference:** none found (first spellcasting rules test plan in this batch)

---

## Summary

57 TCs (TC-SP-01–57) covering: traditions/schools, spell slots (prepared/spontaneous), heightening, cantrips/focus spells, innate spells, casting mechanics (actions/components/metamagic), spell attacks/DCs, area/range/targeting, durations, special spell types (illusions/counteract/polymorph/summoning), spell stat block data model, spell lists (content gates), focus spells by class, edge cases, and failure modes.

**Dependency split:**
- 46 TCs immediately activatable (depend on dc-cr-spellcasting only, which is already in the backlog)
- 11 TCs conditional on dc-cr-focus-spells (TC-SP-21–28) or dc-cr-rituals (TC-SP-57)

---

## Test Cases

### Traditions and Schools (TC-SP-01–05)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-01 | Spell entity has `traditions[]` array (one or more of Arcane/Divine/Occult/Primal) and `school` field (exactly one of 8 schools) | Playwright / data validation | Spell with tradition="arcane" and school="evocation" displays correctly; saving with two schools returns validation error | authenticated |
| TC-SP-02 | Caster's tradition is determined by class; tradition trait appended to cast spell action output | Playwright / encounter | Wizard casts spell → output includes tradition trait "arcane"; Cleric → "divine" | authenticated |
| TC-SP-03 | School specialization field (class feature flag) captured in character entity; no automatic bonus applied without flag | Playwright / character creation | Wizard with school specialization flag set → specialization effects available; without flag → blocked | authenticated |
| TC-SP-04 | Essence classification (mental/vital/material/spiritual) stored on spell entity as lore field | Data validation | Spell retrieval API returns `essence_type` field matching one of four values; null allowed for non-classified spells | authenticated |
| TC-SP-05 | Tradition membership gate: arcane caster can only access arcane list spells (not divine-only spells) unless multi-tradition class feature | Playwright / spellcasting | Wizard with no multi-tradition feature cannot add divine-only spell to spell slots | authenticated |

### Spell Slots and Spellcasting Types (TC-SP-06–11)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-06 | Character tracks spell slots per level (1–10) independently; not a shared pool | Playwright / character sheet | Expending a 3rd-level slot does not reduce 2nd-level slot count | authenticated |
| TC-SP-07 | Spell slots refresh on daily preparation event | Playwright / downtime | After daily prep event fires, all spell slots restored to max; partial restoration is a regression | authenticated |
| TC-SP-08 | Prepared spellcaster: daily prep locks in spell selection per slot; casting expends that slot | Playwright / encounter | Wizard casts prepared spell → slot consumed; same spell not castable from same slot again until next daily prep | authenticated |
| TC-SP-09 | Same spell prepared in multiple slots allows multiple casts per day | Playwright / encounter | Wizard with Magic Missile prepared in two 1st-level slots can cast it twice | authenticated |
| TC-SP-10 | Spontaneous caster: any known spell castable from any available same-or-higher slot | Playwright / encounter | Sorcerer with 3 known spells can cast any of them using available slots in any order; slot level must be ≥ spell level | authenticated |
| TC-SP-11 | Spontaneous caster: daily prep refreshes slot count, not repertoire; repertoire unchanged after prep | Playwright / character sheet | After daily prep, sorcerer slot count restored but known spells list identical to pre-prep state | authenticated |

### Signature Spells (TC-SP-12–13)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-12 | Signature spell (class feature flag) can be heightened to any available slot level, even if only known at one level | Playwright / encounter | Sorcerer with signature spell in repertoire at 1st-level can cast it in a 5th-level slot | authenticated |
| TC-SP-13 | Non-signature spontaneous spell blocked from heightening to level not in repertoire | Playwright / encounter | Sorcerer without signature flag on spell → attempting to cast in higher slot level returns "cannot heighten: not signature spell" | authenticated |

### Heightening (TC-SP-14–16)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-14 | Heightened (Nth) entries: bonus applies only at exact level specified | Playwright / encounter | Spell cast at level 4 shows Heightened (4th) bonus; cast at level 5 does not show it | authenticated |
| TC-SP-15 | Heightened (+N) cumulative entries: bonus stacks for each increment above base | Playwright / encounter | Spell with base level 2 and "+2" entry: cast at level 4 applies 1× bonus, level 6 applies 2× bonus | authenticated |
| TC-SP-16 | Heightening requires a slot of sufficient level; lower slot rejected | Playwright / encounter | Attempting to cast 3rd-level spell in 2nd-level slot returns validation error | authenticated |

### Cantrips (TC-SP-17–18)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-17 | Cantrip effective level = ceil(character level / 2); auto-heightened | Playwright / character sheet | Level 1 character: cantrip level 1; Level 5: cantrip level 3; Level 10: cantrip level 5 | authenticated |
| TC-SP-18 | Cantrips cost no spell slots; castable any number of times | Playwright / encounter | Casting cantrip 10 times in one encounter does not deplete any slot | authenticated |

### Focus Spells and Focus Pool (TC-SP-19–28)

| TC | Description | Suite | Expected behavior | Roles | Dependency |
|----|-------------|-------|-------------------|-------|------------|
| TC-SP-19 | Focus pool separate from spell slots; max cap 3 | Playwright / character sheet | Adding 4 focus abilities still shows max pool = 3 | authenticated | — |
| TC-SP-20 | Refocus activity (10 min): restores 1 Focus Point; requires class-specific deeds modeled | Playwright / downtime | Character with 0 FP and Refocus ability gains 1 FP after 10-min Refocus; second Refocus has no additional effect until FP spent | authenticated | — |
| TC-SP-21 | Focus spells auto-heightened: effective level = ceil(character level / 2) | Playwright | Level 8 character's focus spells display at level 4 | authenticated | dc-cr-focus-spells |
| TC-SP-22 | Focus spells cannot be placed in spell slots | Playwright | Attempting to prepare focus spell in a spell slot returns error | authenticated | dc-cr-focus-spells |
| TC-SP-23 | Spell slots cannot activate focus spells | Playwright | Spending a spell slot on a focus spell blocked at validation layer | authenticated | dc-cr-focus-spells |
| TC-SP-24 | Non-spellcaster with focus ability is not flagged as "spellcaster" class role | Data validation | Fighter with Ki Strike has focus pool but character.is_spellcaster = false | authenticated | dc-cr-focus-spells |
| TC-SP-25 | Multiple focus sources share one pool; all focus abilities draw from same pool | Playwright | Monk with two focus abilities spends 2 FP; pool decrements correctly | authenticated | dc-cr-focus-spells |
| TC-SP-26 | Daily preparation restores all Focus Points to pool maximum | Playwright / downtime | After daily prep, FP = pool max (up to 3) | authenticated | dc-cr-focus-spells |
| TC-SP-27 | Bard composition focus spells (Inspire Courage etc.) accessible to bard with focus ability | Playwright | Bard with Lingering Composition feat has focus spell in repertoire | authenticated | dc-cr-focus-spells |
| TC-SP-28 | Wizard school focus spells gated behind school feature (Hand of the Apprentice requires school selection) | Playwright | Wizard without school feature cannot access school focus spells | authenticated | dc-cr-focus-spells |

### Innate Spells (TC-SP-29–31)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-29 | Innate spells use Charisma modifier for attack/DC; character proficiency at least Trained | Playwright / encounter | Ancestry innate spell attack roll = Cha mod + proficiency (min Trained); does not use class spellcasting attribute | authenticated |
| TC-SP-30 | Innate non-cantrip: one use per day; cannot be heightened | Playwright / encounter | Character uses innate spell → use-counter = 1/1 (spent); second cast blocked; daily prep restores it; casting at higher slot returns error | authenticated |
| TC-SP-31 | Innate cantrips auto-heightened like class cantrips | Playwright | Ancestry innate cantrip scales with ceil(char level / 2) same as prepared cantrips | authenticated |

### Casting Mechanics — Actions and Disruption (TC-SP-32–35)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-32 | Casting action cost derived from spell entity (1/2/3/reaction/free action) | Playwright / encounter | Spell with cast=2 displays and enforces 2-action cost in encounter; 1-action override returns validation error | authenticated |
| TC-SP-33 | Effect applies at end of all casting actions; disruption mid-cast expends slot but produces no effect | Playwright / encounter | Disruption on first action of a 2-action spell: slot consumed, target takes no effect, no refund | authenticated |
| TC-SP-34 | Spells with casting time > 1 round have Exploration trait; blocked in encounter mode | Playwright / encounter | Attempting to cast ritual-tier spell (exploration trait) during combat returns "cannot cast in encounter: exploration trait" | authenticated |
| TC-SP-35 | Disruption during Sustain ends spell immediately | Playwright / encounter | Sustained spell disrupted → spell ends immediately; no additional sustain attempt allowed | authenticated |

### Casting Components (TC-SP-36–39)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-36 | Material component: free hand required; consumed even on disruption; material component pouch counts | Playwright / encounter | Casting material-component spell with no free hand returns "free hand required"; material component pouch satisfies requirement | authenticated |
| TC-SP-37 | Somatic component: requires ability to gesture; cannot cast if fully restrained | Playwright / encounter | Grappled/restrained condition blocks somatic spells; holding item in hand does not block somatic | authenticated |
| TC-SP-38 | Verbal component: requires ability to speak; produces audible signature; silenced condition blocks | Playwright / encounter | Silenced character attempting verbal-component spell returns "cannot speak" block; Silence area blocks verbal spells | authenticated |
| TC-SP-39 | Innate spells always allow Material→Somatic substitution regardless of free-hand state | Playwright / encounter | Innate spell with material component and both hands occupied: somatic substitution applies automatically | authenticated |

### Metamagic (TC-SP-40–41)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-40 | Metamagic declared before cast; modifies subsequent Cast a Spell action | Playwright / encounter | Metamagic feat used → next action must be Cast a Spell; metamagic effect applied to spell output | authenticated |
| TC-SP-41 | Metamagic wasted if next action is not Cast a Spell; no refund | Playwright / encounter | Metamagic declared → character moves instead of casting → metamagic consumed with no effect; action still spent | authenticated |

### Spell Attacks and DCs (TC-SP-42–45)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-42 | Spell attack roll = spellcasting ability mod + proficiency + bonuses + penalties; MAP applies | Playwright / encounter | Second spell attack in same turn has –5 MAP applied (–4 if agile); verified against attack log | authenticated |
| TC-SP-43 | Spell DC = 10 + spellcasting mod + proficiency + bonuses + penalties | Data validation | DC calculation matches formula across all caster types; weapon-spec bonus absent from spell DC | authenticated |
| TC-SP-44 | Weapon specialization bonus does NOT apply to spell attack rolls | Playwright / encounter | Fighter with weapon specialization casts spell attack → spell attack log shows no weapon-spec bonus | authenticated |
| TC-SP-45 | Basic saving throw degrees correct: Crit Success=0, Success=half, Failure=full, Crit Failure=double | Playwright / encounter | Fireball vs each degree of save: crit success → 0 damage; success → (result)/2; failure → full; crit fail → ×2 | authenticated |

### Area, Range, Targeting (TC-SP-46–49)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-46 | Touch spells require target in unarmed reach (0 ft base, extended by reach modifier) | Playwright / encounter | Touch spell targeting creature outside reach returns "target out of range" | authenticated |
| TC-SP-47 | Willing target can be declared at any time regardless of conditions | Playwright / encounter | Incapacitated character can still be declared willing target for beneficial spell | authenticated |
| TC-SP-48 | Invalid target exclusion: spell fires on valid targets; invalid target excluded, not a full-fizzle | Playwright / encounter | Fireball with one valid and one invalid target: invalid target receives no effect; valid target affected normally | authenticated |
| TC-SP-49 | Line of effect required from caster to target/origin point | Playwright / encounter | Spell targeting creature behind total cover with no line of effect returns "no line of effect" block | authenticated |

### Durations (TC-SP-50–52)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-50 | Round-based duration decrements at start of caster's turn; ends when reaches 0 | Playwright / encounter | Spell with "2 rounds" duration: active at cast; decrements start of caster's next turn; ends start of second turn | authenticated |
| TC-SP-51 | Sustained spell: ends at end of caster's next turn; Concentrate action extends one turn; sustain >100 rounds → fatigue + spell ends | Playwright / encounter | Sustain counter tracked; at 101 sustain actions spell ends and character gains fatigued condition | authenticated |
| TC-SP-52 | Dismiss (1 action, Concentrate): ends dismissible spell immediately | Playwright / encounter | Character with dismissible spell active uses Dismiss → spell ends immediately; non-dismissible spell cannot be dismissed | authenticated |

### Special Spell Types (TC-SP-53–56)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-53 | Illusion disbelief: Interact action or physical proof allows save; Crit Success = fully reveals illusion; Success = hazy | Playwright / encounter | Character touches illusion → save triggered; on success: note "hazy but present"; on crit success: illusion revealed; physical proof alone does not grant disbelief automatically | authenticated |
| TC-SP-54 | Counteract check is separate roll from spell attack; light/darkness can always counteract each other; non-magical light cannot overcome magical darkness | Playwright / encounter | Magical darkness spell counteract attempt with Light spell (magical): counteract check rolls; torch (non-magical light) in magical darkness area: torch suppressed | authenticated |
| TC-SP-55 | Incapacitation trait: applies hard downgrade for creatures > half caster's level | Playwright / encounter | Caster at level 4 uses incapacitation spell against level 5 creature: worst outcome = Failure (not Crit Fail); Crit Success downgrades to Success | authenticated |
| TC-SP-56 | Polymorph (battle form): gear absorbed into form; cannot cast spells, speak, or use manipulate actions | Playwright / encounter | Character in battle form has spellcasting actions blocked; speech/manipulate actions blocked; gear not accessible; own unarmed attacks replaced by form attacks | authenticated |

### Rituals (TC-SP-57)

| TC | Description | Suite | Expected behavior | Roles | Dependency |
|----|-------------|-------|-------------------|-------|------------|
| TC-SP-57 | Ritual spells use separate system (no spell slots, time-based, secondary casters); blocked in encounter mode | Playwright | Ritual cast attempt during encounter blocked; ritual requires downtime/exploration context and time cost | authenticated | dc-cr-rituals |

### Spell Stat Block Data Model (TC-SP-58–61)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-58 | Spell entity stores all required fields: name, type, level, school, traditions, rarity, cast, components, trigger, cost, range, area, targets, save, duration, description, heightened_effects | Data validation | API response for any spell includes all fields; null allowed for optional fields (trigger, cost, area, targets, save) | authenticated |
| TC-SP-59 | Heightened entries stored as structured data: specific-level vs cumulative distinguished | Data validation | Spell with both "Heightened (4th)" and "Heightened (+2)" entries serialized correctly; retrieval preserves type distinction | authenticated |
| TC-SP-60 | Rarity flags: H (has heightened), U (uncommon), R (rare); rare/uncommon require access gate | Playwright | Uncommon spell not accessible to character without explicit access grant; rare spell similarly gated | authenticated |
| TC-SP-61 | Spell variants (Harm vs Heal) stored as distinct spell entities sharing no state | Data validation | harm.id ≠ heal.id; modifying Harm spell record has no effect on Heal spell record | authenticated |

### Spell Lists — Content Coverage Gates (TC-SP-62–65)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-SP-62 | Arcane spell list: all entries accessible to arcane casters | Playwright / role audit | Wizard (arcane) spell selection UI contains all arcane list entries at correct levels; no divine-only spells present | authenticated |
| TC-SP-63 | Divine spell list: all entries accessible to divine casters | Playwright / role audit | Cleric (divine) spell selection UI contains all divine list entries | authenticated |
| TC-SP-64 | Occult spell list: all entries accessible to occult casters | Playwright / role audit | Bard (occult) spell selection UI contains all occult list entries | authenticated |
| TC-SP-65 | Primal spell list: all entries accessible to primal casters | Playwright / role audit | Druid (primal) spell selection UI contains all primal list entries | authenticated |

### Focus Spells by Class — Content Gates (TC-SP-66–73)

| TC | Description | Suite | Expected behavior | Dependency |
|----|-------------|-------|-------------------|------------|
| TC-SP-66 | Bard composition focus spells (Inspire Courage, Inspire Defense, Inspire Competence, Counter Performance, Lingering Composition) present and accessible via bard focus ability feats | Playwright | Each bard feat grants correct focus spell; focus point cost = 1 | dc-cr-focus-spells |
| TC-SP-67 | Champion devotion focus spells (Lay on Hands, Litany Against Wrath, Litany of Righteousness, Hero's Defiance) present | Playwright | Champion with devotion feat has spell in focus repertoire | dc-cr-focus-spells |
| TC-SP-68 | Cleric domain focus spells organized by domain; each domain selection adds 1 FP to pool | Playwright | Cleric with 2 domain selections has FP max = 2; domain focus spells in repertoire | dc-cr-focus-spells |
| TC-SP-69 | Druid order focus spells per order (Animal/Leaf/Stone/Storm/Wild); Wild Shape available at cantrip level, heightened at 2nd/4th/6th | Playwright | Druid Wild order has Wild Shape; heightened versions scale as expected | dc-cr-focus-spells |
| TC-SP-70 | Monk ki focus spells (Ki Strike, Ki Rush, Wholeness of Body, Ki Blast, Quivering Palm, Stunning Fist) accessible via feats | Playwright | Monk with Ki Strike feat has access to ki focus spell at level matching feat prereq | dc-cr-focus-spells |
| TC-SP-71 | Sorcerer bloodline focus spells per bloodline (Draconic, Demonic, Undead, Fey, Angelic, Imperial) — each bloodline grants distinct focus spell | Playwright | Draconic sorcerer has Draconic bloodline spell; Fey sorcerer has different spell | dc-cr-focus-spells |
| TC-SP-72 | Wizard school focus spells per school (Augment Summoning, Charming Words, Hand of the Apprentice, Diviner's Sight) gated by school selection | Playwright | Abjuration specialist cannot access Evocation school focus spell | dc-cr-focus-spells |
| TC-SP-73 | Domain advanced focus spells (levels 4–6) require class feature unlock; not available at basic domain selection | Playwright | Cleric without advanced domain feature cannot access advanced domain focus spells | dc-cr-focus-spells |

### Edge Cases (TC-SP-74–77)

| TC | Description | Suite | Expected behavior |
|----|-------------|-------|-------------------|
| TC-SP-74 | Spontaneous non-signature heighten attempt to level not in repertoire: blocked | Playwright / encounter | Error: "spell not known at target level; mark as signature to heighten freely" |
| TC-SP-75 | Metamagic followed by non-Cast action: metamagic consumed, no refund, no effect | Playwright / encounter | Metamagic action count spent; no metamagic bonus on subsequent cast; log entry records wasted metamagic |
| TC-SP-76 | Sustain > 100 rounds triggers fatigue + spell end | Playwright / encounter | At sustain count 101: fatigued condition added to character; sustained spell removed from active effects |
| TC-SP-77 | Focus Pool at 3 with additional focus source: pool cap enforced at 3 | Playwright / character sheet | Adding 4th focus ability does not increase pool max beyond 3; UI shows "3/3 (capped)" |

### Failure Modes (TC-SP-78–81)

| TC | Description | Suite | Expected behavior |
|----|-------------|-------|-------------------|
| TC-SP-78 | Disrupted spell: slot/FP/action expended; no effect | Playwright / encounter | Disruption interrupt → slot consumed = true, target affected = false; resource log shows cost paid |
| TC-SP-79 | Invalid target: only that target excluded; valid targets still affected | Playwright / encounter | Multi-target spell with 1 invalid target: valid targets receive full effect; invalid target record shows "excluded: invalid target" |
| TC-SP-80 | Weapon specialization bonus absent from spell attack roll | Playwright / encounter | Fighter with Weapon Spec casting a touch spell attack: attack bonus log shows 0 contribution from weapon_spec |
| TC-SP-81 | Innate non-cantrip used twice in one day: second cast blocked | Playwright / encounter | First cast succeeds, use_count = 1/1; second cast attempt returns "daily use exhausted" |

### ACL Regression (TC-SP-82)

| TC | Description | Suite | Expected behavior |
|----|-------------|-------|-------------------|
| TC-SP-82 | Spell routes/forms require character ownership; anonymous access blocked | role-url-audit | Unauthenticated GET/POST to spell cast endpoints returns 403/redirect; spell repertoire API requires auth |

---

## Open PM questions / automation notes

1. **TC-SP-58 field completeness**: PM should confirm the canonical list of nullable vs required spell fields in the DB schema (trigger, cost, area, targets, save are nullable; name/level/traditions/school/cast/components are required).
2. **TC-SP-62–65 content volume**: "All spells" gate is a content-completeness check, not a mechanical rule — needs a count or manifest to verify against. PM should provide expected spell counts per tradition at each level.
3. **TC-SP-66–73 focus spell implementation sequence**: These 8 TCs depend on dc-cr-focus-spells feature being in scope; recommend bundling into same release as dc-cr-focus-spells rather than dc-cr-spells-ch07 activation.
4. **TC-SP-55 incapacitation exact downgrade rule**: AC states "worst outcome = Failure" for creatures > half level — automation must confirm the degree-downgrade mapping (Crit Fail → Fail, Crit Success → Success) is implemented server-side, not just UI.
5. **TC-SP-33 disruption trigger model**: How disruption is modeled in the encounter state machine (explicit interrupt action vs damage threshold) needs Dev implementation note before Stage 0.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-spells-ch07

## Gap analysis reference
- DB sections: core/ch07 — Casting Spells (39), Spell Slots and Spellcasting Types (14), Arcane/Divine/Occult/Primal Spell Lists (11 each), Focus Spells by Class (9), Special Spell Types (13), Spell Stat Block Format (10), Traditions and Schools (6)
- Depends on: dc-cr-spellcasting, dc-cr-focus-spells, dc-cr-rituals

---

## Happy Path

### Traditions and Schools
- [ ] `[NEW]` Four distinct magical traditions: Arcane, Divine, Occult, Primal.
- [ ] `[NEW]` Each spell belongs to one or more traditions (via spell list membership) and exactly one school (8 schools: Abjuration, Conjuration, Divination, Enchantment, Evocation, Illusion, Necromancy, Transmutation).
- [ ] `[NEW]` A caster's tradition is determined by class (and sometimes bloodline/deity); tradition trait added to all spells cast.
- [ ] `[NEW]` Schools define the spell's nature; casters may specialize in a specific school (see class features).
- [ ] `[NEW]` Essence classification (mental/vital/material/spiritual) captured in system lore and used for resistances/immunities.

### Spell Slots and Spellcasting Types
- [ ] `[NEW]` Each spellcaster character tracks spell slots independently by spell level (1–10); not a shared pool.
- [ ] `[NEW]` Spell slots refresh on daily preparation.
- [ ] `[NEW]` Spells have a spell level (1–10) independent of character level.

#### Prepared Spellcasters (Cleric, Druid, Wizard)
- [ ] `[NEW]` Prepared spellcasters select spells during daily prep; each spell occupies a slot.
- [ ] `[NEW]` Casting expends the slot; the same spell must be prepared multiple times to cast multiple times.
- [ ] `[NEW]` Cantrips: prepared once during daily prep; castable indefinitely without expending slots.

#### Spontaneous Spellcasters (Bard, Sorcerer)
- [ ] `[NEW]` Spontaneous casters have a fixed spell repertoire; any known spell castable using any available slot of appropriate level.
- [ ] `[NEW]` Daily prep refreshes slots, not the repertoire.
- [ ] `[NEW]` Signature spells (class feature): can be heightened spontaneously even if only known at one level.
- [ ] `[NEW]` Spontaneous casters can only heighten to levels where they have the spell in their repertoire (except signature spells).

### Heightening
- [ ] `[NEW]` Heightening: casting a spell in a higher slot raises its effective level.
- [ ] `[NEW]` Heightened entries at specific levels ("Heightened (4th)") apply only at that level.
- [ ] `[NEW]` Cumulative entries ("Heightened (+2)") stack above base level at each step.

### Cantrips and Focus Spells
- [ ] `[NEW]` Cantrips: effective level = ceil(character level / 2); no slot cost; auto-heightened to caster's maximum effective spell level.
- [ ] `[NEW]` Focus spells: effective level = ceil(character level / 2).
- [ ] `[NEW]` Focus Pool: separate from spell slots; max capacity = 3 (hard cap); each focus ability adds 1 (capped at 3).
- [ ] `[NEW]` Refocus: 10-minute activity restores 1 Focus Point; requires class-specific deeds.
- [ ] `[NEW]` Daily preparation restores all Focus Points.
- [ ] `[NEW]` Focus spells cannot be placed in spell slots; spell slots cannot activate focus spells.
- [ ] `[NEW]` Non-spellcasters with focus spells gain proficiency from granting ability but are not "spellcasters".
- [ ] `[NEW]` Multiple focus sources share one pool; points fungible across all focus spells.

### Innate Spells
- [ ] `[NEW]` Innate spells granted by ancestry or items; refresh daily; separate from class spell slots.
- [ ] `[NEW]` Innate spells use Charisma modifier for attack/DC by default; caster always at least Trained in innate spell rolls/DCs.
- [ ] `[NEW]` Innate cantrips: auto-heightened; innate non-cantrips: once/day; cannot be heightened.

### Casting Spells
- [ ] `[NEW]` Casting a spell is an activity costing the listed number of actions (1/2/3/reaction/free).
- [ ] `[NEW]` Effect applies at end of all casting actions; spell can be disrupted mid-cast.
- [ ] `[NEW]` Spells with casting times > 1 round have Exploration trait; cannot be used in encounters.

#### Components
- [ ] `[NEW]` Four component types: Material, Somatic, Verbal, Focus; each adds corresponding trait.
- [ ] `[NEW]` Material: free hand required; consumed on cast (even if disrupted); covered by material component pouch.
- [ ] `[NEW]` Somatic: must be able to gesture; can hold item in hand; must be unrestrained.
- [ ] `[NEW]` Verbal: must be able to speak; generates noise; reveals spellcasting.
- [ ] `[NEW]` Focus component: specific item required; can be returned to holster after cast.
- [ ] `[NEW]` Disrupted spell: slot/Focus Point/action expended; no effect; if disrupted during Sustain → spell ends immediately.
- [ ] `[NEW]` Class-specific component substitutions implemented per class rules.
- [ ] `[NEW]` Innate spells always allow Material→Somatic substitution.

#### Metamagic
- [ ] `[NEW]` Metamagic declared before casting; applies effects to the subsequent Cast a Spell activity.
- [ ] `[NEW]` Metamagic wasted if anything other than Cast a Spell immediately follows.

### Spell Attacks and DCs
- [ ] `[NEW]` Spell attack roll: spellcasting mod + proficiency + bonuses + penalties.
- [ ] `[NEW]` Spell DC: 10 + spellcasting mod + proficiency + bonuses + penalties.
- [ ] `[NEW]` Multiple attack penalty applies to spell attacks.
- [ ] `[NEW]` Weapon-specific bonuses (weapon specialization etc.) do NOT apply to spell attacks.
- [ ] `[NEW]` Spell attacks target AC; basic saving throw spells use spell DC.
- [ ] `[NEW]` Basic saving throw degrees: Crit Success = 0 damage; Success = half; Failure = full; Crit Failure = double.

### Area, Range, Targeting
- [ ] `[NEW]` Area spell measurement follows burst/cone/emanation/line geometry (see ch09).
- [ ] `[NEW]` Touch spells use unarmed reach; range for touch starts at 0 ft.
- [ ] `[NEW]` Willing targets declared by player at any time (regardless of condition).
- [ ] `[NEW]` Invalid target: spell fails on that target only (does not invalidate other targets).
- [ ] `[NEW]` Line of effect required from caster to spell target/origin point.

### Durations
- [ ] `[NEW]` Duration types: instantaneous, round-based, timed, sustained, until next daily prep, unlimited.
- [ ] `[NEW]` Round-based durations: decrement at start of caster's turn; ends at 0.
- [ ] `[NEW]` Sustained spells: last until end of next turn; require 1 action (Concentrate) to sustain; sustaining > 100 rounds causes fatigue + ends spell (unless "sustained up to N" specified).
- [ ] `[NEW]` If caster dies/incapacitated: timed spells continue until expiry; sustained spells end if not sustained.
- [ ] `[NEW]` Dismiss action (1 action, Concentrate): ends a dismissible spell.

### Special Spell Types
- [ ] `[NEW]` Illusions with disbelief: Crit Success = reveals nothing real; Success = hazy but may not fully remove.
- [ ] `[NEW]` Physical proof of illusion reveals it but does not automatically grant disbelief.
- [ ] `[NEW]` Counteract mechanism: separate check from normal spell attack; light/darkness can always counteract each other.
- [ ] `[NEW]` Non-magical light cannot overcome magical darkness.
- [ ] `[NEW]` Incapacitation trait: hard counter for creatures above half caster's level.
- [ ] `[NEW]` Polymorph (battle form): gear absorbed; no casting; no speech; no manipulate actions.
- [ ] `[NEW]` Summoned creatures: use 2 actions on appearance turn; minion rules apply.
- [ ] `[NEW]` Trigger-set spells: react when sensor observes matching trigger; can be fooled by disguise/illusion.
- [ ] `[NEW]` Spell identification: auto if prepared/known; else Recall Knowledge; pre-existing effects require Identify Magic.

### Spell Stat Block Format
- [ ] `[NEW]` Spell data model captures: name, type, level, school, traditions, rarity, cast actions, components, trigger, cost, range, area, targets, save type, duration, description, heightened effects.
- [ ] `[NEW]` Heightened entries displayed: specific levels ("Heightened (4th)") or cumulative ("Heightened (+2)").
- [ ] `[NEW]` "H" flag = has heightened benefits; "U" = uncommon; "R" = rare.
- [ ] `[NEW]` Rare/uncommon spells require access restriction.
- [ ] `[NEW]` Spell variants (e.g., Harm vs Heal) implemented as distinct spells.
- [ ] `[NEW]` All spells in all four tradition spell lists implemented with full stat blocks (levels 0–10).

### Spell Lists (Content)
- [ ] `[NEW]` All Arcane spell list entries (cantrips through 10th) implemented and accessible to arcane casters.
- [ ] `[NEW]` All Divine spell list entries implemented and accessible to divine casters.
- [ ] `[NEW]` All Occult spell list entries implemented and accessible to occult casters.
- [ ] `[NEW]` All Primal spell list entries implemented and accessible to primal casters.

### Focus Spells by Class
- [ ] `[NEW]` Bard composition focus spells implemented (Inspire Courage, Inspire Defense, Inspire Competence, Counter Performance, Lingering Composition, etc.).
- [ ] `[NEW]` Champion devotion focus spells implemented (Lay on Hands, Litany Against Wrath, Litany of Righteousness, Hero's Defiance, etc.).
- [ ] `[NEW]` Cleric domain focus spells organized by domain; each selection adds 1 Focus Point.
- [ ] `[NEW]` Druid order focus spells implemented per order (Animal/Leaf/Stone/Storm/Wild); Wild Shape foundation at cantrip level with heightened versions (2nd/4th/6th).
- [ ] `[NEW]` Monk ki focus spells implemented (Ki Strike, Ki Rush, Wholeness of Body, Ki Blast, Quivering Palm, Stunning Fist, etc.).
- [ ] `[NEW]` Sorcerer bloodline focus spells implemented per bloodline (Draconic, Demonic, Undead, Fey, Angelic, Imperial, etc.).
- [ ] `[NEW]` Wizard school focus spells implemented per school (Augment Summoning, Charming Words, Hand of the Apprentice, Diviner's Sight, etc.).
- [ ] `[NEW]` Domain advanced focus spells (levels 4–6) available once class feature grants them.

---

## Edge Cases
- [ ] `[NEW]` Spontaneous caster attempting to heighten non-signature spell to a level they don't have it: blocked.
- [ ] `[NEW]` Metamagic followed by non-Cast action: metamagic wasted (no effect, no refund).
- [ ] `[NEW]` Sustain > 100 rounds: fatigue applied and spell ends.
- [ ] `[NEW]` Focus Pool at 3 with additional focus source: pool stays at 3 (hard cap).

## Failure Modes
- [ ] `[TEST-ONLY]` Disrupted spell still expends slot/Focus Point/action.
- [ ] `[TEST-ONLY]` Invalid target: spell fires on valid targets; invalid target excluded (not a full-fizzle).
- [ ] `[TEST-ONLY]` Weapon specialization bonus does NOT apply to spell attack rolls.
- [ ] `[TEST-ONLY]` Innate non-cantrip used twice in one day: blocked on second use.

## Security acceptance criteria
- Security AC exemption: game-mechanic spellcasting system; no new routes or user-facing input beyond existing character creation and encounter phase forms
- Agent: qa-dungeoncrawler
- Status: pending
