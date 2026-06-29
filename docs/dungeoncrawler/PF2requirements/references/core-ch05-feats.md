# PF2E Core Rulebook — Chapter 5: Feats
## Requirements Extraction

---

## SECTION: Chapter Overview

### Paragraph — General Feats

> Abilities that require training but can be learned by anyone—not only members of certain ancestries or classes—are called general feats. Most classes grant a general feat at 3rd level and every 4 levels thereafter. Skill feats are a subcategory of general feats with the skill trait; most characters gain skill feats at 2nd level and every 2 levels thereafter. When gaining a skill feat, you must select a general feat with the skill trait.

- REQ: System must support a general feat category accessible to all characters regardless of class/ancestry
- REQ: General feats are grantable at: 3rd level and every 4 levels thereafter (levels 3, 7, 11, 15, 19)
- REQ: Skill feats are a subcategory of general feats; granted at 2nd level and every 2 levels thereafter
- REQ: When a character gains a skill feat, it must be a feat with the skill trait; non-skill general feats do not satisfy skill feat slots
- REQ: Feat level = minimum level at which a character could meet its proficiency prerequisite

---

## SECTION: Non-Skill General Feats Table

### Paragraph — Table 5-1: Non-Skill Feats

> Non-skill general feats by level with brief benefit descriptions.

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Adopted Ancestry | 1 | — | Access ancestry feats from another ancestry |
| Armor Proficiency | 1 | — | Become trained in next armor type (light→medium→heavy) |
| Breath Control | 1 | — | Hold breath 25× longer; +1 circ to saves vs inhaled threats; success→crit success |
| Canny Acumen | 1 | — | Become expert in one saving throw or Perception; master at 17th |
| Diehard | 1 | — | Die at dying 5 instead of dying 4 |
| Fast Recovery | 1 | Con 14 | Regain 2× HP from rest; succeed Fort vs disease/poison → reduce stage by 2 (1 for virulent) |
| Feather Step | 1 | Dex 14 | Can Step into difficult terrain |
| Fleet | 1 | — | Speed +5 feet |
| Incredible Initiative | 1 | — | +2 circumstance bonus to initiative rolls |
| Ride | 1 | — | Auto-succeed Command an Animal to move when mounted; mount acts on your turn |
| Shield Block | 1 | — | Reaction: use raised shield to absorb up to Hardness damage from physical attack |
| Toughness | 1 | — | Max HP +level; recovery check DC –1 |
| Weapon Proficiency | 1 | — | Trained in all simple → martial → one advanced weapon (each selection) |
| Ancestral Paragon | 3 | — | Gain a 1st-level ancestry feat |
| Untrained Improvisation | 3 | — | Untrained skill checks = half level (7th+: full level); no trained actions |
| Expeditious Search | 7 | Master Perception | Search areas in half the time (legendary: 4× faster) |
| Incredible Investiture | 11 | Cha 16 | Invest up to 12 magic items instead of 10 |

- REQ: Each non-skill general feat must be implementable as a character option at its listed level
- REQ: Repeatable feats (Armor Proficiency, Weapon Proficiency) must track progression and gate subsequent selections

---

## SECTION: Skill Feats Table

### Paragraph — Table 5-2: General Skill Feats

> Full skill feat list by skill, level, prerequisites, and benefit.

**Varying (multi-skill) Skill Feats:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Assurance | 1 | Trained in ≥1 skill | Forgo roll; get 10 + proficiency bonus (no other modifiers) |
| Dubious Knowledge | 1 | Trained in Recall Knowledge skill | On fail (not crit fail): learn one true + one false fact |
| Quick Identification | 1 | Trained in Arcana/Nature/Occultism/Religion | Identify Magic in 1 minute (master: 3 actions; legendary: 1 action) |
| Recognize Spell | 1 | Trained in Arcana/Nature/Occultism/Religion | Reaction: identify spell as it's cast; auto-identify low-level spells |
| Skill Training | 1 | Int 12 | Become trained in one skill of choice |
| Trick Magic Item | 1 | Trained in Arcana/Nature/Occultism/Religion | Activate a magic item you normally can't use |
| Automatic Knowledge | 2 | Expert + Assurance in same Recall Knowledge skill | Once/round: Recall Knowledge as free action using Assurance |
| Magical Shorthand | 2 | Expert in tradition skill | Learn spells faster (expert: 10 min/level; master: 5 min; legendary: 1 min) |
| Quick Recognition | 7 | Master in tradition skill + Recognize Spell | Recognize Spell as free action once per round |

