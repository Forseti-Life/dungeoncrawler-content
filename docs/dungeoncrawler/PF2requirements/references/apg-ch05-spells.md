# APG Chapter 5: Spells — Requirements Extraction

Source: PF2E Advanced Players Guide, Chapter 5 (Spells) — extracted from merged Ch.5–6 analysis
Status: Complete

---

## SECTION: Spell System (New Spells)

### Paragraph — APG Spell Lists by Tradition

> The APG expands each tradition's spell list with new spells across all levels. Spell descriptions are in the Core Rulebook format but are distributed differently by tradition in the APG.

- REQ: System must support adding new spells to existing spell lists without structural changes
- REQ: APG adds new arcane, divine, occult, and primal spells at all levels 1–9
- REQ: Key new spell categories: animation (undead), elemental projectiles, social manipulation, debilitation, divination, shadow/darkness, natural phenomena
- REQ: Heightened spell variants (marked H in spell lists) apply graduated effects per spell level — system must support heightened spell parameters

### Paragraph — Notable APG Standard Spells

> New spells include (representative examples):
> - Animate Dead (nec, 1st, arcane/divine/occult): animate undead minion
> - Blood Vendetta (nec, 2nd): causes bleeding when hit
> - Déjà Vu (enc, 1st): force creature to repeat action
> - Final Sacrifice (evo, 2nd): detonate a minion for AoE damage
> - Heat Metal (evo, 2nd): superheat worn metal
> - Mad Monkeys (conj, 3rd): mischievous monkey spirits vex foes
> - Pummeling Rubble (evo, 1st): cone of rocks
> - Vomit Swarm (evo, 2nd): swarm erupts as cone attack

- REQ: Blood Vendetta requires tracking a persistent retaliatory damage condition tied to attacker
- REQ: Final Sacrifice targets a minion under your control; minion must have minion trait; AoE on explosion
- REQ: Heat Metal applies a persistent burning condition to armored targets; force item saving throw
- REQ: Mad Monkeys creates a sustained summon-like effect that harasses creatures in area

### Spell Detail — Animate Dead (Spell 1)

> Traditions: arcane, divine, occult. Cast: [three-actions] material, somatic, verbal. Range: 30 feet. Duration: sustained up to 1 minute. Effect: summon a common undead creature of level -1; summoned creature gains summoned trait. Heightened: (2nd) level 1, (3rd) level 2, (4th) level 3, (5th) level 5, (6th) level 7, (7th) level 9, (8th) level 11, (9th) level 13, (10th) level 15.

- REQ: `Animate Dead` shall be implemented as a 3-action spell with material, somatic, and verbal components.
- REQ: `Animate Dead` shall target a summon point within 30 feet and create exactly one common undead summon.
- REQ: Summoned creature eligibility shall be capped by spell rank mapping: rank 1 -> level -1, rank 2 -> level 1, rank 3 -> level 2, rank 4 -> level 3, rank 5 -> level 5, rank 6 -> level 7, rank 7 -> level 9, rank 8 -> level 11, rank 9 -> level 13, rank 10 -> level 15.
- REQ: Summoned creature shall receive and obey summoned-trait constraints and disappear when the spell ends.
- REQ: Spell has no damage roll and no saving throw; effect is entirely summon-based.
- REQ: Spell duration shall require Sustain each round and end automatically after 1 minute if not ended earlier.

### Spell Detail — Blood Vendetta (Spell 2)

> Traditions: arcane, divine, occult. Cast: [reaction] verbal. Trigger: a creature deals piercing, slashing, or persistent bleed damage to you. Requirement: you can bleed. Range: 30 feet. Target: triggering creature. Save: Will. Duration: varies. Effect: target takes persistent bleed damage and can gain weakness to piercing/slashing based on save result. Heightened (+2): persistent bleed damage increases.

- REQ: `Blood Vendetta` shall be implemented as a reaction spell with trigger validation for incoming piercing/slashing/bleed damage against the caster.
- REQ: Spell shall enforce requirement gating that the caster is capable of bleeding.
- REQ: Base effect shall apply 2d6 persistent bleed damage to the target before/with Will-save resolution.
- REQ: Save outcomes shall resolve as: critical success unaffected; success half persistent bleed; failure full persistent bleed plus weakness 1 to piercing and slashing while bleeding persists; critical failure same rider with double persistent bleed.
- REQ: Heightened scaling shall add +2d6 persistent bleed per +2 spell rank.

### Spell Detail — Déjà Vu (Spell 1)

> Traits: enchantment, incapacitation, mental. Traditions: arcane, occult. Cast: [two-actions] somatic, verbal. Range: 100 feet. Target: 1 creature. Save: Will. Duration: 2 rounds. Effect: on failed save, target must repeat next turn's action sequence on following turn as closely as possible; if an action can't be repeated, target chooses replacement action but becomes stupefied 1 until end of turn.

- REQ: `Déjà Vu` shall be implemented as a 2-action Will-save spell against one target within 100 feet.
- REQ: On failed save, engine shall record exact action order and actionable specifics from the target's next turn and enforce replay on the turn after.
- REQ: Replay enforcement shall attempt same target, movement direction, and action sequence when still legal.
- REQ: For each action that cannot be legally repeated, target may choose a legal substitute action and gains stupefied 1 until end of that turn.
- REQ: Spell has no direct damage component.

