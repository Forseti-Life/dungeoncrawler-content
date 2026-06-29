# APG Chapter 5 (Spells) & Chapter 6 (Items) — Requirements Extraction

Source: PF2E Advanced Players Guide, Chapters 5–6 (lines 29055–40047)
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

## SECTION: New Weapons and Adventuring Gear

### Paragraph — New Melee Weapons

> New martial weapons:
> - Sword Cane (5 gp, 1d6 P, L Bulk, 1 hand, Sword group): concealed in walking cane; social concealment
> - Bola (5 sp, 1d6 B, range 20 ft, ranged martial; no reload): thrown, can Trip on hit
> 
> New Uncommon martial weapons:
> - Claw Blade (2 gp, 1d4 S, L Bulk, Knife group): catfolk cultural weapon
> - Khakkara (2 gp, 1d6 B, 1 Bulk, Club group): announces presence, scares animals
> - Tengu Gale Blade (4 gp, 1d6 S, L Bulk, Sword group): fan-shaped sword
> - Wakizashi (1 gp, 1d4 S, L Bulk, Sword group): paired with katana
>
> New Uncommon advanced weapons:
> - Daikyu (8 gp, 1d8 P, 2 Bulk, Bow group): asymmetrical longbow; limited firing arc when mounted

- REQ: Sword Cane looks like a mundane cane while sheathed; social inspection may not identify it as a weapon
- REQ: Bola is a thrown ranged weapon with no reload; successful hit can attempt to Trip target
- REQ: Daikyu has a firing restriction when mounted (left-side only)
- REQ: All above weapons use standard weapon mechanics from Core Rulebook

### Paragraph — New Adventuring Gear

> Key new gear items:
> - Detective's Kit (investigator class kit component): +1 item bonus to investigate crime scene checks
> - Dueling Cape: worn; when deployed as offhand tool, grants +1 circumstance AC and Feint bonus
> - Brass Ear: listening aid; reduces Perception DC increase for hearing through barriers (halves the increase)
> - Concealed Sheath: +1 item bonus to Stealth to hide concealed item (internal holster)
> - Net (ranged): can Grapple targets up to 10 ft away (vs. normal adjacent only); hit = flat-footed + –10 ft Speed; crit = immobilized

- REQ: Detective's Kit grants +1 item bonus on investigation/examination skill checks
- REQ: Dueling Cape requires an Interact action to deploy; grants AC and Feint bonuses when deployed
- REQ: Net has two modes: rope-attached (extend Grapple range to 10 ft) or thrown (ranged attack, immobilize on crit)
- REQ: Net imposes flat-footed condition and –10 ft Speed penalty; Escape DC 16; can be removed by adjacent ally with Interact

---

## SECTION: Alchemical Items

### Paragraph — New Alchemical Bombs

> New bomb types:
> - Blight Bomb (L1/3/11/17, poison damage + persistent poison + splash): standard tiered bomb
> - Dread Ampoule (L1+, emotion/fear/poison; hit = Enfeebled 2 until start of your next turn)
> - Ghost Charge (L1+, negative energy bomb; effective vs. undead AND living; splash)
> - Crystal Shards (L4+, piercing bomb; splash area creates difficult terrain caltrops OR climbing handholds)

- REQ: Blight Bomb deals poison damage + persistent poison + splash (3-component damage bomb)
- REQ: Dread Ampoule: hit imposes Enfeebled 2 until start of thrower's next turn (fear/emotion trait)
- REQ: Crystal Shards: splash area grows crystals that act as caltrops on floor or climbing handholds on walls (context-dependent environmental effect)

### Paragraph — New Alchemical Elixirs

> New elixirs:
> - Focus Cathartic (L2+): counteract Confused or Stupefied condition (counteract check +6/+8/+19/+28)
> - Sinew-Shock Serum (L2+): counteract Clumsy or Enfeebled condition (same counteract progression)
> - Olfactory Obfuscator (L3/10): +4 item bonus vs. scent detection; concealment vs. precise scent; 10 min/8 hr duration
> - Drakeheart Mutagen (L1+): draconic power mutagen (specific stats in full entry)

