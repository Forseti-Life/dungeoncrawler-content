# Feat Implementation Review (162/162)

Generated from current resolver code in `src/Service/FeatEffectManager.php`.

## Hook Chain

- `FeatEffectManager::buildEffectState()` resolves feat effects.
- `CharacterStateService::applyFeatEffectsToState()` persists derived feat effects into campaign `state_data`.
- `CharacterViewController` + `character-sheet.html.twig` surface feat effects on the sheet.

## Per-Feat Implementation

| # | Feat ID | Name | Implementation Path | Hook(s) | In-Game Impact |
|---:|---|---|---|---|---|
| 1 | `adapted-cantrip` | Adapted Cantrip | switch-case | addSelectionGrant, available_actions.at_will | Adds selection grant metadata; Adds feat action to at-will action list |
| 2 | `ancestral-longevity` | Ancestral Longevity | switch-case | addSelectionGrant, addSkillTraining | Adds selection grant metadata; Adds trained skill grants |
| 3 | `animal-accomplice` | Animal Accomplice | switch-case | addSelectionGrant, available_actions.at_will | Adds selection grant metadata; Adds feat action to at-will action list |
| 4 | `beak-adept` | Beak Adept | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 5 | `burn-it` | Burn It! | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 6 | `burrow-elocutionist` | Burrow Elocutionist | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 7 | `cat-nap` | Cat Nap | switch-case | addLongRestLimitedAction | Adds long-rest action and resource tracking |
| 8 | `catfolk-lore` | Catfolk Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 9 | `catfolk-weapon-familiarity` | Catfolk Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 10 | `cheek-pouches` | Cheek Pouches | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 11 | `city-scavenger` | City Scavenger | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 12 | `communal-instinct` | Communal Instinct | switch-case | addConditionalSaveModifier | Adds conditional saving throw modifier |
| 13 | `cooperative-nature` | Cooperative Nature | switch-case | addConditionalSaveModifier | Adds conditional saving throw modifier |
| 14 | `cross-cultural-upbringing` | Cross-Cultural Upbringing | switch-case | addSelectionGrant | Adds selection grant metadata |
| 15 | `distracting-shadows` | Distracting Shadows | switch-case | buildEffectState switch case | Custom first-pass feat effect mapping |
| 16 | `draconic-scout` | Draconic Scout | switch-case | addSense, addLongRestLimitedAction | Adds a sense/vision entry; Adds long-rest action and resource tracking |
| 17 | `draconic-ties` | Draconic Ties | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 18 | `dwarven-lore` | Dwarven Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 19 | `dwarven-weapon-familiarity` | Dwarven Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 20 | `elf-atavism` | Elf Atavism | switch-case | addSelectionGrant | Adds selection grant metadata |
| 21 | `elven-instincts` | Elven Instincts | switch-case | derived_adjustments.initiative_bonus | Changes initiative bonus |
| 22 | `elven-lore` | Elven Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 23 | `elven-weapon-familiarity` | Elven Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 24 | `feline-eyes` | Feline Eyes | switch-case | addSense | Adds a sense/vision entry |
| 25 | `feral-endurance` | Feral Endurance | switch-case | addLongRestLimitedAction | Adds long-rest action and resource tracking |
| 26 | `fey-fellowship` | Fey Fellowship | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 27 | `first-world-magic` | First World Magic | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 28 | `forest-step` | Forest Step | switch-case | buildEffectState switch case | Custom first-pass feat effect mapping |
| 29 | `forlorn` | Forlorn | switch-case | addConditionalSaveModifier, conditional_modifiers.outcome_upgrades | Adds conditional saving throw modifier; Adds degree-of-success outcome upgrade |
| 30 | `forlorn-half-elf` | Forlorn Half-Elf | switch-case | addConditionalSaveModifier | Adds conditional saving throw modifier |
| 31 | `general-training` | General Training | switch-case | addSelectionGrant | Adds selection grant metadata |
| 32 | `gnome-obsession` | Gnome Obsession | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 33 | `gnome-weapon-familiarity` | Gnome Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 34 | `goblin-lore` | Goblin Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 35 | `goblin-scuttle` | Goblin Scuttle | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 36 | `goblin-song` | Goblin Song | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 37 | `goblin-weapon-familiarity` | Goblin Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 38 | `graceful-step` | Graceful Step | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 39 | `halfling-lore` | Halfling Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 40 | `halfling-luck` | Halfling Luck | switch-case | addLongRestLimitedAction | Adds long-rest action and resource tracking |
| 41 | `halfling-weapon-familiarity` | Halfling Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 42 | `haughty-obstinacy` | Haughty Obstinacy | switch-case | addConditionalSaveModifier | Adds conditional saving throw modifier |
| 43 | `hold-scarred` | Hold-Scarred Orc | switch-case | addLongRestLimitedAction | Adds long-rest action and resource tracking |
| 44 | `illusion-sense` | Illusion Sense | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 45 | `intimidating-glare-half-orc` | Intimidating Glare | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 46 | `junk-tinker` | Junk Tinker | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 47 | `kobold-lore` | Kobold Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 48 | `kobold-weapon-familiarity` | Kobold Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 49 | `leshy-lore` | Leshy Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 50 | `mixed-heritage-adaptability` | Mixed Heritage Adaptability | switch-case | addSelectionGrant | Adds selection grant metadata |
| 51 | `multitalented` | Multitalented | switch-case | addSelectionGrant | Adds selection grant metadata |
| 52 | `natural-ambition` | Natural Ambition | switch-case | addSelectionGrant | Adds selection grant metadata |
| 53 | `natural-skill` | Natural Skill | switch-case | addSelectionGrant | Adds selection grant metadata |
| 54 | `nimble-elf` | Nimble Elf | switch-case | derived_adjustments.speed_override | Overrides base speed floor |
| 55 | `one-toed-hop` | One-Toed Hop | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 56 | `orc-atavism` | Orc Atavism | switch-case | addSelectionGrant | Adds selection grant metadata |
| 57 | `orc-ferocity` | Orc Ferocity | switch-case | addLongRestLimitedAction | Adds long-rest action and resource tracking |
| 58 | `orc-sight` | Orc Sight | switch-case | addSense | Adds a sense/vision entry |
| 59 | `orc-superstition` | Orc Superstition | switch-case | addConditionalSaveModifier | Adds conditional saving throw modifier |
| 60 | `orc-weapon-carnage` | Orc Weapon Carnage | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 61 | `orc-weapon-familiarity` | Orc Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 62 | `orc-weapon-familiarity-half-orc` | Orc Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 63 | `otherworldly-magic` | Otherworldly Magic | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 64 | `photosynthetic-recovery` | Photosynthetic Recovery | switch-case | addLongRestLimitedAction | Adds long-rest action and resource tracking |
| 65 | `ratfolk-lore` | Ratfolk Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 66 | `ratfolk-weapon-familiarity` | Ratfolk Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 67 | `rock-runner` | Rock Runner | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 68 | `rooted-resilience` | Rooted Resilience | switch-case | buildEffectState switch case | Custom first-pass feat effect mapping |
| 69 | `scar-thickened` | Scar-Thickened | switch-case | addConditionalSaveModifier | Adds conditional saving throw modifier |
| 70 | `scrounger` | Scrounger | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 71 | `seedpod` | Seedpod | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 72 | `sky-bridge-runner` | Sky-Bridge Runner | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 73 | `snare-setter` | Snare Setter | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 74 | `squawk` | Squawk | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 75 | `stonecunning` | Stonecunning | switch-case | derived_adjustments.perception_bonus | Changes perception bonus |
| 76 | `sure-feet` | Sure Feet | switch-case | conditional_modifiers.outcome_upgrades | Adds degree-of-success outcome upgrade |
| 77 | `tengu-lore` | Tengu Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 78 | `tengu-weapon-familiarity` | Tengu Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 79 | `titan-slinger` | Titan Slinger | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 80 | `tunnel-runner` | Tunnel Runner | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 81 | `tunnel-vision` | Tunnel Vision | switch-case | derived_adjustments.perception_bonus | Changes perception bonus |
| 82 | `unburdened-iron` | Unburdened Iron | switch-case | buildEffectState switch case | Custom first-pass feat effect mapping |
| 83 | `unconventional-weaponry` | Unconventional Weaponry | switch-case | addSelectionGrant | Adds selection grant metadata |
| 84 | `unfettered-halfling` | Unfettered Halfling | switch-case | addConditionalSkillModifier, conditional_modifiers.outcome_upgrades | Adds conditional skill modifier; Adds degree-of-success outcome upgrade |
| 85 | `unwavering-mien` | Unwavering Mien | switch-case | conditional_modifiers.outcome_upgrades | Adds degree-of-success outcome upgrade |
| 86 | `unyielding-will` | Unyielding Will | switch-case | addConditionalSaveModifier | Adds conditional saving throw modifier |
| 87 | `vengeful-hatred` | Vengeful Hatred | switch-case | addConditionalSaveModifier | Adds conditional saving throw modifier |
| 88 | `verdant-voice` | Verdant Voice | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 89 | `well-groomed` | Well-Groomed | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 90 | `animal-companion` | Animal Companion | switch-case | addSelectionGrant | Adds selection grant metadata |
| 91 | `counterspell` | Counterspell | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 92 | `crossbow-ace` | Crossbow Ace | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 93 | `double-slice` | Double Slice | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 94 | `eschew-materials` | Eschew Materials | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 95 | `exacting-strike` | Exacting Strike | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 96 | `familiar` | Familiar | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 97 | `hand-of-the-apprentice` | Hand of the Apprentice | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 98 | `hunted-shot` | Hunted Shot | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 99 | `monster-hunter` | Monster Hunter | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 100 | `nimble-dodge` | Nimble Dodge | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 101 | `point-blank-shot` | Point-Blank Shot | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 102 | `power-attack` | Power Attack | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 103 | `reach-spell` | Reach Spell | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 104 | `reactive-shield` | Reactive Shield | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 105 | `snagging-strike` | Snagging Strike | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 106 | `trap-finder` | Trap Finder | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 107 | `twin-feint` | Twin Feint | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 108 | `twin-takedown` | Twin Takedown | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 109 | `widen-spell` | Widen Spell | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 110 | `you-re-next` | You're Next | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 111 | `adopted-ancestry` | Adopted Ancestry | switch-case | addSelectionGrant | Adds selection grant metadata |
| 112 | `armor-proficiency` | Armor Proficiency | switch-case | addProficiencyGrant | Adds proficiency training grant |
| 113 | `breath-control` | Breath Control | switch-case | addLongRestLimitedAction | Adds long-rest action and resource tracking |
| 114 | `canny-acumen` | Canny Acumen | switch-case | addSelectionGrant | Adds selection grant metadata |
| 115 | `diehard` | Diehard | switch-case | addLongRestLimitedAction | Adds long-rest action and resource tracking |
| 116 | `fast-recovery` | Fast Recovery | switch-case | addLongRestLimitedAction | Adds long-rest action and resource tracking |
| 117 | `feather-step` | Feather Step | switch-case | buildEffectState switch case | Custom first-pass feat effect mapping |
| 118 | `fleet` | Fleet | switch-case | derived_adjustments.speed_bonus | Changes movement speed derivation |
| 119 | `incredible-initiative` | Incredible Initiative | switch-case | derived_adjustments.initiative_bonus | Changes initiative bonus |
| 120 | `ride` | Ride | switch-case | addConditionalSaveModifier | Adds conditional saving throw modifier |
| 121 | `shield-block` | Shield Block | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 122 | `toughness` | Toughness | switch-case | derived_adjustments.hp_max_bonus | Changes max HP derivation |
| 123 | `weapon-proficiency` | Weapon Proficiency | switch-case | addProficiencyGrant | Adds proficiency training grant |
| 124 | `assurance` | Assurance | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 125 | `bargain-hunter` | Bargain Hunter | switch-case | addConditionalSkillModifier, available_actions.at_will | Adds conditional skill modifier; Adds feat action to at-will action list |
| 126 | `cat-fall` | Cat Fall | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 127 | `charming-liar` | Charming Liar | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 128 | `combat-climber` | Combat Climber | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 129 | `courtly-graces` | Courtly Graces | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 130 | `experienced-smuggler` | Experienced Smuggler | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 131 | `experienced-tracker` | Experienced Tracker | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 132 | `fascinating-performance` | Fascinating Performance | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 133 | `forager` | Forager | switch-case | addConditionalSkillModifier, available_actions.at_will | Adds conditional skill modifier; Adds feat action to at-will action list |
| 134 | `group-impression` | Group Impression | switch-case | addConditionalSkillModifier, available_actions.at_will | Adds conditional skill modifier; Adds feat action to at-will action list |
| 135 | `hefty-hauler` | Hefty Hauler | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 136 | `hobnobber` | Hobnobber | switch-case | addConditionalSkillModifier, available_actions.at_will | Adds conditional skill modifier; Adds feat action to at-will action list |
| 137 | `intimidating-glare` | Intimidating Glare | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 138 | `lengthy-diversion` | Lengthy Diversion | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 139 | `lie-to-me` | Lie to Me | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 140 | `multilingual` | Multilingual | switch-case | addSelectionGrant | Adds selection grant metadata |
| 141 | `natural-medicine` | Natural Medicine | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 142 | `oddity-identification` | Oddity Identification | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 143 | `pickpocket` | Pickpocket | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 144 | `quick-identification` | Quick Identification | switch-case | addConditionalSkillModifier, available_actions.at_will | Adds conditional skill modifier; Adds feat action to at-will action list |
| 145 | `quick-jump` | Quick Jump | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 146 | `rapid-mantel` | Rapid Mantel | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 147 | `read-lips` | Read Lips | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 148 | `recognize-spell` | Recognize Spell | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 149 | `sign-language` | Sign Language | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 150 | `snare-crafting` | Snare Crafting | switch-case | addConditionalSkillModifier, available_actions.at_will | Adds conditional skill modifier; Adds feat action to at-will action list |
| 151 | `specialty-crafting` | Specialty Crafting | switch-case | addSelectionGrant, addConditionalSkillModifier | Adds selection grant metadata; Adds conditional skill modifier |
| 152 | `steady-balance` | Steady Balance | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 153 | `streetwise` | Streetwise | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 154 | `student-of-the-canon` | Student of the Canon | switch-case | addConditionalSkillModifier, available_actions.at_will | Adds conditional skill modifier; Adds feat action to at-will action list |
| 155 | `subtle-theft` | Subtle Theft | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 156 | `survey-wildlife` | Survey Wildlife | switch-case | addConditionalSkillModifier, available_actions.at_will | Adds conditional skill modifier; Adds feat action to at-will action list |
| 157 | `terrain-expertise` | Terrain Expertise | switch-case | addSelectionGrant, addConditionalSkillModifier | Adds selection grant metadata; Adds conditional skill modifier |
| 158 | `titan-wrestler` | Titan Wrestler | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 159 | `train-animal` | Train Animal | switch-case | addConditionalSkillModifier, available_actions.at_will | Adds conditional skill modifier; Adds feat action to at-will action list |
| 160 | `trick-magic-item` | Trick Magic Item | switch-case | addSelectionGrant, addConditionalSkillModifier, available_actions.at_will | Adds selection grant metadata; Adds conditional skill modifier; Adds feat action to at-will action list |
| 161 | `underwater-marauder` | Underwater Marauder | switch-case | buildEffectState switch case | Custom first-pass feat effect mapping |
| 162 | `virtuosic-performer` | Virtuosic Performer | switch-case | addSelectionGrant, addConditionalSkillModifier, available_actions.at_will | Adds selection grant metadata; Adds conditional skill modifier; Adds feat action to at-will action list |

## Notes

- This report is generated from code shape (helpers called + buckets touched).
- During one-by-one deep refactor, each feat can be upgraded from first-pass mappings to fully rules-authoritative mechanics.