**Acrobatics:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Cat Fall | 1 | Trained | Falls treated 10 ft shorter (expert: 25; master: 50; legendary: always safe) |
| Quick Squeeze | 1 | Trained | Squeeze 5 ft/round (crit: 10 ft; legendary: full Speed) |
| Steady Balance | 1 | Trained | Balance success→crit success; not flat-footed while Balancing; can use Acrobatics for Grab an Edge |
| Nimble Crawl | 2 | Expert | Crawl at half Speed (master: full Speed; legendary: not flat-footed while prone) |
| Kip Up | 7 | Master | Stand up as free action without triggering reactions |

**Arcana:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Arcane Sense | 1 | Trained | Cast detect magic at will as arcane innate spell (master: 3rd level; legendary: 4th level) |
| Unified Theory | 15 | Legendary | Use Arcana for Nature/Occultism/Religion checks; no tradition penalty |

**Athletics:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Combat Climber | 1 | Trained | Not flat-footed while Climbing; can Climb with one hand occupied |
| Hefty Hauler | 1 | Trained | Max and encumbered Bulk limits +2 |
| Quick Jump | 1 | Trained | High/Long Jump as 1 action; no Stride required |
| Titan Wrestler | 1 | Trained | Disarm/Grapple/Shove/Trip targets up to 2 sizes larger (legendary: 3 sizes) |
| Underwater Marauder | 1 | Trained | Not flat-footed in water; no penalty for bludgeoning/slashing melee in water |
| Powerful Leap | 2 | Expert | Leap 5 ft higher vertically; +5 ft horizontal distance |
| Rapid Mantel | 2 | Expert | Grab an Edge → pull self onto surface and stand; use Athletics instead of Reflex for Grab an Edge |
| Quick Climb | 7 | Master | Climb 5 ft farther on success, 10 ft farther on crit (legendary: gain climb Speed = Speed) |
| Quick Swim | 7 | Master | Swim 5 ft farther on success, 10 ft farther on crit (legendary: gain swim Speed = Speed) |
| Wall Jump | 7 | Master | Jump off walls; adjacent to wall at jump end → next action can jump without falling |
| Cloud Jump | 15 | Legendary | Long Jump distance tripled; extra actions spent = +Speed to jump limit |

**Crafting:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Alchemical Crafting | 1 | Trained | Can Craft alchemical items; gain 4 common 1st-level alchemical formulas |
| Quick Repair | 1 | Trained | Repair takes 1 minute (master: 3 actions; legendary: 1 action) |
| Snare Crafting | 1 | Trained | Can Craft snares; gain 4 common snare formulas |
| Specialty Crafting | 1 | Trained | +1 circ to Craft checks for chosen specialty (master: +2); 12 specialty types |
| Magical Crafting | 2 | Expert | Can Craft magic items; gain 4 common 2nd-level magic item formulas |
| Impeccable Crafting | 7 | Master + Specialty Crafting | Craft success→crit success for specialty type items |
| Inventor | 7 | Master | Downtime: invent new item formula (works like Craft but produces formula) |
| Craft Anything | 15 | Legendary | Ignore most Craft requirements (ancestry, spells, etc.); must still meet level/proficiency/cost |