- REQ: Focus Cathartic attempts to counteract Confused or Stupefied (one condition per use; uses counteract rules)
- REQ: Sinew-Shock Serum attempts to counteract Clumsy or Enfeebled (one condition per use)
- REQ: Counteract modifier scales by tier: +6 (L2), +8 (L4), +19 (L12), +28 (L18)
- REQ: Olfactory Obfuscator suppresses scent-based detection; concealment vs. precise scent for duration

### Paragraph — New Alchemical Poisons

> New poisons:
> - Leadenleg (L4 injury poison): slows target (Speed reduction)
> - Cerulean Scourge (L16 injury poison): DC 36 Fort; massive poison damage across 3 stages (9d6 → 12d6 → 15d6)
> - Various uncommon/rare poisons at higher levels

- REQ: Leadenleg reduces target's Speed (specific reduction per entry); Fortitude save resists
- REQ: Cerulean Scourge is a high-level 3-stage affliction poison with escalating damage

### Paragraph — New Alchemical Tools

> New tools:
> - Forensic Dye (L1): marks creature/object; +2 to tracking or Seek checks vs. marked target
> - Ghost Ink (L2): invisible writing visible only under special light conditions
> - Timeless Salts (L4): preserves object for 1 week (prevents decay; extends raise dead and speak with dead windows)
> - Universal Solvent (L5+): counteracts adhesives; automatically dissolves sovereign glue

- REQ: Timeless Salts prevent corpse decay for 1 week; extend magical revival window
- REQ: Universal Solvent auto-counteracts sovereign glue; uses counteract check vs. other adhesives
- REQ: Forensic Dye creates a tracking mark that improves Seek/Track checks against target

---

## SECTION: Snares

### Paragraph — New APG Snares

> New snares:
> - Engulfing Snare (L14): high-damage (9d8 piercing) + immobilize cage; Reflex save; crit fail = doubled damage + immobilized
> - Flare Snare (L1, mechanical): non-damaging; creates bright light flash as alarm/signal

- REQ: Engulfing Snare creates an impeding cage structure; immobilized condition requires Escape (DC 31) or destroying cage (Hardness 5, HP 30)
- REQ: Flare Snare functions as mechanical signal device; no damage; bright light (useful for scouting/alarm systems)

---

## SECTION: Consumable Magic Items

### Paragraph — Notable New Consumable Magic Items

> New consumables:
> - Candle of Revealing (L7): 1-minute duration; creatures in 10-ft radius lose invisible condition but become concealed (body outline)
> - Corrosive Ammunition (L7): persistent acid damage to armor that bypasses Hardness; ends when armor breaks
> - Dust of Corpse Animation (L8/16): animate a skeleton or zombie (minion trait; max L3 undead at base; L11 at greater); lasts 1 minute
> - Freezing Ammunition (L5): Fort save or Slowed 1 for 1 round (1 minute on crit fail)
> - Oil of Unlife (L3): apply to undead; functions like a healing potion for undead (restores HP using negative energy rules)
> - Potion of Shared Memories (L1): transfer a specific memory between creatures; drinker gains the memory
> - Potion of Retaliation (L3+): aura that automatically deals damage back to creatures that hit you (typed damage)
> - Ration Tonic (L1+): nourishes as a day of food and water
> - Terrifying Ammunition (L6): Will save vs. Frightened 1-2 + can't reduce frightened below 1 until concentrates

- REQ: Candle of Revealing removes invisible condition within area (not full visibility — concealed instead)
- REQ: Dust of Corpse Animation creates a temporary minion (1 minute duration; max 4 total minions including this one)
- REQ: Potion of Retaliation must specify damage type when crafted; deals that type in an aura when hit
- REQ: Terrifying Ammunition on failure: target can't reduce frightened below 1 until spending a concentrate action
- REQ: Oil of Unlife: applies negative healing to undead (heals undead, not harms them); functions like a potion

---

## SECTION: Permanent Magic Items

### Paragraph — New Armor and Shields

> New permanent items:
> - Glamorous Buckler (L2): +1 Deception for Feint while raised; once/day on successful Feint: target dazzled 1 round
> - Victory Plate (L9+, Uncommon): +1 resilient full plate; records up to 4 victories; activate to gain resistance 5 to creature type's damage

- REQ: Glamorous Buckler grants Feint bonus while raised; daily activation on successful Feint causes dazzled condition
- REQ: Victory Plate tracks creature kills (≥ plate level); records heraldry; activated to grant resistance based on slain creature trait