### Spell Detail — Final Sacrifice (Spell 2)

> Traits: evocation, fire (or cold when target has cold/water trait). Traditions: arcane, divine, occult, primal. Cast: [two-actions] somatic, verbal. Range: 120 feet. Target: 1 creature with minion trait that you summoned or permanently control. Save: basic Reflex (20-foot emanation from minion). Effect: target minion is immediately slain; nearby creatures take damage. Heightened (+1): damage increases.

- REQ: `Final Sacrifice` shall only accept a target with minion trait that is either summoned by the caster or permanently controlled by the caster.
- REQ: On cast, target minion shall be immediately slain as a mandatory cost/effect.
- REQ: Nearby creatures within 20 feet of the minion shall take 6d6 fire damage with a basic Reflex save.
- REQ: If minion has cold or water trait, damage type shall swap to cold and spell trait context shall swap from fire to cold.
- REQ: Casting on a non-mindless creature shall apply evil-trait classification metadata.
- REQ: Casting on a temporarily seized minion (for example via temporary command effects) shall automatically fail and end that control effect.
- REQ: Heightened scaling shall add +2d6 damage per +1 spell rank.

### Spell Detail — Heat Metal (Spell 2)

> Traits: evocation, fire. Traditions: arcane, primal. Cast: [two-actions] somatic, verbal. Range: 30 feet. Target: 1 metal item or metal creature. Save: Reflex (if worn/carried item or metal creature target). Effect: heating causes initial fire damage plus persistent fire on applicable targets; unattended item usually has no save and usually no creature damage.

- REQ: `Heat Metal` shall support target types: unattended metal item, worn/carried metal item, or creature made primarily of metal.
- REQ: Unattended item targets shall receive no saving throw; system may flag secondary environmental ignition/melt interactions for adjudication.
- REQ: Worn/carried item or metal-creature targets shall take 4d6 fire plus 2d4 persistent fire, resolved by Reflex save.
- REQ: If targeting a held item, wielder may Release to improve degree of success by one step after roll resolution.
- REQ: Persistent fire shall be bound to the heated item and damage any creature holding/wearing it until extinguished by normal persistent-damage flat checks.
- REQ: Save outcomes shall resolve as: critical success unaffected; success half initial and no persistent; failure full initial and full persistent; critical failure double initial and double persistent.
- REQ: Heightened scaling shall add +2d6 initial fire and +1d4 persistent fire per +1 spell rank.

### Spell Detail — Mad Monkeys (Spell 3)

> Traditions: primal. Cast: [two-actions] somatic, verbal. Range: 30 feet. Area: 5-foot burst. Duration: sustained up to 1 minute. Effect: caster chooses one mischief mode when cast; effect occurs on cast and each Sustain; Sustain may also move area 5 feet.

- REQ: `Mad Monkeys` shall create a sustained area effect for up to 1 minute and allow 5-foot area repositioning on Sustain.
- REQ: Mischief mode selection shall occur at cast time and remain active for repeated pulse effects each round.
- REQ: `Flagrant Burglary` mode shall attempt one Steal action against one creature in area each pulse, using Thievery modifier `(spell DC - 10)`.
- REQ: Stolen items shall be recoverable via Steal/Disarm checks against spell DC and drop in chosen square in area when spell ends.
- REQ: `Raucous Din` mode shall force Fortitude saves each pulse with outcomes: crit success unaffected + 10-minute temporary immunity, success unaffected, failure deafened 1 round, crit failure deafened 1 minute.
- REQ: `Tumultuous Gymnastics` mode shall force Reflex saves each pulse with outcomes: crit success unaffected + 10-minute temporary immunity, success unaffected, failure DC 5 flat check to perform manipulate actions for 1 round (lose action on failed flat check), crit failure same effect lasting until spell ends even outside area.
- REQ: Calming overlay effects (such as calm emotions) over the monkeys shall suppress mischief while overlap persists.

### Spell Detail — Pummeling Rubble (Spell 1)

> Traits: earth, evocation. Traditions: arcane, primal. Cast: [two-actions] somatic, verbal. Area: 15-foot cone. Save: Reflex. Effect: bludgeoning burst that can push targets. Heightened (+1): damage increases.

- REQ: `Pummeling Rubble` shall deal 2d4 bludgeoning in a 15-foot cone with Reflex save resolution.
- REQ: Save outcomes shall resolve as: critical success unaffected; success half damage; failure full damage + push 5 feet away from caster; critical failure double damage + push 10 feet away from caster.
- REQ: Forced movement from this spell shall push directly away from caster origin and respect movement blocking constraints.
- REQ: Heightened scaling shall add +2d4 damage per +1 spell rank.

### Spell Detail — Vomit Swarm (Spell 2)

> Traits: evocation. Traditions: arcane, occult, primal. Cast: [two-actions] somatic, verbal. Area: 30-foot cone. Save: basic Reflex. Effect: magical vermin swarm deals piercing damage; failed save also applies sickened. Heightened (+1): damage increases.