**Deception:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Charming Liar | 1 | Trained | Crit success on Lie → target's attitude improves 1 step (once per conversation) |
| Lengthy Diversion | 1 | Trained | Crit success on Create a Diversion → remain hidden beyond end of turn (GM determines duration) |
| Lie to Me | 1 | Trained | Use Deception DC instead of Perception DC when detecting lies in conversation |
| Confabulator | 2 | Expert | Reduce the +4 circumstance bonus targets gain from previous lies/diversions to +2 (master: +1; legendary: 0) |
| Quick Disguise | 2 | Expert | Disguise in half the time (~5 min; master: 1/10 time; legendary: 3-action activity) |
| Slippery Secrets | 7 | Master | Deception check vs mind-reading/lie-detection/alignment-reveal spells to block them |

**Diplomacy:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Bargain Hunter | 1 | Trained | Earn Income with Diplomacy; find item discounts; +2 gp at character creation |
| Group Impression | 1 | Trained | Make an Impression on 2 targets simultaneously (expert: 4; master: 10; legendary: 25) |
| Hobnobber | 1 | Trained | Gather Information in half the time (~1 hour); master at normal speed: crit fail→fail |
| Glad-Hand | 2 | Expert | Make an Impression immediately on meeting (–5 penalty); can retry after 1 min on fail |
| Shameless Request | 7 | Master | Reduce outrageous request DC increases by 2; crit fail→fail on Request |
| Legendary Negotiation | 15 | Legendary | 3-action: Make Impression + Request to stop fight and parley (–5 penalty) |

**Intimidation:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Group Coercion | 1 | Trained | Coerce 2 targets at once (expert: 4; master: 10; legendary: 25) |
| Intimidating Glare | 1 | Trained | Demoralize becomes visual (not auditory); no language penalty |
| Quick Coercion | 1 | Trained | Coerce after 1 round of conversation instead of 1 minute |
| Intimidating Prowess | 2 | Str 16 + Expert | +1 circ when physically menacing during Coerce/Demoralize; ignore language penalty (Str 20+master: +2) |
| Lasting Coercion | 2 | Expert | Coerce compliance extends to up to 1 week (legendary: 1 month) |
| Battle Cry | 7 | Master | Free action Demoralize on initiative roll; legendary: reaction Demoralize on crit attack |
| Terrified Retreat | 7 | Master | Crit success Demoralize → target fleeing 1 round (if lower level than you) |
| Scare to Death | 15 | Legendary | 1 action: Intimidation vs Will DC; crit success = target rolls Fort vs your Intimidation DC (crit fail = death) |

**Lore:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Additional Lore | 1 | Trained | Gain training in another Lore subcategory; bonus skill increases at 3rd/7th/15th |
| Experienced Professional | 1 | Trained | Crit fail on Earn Income with Lore → fail instead; expert: fail gives 2× income |
| Unmistakable Lore | 2 | Expert | Recall Knowledge crit fail → fail; master: crit success yields extra context |
| Legendary Professional | 15 | Legendary | Fame spreads; NPCs start at better attitude; attract higher-level Earn Income jobs |

