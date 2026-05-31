# Dungeoncrawler Feature Index

**Purpose:** Fast duplicate-detection and PM triage lookup. Before creating any new `dc-*` feature stub, check this file first. If the slug or concept already exists here, skip it. PM can filter by Category column to prioritize triage passes. `Depends on` column shows prerequisite features for implementation sequencing.

**Maintained by:** `ba-dungeoncrawler` — update this file at the end of every scan chunk (same commit as new feature stubs). Include `Category` and `Depends on` for every new row (`Depends on` may be blank if no dependencies).

**Last updated:** 2026-04-18 | Active release: `20260412-dungeoncrawler-release-n` | Runtime next release pointer: `20260412-dungeoncrawler-release-o` | Total: 86

**Release sync note:** Treat this index as backlog/duplicate-detection inventory. Live per-feature status and release progress come from each feature's `feature.md`, `dashboards/FEATURE_PROGRESS.md`, and the runtime pointers under `tmp/release-cycle-active/`.

---

## Index (sorted by work item id)

| Work item id | Category | Depends on | One-line summary |
|---|---|---|---|
| dc-cr-action-economy | rule-system |  | Three-action turn economy (3 actions + 1 reaction) underpinning all encounter play |
| dc-cr-alchemical-items | item |  | Alchemical consumables (bombs/elixirs/mutagens/poisons) with alchemist daily crafting |
| dc-cr-ancestry-feat-schedule | game-mechanic | dc-cr-ancestry-system, dc-cr-character-leveling | Ancestry feat progression slots at levels 1/5/9/13/17 with prerequisite checking |
| dc-cr-ancestry-system | game-mechanic |  | Ancestry selection (dwarf/elf/gnome/goblin/halfling/human) with stat grants and feat trees |
| dc-cr-ancestry-traits | rule-system | dc-cr-ancestry-system | Creature trait tags (e.g. Dwarf, Humanoid) enabling correct spell/ability targeting |
| dc-cr-animal-accomplice | game-mechanic | dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-familiar | Gnome Feat 1: bonded animal familiar (gnomes typically choose burrowing animals) |
| dc-cr-animal-companion | game-mechanic |  | Animal companions for druids/rangers with own stat blocks and advancement |
| dc-cr-background-system | game-mechanic |  | Background selection granting ability boosts, skill training, and a skill feat |
| dc-cr-burrow-elocutionist | game-mechanic | dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule | Gnome Feat 1: comprehend and speak with burrowing creatures |
| dc-cr-character-class | game-mechanic |  | 12 character classes (fighter, cleric, wizard, alchemist, etc.) with advancement tables |
| dc-cr-character-creation | rule-system |  | End-to-end character creation workflow (ancestry → class → background → stats) |
| dc-cr-character-leveling | rule-system |  | Level-up flow applying class features, ability boosts, and feats at each level |
| dc-cr-clan-dagger | item | dc-cr-dwarf-ancestry, dc-cr-equipment-system | Free dwarven ancestral starting weapon with cultural taboo on selling |
| dc-cr-conditions | rule-system |  | Conditions catalog (dying, frightened, flat-footed, etc.) with valued conditions engine |
| dc-cr-crafting | rule-system | dc-cr-downtime-mode | Crafting downtime: skill check vs. item DC, material cost, formula requirement |
| dc-cr-darkvision | rule-system |  | Darkvision sense: see in darkness/dim light as bright light (black-and-white in darkness); shared across multiple ancestries |
| dc-cr-dice-system | rule-system |  | Virtual polyhedral dice engine (d4 through d20, d%) powering all game resolution |
| dc-cr-difficulty-class | rule-system |  | DC system with level-based tables and four-degree success resolution (crit-success through crit-fail) |
| dc-cr-dwarf-ancestry | game-mechanic | dc-cr-ancestry-system, dc-cr-heritage-system, dc-cr-clan-dagger | Dwarf stat block: HP 10, speed 20, Con/Wis/Free boosts, Cha flaw, Common+Dwarven |
| dc-cr-dwarf-heritage-ancient-blooded | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-heritage-system | Ancient-Blooded Dwarf heritage granting Call on Ancient Blood reaction vs. magical saves |
| dc-cr-dwarf-heritage-death-warden | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-heritage-system | Death Warden heritage: success on necromancy saves upgraded to critical success |
| dc-cr-dwarf-heritage-forge | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-heritage-system | Forge Dwarf heritage: fire resistance (half level, min 1) and environmental heat severity downgrade |
| dc-cr-dwarf-heritage-rock | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-heritage-system | Rock Dwarf heritage: +2 DC vs. Shove/Trip, forced movement halved |
| dc-cr-dwarf-heritage-strong-blooded | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-heritage-system, dc-cr-conditions | Strong-Blooded heritage: poison resistance (half level) and accelerated poison stage reduction |
| dc-cr-dwarven-weapon-familiarity | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-equipment-system | Dwarven Weapon Familiarity feat: trained with battle axe/pick/warhammer; dwarf weapon category downgrade |
| dc-cr-dwarven-weapon-expertise | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-dwarven-weapon-familiarity, dc-cr-equipment-system | Dwarven Weapon Expertise (Feat 13): extend class expert+ proficiency to battle axe/pick/warhammer and trained dwarven weapons |
| dc-cr-downtime-mode | rule-system |  | Long-duration downtime activities: Earn Income, Craft, Retrain, etc. |
| dc-cr-elf-ancestry | game-mechanic | dc-cr-ancestry-system, dc-cr-heritage-system, dc-cr-low-light-vision, dc-cr-languages | Elf stat block: HP 6, speed 30, Dex/Int/Free boosts, Con flaw, Common+Elven+Int-bonus languages |
| dc-cr-elf-heritage-arctic | game-mechanic | dc-cr-elf-ancestry, dc-cr-heritage-system | Arctic Elf heritage: cold resistance (half level, min 1) and environmental cold severity downgrade |
| dc-cr-elf-heritage-cavern | game-mechanic | dc-cr-elf-ancestry, dc-cr-heritage-system, dc-cr-darkvision | Cavern Elf heritage: replaces low-light vision with darkvision |
| dc-cr-encounter-rules | rule-system |  | Full combat loop: initiative, MAP, degree-of-success attack resolution, HP tracking |
| dc-cr-equipment-system | item |  | Weapons, armor, shields, and adventuring gear with damage/AC/bulk fields |
| dc-cr-exploration-mode | rule-system |  | Between-encounter mode with ongoing exploration activities that affect initiative |
| dc-cr-familiar | game-mechanic |  | Magical familiars for casters with daily-selectable familiar abilities |
| dc-cr-fey-fellowship | game-mechanic | dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule | Gnome Feat 1: +2 vs fey Perception/saves; immediate Diplomacy with fey (–5 penalty) |
| dc-cr-first-world-adept | game-mechanic | dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-spellcasting | Gnome Feat 9: faerie fire + invisibility as 2nd-level primal innate spells (1/day each) |
| dc-cr-first-world-magic | game-mechanic | dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-spellcasting | Gnome Feat 1: one primal cantrip as at-will innate spell (fixed at selection) |
| dc-cr-focus-spells | game-mechanic |  | Focus Point pool and focus spells that auto-heighten; used by 6+ classes |
| dc-cr-general-feats | game-mechanic |  | General feat catalog available to all characters at levels 3/7/11/15/19 |
| dc-cr-gnome-ancestry | game-mechanic |  | Gnome ancestry: 8 HP, Small, Speed 25, Con+Cha boosts, Str flaw, Low-Light Vision |
| dc-cr-gnome-heritage-chameleon | game-mechanic | dc-cr-gnome-ancestry, dc-cr-heritage-system | Chameleon Gnome: +2 Stealth when coloration matches terrain (1-action minor shift) |
| dc-cr-gnome-heritage-fey-touched | game-mechanic | dc-cr-gnome-ancestry, dc-cr-heritage-system, dc-cr-spellcasting | Fey-touched Gnome: fey trait, primal cantrip at will, daily cantrip swap (10-min activity) |
| dc-cr-gnome-heritage-sensate | game-mechanic | dc-cr-gnome-ancestry, dc-cr-heritage-system | Sensate Gnome: imprecise scent 30 ft + +2 Perception vs undetected within scent range |
| dc-cr-gnome-heritage-umbral | game-mechanic | dc-cr-gnome-ancestry, dc-cr-heritage-system, dc-cr-darkvision | Umbral Gnome: darkvision (see in complete darkness) |
| dc-cr-gnome-heritage-wellspring | game-mechanic | dc-cr-gnome-ancestry, dc-cr-heritage-system, dc-cr-spellcasting | Wellspring Gnome: choose tradition (arcane/divine/occult); cantrip at will; override primal innate spells |
| dc-cr-gnome-obsession | game-mechanic | dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule | Gnome Feat 1: chosen Lore skill auto-scales to expert/master/legendary at levels 2/7/15 |
| dc-cr-gnome-weapon-expertise | game-mechanic | dc-cr-gnome-ancestry, dc-cr-gnome-weapon-familiarity, dc-cr-ancestry-feat-schedule | Gnome Feat 13: class weapon proficiency upgrades cascade to glaive/kukri/gnome weapons |
| dc-cr-gnome-weapon-familiarity | game-mechanic | dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule | Gnome Feat 1: trained in glaive + kukri; gnome martial weapons count as simple |
| dc-cr-gnome-weapon-specialist | game-mechanic | dc-cr-gnome-ancestry, dc-cr-gnome-weapon-familiarity, dc-cr-ancestry-feat-schedule | Gnome Feat 5: critical specialization effects with glaive, kukri, and gnome weapons |
| dc-cr-goblin-ancestry | game-mechanic |  | Goblin ancestry: 6 HP, Small, Speed 25, Dex+Cha+Free boosts, Wisdom flaw |
| dc-cr-goblin-very-sneaky | game-mechanic | dc-cr-goblin-ancestry, dc-cr-ancestry-feat-schedule | Goblin Feat 1: Sneak +5 ft movement; don't become Observed if cover/concealment held at end of turn |
| dc-cr-goblin-weapon-familiarity | game-mechanic | dc-cr-goblin-ancestry, dc-cr-ancestry-feat-schedule | Goblin Feat 1: trained with dogslicer and horsechopper; uncommon goblin weapons unlocked; goblin weapon proficiencies remapped down by one step |
| dc-cr-goblin-weapon-frenzy | game-mechanic | dc-cr-goblin-ancestry, dc-cr-goblin-weapon-familiarity, dc-cr-ancestry-feat-schedule | Goblin Feat 5: critical specialization effects with goblin weapons (prereq: Goblin Weapon Familiarity) |
| dc-cr-half-elf-heritage | game-mechanic | dc-cr-human-ancestry, dc-cr-heritage-system, dc-cr-low-light-vision, dc-cr-ancestry-feat-schedule | Human heritage overlay granting elf/half-elf traits, low-light vision, and expanded ancestry feat eligibility |
| dc-cr-halfling-ancestry | game-mechanic | dc-cr-ancestry-system | Halfling ancestry: 6 HP, Small, Speed 25, Dex+Wis boosts, Keen Eyes trait, Lucky Halfling |
| dc-cr-halfling-heritage-gutsy | game-mechanic | dc-cr-halfling-ancestry, dc-cr-heritage-system | Halfling Heritage: success on emotion saving throw upgraded to critical success |
| dc-cr-halfling-heritage-hillock | game-mechanic | dc-cr-halfling-ancestry, dc-cr-heritage-system | Halfling Heritage: add level to overnight HP recovery; add level to Treat Wounds HP |
| dc-cr-halfling-keen-eyes | game-mechanic | dc-cr-halfling-ancestry | Halfling trait: +2 Seek vs hidden/undetected within 30 ft; flat check DC 3 (concealed) / 9 (hidden) |
| dc-cr-gm-narrative-engine | rule-system | dc-cr-gm-tools, dc-cr-npc-system, dc-cr-session-structure | AI GM storytelling pipeline: scene framing, NPC dialogue, outcome narration |
| dc-cr-gm-tools | rule-system |  | GM encounter budgeting, NPC stat blocks, loot-by-level tables for AI GM use |
| dc-cr-heritage-system | game-mechanic | dc-cr-ancestry-system | Heritage selection: one heritage per ancestry at level 1, locked after creation |
| dc-cr-hazards | game-mechanic |  | Trap/haunt/environmental hazard stat blocks with Stealth/Disable DCs and effects |
| dc-cr-human-ancestry | game-mechanic | dc-cr-ancestry-system, dc-cr-heritage-system, dc-cr-languages, dc-cr-ancestry-feat-schedule | Human ancestry: 8 HP, Medium, 25-ft speed, two free boosts, bonus language, extra trained skill, extra skill feat |
| dc-cr-languages | game-mechanic |  | Language tracking with ancestry defaults and Intelligence-based free language slots |
| dc-cr-low-light-vision | rule-system |  | Low-Light Vision sense: see in dim light as bright light; ignore concealed condition due to dim light; elf default vision |
| dc-cr-magic-items | item |  | Magic item catalog (weapons/armor/wondrous) with investment, activation, rune system |
| dc-cr-mountains-stoutness | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-conditions | Mountains Stoutness: +4 HP at level 1, +1 HP/level going forward; +2 max HP for resting recovery |
| dc-cr-multiclass-archetype | game-mechanic |  | Multiclass archetypes via dedication feats enabling cross-class feature access |
| dc-cr-npc-system | game-mechanic |  | NPC entity type (allies/contacts/villains) distinct from monsters, with AI GM dialogue hooks |
| dc-cr-rituals | rule-system |  | Extended-casting rituals with skill checks, material costs, and four-degree outcomes |
| dc-cr-rock-runner | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule | Rock Runner: ignore difficult terrain from rubble/rock/stone; no flat-footed on uneven stone |
| dc-cr-session-structure | rule-system |  | One-shot and campaign session model with persistent character/world state between sessions |
| dc-cr-skill-feats | game-mechanic |  | Skill feat catalog gated by skill proficiency; taken at even levels for most classes |
| dc-cr-skill-system | game-mechanic |  | 17 skills with proficiency ranks (Untrained → Legendary) and skill check resolution |
| dc-cr-spellcasting | rule-system |  | Core spellcasting rules: spell slots, traditions, prepared vs. spontaneous, DCs |
| dc-cr-tactical-grid | rule-system |  | 5-foot grid spatial system for combat positioning, reach, area effects, and flanking |
| dc-cr-unburdened-iron | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-equipment-system | Unburdened Iron: reduce Speed penalty from armor to 0; 5-ft penalty reduction stacks for others |
| dc-cr-vengeful-hatred | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule | Vengeful Hatred: +1 circumstance bonus on attacks vs. chosen ancestry or creature type |
| dc-cr-vivacious-conduit | game-mechanic | dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule | Gnome Feat 9: 10-min rest heals HP = Con mod × (level/2); stacks with Treat Wounds |
| dc-cr-xp-rewards | rule-system | dc-cr-character-leveling | XP tracking with 1,000 XP per level threshold; triggers character-leveling flow |
| dc-ui-encounter-party-rail | ui-system | dc-ui-hexmap-thin-client, dc-cr-action-economy, dc-cr-encounter-rules, dc-cr-conditions | Rich initiative/party rail with turn, team, HP, and condition visibility |
| dc-ui-hexmap-thin-client | ui-system | dc-cr-encounter-rules, dc-cr-exploration-mode, dc-cr-session-structure, dc-cr-npc-system | Refactor hexmap into a display/chat client while gameplay authority lives on backend services |
| dc-ui-map-first-player-shell | ui-system | dc-cr-exploration-mode, dc-cr-encounter-rules | Board-first hexmap shell that gates debug surfaces and centers active play |
| dc-ui-scene-layer-contract | ui-system | dc-cr-tactical-grid, dc-cr-exploration-mode, dc-cr-encounter-rules | Formal Pixi render-layer stack for background, props, tokens, overlays, FX, and HUD |
| dc-ui-sidebar-drawers | ui-system | dc-ui-map-first-player-shell | Drawer-based character/inventory/quest/inspector shell with docked support panels |
| dc-ui-token-readability | ui-system | dc-ui-hexmap-thin-client, dc-ui-scene-layer-contract, dc-cr-conditions, dc-cr-encounter-rules | On-map team rings, HP bars, condition badges, and interaction markers for tokens |