### Paragraph — New Held Items and Worn Items

> - Rope of Climbing (L3+): 50 ft animated rope; activates to move toward target on its own (10 ft/round); commands: stop, fasten, detach, knot
> - Sleeves of Storage (L4+): extradimensional storage in sleeves; quick retrieval
> - Slates of Distant Letters (L13): matched pair; writing on one appears on other regardless of distance (same plane); 1/hour

- REQ: Rope of Climbing animates on activation; follows commands (stop, fasten, detach, knot/unknot)
- REQ: Slates of Distant Letters pair must be crafted together; one slate breaks = both shatter; 25 words per activation; 1/hour each

### Paragraph — New Weapons (Magic)

> - Four-Ways Dogslicer (L12, Uncommon): +2 striking dogslicer with 3 active elemental runes (flaming/frost/shock); switch with Interact (take 1d6 damage of chosen type); 4th black gem disables
> - Infiltrator's Accessory (L5): +1 striking sword cane; appears as mundane accessory for social events

- REQ: Four-Ways Dogslicer has 3 property runes that can be swapped as 1-action Interact; cost is 1d6 damage of the activated type
- REQ: Infiltrator's Accessory concealment property should be handled by social context rules (not magical detection evasion)

### Paragraph — New Runes

> - Winged Rune (L13+): armor rune; once/hour, 2-action activation to grow wings granting Fly Speed (25 ft or land Speed, whichever lower) for 5 minutes or until dismissed

- REQ: Winged Rune grants a timed Fly Speed; frequency 1/hour; 5-minute duration; dismissable

### Paragraph — New Wands

> - Wand of Overflowing Life (L9+): cast heal at listed level; after casting, the wand also heals the caster at 1-action heal level (bonus self-heal on top of the cast spell)
> - Wand of the Snowfields (L14+): cast cone of cold; area becomes difficult terrain (deep snow) for 1 minute
> - Wand of the Spider (L7+): cast web; webbing is envenomed (failure to navigate = poison damage)

- REQ: Wand of Overflowing Life grants a free 1-action heal targeting the caster each time the wand is activated (bonus effect)
- REQ: Wand of the Snowfields adds environmental difficult terrain to cone of cold effect

### Paragraph — Special Items

> - Urn of Ashes (L9): reaction to intercept Doomed condition (urn takes it instead); once per night reduce own or urn's doomed; shoot negative energy bolt (30 ft, +15 spell attack, 4d4 negative)
> - Rod of Cancellation (L20): counteract any magical effect or item on touch (2d6-hour recharge after use)

- REQ: Urn of Ashes protects against Doomed condition as a reaction; finite protection (urn has its own doomed value; only one doomed is reduced per night's rest)
- REQ: Rod of Cancellation: on counteract success, target magical effect or item is permanently canceled; long cooldown (2d6 hours)

---

## Summary: APG Ch.4 Key Systems

| Category | Key Additions |
|---|---|
| Spell lists | New arcane/divine/occult/primal spells L1–9; all traditions expanded |
| Oracle focus spells | 8 mystery-specific revelation spell sets (cursebound); see Ch.2 |
| Witch hexes | ~15 hexes; hex cantrips (free); basic/greater/major lesson hexes |
| Bard compositions | Hymn of Healing, Song of Strength |
| Ranger warden spells | Gravity Weapon, Soothing Mist, Heal Companion |
| Rituals | Astral Projection, Create Undead, Atone, Talking Corpse, Teleportation Circle |
| New weapons | Sword Cane, Bola, Claw Blade, Daikyu, Tengu Gale Blade, Wakizashi |
| Alchemical bombs | Blight Bomb, Dread Ampoule, Ghost Charge, Crystal Shards |
| Alchemical elixirs | Focus Cathartic, Sinew-Shock Serum, Olfactory Obfuscator, Drakeheart Mutagen |
| Alchemical tools | Timeless Salts, Universal Solvent, Forensic Dye |
| Consumable magic | Candle of Revealing, Dust of Corpse Animation, Terrifying Ammunition, Potion of Shared Memories |
| Permanent magic | Glamorous Buckler, Victory Plate, Rope of Climbing, Winged Rune, Wand of Overflowing Life |