**Medicine:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Battle Medicine | 1 | Trained | 1-action Treat Wounds in combat; target immune 1 day (doesn't remove wounded condition) |
| Continual Recovery | 2 | Expert | Your Treat Wounds immunity reduced to 10 minutes instead of 1 hour |
| Robust Recovery | 2 | Expert | Treat Disease/Poison success bonus → +4; patient success→crit success |
| Ward Medic | 2 | Expert | Treat Disease/Wounds on 2 targets at once (master: 4; legendary: 8) |
| Legendary Medic | 15 | Legendary | 1-hour treatment: attempt Medicine check to remove disease or blinded/deafened/doomed/drained condition |

**Nature:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Natural Medicine | 1 | Trained | Use Nature instead of Medicine to Treat Wounds; +2 circ in wilderness |
| Train Animal | 1 | Trained | Downtime: teach animal a new basic action (GM sets DC/time) |
| Bonded Animal | 2 | Expert | 7 days + DC 20 Nature check → animal becomes permanently helpful; replaces previous bond |

**Occultism:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Oddity Identification | 1 | Trained | +2 circ to Identify Magic with mental/possession/prediction/scrying traits |
| Bizarre Magic | 7 | Master | DC to Recognize Spell or Identify Magic on your spells/effects increases by 5 |

**Performance:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Fascinating Performance | 1 | Trained | Perform → compare vs observer's Will DC; success = fascinated 1 round (expert: 4 targets; master: 10; legendary: unlimited) |
| Impressive Performance | 1 | Trained | Use Performance instead of Diplomacy to Make an Impression |
| Virtuosic Performer | 1 | Trained | +1 circ to chosen performance specialty (master: +2); 9 specialty types |
| Legendary Performer | 15 | Legendary + Virtuosic Performer | Fame causes NPCs to know you (DC 10 Society); typically better attitude; attract higher-level audiences for Earn Income |

**Religion:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Student of the Canon | 1 | Trained | Crit fail on Religion Decipher/Recall → fail; fail own faith Recall → success; success own faith → crit success |
| Divine Guidance | 15 | Legendary | Spend 10 min + Religion check to gain cryptic guidance from scriptures on a problem |

**Society:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Courtly Graces | 1 | Trained | Use Society to Make an Impression/Impersonate as a noble |
| Multilingual | 1 | Trained | Learn 2 languages; +1 at master; +1 at legendary (can select multiple times) |
| Read Lips | 1 | Trained | Read lips of visible creatures; fascinated + flat-footed in encounter; Society check required |
| Sign Language | 1 | Trained | Learn sign language versions of all known languages |
| Streetwise | 1 | Trained | Use Society instead of Diplomacy to Gather Info; instant Recall Knowledge in familiar settlements |
| Connections | 2 | Expert + Courtly Graces | Arrange meetings with political figures or trade favors via Society check (uncommon feat) |
| Legendary Codebreaker | 15 | Legendary | Decipher Writing with Society at reading speed; full time = success→crit success |
| Legendary Linguist | 15 | Legendary + Multilingual | Create pidgin language to communicate with any creature that has any language |

**Stealth:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Experienced Smuggler | 1 | Trained | Conceal Object passive check uses max(rolled, 10); master: max(rolled, 15); legendary: auto-succeed passively |
| Terrain Stalker | 1 | Trained | Sneak without check in chosen terrain type (rubble/snow/underbrush) if ≤5 ft movement and not within 10 ft of enemy |
| Quiet Allies | 2 | Expert | Allies Following the Expert share single Stealth check (lowest modifier) instead of rolling separately |
| Foil Senses | 7 | Master | Always taking precautions against special senses during Avoid Notice/Hide/Sneak |
| Swift Sneak | 7 | Master | Sneak at full Speed instead of half Speed |
| Legendary Sneak | 15 | Legendary + Swift Sneak | Hide and Sneak without cover or concealment; Avoid Notice benefits apply during other exploration tactics |

**Survival:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Experienced Tracker | 1 | Trained | Track at full Speed with –5 penalty (master: no penalty; legendary: no hourly recheck) |
| Forager | 1 | Trained | Subsist failure→success; success provides for self + 4 others (scales with rank: 8/16/32); can provide comfortable instead of subsistence |
| Survey Wildlife | 1 | Trained | 10-min assessment: Survival check to learn what creatures are nearby from signs; can follow with Recall Knowledge (–2 penalty; master: no penalty) |
| Terrain Expertise | 1 | Trained | +1 circ to Survival in chosen terrain type (repeatable per terrain) |
| Planar Survival | 7 | Master | Subsist on any plane regardless of available resources |
| Legendary Survivalist | 15 | Legendary | Survive indefinitely without food/water; immune to damage from severe/extreme/incredible temperature |

**Thievery:**

| Feat | Level | Prerequisites | Benefit |
|------|-------|---------------|---------|
| Pickpocket | 1 | Trained | Steal/Palm closely guarded items (pocket) without –5 penalty; master: can Steal in combat (2-action, –5) |
| Subtle Theft | 1 | Trained | Successful theft: observers –2 circ to Perception DC; Palm/Steal after Create Diversion doesn't end undetected |
| Wary Disarmament | 2 | Expert | If triggering device/trap while disarming: +2 circ to AC/save against triggered effect |
| Quick Unlock | 7 | Master | Pick a Lock as 1 action instead of 2 |
| Legendary Thief | 15 | Legendary + Pickpocket | Can Steal worn/wielded items with 1+ minute careful approach; –5 penalty |

---

## SECTION: Key Feat Mechanic Notes

### Paragraph — Assurance

> Even in the worst circumstances, you can perform basic tasks. Forgo rolling to receive 10 + proficiency bonus (no other bonuses, penalties, or modifiers). Selectable multiple times for different skills.

- REQ: Assurance is a fortune trait feat; produces fixed result = 10 + proficiency bonus only
- REQ: No other bonuses, penalties, or modifiers apply to an Assurance result
- REQ: Can be selected once per skill; each selection is independent

### Paragraph — Recognize Spell (Reaction)

> Trigger: observe a creature cast a spell not in your repertoire/prepared spells. Auto-identify common spells at/below level threshold (2nd for trained; 4th expert; 6th master; 10th legendary). GM rolls secret check.
> Crit Success: Identify + +1 circ to save/AC against it. Success: Identify. Fail: Can't identify. Crit Fail: Misidentify.

- REQ: Recognize Spell is a reaction with no action cost; requires awareness of casting
- REQ: Auto-identify threshold by rank: trained=level ≤2; expert=≤4; master=≤6; legendary=≤10 (common spells only)
- REQ: Crit Success grants +1 circumstance bonus to save or AC against the spell
- REQ: Crit Fail produces false identification

### Paragraph — Trick Magic Item (1 action)

> Attempt to activate a magic item you can't normally use. Must know what activating it does. Roll appropriate tradition skill vs item level DC. If spell attack/DC required and no spellcasting ability: use level as proficiency bonus + highest Int/Wis/Cha; master skill→trained prof; legendary→expert prof.
> Success: Activate item for rest of current turn. Fail: Can't use this turn. Crit Fail: Can't use until next daily prep.

- REQ: Trick Magic Item is 1-action manipulate; requires trained in appropriate tradition skill
- REQ: Must know what the item does before attempting
- REQ: Spell attack/DC falls back to level-based proficiency + highest mental ability score
- REQ: Crit Fail locks out until next daily preparations

### Paragraph — Battle Medicine (1 action)

> Heal ally (or self) in combat. Same DC and HP restoration as Treat Wounds; does NOT remove wounded condition. Target immune to your Battle Medicine for 1 day (not immune to other Treat Wounds).

- REQ: Battle Medicine is 1 action + manipulate; requires healer's tools + trained Medicine
- REQ: Uses same DC/HP table as Treat Wounds; does NOT remove wounded condition
- REQ: Per-character 1-day immunity; does not block other healers from using Treat Wounds on same target

### Paragraph — Specialty Crafting Specialties

> 12 specialties: Alchemy (alchemical items), Artistry (fine art/jewelry), Blacksmithing (metal goods/armor), Bookmaking (books/paper), Glassmaking (glass), Leatherworking (leather goods/armor), Pottery (ceramic), Shipbuilding (ships/boats), Stonemasonry (stone), Tailoring (clothing), Weaving (textiles/baskets/rugs), Woodworking (wooden goods/structures).

- REQ: Specialty Crafting +1 circ to relevant Craft checks; master → +2
- REQ: GM adjudicates partial applicability for items that span multiple specialties

### Paragraph — Virtuosic Performer Specialties

> 9 specialties: Acting, Comedy, Dance, Keyboards, Oratory, Percussion, Singing, Strings, Winds.

- REQ: Virtuosic Performer +1 circ to chosen performance type; master → +2

---