## New features (roadmap audit 2026-04-07)

- `dc-apg-ancestries` — APG Ancestries and Versatile Heritages [planned]
- `dc-apg-archetypes` — APG Archetypes System [planned]
- `dc-apg-class-investigator` — Investigator Class Mechanics (APG) [planned]
- `dc-apg-class-oracle` — Oracle Class Mechanics (APG) [planned]
- `dc-apg-class-swashbuckler` — Swashbuckler Class Mechanics (APG) [planned]
- `dc-apg-class-witch` — Witch Class Mechanics (APG) [planned]
- `dc-apg-equipment` — APG Equipment, Magic Items, and Alchemical Items [planned]
- `dc-apg-feats` — APG General and Skill Feats [planned]
- `dc-apg-focus-spells` — APG Focus Spells [planned]
- `dc-apg-rituals` — APG New Rituals [planned]
- `dc-apg-spells` — APG New Spells [planned]
- `dc-b1-bestiary1` — Bestiary 1 (deferred) [deferred]
- `dc-b2-bestiary2` — Bestiary 2 (deferred) [deferred]
- `dc-b3-bestiary3` — Bestiary 3 (deferred) [deferred]
- `dc-cr-action-economy` — Action Economy System [done]
- `dc-cr-alchemical-items` — Alchemical Items [deferred]
- `dc-cr-ancestry-feat-schedule` — Ancestry Feat Schedule [deferred]
- `dc-cr-ancestry-system` — Ancestry System [done]
- `dc-cr-ancestry-traits` — Ancestry Traits System [ready]
- `dc-cr-animal-companion` — Animal Companion System [deferred]
- `dc-cr-background-system` — Background System [done]
- `dc-cr-character-class` — Character Class System [done]
- `dc-cr-character-creation` — Character Creation Workflow [done]
- `dc-cr-character-leveling` — Character Leveling and Advancement [ready]
- `dc-cr-clan-dagger` — Clan Dagger (Dwarven Starting Equipment) [shipped]
- `dc-cr-class-alchemist` — Alchemist Class Mechanics [planned]
- `dc-cr-class-barbarian` — Barbarian Class Mechanics [planned]
- `dc-cr-class-bard` — Bard Class Mechanics [planned]
- `dc-cr-class-champion` — Champion Class Mechanics [planned]
- `dc-cr-class-cleric` — Cleric Class Mechanics [planned]
- `dc-cr-class-druid` — Druid Class Mechanics [planned]
- `dc-cr-class-fighter` — Fighter Class Mechanics [planned]
- `dc-cr-class-monk` — Monk Class Mechanics [planned]
- `dc-cr-class-ranger` — Ranger Class Mechanics [planned]
- `dc-cr-class-rogue` — Rogue Class Mechanics [planned]
- `dc-cr-class-sorcerer` — Sorcerer Class Mechanics [planned]
- `dc-cr-class-wizard` — Wizard Class Mechanics [planned]
- `dc-cr-conditions` — Conditions System [in_progress]
- `dc-cr-crafting` — Crafting System [deferred]
- `dc-cr-darkvision` — Darkvision Sense [ready]
- `dc-cr-dice-system` — Polyhedral Dice Engine [done]
- `dc-cr-difficulty-class` — Difficulty Class (DC) System [in_progress]
- `dc-cr-downtime-mode` — Downtime Mode [deferred]
- `dc-cr-dwarf-ancestry` — Dwarf Ancestry [deferred]
- `dc-cr-dwarf-heritage-ancient-blooded` — Dwarf Heritage — Ancient-Blooded [ready]
- `dc-cr-dwarf-heritage-death-warden` — Dwarf Heritage — Death Warden [deferred]
- `dc-cr-dwarf-heritage-forge` — Dwarf Heritage — Forge Dwarf [deferred]
- `dc-cr-dwarf-heritage-rock` — Dwarf Heritage — Rock Dwarf [deferred]
- `dc-cr-dwarf-heritage-strong-blooded` — Dwarf Heritage — Strong-Blooded Dwarf [deferred]
- `dc-cr-dwarven-weapon-expertise` — Dwarven Weapon Expertise [deferred]
- `dc-cr-dwarven-weapon-familiarity` — Dwarven Weapon Familiarity (Ancestry Feat) [deferred]
- `dc-cr-economy` — Economy, Services, and Currency [planned]
- `dc-cr-elf-ancestry` — Elf Ancestry [ready]
- `dc-cr-elf-heritage-arctic` — Arctic Elf Heritage [deferred]
- `dc-cr-elf-heritage-cavern` — Cavern Elf Heritage [ready]
- `dc-cr-encounter-rules` — Encounter and Combat Rules [done]
- `dc-cr-equipment-ch06` — Core Book Chapter 6 — Complete Equipment Rules [planned]
- `dc-cr-equipment-system` — Equipment and Gear System [in_progress]
- `dc-cr-exploration-mode` — Exploration Mode [deferred]
- `dc-cr-familiar` — Familiar System [deferred]
- `dc-cr-feats-ch05` — Core Book Chapter 5 — Feats Overview and Key Mechanics [planned]
- `dc-cr-focus-spells` — Focus Spells [deferred]
- `dc-cr-general-feats` — General Feats [deferred]
- `dc-cr-gm-narrative-engine` — AI GM Narrative Engine [deferred]
- `dc-cr-gm-tools` — GM Tools and Adventure Preparation [deferred]
- `dc-cr-hazards` — Environmental Hazards [deferred]
- `dc-cr-heritage-system` — Heritage Selection System [done]
- `dc-cr-languages` — Languages System [ready]
- `dc-cr-low-light-vision` — Low-Light Vision [ready]
- `dc-cr-magic-ch11` — Core Book Chapter 11 — Complete Magic Items and Treasure [planned]
- `dc-cr-magic-items` — Magic Items and Treasure [deferred]
- `dc-cr-mountains-stoutness` — Mountain's Stoutness (Dwarf Ancestry Feat) [deferred]
- `dc-cr-multiclass-archetype` — Multiclass Archetype System [deferred]
- `dc-cr-npc-system` — NPC System [deferred]
- `dc-cr-rituals` — Ritual Magic System [deferred]
- `dc-cr-rock-runner` — Rock Runner (Dwarf Ancestry Feat) [deferred]
- `dc-cr-rune-system` — Runes, Materials, and Magic Items [planned]
- `dc-cr-session-structure` — Session and Campaign Structure [deferred]
- `dc-cr-skill-feats` — Skill Feats [deferred]
- `dc-cr-skill-system` — Skill System [done]
- `dc-cr-snares` — Snares (Core + APG) [planned]
- `dc-cr-spellcasting` — Spellcasting Rules System [deferred]
- `dc-cr-spells-ch07` — Core Book Chapter 7 — Spellcasting Rules [planned]
- `dc-cr-tactical-grid` — Tactical Grid System [deferred]
- `dc-cr-unburdened-iron` — Unburdened Iron (Dwarf Ancestry Feat) [deferred]
- `dc-cr-vengeful-hatred` — Vengeful Hatred (Dwarf Ancestry Feat) [deferred]
- `dc-cr-xp-rewards` — XP and Rewards System [deferred]
- `dc-gam-guns-gears` — Guns and Gears (deferred) [deferred]
- `dc-gmg-hazards` — GMG Hazards and Traps [planned]
- `dc-gmg-npc-gallery` — GMG NPC Gallery System [planned]
- `dc-gmg-running-guide` — GMG Chapter 1 — Running the Game [planned]
- `dc-gng-lost-omens` — Lost Omens: Gods & Magic (deferred) [deferred]
- `dc-home-suggestion-notice` — Add a general statement on the Dungeoncrawler home page informing players that user suggestions are being implemented [ready]
- `dc-som-secrets-of-magic` — Secrets of Magic (deferred) [deferred]