- REQ: `Vomit Swarm` shall apply 2d8 piercing damage in a 30-foot cone using basic Reflex save resolution.
- REQ: Targets that fail (or critically fail) the save shall additionally become sickened 1.
- REQ: Swarm manifestation shall be visual/flavor-only and removed automatically when spell ends (no persistent summon entity).
- REQ: Heightened scaling shall add +1d8 piercing damage per +1 spell rank.

---

## SECTION: Focus Spells (APG)

### Paragraph — Oracle Focus Spells

> Each oracle mystery grants unique revelation spells. All have the cursebound trait (advance oracle curse on cast). Key examples per mystery:
> - Ancestors: Ancestral Defense (reaction), Ancestral Influence
> - Battle: Athletic Rush (move speed/jump bonus), Vision of Weakness, Weapon Surge
> - Bones: Soul Siphon, Ghostly Grasp
> - Cosmos: Interstellar Void, Spray of Stars
> - Flames: Incendiary Aura, Flaming Fusillade
> - Life: Life Link, Delay Affliction
> - Lore: Brain Drain, Access Lore
> - Tempest: Tempest Form, Thunderous Strike

- REQ: Each oracle mystery must define: initial revelation spell, advanced revelation spell, greater revelation spell, and domain spell choices
- REQ: All oracle revelation spells have cursebound trait — casting one advances curse stage
- REQ: Each mystery defines curse progression (4 stages: basic/minor/moderate/major/extreme)
- REQ: Mystery curse must be implemented as a unique effect per mystery, not a generic condition

### Paragraph — Witch Focus Spells (Hexes)

> Witch hexes are focus spells. Key hexes include:
> - Cackle (1-action, reaction; extend a sustained hex by 1 round): free-action hex extension
> - Evil Eye (cantrip hex; inflict –2 status penalty; sustained; ends if target succeeds)
> - Phase Familiar (reaction; familiar becomes incorporeal briefly to avoid damage)
> - Blood Ward (hex; +1 circumstance to saves vs. one creature's effects)
> - Needle of Vengeance (hex; mental damage when target attacks the protected creature)
> - Life Boost (hex; target gains fast healing 2/4/6/8 based on witch level for 1 round)
> - Curse of Death (major lesson hex; inflict wound condition that escalates)
> - Veil of Dreams (basic lesson hex; inflict stupefied 2 after failed Will save)

- REQ: Cackle is a 1-action hex that extends another active hex by 1 round (free action in some contexts)
- REQ: Evil Eye is a cantrip hex (no Focus Point cost) that imposes –2 status penalty (sustained)
- REQ: Evil Eye ends early if target succeeds at a Will save when affected
- REQ: Phase Familiar is a reaction hex: familiar becomes incorporeal briefly, avoiding damage
- REQ: All hexes (except hex cantrips) cost 1 Focus Point; only one hex per turn (any type)
- REQ: Hex cantrips (like Evil Eye) auto-heighten to half witch level; no Focus Point cost; still 1/turn

### Paragraph — Bard Focus Spells (New Composition Spells)

> New bard composition spells include:
> - Hymn of Healing (2-action composition; ongoing healing while sustained; 2 HP/round at base)
> - Song of Strength (cantrip; +2 circumstance to Athletics)

- REQ: Hymn of Healing is a sustained composition focus spell; heals 2 HP per round while sustained (scales with heightening)
- REQ: Song of Strength grants +2 circumstance bonus to Athletics checks for its duration

### Paragraph — Other Class Focus Spells

> Focus spells added for other classes include:
> - Ranger: Gravity Weapon (warden spell; +1 status to damage per die, +2 vs. large+ creatures), Soothing Mist (warden; heal companion), Heal Companion (warden; fast healing for companion)
> - Druid: Domain-specific order spells per APG order options
> - Monk: Ki spells for new stances (Gorilla Stance, Monastic Archer, etc.)
> - Investigator: No focus spells (class has no focus pool by default)
> - Swashbuckler: No focus spells (class has no focus pool by default)

- REQ: Gravity Weapon grants a status bonus to damage equal to number of weapon damage dice; doubles vs. Large+ targets
- REQ: Warden spells use the ranger's primal focus pool; Refocus via 10 minutes in nature

---

## SECTION: Rituals (APG New Rituals)

### Paragraph — New Ritual Overview

> APG adds rituals at levels 2–9. All rituals follow Core Rulebook ritual casting rules (time in hours/days, primary/secondary casters, skill check DCs).

- REQ: Ritual system from Core Rulebook must accommodate addition of new rituals without structural change
- REQ: Rituals require tracking: casting time, cost components, primary check skill + proficiency minimum, secondary casters and their checks
- REQ: New ritual examples:
  - Atone (L4): restore alignment/deity relationship after transgression
  - Astral Projection (L5, Uncommon): project souls onto the Astral Plane
  - Create Undead (L2+): permanently animate undead (more powerful than Dust of Corpse Animation)
  - Talking Corpse (L4): compel a dead creature to answer questions
  - Teleportation Circle (L7): create permanent/temporary teleportation circle

---
