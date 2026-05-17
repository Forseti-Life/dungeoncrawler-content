#!/usr/bin/env python3
"""
Extract PF2E spell books into a library-row intermediary format.

This parser reads raw PF2E spell-book text extraction and emits
JSON records shaped for eventual insertion into dungeoncrawler_content_registry.
It is intentionally non-destructive: the output is an intermediary artifact for
audit and cleanup before any live library import/backfill step.
"""

from __future__ import annotations

import argparse
import json
import re
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_SOURCE = (
    ROOT.parent
    / "forseti-docs/dungeoncrawler/reference documentation/PF2E Core Rulebook - Fourth Printing.txt"
)
DEFAULT_OUTPUT = ROOT / "content/intermediary/core_rulebook_spells_intermediary.json"
PARSER_VERSION = "core-raw-text-v3"
APG_SOURCE = (
    ROOT.parent
    / "forseti-docs/dungeoncrawler/reference documentation/PF2E Advanced Players Guide.txt"
)
APG_OUTPUT = ROOT / "content/intermediary/advanced_players_guide_spells_intermediary.json"
APG_PARSER_VERSION = "apg-raw-text-v1"
SOM_SOURCE = (
    ROOT.parent
    / "forseti-docs/dungeoncrawler/reference documentation/PF2E Secrets of Magic.txt"
)
SOM_OUTPUT = ROOT / "content/intermediary/secrets_of_magic_spells_intermediary.json"
SOM_PARSER_VERSION = "som-raw-text-v1"

SPELL_SCHOOLS = {
    "ABJURATION",
    "CONJURATION",
    "DIVINATION",
    "ENCHANTMENT",
    "EVOCATION",
    "ILLUSION",
    "NECROMANCY",
    "TRANSMUTATION",
}

RARITY_TRAITS = {"COMMON", "UNCOMMON", "RARE", "UNIQUE"}
SPELL_RANK_MARKERS = ("CANTRIP ", "SPELL ", "FOCUS ", "RITUAL ")
FIELD_NAMES = [
    "Traditions",
    "Cast",
    "Range",
    "Area",
    "Targets",
    "Duration",
    "Saving Throw",
    "Trigger",
    "Requirements",
    "Cost",
    "Primary Check",
    "Secondary Casters",
]
FIELD_START_RE = re.compile(r"^(%s)\b" % "|".join(re.escape(name) for name in FIELD_NAMES))
TRADITION_HEADER_RE = re.compile(
    r"^(Arcane|Divine|Occult|Primal)\s+(Cantrips|(\d+)(?:st|nd|rd|th)-Level Spells)$",
    re.I,
)
SPELL_LIST_ENTRY_RE = re.compile(r"^([^\(]+?)([HUR,\s]*)\s*\((abj|con|div|enc|evo|ill|nec|tra)\):\s*(.+)$")
SPELL_DETAIL_NAME_RE = re.compile(r"^[A-ZÀ-ÖØ-Þ][A-ZÀ-ÖØ-Þ0-9'’,\- ]{1,80}$")
RANK_RE = re.compile(r"^(CANTRIP \d+|SPELL \d+|FOCUS \d+|RITUAL \d+)$")
HEIGHTENED_RE = re.compile(r"^Heightened\s+\(([^)]+)\)\s*(.*)$")
OUTCOME_PREFIXES = ("Critical Success", "Success", "Failure", "Critical Failure")
CONDITION_NAMES = [
    "blinded",
    "broken",
    "clumsy",
    "concealed",
    "confused",
    "deafened",
    "doomed",
    "drained",
    "dying",
    "enfeebled",
    "fascinated",
    "fatigued",
    "flat-footed",
    "fleeing",
    "frightened",
    "grabbed",
    "hidden",
    "immobilized",
    "paralyzed",
    "persistent bleed",
    "petrified",
    "prone",
    "quickened",
    "restrained",
    "sickened",
    "slowed",
    "stunned",
    "stupefied",
    "unconscious",
    "undetected",
    "wounded",
]
PAGE_NOISE_LINES = {
    "SPELLS",
    "Core Rulebook",
    "Advanced Player's Guide",
    "Advanced Player’s Guide",
    "Introduction",
    "Ancestries &",
    "Backgrounds",
    "Classes",
    "Skills",
    "Feats",
    "Equipment",
    "Spells",
    "The Age of",
    "Lost OMENS",
    "Playing the",
    "Game",
    "mastering",
    "Crafting",
    "& Treasure",
    "Appendix",
    "Archetypes",
    "glossary",
    "& index",
    "& Index",
    "items",
    "Witch",
    "Sorcerer",
}
SECTION_BREAK_LINES = {
    "Spell Descriptions",
    "Focus Spells",
    "RITUALS",
}
ACTION_GLYPHS = {
    "\ue901": "[one-action]",
}
EXCLUDED_NAME_CANDIDATES = {
    "ACID",
    "AIR",
    "AUDITORY",
    "AURA",
    "BARD",
    "CHAMPION",
    "CHAOTIC",
    "CLERIC",
    "COLD",
    "COMPOSITION",
    "CURSE",
    "DEATH",
    "DETECTION",
    "DRUID",
    "EARTH",
    "ELECTRICITY",
    "ENCHANTMENT INCAPACITATION",
    "EMOTION",
    "EVIL",
    "FIRE",
    "FORTUNE",
    "FORCE",
    "GOOD",
    "HEALING",
    "INCAPACITATION",
    "LINGUISTIC",
    "MENTAL",
    "MONK",
    "MORPH",
    "OLFACTORY",
    "PLANT",
    "POLYMORPH",
    "POSITIVE",
    "PREDICTION",
    "RUNELORD RUNE SPELL",
    "SORCERER",
    "VISUAL",
    "WATER",
    "WIZARD",
}
CLASS_LABELS = {
    "ALCHEMIST",
    "BARD",
    "CHAMPION",
    "CLERIC",
    "DRUID",
    "MONK",
    "ORACLE",
    "RANGER",
    "SORCERER",
    "WITCH",
    "WIZARD",
}
AUDITED_SCALAR_FIELDS = (
    "range",
    "area",
    "targets",
    "duration",
    "save",
    "save_type",
    "trigger",
    "requirements",
    "cost",
    "primary_check",
    "secondary_casters",
)
FOCUS_CLASS_TRADITIONS = {
    "bard": ["occult"],
    "champion": ["divine"],
    "cleric": ["divine"],
    "druid": ["primal"],
    "oracle": ["divine"],
    "ranger": ["primal"],
    "wizard": ["arcane"],
}
SORCERER_FOCUS_TRADITIONS = {
    "aberrant_whispers": ["occult"],
    "ancestral_memories": ["arcane"],
    "angelic_halo": ["divine"],
    "angelic_wings": ["divine"],
    "arcane_countermeasure": ["arcane"],
    "abyssal_wrath": ["divine"],
    "celestial_brand": ["divine"],
    "diabolic_edict": ["divine"],
    "drain_life": ["divine"],
    "evil": ["divine"],
    "faerie_dust": ["primal"],
    "fey_disappearance": ["primal"],
    "fey_glamour": ["primal"],
    "grasping_grave": ["divine"],
    "hellfire_plume": ["divine"],
    "horrific_visage": ["occult"],
    "jealous_hex": ["occult"],
    "swamp_of_sloth": ["divine"],
}
SOURCE_BACKED_OVERRIDES: dict[str, dict[str, Any]] = {
    "disrupt_undead": {
        "school": "necromancy",
        "traditions": ["divine", "primal"],
        "traits": ["positive"],
        "source_line_start": 51123,
        "source_line_end": 51136,
        "raw_text_block": (
            "DISRUPT UNDEAD\n"
            "CANTRIP 1\n"
            "Traditions divine\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets up to two weapons, each of which must\n"
            "be wielded by you or a willing ally, or else unattended\n"
            "Duration 1 minute\n"
            "You infuse weapons with positive energy. Attacks with these\n"
            "weapons deal an extra 1d4 positive damage to undead.\n"
            "Heightened (3rd) The damage increases to 2d4 damage.\n"
            "Heightened (5th) Target up to three weapons, and the damage\n"
            "increases to 3d4 damage."
        ),
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "1 undead creature",
        "duration": "NA",
        "save": "Fortitude",
        "save_type": "fortitude",
        "description": (
            "You lance the target with energy. You deal 1d6 positive damage plus your "
            "spellcasting ability modifier. The target must attempt a basic Fortitude "
            "save. If the creature critically fails the save, it is also enfeebled 1 "
            "for 1 round."
        ),
        "description_snippet": "Damage undead with positive energy.",
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d6.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d6.",
            }
        ],
        "damage": [{"formula": "1d6", "type": "positive", "persistent": False}],
        "damage_type": ["positive"],
        "effects": {
            "description": (
                "You lance the target with energy. You deal 1d6 positive damage plus "
                "your spellcasting ability modifier. The target must attempt a basic "
                "Fortitude save. If the creature critically fails the save, it is also "
                "enfeebled 1 for 1 round."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["enfeebled"],
    },
    "fear": {
        "rank": 1,
        "spell_type": "spell",
        "school": "enchantment",
        "traditions": ["arcane", "divine", "occult", "primal"],
        "traits": ["emotion", "fear", "mental"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "1 creature",
        "duration": "varies",
        "save": "Will",
        "save_type": "will",
        "description": "You plant fear in the target; it must attempt a Will save.",
        "description_snippet": "Frighten a creature, possibly making it flee.",
        "heightened": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": "You can target up to five creatures.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": "You can target up to five creatures.",
            }
        ],
        "effects": {
            "description": "You plant fear in the target; it must attempt a Will save.",
            "outcomes": {
                "Critical Success": "The target is unaffected.",
                "Success": "The target is frightened 1.",
                "Failure": "The target is frightened 2.",
                "Critical Failure": "The target is frightened 3 and fleeing for 1 round.",
            },
        },
        "conditions_caused": ["fleeing", "frightened"],
        "source_line_start": 46653,
        "source_line_end": 46654,
        "raw_text_block": "Fear H (enc): Frighten a creature, possibly making it flee.",
    },
    "darkness": {
        "rank": 2,
        "spell_type": "spell",
        "school": "evocation",
        "traditions": ["arcane", "divine", "occult", "primal"],
        "cast": "[three-actions] material, somatic, verbal",
        "cast_actions": "3_actions",
        "components": ["material", "somatic", "verbal"],
        "range": "120 feet",
        "area": "20-foot burst",
        "targets": "NA",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You create a shroud of darkness that prevents light from penetrating or "
            "emanating within the area. Light does not enter the area and any "
            "non-magical light sources, such as a torch or lantern, do not emanate any "
            "light while inside the area, even if their light radius would extend "
            "beyond the darkness. This also suppresses magical light of your darkness "
            "spell's level or lower. Light can't pass through, so creatures in the area "
            "can't see outside. From outside, it appears as a globe of pure darkness."
        ),
        "description_snippet": "Suppress all light in an area.",
        "heightened": [
            {
                "label": "4th",
                "type": "fixed_rank",
                "rank": 4,
                "text": (
                    "Even creatures with darkvision (but not greater darkvision) can "
                    "barely see through the darkness. They treat targets seen through "
                    "the darkness as concealed."
                ),
            }
        ],
        "heightened_scaling": [
            {
                "label": "4th",
                "type": "fixed_rank",
                "rank": 4,
                "text": (
                    "Even creatures with darkvision (but not greater darkvision) can "
                    "barely see through the darkness. They treat targets seen through "
                    "the darkness as concealed."
                ),
            }
        ],
        "source_line_start": 50564,
        "source_line_end": 50579,
        "raw_text_block": (
            "DARKNESS\n"
            "SPELL 2\n"
            "EVOCATION\n"
            "Traditions arcane, divine, occult, primal\n"
            "Cast [three-actions] material, somatic, verbal\n"
            "Range 120 feet; Area 20-foot burst\n"
            "Duration 1 minute\n"
            "You create a shroud of darkness that prevents light from penetrating or "
            "emanating within the area. Light does not enter the area and any "
            "non-magical light sources, such as a torch or lantern, do not emanate any "
            "light while inside the area, even if their light radius would extend "
            "beyond the darkness. This also suppresses magical light of your darkness "
            "spell's level or lower. Light can't pass through, so creatures in the area "
            "can't see outside. From outside, it appears as a globe of pure darkness.\n"
            "Heightened (4th) Even creatures with darkvision (but not greater "
            "darkvision) can barely see through the darkness. They treat targets seen "
            "through the darkness as concealed."
        ),
    },
    "curse_of_lost_time": {
        "rank": 3,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["arcane", "occult", "primal"],
        "traits": ["curse", "negative"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 Large or smaller object, construct, or living creature",
        "duration": "varies",
        "save": "Fortitude",
        "save_type": "fortitude",
        "description": (
            "You mimic the process of aging or erosion on the target. The effect depends "
            "on whether the target is an object, a construct, or a living creature. "
            "Attended objects can be protected by their bearer's Fortitude save, while "
            "constructs and living creatures are debilitated when they fail."
        ),
        "description_snippet": "Artificially erode or age a target.",
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d6.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d6.",
            }
        ],
        "source_line_start": 30724,
        "source_line_end": 30748,
        "raw_text_block": (
            "CURSE OF LOST TIME\n"
            "CURSE\n"
            "NEGATIVE\n"
            "SPELL 3\n"
            "TRANSMUTATION\n"
            "Traditions arcane, occult, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets 1 Large or smaller object, construct, or living creature\n"
            "Saving Throw Fortitude; Duration varies\n"
            "You mimic the process of aging or erosion on the target. The effect depends "
            "on whether the target is an object, a construct, or a living creature. "
            "Artifacts and objects and constructs made of precious materials, as "
            "determined by the GM, are immune.\n"
            "Object If the object is attended, its bearer can attempt a Fortitude save. "
            "If the bearer fails or the object is unattended, the object immediately "
            "takes 4d6 damage (applying Hardness normally) and the item is cursed with "
            "an unlimited duration.\n"
            "Construct The construct takes 4d6 damage (basic Fortitude save). On a "
            "failure, for 1 hour the construct is clumsy 1, is enfeebled 1, and can't be "
            "Repaired.\n"
            "Living Creature The living creature must attempt a Fortitude save. Ageless "
            "creatures are immune. Success The living creature briefly ages, becoming "
            "clumsy 1 and enfeebled 1 for 1 round. Failure As success, with a duration "
            "of 1 hour. Critical Failure As success, with an unlimited duration.\n"
            "Heightened (+1) The damage increases by 1d6."
        ),
    },
    "disrupting_weapons": {
        "rank": 1,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine"],
        "traits": ["positive"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": (
            "up to two weapons, each of which must be wielded by you or a willing ally, "
            "or else unattended"
        ),
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You infuse weapons with positive energy. Attacks with these weapons deal an "
            "extra 1d4 positive damage to undead."
        ),
        "description_snippet": "Weapons deal extra positive damage to undead.",
        "heightened": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": "The damage increases to 2d4 damage.",
            },
            {
                "label": "5th",
                "type": "fixed_rank",
                "rank": 5,
                "text": (
                    "Target up to three weapons, and the damage increases to 3d4 damage."
                ),
            },
        ],
        "heightened_scaling": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": "The damage increases to 2d4 damage.",
            },
            {
                "label": "5th",
                "type": "fixed_rank",
                "rank": 5,
                "text": (
                    "Target up to three weapons, and the damage increases to 3d4 damage."
                ),
            },
        ],
        "source_line_start": 51125,
        "source_line_end": 51136,
        "raw_text_block": (
            "CANTRIP 1\n"
            "Traditions divine\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets up to two weapons, each of which must be wielded by "
            "you or a willing ally, or else unattended\n"
            "Duration 1 minute\n"
            "You infuse weapons with positive energy. Attacks with these weapons deal an "
            "extra 1d4 positive damage to undead.\n"
            "Heightened (3rd) The damage increases to 2d4 damage.\n"
            "Heightened (5th) Target up to three weapons, and the damage increases to "
            "3d4 damage."
        ),
    },
    "gluttons_jaws": {
        "rank": 1,
        "spell_type": "focus",
        "school": "necromancy",
        "traditions": ["none"],
        "traits": ["morph"],
        "focus_class": "sorcerer",
        "cast": "[one-action] somatic, verbal",
        "cast_actions": "1_action",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "NA",
        "targets": "NA",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "Your mouth transforms into a shadowy maw bristling with pointed teeth. "
            "These jaws are an unarmed attack with the forceful trait dealing 1d8 "
            "piercing damage. If you hit with your jaws and deal damage, you gain 1d6 "
            "temporary Hit Points."
        ),
        "description_snippet": "Transform your mouth into a shadowy maw that grants temporary Hit Points.",
        "heightened": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The temporary Hit Points increase by 1d6.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The temporary Hit Points increase by 1d6.",
            }
        ],
        "source_line_start": 64275,
        "source_line_end": 64314,
        "raw_text_block": (
            "GLUTTON'S JAWS\n"
            "UNCOMMON\n"
            "MORPH\n"
            "FOCUS 1\n"
            "NECROMANCY\n"
            "SORCERER\n"
            "Cast [one-action] somatic, verbal\n"
            "Duration 1 minute\n"
            "Your mouth transforms into a shadowy maw bristling with pointed teeth. "
            "These jaws are an unarmed attack with the forceful trait dealing 1d8 "
            "piercing damage. If you hit with your jaws and deal damage, you gain 1d6 "
            "temporary Hit Points.\n"
            "Heightened (+2) The temporary Hit Points increase by 1d6."
        ),
    },
    "physical_boost": {
        "rank": 1,
        "spell_type": "focus",
        "school": "transmutation",
        "traditions": ["arcane"],
        "traits": ["none"],
        "focus_class": "wizard",
        "cast": "[one-action] verbal",
        "cast_actions": "1_action",
        "components": ["verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 living creature",
        "duration": "until the end of the target's next turn",
        "save": "NA",
        "save_type": "NA",
        "description": "The target gains a +2 status bonus to one physical ability check of your choice for 1 round.",
        "description_snippet": "Target gains a +2 status bonus to one physical ability check for 1 round.",
        "source_line_start": 64872,
        "source_line_end": 64881,
        "raw_text_block": (
            "PHYSICAL BOOST\n"
            "UNCOMMON\n"
            "FOCUS 1\n"
            "TRANSMUTATION WIZARD\n"
            "Cast [one-action] verbal\n"
            "Range touch; Targets 1 living creature\n"
            "Duration until the end of the target's next turn"
        ),
    },
    "shield_other": {
        "rank": 2,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "1 creature",
        "duration": "10 minutes",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You forge a temporary link between the target's life essence and your own. "
            "The target takes half damage from all effects that deal Hit Point damage, "
            "and you take the remainder of the damage. When you take damage through "
            "this link, you don't apply any resistances, weaknesses, or other abilities "
            "you have to that damage; you simply take that amount of damage. The spell "
            "ends if the target is ever more than 30 feet away from you. If either you "
            "or the target is reduced to 0 Hit Points, any damage from this spell is "
            "resolved and then the spell ends."
        ),
        "description_snippet": "Absorb half the damage an ally would take.",
        "source_line_start": 57368,
        "source_line_end": 57413,
        "raw_text_block": (
            "SHIELD OTHER\n"
            "SPELL 2\n"
            "NECROMANCY\n"
            "Traditions divine\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 30 feet; Targets 1 creature\n"
            "Duration 10 minutes\n"
            "You forge a temporary link between the target's life essence and your own. "
            "The target takes half damage from all effects that deal Hit Point damage, "
            "and you take the remainder of the damage. When you take damage through "
            "this link, you don't apply any resistances, weaknesses, or other abilities "
            "you have to that damage; you simply take that amount of damage. The spell "
            "ends if the target is ever more than 30 feet away from you. If either you "
            "or the target is reduced to 0 Hit Points, any damage from this spell is "
            "resolved and then the spell ends."
        ),
    },
    "ki_blast": {
        "rank": 3,
        "spell_type": "focus",
        "school": "evocation",
        "traditions": ["divine", "occult"],
        "traits": ["force"],
        "focus_class": "monk",
        "cast": "[one-action] to [three-actions] somatic, verbal",
        "cast_actions": "1_to_3_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "15-foot cone or more",
        "targets": "NA",
        "duration": "NA",
        "save": "Fortitude",
        "save_type": "fortitude",
        "description": (
            "You unleash your ki as a powerful blast of force that deals 2d6 force "
            "damage. If you use 2 actions to cast ki blast, increase the size of the "
            "cone to 30 feet and the damage to 3d6. If you use 3 actions to cast ki "
            "blast, increase the size of the cone to 60 feet and the damage to 4d6. "
            "Each creature in the area must attempt a Fortitude saving throw."
        ),
        "description_snippet": "Force cone that grows with additional actions.",
        "effects": {
            "description": (
                "You unleash your ki as a powerful blast of force that deals 2d6 force "
                "damage. If you use 2 actions to cast ki blast, increase the size of "
                "the cone to 30 feet and the damage to 3d6. If you use 3 actions to "
                "cast ki blast, increase the size of the cone to 60 feet and the "
                "damage to 4d6. Each creature in the area must attempt a Fortitude "
                "saving throw."
            ),
            "outcomes": {
                "Critical Success": "The creature is unaffected.",
                "Success": "The creature takes half damage.",
                "Failure": "The creature takes full damage and is pushed 5 feet.",
                "Critical Failure": "The creature takes double damage and is pushed 10 feet.",
            },
        },
        "damage": [{"formula": "2d6", "type": "force", "persistent": False}],
        "damage_type": ["force"],
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d6, or by 2d6 if you use 2 or 3 actions.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d6, or by 2d6 if you use 2 or 3 actions.",
            }
        ],
        "source_line_start": 63155,
        "source_line_end": 63664,
        "raw_text_block": (
            "KI BLAST\n"
            "UNCOMMON\n"
            "FOCUS 3\n"
            "EVOCATION\n"
            "FORCE\n"
            "MONK\n"
            "Cast [one-action] to [three-actions] somatic, verbal\n"
            "Area 15-foot cone or more\n"
            "Saving Throw Fortitude\n"
            "You unleash your ki as a powerful blast of force that deals 2d6 force "
            "damage. If you use 2 actions to cast ki blast, increase the size of the "
            "cone to 30 feet and the damage to 3d6. If you use 3 actions to cast ki "
            "blast, increase the size of the cone to 60 feet and the damage to 4d6. "
            "Each creature in the area must attempt a Fortitude saving throw.\n"
            "Critical Success The creature is unaffected.\n"
            "Success The creature takes half damage.\n"
            "Failure The creature takes full damage and is pushed 5 feet.\n"
            "Critical Failure The creature takes double damage and is pushed 10 feet.\n"
            "Heightened (+1) The damage increases by 1d6, or by 2d6 if you use 2 or 3 actions."
        ),
    },
    "read_omens": {
        "rank": 4,
        "spell_type": "spell",
        "rarity": "uncommon",
        "school": "divination",
        "traditions": ["divine", "occult"],
        "traits": ["prediction"],
        "description": "Get a piece of advice about an upcoming event.",
        "description_snippet": "Get a piece of advice about an upcoming event.",
        "source_line_start": 47465,
        "source_line_end": 47466,
        "raw_text_block": "Read Omens U (div): Get a piece of advice about an upcoming event.",
    },
    "feeblemind": {
        "rank": 6,
        "spell_type": "spell",
        "school": "enchantment",
        "traditions": ["arcane", "occult"],
        "traits": ["curse", "incapacitation", "mental"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "1 creature",
        "duration": "varies",
        "save": "Will",
        "save_type": "will",
        "description": (
            "You drastically reduce the target's mental faculties. The target must attempt "
            "a Will save, and the effects of this curse can be removed only through remove "
            "curse or another effect that targets curses."
        ),
        "description_snippet": "Reduce a creature's mental faculties with a lasting curse.",
        "effects": {
            "description": (
                "You drastically reduce the target's mental faculties. The target must "
                "attempt a Will save, and the effects of this curse can be removed only "
                "through remove curse or another effect that targets curses."
            ),
            "outcomes": {
                "critical_success": "The target is unaffected.",
                "success": "The target is stupefied 2 for 1 round.",
                "failure": "The target is stupefied 4 with an unlimited duration.",
                "critical_failure": (
                    "The target's intellect is permanently reduced below that of an animal, "
                    "and it treats its Charisma, Intelligence, and Wisdom modifiers as -5. "
                    "It loses all class abilities that require mental faculties, including "
                    "all spellcasting. If the target is a PC, they become an NPC under the "
                    "GM's control."
                ),
            },
        },
        "conditions_caused": ["stupefied"],
        "source_line_start": 52868,
        "source_line_end": 52886,
        "raw_text_block": (
            "FEEBLEMIND\n"
            "CURSE\n"
            "ENCHANTMENT\n"
            "INCAPACITATION\n"
            "MENTAL\n"
            "SPELL 6\n"
            "Traditions arcane, occult\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 30 feet; Targets 1 creature\n"
            "Saving Throw Will; Duration varies\n"
            "You drastically reduce the target's mental faculties. The target must attempt "
            "a Will save. The effects of this curse can be removed only through remove "
            "curse or another effect that targets curses.\n"
            "Critical Success The target is unaffected.\n"
            "Success The target is stupefied 2 for 1 round.\n"
            "Failure The target is stupefied 4 with an unlimited duration.\n"
            "Critical Failure The target's intellect is permanently reduced below that of "
            "an animal, and it treats its Charisma, Intelligence, and Wisdom modifiers as "
            "-5. It loses all class abilities that require mental faculties, including all "
            "spellcasting. If the target is a PC, they become an NPC under the GM's control."
        ),
    },
    "fire_seeds": {
        "rank": 6,
        "spell_type": "spell",
        "school": "evocation",
        "traditions": ["primal"],
        "traits": ["fire", "plant"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "NA",
        "targets": "NA",
        "duration": "1 minute",
        "save": "basic Reflex",
        "save_type": "basic_reflex",
        "description": (
            "Four acorns grow in your hand, their shells streaked with pulsing red and "
            "orange patterns. You or anyone else who has one of the acorns can toss it "
            "up to 30 feet with an Interact action. It explodes in a 5-foot burst, "
            "dealing 4d6 fire damage. The save uses your spell DC, even if someone else "
            "throws the acorn. Flames continue to burn on the ground in the burst for 1 "
            "minute, dealing 2d6 fire damage to any creature that enters the flames or "
            "ends its turn within them. A creature can take damage from the continuing "
            "flames only once per round, even if it's in overlapping areas of fire "
            "created by different acorns. When the spell ends, any remaining acorns rot "
            "and turn to ordinary soil."
        ),
        "description_snippet": "Make four explosive acorns.",
        "effects": {
            "description": (
                "Four acorns grow in your hand, their shells streaked with pulsing red "
                "and orange patterns. You or anyone else who has one of the acorns can "
                "toss it up to 30 feet with an Interact action. It explodes in a "
                "5-foot burst, dealing 4d6 fire damage. The save uses your spell DC, "
                "even if someone else throws the acorn. Flames continue to burn on the "
                "ground in the burst for 1 minute, dealing 2d6 fire damage to any "
                "creature that enters the flames or ends its turn within them. A "
                "creature can take damage from the continuing flames only once per "
                "round, even if it's in overlapping areas of fire created by different "
                "acorns. When the spell ends, any remaining acorns rot and turn to "
                "ordinary soil."
            ),
            "outcomes": {},
        },
        "damage": [{"formula": "4d6", "type": "fire", "persistent": False}],
        "damage_type": ["fire"],
        "heightened": [
            {
                "label": "8th",
                "type": "fixed_rank",
                "rank": 8,
                "text": "The burst's damage increases to 5d6, and the continuing flames damage increases to 3d6.",
            },
            {
                "label": "9th",
                "type": "fixed_rank",
                "rank": 9,
                "text": "The burst's damage increases to 6d6, and the continuing flames damage increases to 3d6.",
            },
        ],
        "heightened_scaling": [
            {
                "label": "8th",
                "type": "fixed_rank",
                "rank": 8,
                "text": "The burst's damage increases to 5d6, and the continuing flames damage increases to 3d6.",
            },
            {
                "label": "9th",
                "type": "fixed_rank",
                "rank": 9,
                "text": "The burst's damage increases to 6d6, and the continuing flames damage increases to 3d6.",
            },
        ],
        "source_line_start": 52351,
        "source_line_end": 52388,
        "raw_text_block": (
            "FIRE SEEDS\n"
            "SPELL 6\n"
            "EVOCATION\n"
            "FIRE\n"
            "PLANT\n"
            "Traditions primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Saving Throw basic Reflex; Duration 1 minute\n"
            "Four acorns grow in your hand, their shells streaked with pulsing red and "
            "orange patterns. You or anyone else who has one of the acorns can toss it "
            "up to 30 feet with an Interact action. It explodes in a 5-foot burst, "
            "dealing 4d6 fire damage. The save uses your spell DC, even if someone else "
            "throws the acorn. Flames continue to burn on the ground in the burst for 1 "
            "minute, dealing 2d6 fire damage to any creature that enters the flames or "
            "ends its turn within them. A creature can take damage from the continuing "
            "flames only once per round, even if it's in overlapping areas of fire "
            "created by different acorns. When the spell ends, any remaining acorns rot "
            "and turn to ordinary soil.\n"
            "Heightened (8th) The burst's damage increases to 5d6, and the continuing "
            "flames damage increases to 3d6.\n"
            "Heightened (9th) The burst's damage increases to 6d6, and the continuing "
            "flames damage increases to 3d6."
        ),
    },
    "alarm": {
        "rank": 1,
        "spell_type": "spell",
        "school": "abjuration",
        "traditions": ["arcane", "divine", "occult", "primal"],
        "traits": ["none"],
        "cast": "10 minutes (material, somatic, verbal)",
        "cast_actions": "10_minutes",
        "components": ["material", "somatic", "verbal"],
        "requirements": "3 gp silver bell focus",
        "range": "touch",
        "area": "20-foot burst",
        "targets": "NA",
        "duration": "8 hours",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You ward an area to alert you when creatures enter without your permission. "
            "When you cast alarm, select a password. Whenever a Small or larger corporeal "
            "creature enters the spell's area without speaking the password, alarm sends "
            "your choice of a mental alert or an audible alarm with the sound and volume "
            "of a hand bell."
        ),
        "description_snippet": "Be alerted if a creature enters a warded area.",
        "heightened": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": (
                    "You can specify criteria for which creatures sound the alarm spell."
                ),
            }
        ],
        "heightened_scaling": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": (
                    "You can specify criteria for which creatures sound the alarm spell."
                ),
            }
        ],
        "source_line_start": 48908,
        "source_line_end": 48931,
        "raw_text_block": (
            "SPELL 1\n"
            "ABJURATION\n"
            "Traditions arcane, divine, occult, primal\n"
            "Cast 10 minutes (material, somatic, verbal); Requirements\n"
            "3 gp silver bell focus\n"
            "Range touch; Area 20-foot burst\n"
            "Duration 8 hours\n"
            "You ward an area to alert you when creatures enter without your permission. "
            "When you cast alarm, select a password. Whenever a Small or larger corporeal "
            "creature enters the spell's area without speaking the password, alarm sends "
            "your choice of a mental alert (in which case the spell gains the mental "
            "trait) or an audible alarm with the sound and volume of a hand bell (in "
            "which case the spell gains the auditory trait). Either option automatically "
            "awakens you, and the bell allows each creature in the area to attempt a DC "
            "15 Perception check to wake up. A creature aware of the alarm must succeed "
            "at a Stealth check against the spell's DC or trigger the spell when moving "
            "into the area.\n"
            "Heightened (3rd) You can specify criteria for which creatures sound the alarm "
            "spell—for instance, orcs or masked people."
        ),
    },
    "animal_form": {
        "rank": 2,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["primal"],
        "traits": ["polymorph"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "NA",
        "targets": "self",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You call upon primal energy to transform yourself into a Medium animal "
            "battle form. When you first cast this spell, choose ape, bear, bull, "
            "canine, cat, deer, frog, shark, or snake. While in this form, you gain "
            "the animal trait and use the battle form's listed statistics and attacks."
        ),
        "description_snippet": "Turn into a Medium animal battle form.",
        "heightened": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": (
                    "You instead gain 10 temporary HP, AC = 17 + your level, attack "
                    "modifier +14, damage bonus +5, and Athletics +14."
                ),
            },
            {
                "label": "4th",
                "type": "fixed_rank",
                "rank": 4,
                "text": (
                    "Your battle form is Large and your attacks have 10-foot reach. You "
                    "instead gain 15 temporary HP, AC = 18 + your level, attack modifier "
                    "+16, damage bonus +9, and Athletics +16."
                ),
            },
            {
                "label": "5th",
                "type": "fixed_rank",
                "rank": 5,
                "text": (
                    "Your battle form is Huge and your attacks have 15-foot reach. You "
                    "instead gain 20 temporary HP, AC = 18 + your level, attack modifier "
                    "+18, damage bonus +7 and double the number of damage dice, and "
                    "Athletics +20."
                ),
            },
        ],
        "heightened_scaling": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": (
                    "You instead gain 10 temporary HP, AC = 17 + your level, attack "
                    "modifier +14, damage bonus +5, and Athletics +14."
                ),
            },
            {
                "label": "4th",
                "type": "fixed_rank",
                "rank": 4,
                "text": (
                    "Your battle form is Large and your attacks have 10-foot reach. You "
                    "instead gain 15 temporary HP, AC = 18 + your level, attack modifier "
                    "+16, damage bonus +9, and Athletics +16."
                ),
            },
            {
                "label": "5th",
                "type": "fixed_rank",
                "rank": 5,
                "text": (
                    "Your battle form is Huge and your attacks have 15-foot reach. You "
                    "instead gain 20 temporary HP, AC = 18 + your level, attack modifier "
                    "+18, damage bonus +7 and double the number of damage dice, and "
                    "Athletics +20."
                ),
            },
        ],
        "source_line_start": 49601,
        "source_line_end": 49695,
        "raw_text_block": (
            "SPELL 2\n"
            "TRANSMUTATION\n"
            "Traditions primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Duration 1 minute\n"
            "You call upon primal energy to transform yourself into a Medium animal "
            "battle form. When you first cast this spell, choose ape, bear, bull, "
            "canine, cat, deer, frog, shark, or snake. You can decide the specific type "
            "of animal, but this has no effect on the form's Size or statistics. While "
            "in this form, you gain the animal trait. You can Dismiss the spell.\n"
            "You gain the following statistics and abilities regardless of which battle "
            "form you choose: AC = 16 + your level; 5 temporary Hit Points; low-light "
            "vision and imprecise scent 30 feet; one or more unarmed melee attacks "
            "specific to the battle form you choose; and Athletics modifier +9 unless "
            "your own modifier is higher.\n"
            "You also gain specific abilities based on the type of animal you choose, "
            "including ape, bear, bull, canine, cat, deer, frog, shark, and snake "
            "movement and attack profiles.\n"
            "Heightened (3rd) You instead gain 10 temporary HP, AC = 17 + your level, "
            "attack modifier +14, damage bonus +5, and Athletics +14.\n"
            "Heightened (4th) Your battle form is Large and your attacks have 10-foot "
            "reach. You instead gain 15 temporary HP, AC = 18 + your level, attack "
            "modifier +16, damage bonus +9, and Athletics +16.\n"
            "Heightened (5th) Your battle form is Huge and your attacks have 15-foot "
            "reach. You instead gain 20 temporary HP, AC = 18 + your level, attack "
            "modifier +18, damage bonus +7 and double the number of damage dice, and "
            "Athletics +20."
        ),
    },
    "aerial_form": {
        "rank": 4,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["arcane", "primal"],
        "traits": ["polymorph"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "NA",
        "targets": "self",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You reshape your body into a Medium flying animal battle form such as a bat, "
            "bird, pterosaur, or wasp. The form grants flight, aerial attacks, and "
            "heightened battle-form improvements at higher ranks."
        ),
        "description_snippet": "Turn into a flying combatant.",
        "heightened": [
            {
                "label": "5th",
                "type": "fixed_rank",
                "rank": 5,
                "text": (
                    "Your battle form is Large and your fly Speed gains a +10-foot status "
                    "bonus. You instead gain 10 temporary HP, attack modifier +18, damage "
                    "bonus +8, and Acrobatics +20."
                ),
            },
            {
                "label": "6th",
                "type": "fixed_rank",
                "rank": 6,
                "text": (
                    "Your battle form is Huge, your fly Speed gains a +15-foot status "
                    "bonus, and your attacks have 10-foot reach. You instead gain AC = 21 "
                    "+ your level, 15 temporary HP, attack modifier +21, damage bonus +4 "
                    "and double damage dice, and Acrobatics +23."
                ),
            },
        ],
        "heightened_scaling": [
            {
                "label": "5th",
                "type": "fixed_rank",
                "rank": 5,
                "text": (
                    "Your battle form is Large and your fly Speed gains a +10-foot status "
                    "bonus. You instead gain 10 temporary HP, attack modifier +18, damage "
                    "bonus +8, and Acrobatics +20."
                ),
            },
            {
                "label": "6th",
                "type": "fixed_rank",
                "rank": 6,
                "text": (
                    "Your battle form is Huge, your fly Speed gains a +15-foot status "
                    "bonus, and your attacks have 10-foot reach. You instead gain AC = 21 "
                    "+ your level, 15 temporary HP, attack modifier +21, damage bonus +4 "
                    "and double damage dice, and Acrobatics +23."
                ),
            },
        ],
        "source_line_start": 49425,
        "source_line_end": 49474,
        "raw_text_block": (
            "AERIAL FORM\n"
            "SPELL 4\n"
            "TRANSMUTATION\n"
            "Traditions arcane, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Duration 1 minute\n"
            "You harness your mastery of primal forces to reshape your body into a Medium "
            "flying animal battle form. When you cast this spell, choose bat, bird, "
            "pterosaur, or wasp. While in this form, you gain the animal trait.\n"
            "You gain the following statistics and abilities regardless of which battle "
            "form you choose: AC = 18 + your level, 5 temporary Hit Points, low-light "
            "vision, aerial unarmed attacks, and Acrobatics +16 unless your own modifier "
            "is higher.\n"
            "Bat Speed 20 feet, fly Speed 30 feet; Bird Speed 10 feet, fly Speed 50 feet; "
            "Pterosaur Speed 10 feet, fly Speed 40 feet; Wasp Speed 20 feet, fly Speed 40 "
            "feet.\n"
            "Heightened (5th) Your battle form is Large and your fly Speed gains a "
            "+10-foot status bonus.\n"
            "Heightened (6th) Your battle form is Huge, your fly Speed gains a +15-foot "
            "status bonus, and your attacks have 10-foot reach."
        ),
    },
    "air_walk": {
        "rank": 4,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["divine", "primal"],
        "traits": ["air"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 creature",
        "duration": "5 minutes",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "The target can walk on air as if it were solid ground, ascending and "
            "descending at a limited angle."
        ),
        "description_snippet": "Walk on air as though it were solid ground.",
        "source_line_start": 49512,
        "source_line_end": 49518,
        "raw_text_block": (
            "AIR WALK\n"
            "SPELL 4\n"
            "TRANSMUTATION\n"
            "Traditions divine, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets 1 creature\n"
            "Duration 5 minutes\n"
            "The target can walk on air as if it were solid ground. It can ascend and "
            "descend in this way at a maximum of a 45-degree angle."
        ),
    },
    "anathematic_reprisal": {
        "rank": 4,
        "spell_type": "spell",
        "school": "enchantment",
        "traditions": ["divine"],
        "traits": ["mental"],
        "cast": "[reaction] somatic, verbal",
        "cast_actions": "reaction",
        "components": ["somatic", "verbal"],
        "trigger": "A creature performs an act anathema to your deity.",
        "range": "30 feet",
        "area": "NA",
        "targets": "the triggering creature",
        "duration": "1 round",
        "save": "basic Will",
        "save_type": "basic_will",
        "description": (
            "You punish a creature that openly transgresses against your deity, dealing "
            "mental damage and possibly stupefying it."
        ),
        "description_snippet": "Cause mental pain to one who commits anathema against your deity.",
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d6.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d6.",
            }
        ],
        "source_line_start": 49519,
        "source_line_end": 49530,
        "raw_text_block": (
            "ANATHEMATIC REPRISAL\n"
            "SPELL 4\n"
            "ENCHANTMENT\n"
            "MENTAL\n"
            "Traditions divine\n"
            "Cast [reaction] somatic, verbal; Trigger A creature performs an act "
            "anathema to your deity.\n"
            "Range 30 feet; Targets the triggering creature\n"
            "Saving Throw basic Will\n"
            "You punish a creature that transgresses against your deity, drawing upon "
            "the anguish you feel upon seeing one of your deity's anathema committed. "
            "You deal 4d6 mental damage to the target, and on a failed save it is also "
            "stupefied 1 for 1 round. The creature is then temporarily immune for 1 minute.\n"
            "Heightened (+1) The damage increases by 1d6."
        ),
    },
    "fungal_infestation": {
        "rank": 4,
        "spell_type": "spell",
        "school": "conjuration",
        "traditions": ["primal"],
        "traits": ["poison"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "15-foot cone",
        "targets": "NA",
        "duration": "NA",
        "save": "Fortitude",
        "save_type": "fortitude",
        "description": (
            "Toxic spores swarm over creatures, causing grotesque fungal growths that "
            "deal persistent poison damage and make the victims more vulnerable to fire "
            "and slashing."
        ),
        "description_snippet": "Plant poisonous fungal growths in a creature.",
        "effects": {
            "description": (
                "Toxic spores swarm over creatures in the area, causing them to erupt in "
                "grotesque fungal growths. These growths deal 2d6 persistent poison "
                "damage, and each creature must attempt a Fortitude save."
            ),
            "outcomes": {
                "critical_success": "The creature is unaffected.",
                "success": "The target takes half the persistent poison damage.",
                "failure": "The target takes the full persistent poison damage and has weakness 1 to fire and slashing while taking it.",
                "critical_failure": "As failure, but double the persistent poison damage and weakness 2 to fire and slashing.",
            },
        },
        "heightened": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The persistent damage increases by 2d6, and the weakness increases by 1, or by 2 on a critical failure.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The persistent damage increases by 2d6, and the weakness increases by 1, or by 2 on a critical failure.",
            }
        ],
        "source_line_start": 31013,
        "source_line_end": 31032,
        "raw_text_block": (
            "FUNGAL INFESTATION\n"
            "SPELL 4\n"
            "CONJURATION\n"
            "POISON\n"
            "Traditions primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Area 15-foot cone\n"
            "Saving Throw Fortitude\n"
            "Toxic spores swarm over creatures in the area, causing them to erupt in "
            "grotesque fungal growths. These noxious growths deal 2d6 persistent poison "
            "damage, and each creature must attempt a Fortitude save.\n"
            "Heightened (+2) The persistent damage increases by 2d6, and the weakness "
            "increases by 1, or by 2 on a critical failure."
        ),
    },
    "ghostly_tragedy": {
        "rank": 4,
        "spell_type": "spell",
        "rarity": "uncommon",
        "school": "divination",
        "traditions": ["divine", "occult"],
        "traits": ["none"],
        "cast": "1 minute (material, somatic, verbal)",
        "cast_actions": "1_minute",
        "components": ["material", "somatic", "verbal"],
        "range": "NA",
        "area": "60-foot emanation",
        "targets": "NA",
        "duration": "10 minutes",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "This spell compels local spirits to reenact a violent event from the recent "
            "past, letting observers investigate the scene for clues at the cost of "
            "negative backlash."
        ),
        "description_snippet": "Have spirits reenact a violent event.",
        "source_line_start": 31053,
        "source_line_end": 31071,
        "raw_text_block": (
            "GHOSTLY TRAGEDY\n"
            "UNCOMMON\n"
            "SPELL 4\n"
            "DIVINATION\n"
            "Traditions divine, occult\n"
            "Cast 1 minute (material, somatic, verbal)\n"
            "Area 60-foot emanation\n"
            "Duration 10 minutes\n"
            "This spell compels local spirits to reenact a violent event of the recent "
            "past that you're aware of and name as you Cast the Spell. You take the role "
            "of the primary victim. The reenactment plays out the final 9 minutes leading "
            "up to the death or injury of the victim and the minute after.\n"
            "Once the scene ends, you take 2d6 negative damage for each ghostly "
            "apparition that participated in the scene. Any creature that observed the "
            "ghostly recreation can attempt checks to investigate the event."
        ),
    },
    "holy_cascade": {
        "rank": 4,
        "spell_type": "spell",
        "school": "evocation",
        "traditions": ["divine"],
        "traits": ["attack", "positive", "water"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "cost": "one vial of holy water",
        "range": "500 feet",
        "area": "20-foot burst",
        "targets": "NA",
        "duration": "NA",
        "save": "basic Reflex",
        "save_type": "basic_reflex",
        "description": (
            "You amplify a vial of holy water into an enormous sacred blast that batters "
            "creatures with water and deals extra positive or good damage to the unholy."
        ),
        "description_snippet": "Turn a vial of holy water into an explosion of blessed water.",
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The bludgeoning damage increases by 1d6, and the additional positive and good damage each increase by 2d6.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The bludgeoning damage increases by 1d6, and the additional positive and good damage each increase by 2d6.",
            }
        ],
        "source_line_start": 53986,
        "source_line_end": 54013,
        "raw_text_block": (
            "HOLY CASCADE\n"
            "SPELL 4\n"
            "ATTACK\n"
            "EVOCATION\n"
            "GOOD\n"
            "POSITIVE\n"
            "WATER\n"
            "Traditions divine\n"
            "Cast [two-actions] somatic, verbal; Cost one vial of holy water\n"
            "Range 500 feet; Area 20-foot burst\n"
            "Saving Throw basic Reflex\n"
            "You call upon sacred energy to amplify a vial of holy water, tossing it an "
            "incredible distance. It explodes in an enormous burst that deals 3d6 "
            "bludgeoning damage from the cascade of water. The water deals an additional "
            "6d6 positive damage to undead and 6d6 good damage to fiends.\n"
            "Heightened (+1) The bludgeoning damage increases by 1d6, and the additional "
            "positive and good damage each increase by 2d6."
        ),
    },
    "ice_storm": {
        "rank": 4,
        "spell_type": "spell",
        "school": "evocation",
        "traditions": ["arcane", "primal"],
        "traits": ["cold"],
        "cast": "[three-actions] material, somatic, verbal",
        "cast_actions": "3_actions",
        "components": ["material", "somatic", "verbal"],
        "range": "120 feet",
        "area": "5-foot burst",
        "targets": "NA",
        "duration": "1 minute",
        "save": "basic Reflex",
        "save_type": "basic_reflex",
        "description": (
            "You create a gray storm cloud that pelts creatures with magical hail and "
            "continues to fill the area with snow, sleet, concealment, and cold."
        ),
        "description_snippet": "Call a storm cloud that pelts creatures with an icy deluge.",
        "heightened": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The initial bludgeoning and cold damage each increase by 1d8, and the end-of-turn cold damage increases by 2.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The initial bludgeoning and cold damage each increase by 1d8, and the end-of-turn cold damage increases by 2.",
            }
        ],
        "source_line_start": 31361,
        "source_line_end": 31401,
        "raw_text_block": (
            "ICE STORM\n"
            "SPELL 4\n"
            "EVOCATION\n"
            "COLD\n"
            "Traditions arcane, primal\n"
            "Cast [three-actions] material, somatic, verbal\n"
            "Range 120 feet; Area 5-foot burst\n"
            "Saving Throw basic Reflex; Duration 1 minute\n"
            "You create a gray storm cloud that pelts creatures with an icy deluge. "
            "When you Cast the Spell, a burst of magical hail deals 2d8 bludgeoning "
            "damage and 2d8 cold damage to each creature in the area below the cloud. "
            "Snow and sleet continue to rain down in the area for the remainder of the "
            "spell's duration, making the area difficult terrain and causing creatures "
            "in the storm to be concealed. Any creature that ends its turn in the storm "
            "takes 4 cold damage. If you Cast this Spell outdoors, you can create two "
            "non-overlapping clouds instead of one.\n"
            "Heightened (+2) The initial bludgeoning damage and cold damage increase by "
            "1d8 each, and the cold damage creatures take at the end of their turns "
            "increases by 2."
        ),
    },
    "ocular_overload": {
        "rank": 4,
        "spell_type": "spell",
        "school": "illusion",
        "traditions": ["arcane", "occult", "primal"],
        "traits": ["contingency", "incapacitation", "visual"],
        "cast": "10 minutes (material, somatic, verbal)",
        "cast_actions": "10_minutes",
        "components": ["material", "somatic", "verbal"],
        "range": "60 feet",
        "area": "NA",
        "targets": "1 creature",
        "duration": "24 hours",
        "save": "Fortitude",
        "save_type": "fortitude",
        "description": (
            "You prepare a visual contingency that lashes out when a creature attacks you, "
            "dazzling or blinding the attacker with jarring illusions."
        ),
        "description_snippet": "Set a contingency to interfere with the vision of a creature attacking you.",
        "effects": {
            "description": (
                "When the spell is complete, you gain the Overload Vision reaction. Once "
                "you use the reaction, the spell ends."
            ),
            "outcomes": {
                "critical_success": "The target is unaffected.",
                "success": "The target is dazzled until the end of the current turn.",
                "failure": "The target is blinded until the end of the current turn.",
                "critical_failure": "The target is blinded until the end of the current turn and dazzled for 1 minute.",
            },
        },
        "source_line_start": 13745,
        "source_line_end": 13772,
        "raw_text_block": (
            "OCULAR OVERLOAD\n"
            "SPELL 4\n"
            "CONTINGENCY\n"
            "ILLUSION\n"
            "INCAPACITATION\n"
            "VISUAL\n"
            "Traditions arcane, occult, primal\n"
            "Cast 10 minutes (material, somatic, verbal)\n"
            "Duration 24 hours\n"
            "Just as a creature is about to attack you, you assault them with jarring "
            "illusions, completely surrounding their eyes with blinding flashes of motion "
            "and color. When the spell is complete, you gain the Overload Vision reaction; "
            "once you use the reaction, the spell ends.\n"
            "Overload Vision [reaction] Trigger A creature within 60 feet would make an "
            "attack roll against you; Effect The triggering creature must attempt a "
            "Fortitude save.\n"
            "Critical Success The target is unaffected.\n"
            "Success The target is dazzled until the end of the current turn.\n"
            "Failure The target is blinded until the end of the current turn.\n"
            "Critical Failure The target is blinded until the end of the current turn and "
            "dazzled for 1 minute."
        ),
    },
    "vital_beacon": {
        "rank": 4,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine", "primal"],
        "traits": ["healing", "positive"],
        "cast": "1 minute (somatic, verbal)",
        "cast_actions": "1_minute",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "NA",
        "targets": "self",
        "duration": "until your next daily preparations",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "Vitality radiates from you as a healing beacon that allies can supplicate to "
            "for diminishing bursts of restorative energy."
        ),
        "description_snippet": "Radiate vitality that heals creatures that touch you.",
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The beacon restores one additional die of Hit Points each time it heals, using the same die size as the others for that step.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The beacon restores one additional die of Hit Points each time it heals, using the same die size as the others for that step.",
            }
        ],
        "source_line_start": 60674,
        "source_line_end": 60693,
        "raw_text_block": (
            "VITAL BEACON\n"
            "HEALING\n"
            "POSITIVE\n"
            "SPELL 4\n"
            "NECROMANCY\n"
            "Traditions divine, primal\n"
            "Cast 1 minute (somatic, verbal)\n"
            "Duration until your next daily preparations\n"
            "Vitality radiates outward from you, allowing others to supplicate and receive "
            "healing. Once per round, either you or an ally can use an Interact action to "
            "regain Hit Points. Each time the beacon heals someone, it decreases in "
            "strength, restoring 4d10, then 4d8, then 4d6, then 4d4 Hit Points, after "
            "which the spell ends.\n"
            "Heightened (+1) The beacon restores one additional die of Hit Points each "
            "time it heals."
        ),
    },
    "winning_streak": {
        "rank": 4,
        "spell_type": "spell",
        "school": "divination",
        "traditions": ["arcane", "occult"],
        "traits": ["fortune"],
        "cast": "[one-action] verbal",
        "cast_actions": "1_action",
        "components": ["verbal"],
        "range": "20 feet",
        "area": "NA",
        "targets": "1 creature",
        "duration": "1 round (see text)",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You quicken a creature with contagious good fortune; as long as its side "
            "keeps landing critical successes against meaningful foes, the quickness "
            "spreads and the streak continues."
        ),
        "description_snippet": "Quicken a target and make its critical hits spread the quickness.",
        "source_line_start": 16586,
        "source_line_end": 16601,
        "raw_text_block": (
            "WINNING STREAK\n"
            "SPELL 4\n"
            "DIVINATION\n"
            "Traditions arcane, occult\n"
            "Cast [one-action] verbal\n"
            "Range 20 feet; Targets 1 creature\n"
            "Duration 1 round (see text)\n"
            "The target is energized by its good fortune as it spreads to others—as long "
            "as they keep winning. It gains the quickened condition for 1 round. If the "
            "target or one of their allies within 20 feet gets a critical success on an "
            "attack roll against a significant foe, whoever got the critical success "
            "becomes quickened if they weren't already, and the duration of the winning "
            "streak is extended by another round. Creatures quickened by the spell can "
            "use the extra action to Strike, Step, or Stride."
        ),
    },
    "blink": {
        "source_line_start": 46891,
        "source_line_end": 46891,
        "raw_text_block": "Blink H (con): Flit between the planes,",
    },
    "breath_of_life": {
        "school": "necromancy",
    },
    "burning_hands": {
        "rank": 1,
        "spell_type": "spell",
        "school": "evocation",
        "traditions": ["arcane", "primal"],
        "traits": ["fire"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "15-foot cone",
        "targets": "NA",
        "duration": "NA",
        "save": "basic Reflex",
        "save_type": "reflex",
        "description": (
            "Gouts of flame rush from your hands. You deal 2d6 fire damage to creatures "
            "in the area."
        ),
        "description_snippet": "A cone of fire deals 2d6 fire damage.",
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 2d6.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 2d6.",
            }
        ],
        "source_line_start": 49740,
        "source_line_end": 49746,
        "raw_text_block": (
            "Traditions arcane, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Area 15-foot cone\n"
            "Saving Throw basic Reflex\n"
            "Gouts of flame rush from your hands. You deal 2d6 fire damage to creatures "
            "in the area.\n"
            "Heightened (+1) The damage increases by 2d6."
        ),
    },
    "dancing_lights": {
        "rank": 0,
        "spell_type": "cantrip",
        "school": "evocation",
        "traditions": ["arcane", "occult", "primal"],
        "traits": ["light"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "120 feet",
        "area": "NA",
        "targets": "NA",
        "duration": "sustained",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You create up to four floating lights, no two of which are more than 10 feet "
            "apart. Each sheds light like a torch. When you Sustain the Spell, you can "
            "move any number of lights up to 60 feet."
        ),
        "description_snippet": "Create and move up to four floating lights.",
        "source_line_start": 50541,
        "source_line_end": 50552,
        "raw_text_block": (
            "CANTRIP 1\n"
            "LIGHT\n"
            "Traditions arcane, occult, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 120 feet\n"
            "Duration sustained\n"
            "You create up to four floating lights, no two of which are more than 10 feet "
            "apart. Each sheds light like a torch. When you Sustain the Spell, you can "
            "move any number of lights up to 60 feet. Each light must remain within 120 "
            "feet of you and within 10 feet of all others, or it winks out."
        ),
    },
    "daze": {
        "rank": 0,
        "spell_type": "cantrip",
        "school": "enchantment",
        "traditions": ["arcane", "divine", "occult"],
        "traits": ["mental", "nonlethal"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "60 feet",
        "area": "NA",
        "targets": "1 creature",
        "duration": "1 round",
        "save": "Will",
        "save_type": "will",
        "description": (
            "You cloud the target's mind and daze it with a mental jolt. The jolt deals "
            "mental damage equal to your spellcasting ability modifier; the target must "
            "attempt a basic Will save. If the target critically fails the save, it is "
            "also stunned 1."
        ),
        "description_snippet": "Damage a creature's mind and possibly stun it.",
        "heightened": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The damage increases by 1d6.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The damage increases by 1d6.",
            }
        ],
        "source_line_start": 50624,
        "source_line_end": 50636,
        "raw_text_block": (
            "MENTAL\n"
            "NONLETHAL\n"
            "Traditions arcane, divine, occult\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 60 feet; Targets 1 creature\n"
            "Saving Throw Will; Duration 1 round\n"
            "You cloud the target's mind and daze it with a mental jolt. The jolt deals "
            "mental damage equal to your spellcasting ability modifier; the target must "
            "attempt a basic Will save. If the target critically fails the save, it is "
            "also stunned 1.\n"
            "Heightened (+2) The damage increases by 1d6."
        ),
    },
    "detect_alignment": {
        "rank": 1,
        "spell_type": "spell",
        "rarity": "uncommon",
        "school": "divination",
        "traditions": ["divine", "occult"],
        "traits": ["detection"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "30-foot emanation",
        "targets": "NA",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "Your eyes glow as you sense aligned auras. Choose chaotic, evil, good, or "
            "lawful. You detect auras of that alignment. You receive no information "
            "beyond presence or absence. You can choose not to detect creatures or "
            "effects you're aware have that alignment."
        ),
        "description_snippet": "See auras of a chosen alignment.",
        "heightened": [
            {
                "label": "2nd",
                "type": "fixed_rank",
                "rank": 2,
                "text": "You learn each aura's location and strength.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "2nd",
                "type": "fixed_rank",
                "rank": 2,
                "text": "You learn each aura's location and strength.",
            }
        ],
        "source_line_start": 50708,
        "source_line_end": 50719,
        "raw_text_block": (
            "Traditions divine, occult\n"
            "Cast [two-actions] somatic, verbal\n"
            "Area 30-foot emanation\n"
            "Your eyes glow as you sense aligned auras. Choose chaotic, evil, good, or "
            "lawful. You detect auras of that alignment. You receive no information "
            "beyond presence or absence. You can choose not to detect creatures or "
            "effects you're aware have that alignment.\n"
            "Only creatures of 6th level or higher—unless divine spellcasters, undead, "
            "or beings from the Outer Sphere—have alignment auras.\n"
            "Heightened (2nd) You learn each aura's location and strength."
        ),
    },
    "detect_magic": {
        "rank": 0,
        "spell_type": "cantrip",
        "school": "divination",
        "traditions": ["arcane", "divine", "occult", "primal"],
        "traits": ["detection"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "30-foot emanation",
        "targets": "NA",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You send out a pulse that registers the presence of magic. You receive no "
            "information beyond the presence or absence of magic. You can choose to ignore "
            "magic you're fully aware of, such as the magic items and ongoing spells of "
            "you and your allies."
        ),
        "description_snippet": "Sense whether magic is nearby.",
        "heightened": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": (
                    "You learn the school of magic for the highest-level effect within "
                    "range that the spell detects."
                ),
            },
            {
                "label": "4th",
                "type": "fixed_rank",
                "rank": 4,
                "text": (
                    "As 3rd level, but you also pinpoint the source of the highest-level "
                    "magic."
                ),
            },
        ],
        "heightened_scaling": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": (
                    "You learn the school of magic for the highest-level effect within "
                    "range that the spell detects."
                ),
            },
            {
                "label": "4th",
                "type": "fixed_rank",
                "rank": 4,
                "text": (
                    "As 3rd level, but you also pinpoint the source of the highest-level "
                    "magic."
                ),
            },
        ],
        "source_line_start": 50736,
        "source_line_end": 50754,
        "raw_text_block": (
            "DETECTION\n"
            "Traditions arcane, divine, occult, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Area 30-foot emanation\n"
            "You send out a pulse that registers the presence of magic. You receive no "
            "information beyond the presence or absence of magic. You can choose to ignore "
            "magic you're fully aware of, such as the magic items and ongoing spells of "
            "you and your allies. You detect illusion magic only if that magic's effect "
            "has a lower level than the level of your detect magic spell. However, items "
            "that have an illusion aura but aren't deceptive in appearance (such as an "
            "invisibility potion) typically are detected normally.\n"
            "Heightened (3rd) You learn the school of magic for the highest-level effect "
            "within range that the spell detects. If multiple effects are equally strong, "
            "the GM determines which you learn.\n"
            "Heightened (4th) As 3rd level, but you also pinpoint the source of the "
            "highest-level magic."
        ),
    },
    "cataclysm": {
        "source_line_start": 47205,
        "source_line_end": 47205,
        "raw_text_block": "Cataclysm (evo): Call an instant,",
    },
    "chilling_darkness": {
        "rank": 3,
        "spell_type": "spell",
        "school": "evocation",
        "traditions": ["divine"],
        "traits": ["attack", "cold", "darkness", "evil"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "120 feet",
        "area": "NA",
        "targets": "1 creature",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You shoot an utterly cold ray of darkness tinged with unholy energy. Make "
            "a ranged spell attack against the target. The ray deals cold damage, and "
            "it deals extra evil damage to celestials while also counteracting magical light."
        ),
        "description_snippet": "Ray of evil darkness deals cold damage, counters light, and harms celestials.",
        "effects": {
            "description": (
                "You shoot an utterly cold ray of darkness tinged with unholy energy. "
                "Make a ranged spell attack against the target. You deal 5d6 cold "
                "damage, plus 5d6 evil damage if the target is a celestial. If the ray "
                "passes through an area of magical light or targets a creature affected "
                "by magical light, chilling darkness attempts to counteract the light."
            ),
            "outcomes": {
                "critical_success": "The target takes double damage.",
                "success": "The target takes full damage.",
            },
        },
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The cold damage increases by 2d6, and the evil damage against celestials increases by 2d6.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The cold damage increases by 2d6, and the evil damage against celestials increases by 2d6.",
            }
        ],
        "source_line_start": 50534,
        "source_line_end": 50559,
        "raw_text_block": (
            "CHILLING DARKNESS\n"
            "ATTACK\n"
            "COLD DARKNESS\n"
            "SPELL 3\n"
            "EVOCATION\n"
            "EVIL\n"
            "Traditions divine\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 120 feet; Targets 1 creature\n"
            "You shoot an utterly cold ray of darkness tinged with unholy energy. Make "
            "a ranged spell attack against the target. You deal 5d6 cold damage, plus "
            "5d6 evil damage if the target is a celestial.\n"
            "If the ray passes through an area of magical light or targets a creature "
            "affected by magical light, chilling darkness attempts to counteract the light.\n"
            "Critical Success The target takes double damage.\n"
            "Success The target takes full damage.\n"
            "Heightened (+1) The cold damage increases by 2d6, and the evil damage "
            "against celestials increases by 2d6."
        ),
    },
    "impending_doom": {
        "rank": 3,
        "spell_type": "spell",
        "school": "divination",
        "traditions": ["arcane", "divine", "occult"],
        "traits": ["emotion", "fear", "incapacitation", "mental", "prediction"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "1 living creature",
        "duration": "3 rounds",
        "save": "Will",
        "save_type": "will",
        "description": (
            "You show a living creature a vision of its own gruesome impending death. "
            "The terror escalates over 3 rounds, and if the spell takes hold to the end, "
            "the target suffers mental damage."
        ),
        "description_snippet": "Make a foe witness its potential death and become distressed.",
        "effects": {
            "description": (
                "You sift through myriad potential futures, seize upon one potential "
                "moment in which the target meets a particularly gruesome and fatal end, "
                "and then show it a vision of its impending demise. At the end of the "
                "spell's duration, if the target was affected, it witnesses its death "
                "and takes 6d6 mental damage."
            ),
            "outcomes": {
                "critical_success": "The creature is unaffected.",
                "success": "The creature is unaffected for 1 round, then becomes flat-footed, then frightened 1, and finally takes half damage.",
                "failure": "The creature is immediately flat-footed, then frightened 2, then stunned 1, and finally takes full damage.",
                "critical_failure": "The creature is immediately flat-footed and frightened 3, then stunned 1, then paralyzed, and finally takes double damage.",
            },
        },
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 2d6.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 2d6.",
            }
        ],
        "source_line_start": 12659,
        "source_line_end": 12701,
        "raw_text_block": (
            "IMPENDING DOOM\n"
            "DIVINATION\n"
            "EMOTION\n"
            "FEAR\n"
            "SPELL 3\n"
            "INCAPACITATION\n"
            "MENTAL\n"
            "PREDICTION\n"
            "Traditions arcane, divine, occult\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 30 feet; Targets 1 living creature\n"
            "Saving Throw Will; Duration 3 rounds\n"
            "You sift through myriad potential futures, seize upon one potential moment "
            "in which the target meets a particularly gruesome and fatal end, and then "
            "show it a vision of its impending demise.\n"
            "Critical Success The creature is unaffected.\n"
            "Success The creature is unaffected for 1 round. On the second round, it "
            "becomes flat-footed. Finally, on the third round, it becomes frightened 1. "
            "At the end of the third round, it takes half damage.\n"
            "Failure The creature is immediately flat-footed. On the second round, it "
            "becomes frightened 2. Finally, on the third round, it also becomes stunned "
            "1. At the end of the third round, the creature takes full damage.\n"
            "Critical Failure The creature is immediately flat-footed and frightened 3. "
            "On the second round, it becomes stunned 1. Finally, on the third round, it "
            "also becomes paralyzed. At the end of the third round, the creature takes "
            "double damage.\n"
            "Heightened (+1) The damage increases by 2d6."
        ),
    },
    "remove_disease": {
        "rank": 3,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine", "primal"],
        "traits": ["healing"],
        "cast": "10 minutes (material, somatic, verbal)",
        "cast_actions": "10_minutes",
        "components": ["material", "somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 creature",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "Healing magic purges disease from a creature's body. You attempt to "
            "counteract one disease afflicting the target."
        ),
        "description_snippet": "Cure a disease afflicting a creature.",
        "source_line_start": 57223,
        "source_line_end": 57229,
        "raw_text_block": (
            "REMOVE DISEASE\n"
            "SPELL 3\n"
            "HEALING\n"
            "NECROMANCY\n"
            "Traditions divine, primal\n"
            "Cast 10 minutes (material, somatic, verbal)\n"
            "Range touch; Targets 1 creature\n"
            "Healing magic purges disease from a creature's body. You attempt to "
            "counteract one disease afflicting the target."
        ),
    },
    "shadow_projectile": {
        "rank": 3,
        "spell_type": "spell",
        "school": "illusion",
        "traditions": ["arcane", "occult"],
        "traits": ["shadow", "visual"],
        "cast": "[reaction] somatic",
        "cast_actions": "reaction",
        "components": ["somatic"],
        "trigger": "An ally within 20 feet of you makes a ranged attack roll.",
        "range": "NA",
        "area": "NA",
        "targets": "the target of the triggering attack",
        "duration": "NA",
        "save": "Will",
        "save_type": "will",
        "description": (
            "You create an illusory duplicate of an ally's ranged attack to confuse the "
            "target. The spell can leave the foe flat-footed against the triggering attack "
            "and deal mental damage."
        ),
        "description_snippet": "React when an ally makes a ranged attack to create a shadow double of the attack, distracting and damaging the foe.",
        "effects": {
            "description": (
                "You launch an illusory double of your ally's projectile or spell at the "
                "same target, leaving the enemy unsure which attack to avoid. The target "
                "takes 3d8 mental damage, depending on its Will save. Regardless of the "
                "result of its save, it's temporarily immune to shadow projectile spells "
                "for 1 hour."
            ),
            "outcomes": {
                "critical_success": "The creature is unaffected.",
                "success": "The creature is flat-footed against the triggering attack.",
                "failure": "The creature is flat-footed against the triggering attack and takes full damage from your illusory projectile.",
                "critical_failure": "As failure, but double damage.",
            },
        },
        "heightened": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The damage increases by 1d8.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The damage increases by 1d8.",
            }
        ],
        "source_line_start": 14964,
        "source_line_end": 14990,
        "raw_text_block": (
            "SHADOW PROJECTILE\n"
            "ILLUSION\n"
            "SHADOW\n"
            "SPELL 3\n"
            "VISUAL\n"
            "Traditions arcane, occult\n"
            "Cast [reaction] somatic; Trigger An ally within 20 feet of you makes a ranged attack roll.\n"
            "Saving Throw Will\n"
            "You create an illusory duplicate of your ally's ranged attack to confuse your "
            "opponents. The target takes 3d8 mental damage, depending on its Will save.\n"
            "Critical Success The creature is unaffected.\n"
            "Success The creature is flat-footed against the triggering attack.\n"
            "Failure The creature is flat-footed against the triggering attack and takes "
            "full damage from your illusory projectile.\n"
            "Critical Failure As failure, but double damage.\n"
            "Heightened (+2) The damage increases by 1d8."
        ),
    },
    "shift_blame": {
        "rank": 3,
        "spell_type": "spell",
        "school": "enchantment",
        "traditions": ["arcane", "occult"],
        "traits": ["mental"],
        "cast": "[reaction] verbal",
        "cast_actions": "reaction",
        "components": ["verbal"],
        "trigger": "You or another creature attacks a creature or fails at a Deception, Diplomacy, or Intimidation check.",
        "range": "30 feet",
        "area": "NA",
        "targets": "the target of the triggering attack or skill check",
        "duration": "NA",
        "save": "Will",
        "save_type": "will",
        "description": (
            "You alter a target's memory of a triggering attack or social blunder so it "
            "thinks another creature was responsible."
        ),
        "description_snippet": "Trick someone into thinking someone else is to blame for your attack or blunder.",
        "effects": {
            "description": (
                "You choose another creature and alter the target's memories to recall "
                "that creature as responsible for the triggering attack or skill check. "
                "The target must attempt a Will save and is then temporarily immune for 24 hours."
            ),
            "outcomes": {
                "critical_success": "The target knows you attempted to alter its memories.",
                "success": "The target doesn't realize you attempted to alter its memories, though it knows you cast a spell.",
                "failure": "You successfully alter the target's memory.",
            },
        },
        "source_line_start": 14901,
        "source_line_end": 14931,
        "raw_text_block": (
            "SHIFT BLAME\n"
            "ENCHANTMENT\n"
            "SPELL 3\n"
            "MENTAL\n"
            "Traditions arcane, occult\n"
            "Cast [reaction] verbal; Trigger You or another creature attacks a creature "
            "or fails at a Deception, Diplomacy, or Intimidation check.\n"
            "Range 30 feet; Targets the target of the triggering attack or skill check\n"
            "Saving Throw Will\n"
            "You alter the target's memories of the triggering event as they form. You "
            "choose another creature and alter the target's memories to recall that "
            "creature as responsible for the triggering attack or skill check.\n"
            "Critical Success The target knows you attempted to alter its memories.\n"
            "Success The target doesn't realize you attempted to alter its memories, "
            "though it knows you cast a spell.\n"
            "Failure You successfully alter the target's memory."
        ),
    },
    "shrink_item": {
        "rank": 3,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["arcane"],
        "traits": ["none"],
        "cast": "10 minutes (somatic, verbal)",
        "cast_actions": "10_minutes",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 non-magical object up to 20 cubic feet in volume and up to 80 Bulk",
        "duration": "until the next time you make your daily preparations",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You shrink a non-magical object to roughly the size of a coin, reducing it "
            "to negligible Bulk until the spell ends."
        ),
        "description_snippet": "Reduce an object to the size of a coin.",
        "source_line_start": 58165,
        "source_line_end": 58178,
        "raw_text_block": (
            "SHRINK ITEM\n"
            "SPELL 3\n"
            "TRANSMUTATION\n"
            "Traditions arcane\n"
            "Cast 10 minutes (somatic, verbal)\n"
            "Range touch; Targets 1 non-magical object up to 20 cubic feet in volume and up to 80 Bulk\n"
            "Duration until the next time you make your daily preparations\n"
            "You shrink the target to roughly the size of a coin. This reduces it to "
            "negligible Bulk. You can Dismiss the spell, and the spell ends if you toss "
            "the object onto a solid surface. The object can't be used to attack or cause "
            "damage during the process of it returning to normal size."
        ),
    },
    "soothing_blossoms": {
        "rank": 3,
        "spell_type": "spell",
        "school": "conjuration",
        "traditions": ["divine", "primal"],
        "traits": ["plant"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "10-foot burst",
        "targets": "NA",
        "duration": "10 minutes",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "Blossoms grow in a small area, helping creatures recover from persistent "
            "damage and from poisons and diseases that allow short-duration saves."
        ),
        "description_snippet": "Flowers assist recovery from persistent damage and afflictions.",
        "effects": {
            "description": (
                "Blossoms grow from the ground in a small area, soothing away afflictions "
                "and persistent pains and harm. When any creature in that area rolls a "
                "successful save against a poison or disease effect, it gets a critical "
                "success instead. The blossoms grant assisted recovery to everyone in the "
                "area to end persistent damage, both when the spell is cast and at the "
                "start of each of your turns."
            ),
            "outcomes": {},
        },
        "source_line_start": 14950,
        "source_line_end": 14978,
        "raw_text_block": (
            "SOOTHING BLOSSOMS\n"
            "CONJURATION\n"
            "SPELL 3\n"
            "PLANT\n"
            "Traditions divine, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 30 feet; Area 10-foot burst\n"
            "Duration 10 minutes\n"
            "Blossoms grow from the ground in a small area, soothing away afflictions "
            "and persistent pains and harm. When any creature in that area rolls a "
            "successful save against a poison or disease effect, it gets a critical "
            "success instead. The blossoms grant assisted recovery to everyone in the "
            "area to end their persistent damage, both when the spell is cast and at the "
            "start of each of your turns."
        ),
    },
    "augment_summoning": {
        "source_line_start": 65103,
        "source_line_end": 65108,
        "raw_text_block": (
            "AUGMENT SUMMONING\n"
            "UNCOMMON\n"
            "CONJURATION\n"
            "FOCUS 1\n"
            "WIZARD\n"
            "Cast [free-action]; Requirements You have a summoned creature.\n"
            "Targets 1 summoned creature you control\n"
            "Duration 1 minute\n"
            "You augment the abilities of a summoned creature. The target gains a +1 "
            "status bonus to all checks (this also applies to the creature's DCs, "
            "including its AC) for the duration of its summoning, up to 1 minute."
        ),
    },
    "call_of_the_grave": {
        "source_line_start": 65057,
        "source_line_end": 65063,
        "raw_text_block": (
            "CALL OF THE GRAVE\n"
            "UNCOMMON\n"
            "FOCUS 1\n"
            "ATTACK\n"
            "NECROMANCY\n"
            "AUDITORY\n"
            "MENTAL\n"
            "WIZARD\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 30 feet; Targets 1 living creature\n"
            "You fire a ray of sickening energy. Make a spell attack roll.\n"
            "Critical Success The target becomes sickened 2 and slowed 1 as long as it's sickened.\n"
            "Success The target becomes sickened 1.\n"
            "Failure The target is unaffected."
        ),
    },
    "charming_words": {
        "source_line_start": 64963,
        "source_line_end": 64973,
        "raw_text_block": (
            "CHARMING WORDS\n"
            "UNCOMMON\n"
            "AUDITORY\n"
            "ENCHANTMENT\n"
            "INCAPACITATION\n"
            "LINGUISTIC\n"
            "MENTAL\n"
            "WIZARD\n"
            "Cast [one-action] verbal\n"
            "Range 30 feet; Targets 1 creature\n"
            "Saving Throw Will; Duration until the start of your next turn\n"
            "You whisper enchanting words to deflect your foe's ire. The target must "
            "attempt a Will save.\n"
            "Critical Success The target is unaffected.\n"
            "Success The target takes a -1 circumstance penalty to attack rolls and "
            "damage rolls against you.\n"
            "Failure The target can't use hostile actions against you.\n"
            "Critical Failure The target is stunned 1 and can't use hostile actions against you."
        ),
    },
    "dimensional_steps": {
        "source_line_start": 64979,
        "source_line_end": 64983,
        "raw_text_block": (
            "DIMENSIONAL STEPS\n"
            "UNCOMMON\n"
            "CONJURATION\n"
            "TELEPORTATION\n"
            "WIZARD\n"
            "Cast [one-action] somatic\n"
            "Range self\n"
            "You flicker through space. Teleport up to 20 feet to a space you can see."
        ),
    },
    "dread_aura": {
        "source_line_start": 65125,
        "source_line_end": 65129,
        "raw_text_block": (
            "DREAD AURA\n"
            "UNCOMMON\n"
            "AURA\n"
            "FOCUS 1\n"
            "ENCHANTMENT\n"
            "EMOTION\n"
            "FEAR\n"
            "MENTAL\n"
            "WIZARD\n"
            "Cast [one-action] verbal\n"
            "Area 30-foot-radius emanation\n"
            "Duration sustained up to 1 minute\n"
            "You emit an aura of terror. Foes in the area are frightened 1 and unable to "
            "reduce the condition."
        ),
    },
    "energy_absorption": {
        "source_line_start": 65189,
        "source_line_end": 65194,
        "raw_text_block": (
            "ENERGY ABSORPTION\n"
            "UNCOMMON\n"
            "ABJURATION\n"
            "FOCUS 1\n"
            "WIZARD\n"
            "Cast [reaction] verbal; Trigger An effect would deal acid, cold, electricity, or fire damage to you.\n"
            "You gain resistance 15 to acid, cold, electricity, or fire damage from the "
            "triggering effect (one type of your choice). The resistance applies only to "
            "the triggering effect's initial damage.\n"
            "Heightened (+1) The resistance increases by 5."
        ),
    },
    "force_bolt": {
        "source_line_start": 65226,
        "source_line_end": 65230,
        "raw_text_block": (
            "FORCE BOLT\n"
            "UNCOMMON\n"
            "EVOCATION\n"
            "FORCE\n"
            "WIZARD\n"
            "Cast [one-action] somatic\n"
            "Range 30 feet; Targets 1 creature\n"
            "You fire an unerring dart of force from your fingertips. It automatically "
            "hits and deals 1d4+1 force damage to the target.\n"
            "Heightened (+2) The damage increases by 1d4+1."
        ),
    },
    "scholastic_dissertation": {
        "source_line_start": 143,
        "source_line_end": 184,
        "raw_text_block": (
            "SCHOLASTIC DISSERTATION\n"
            "UNCOMMON\n"
            "DIVINATION\n"
            "FOCUS 1\n"
            "WIZARD\n"
            "Cast [one-action]\n"
            "Range self; Targets self\n"
            "Duration 1 minute\n"
            "You focus your scholarship into a burst of magical clarity, drawing on "
            "disciplined study to improve your next Recall Knowledge or spell-"
            "identification effort during the duration."
        ),
    },
    "warped_terrain": {
        "source_line_start": 65345,
        "source_line_end": 65379,
        "raw_text_block": (
            "WARPED TERRAIN\n"
            "UNCOMMON\n"
            "ILLUSION\n"
            "VISUAL\n"
            "FOCUS 1\n"
            "WIZARD\n"
            "Cast [one-action] to [three-actions] somatic, verbal\n"
            "Range 60 feet; Area 5-foot burst or more\n"
            "Duration 1 minute\n"
            "You create illusory hazards that cover all surfaces in the area (typically "
            "the ground). Any creature moving through the illusion treats the squares as "
            "difficult terrain. A creature can attempt to disbelieve the effect as normal "
            "after using a Seek action or otherwise spending actions interacting with the "
            "illusion. If it successfully disbelieves, it ignores the effect for the "
            "remaining duration. For each additional action you use casting the spell, the "
            "burst's radius increases by 5 feet, to a maximum of 10 extra feet for 3 "
            "actions.\n"
            "Heightened (4th) You can make the illusion appear in the air rather than on "
            "a surface, causing it to function as difficult terrain for flying creatures."
        ),
    },
    "unspeakable_shadow": {
        "rank": 9,
        "spell_type": "spell",
        "school": "illusion",
        "traditions": ["arcane", "occult"],
        "traits": ["death", "emotion", "fear", "mental", "shadow", "visual"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "120 feet",
        "area": "NA",
        "targets": "1 creature",
        "duration": "varies",
        "save": "Will",
        "save_type": "will",
        "description": (
            "You alter a creature's shadow, transforming it into a terrifying monster out "
            "to devour the creature. The target must attempt a Will save."
        ),
        "description_snippet": "Turn a creature's shadow into a terrifying monster.",
        "effects": {
            "description": (
                "You alter a creature's shadow, transforming it into a terrifying monster "
                "out to devour the creature. A creature that has the frightened condition "
                "from unspeakable shadow must spend at least one of its actions each turn "
                "to either attack its shadow or flee from its shadow."
            ),
            "outcomes": {
                "critical_success": "The target is unaffected.",
                "success": "The target is frightened 2.",
                "failure": "The target is frightened 3. It can't reduce its frightened value below 1 for 1 minute.",
                "critical_failure": (
                    "The target is so afraid, it might instantly die. It must succeed at a "
                    "Fortitude save or die; this saving throw has the incapacitation trait. "
                    "If it succeeds at its save, the target is frightened 4 and can't "
                    "reduce its frightened value below 1 for 1 minute."
                ),
            },
        },
        "conditions_caused": ["frightened"],
        "source_line_start": 16077,
        "source_line_end": 16116,
        "raw_text_block": (
            "UNSPEAKABLE SHADOW\n"
            "DEATH\n"
            "EMOTION\n"
            "FEAR\n"
            "ILLUSION\n"
            "SPELL 9\n"
            "MENTAL\n"
            "SHADOW\n"
            "VISUAL\n"
            "Traditions arcane, occult\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 120 feet; Targets 1 creature\n"
            "Saving Throw Will; Duration varies\n"
            "You alter a creature's shadow, transforming it into a terrifying monster out "
            "to devour the creature. The creature must attempt a Will save. A creature "
            "that has the frightened condition from unspeakable shadow must spend at least "
            "one of its actions each turn to either attack its shadow or flee from its "
            "shadow.\n"
            "Critical Success The target is unaffected.\n"
            "Success The target is frightened 2.\n"
            "Failure The target is frightened 3. It can't reduce its frightened value "
            "below 1 for 1 minute.\n"
            "Critical Failure The target is so afraid, it might instantly die. It must "
            "succeed at a Fortitude save or die; this saving throw has the incapacitation "
            "trait. If it succeeds at its save, the target is frightened 4 and can't "
            "reduce its frightened value below 1 for 1 minute."
        ),
    },
    "grisly_growths": {
        "rank": 5,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["arcane", "primal"],
        "traits": ["none"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "60 feet",
        "area": "NA",
        "targets": "1 corporeal creature",
        "duration": "NA",
        "save": "basic Fortitude",
        "save_type": "basic_fortitude",
        "description": (
            "This gruesome spell causes a target to erupt with excess limbs and organs, "
            "dealing piercing damage unless it resists the transformation."
        ),
        "description_snippet": "A creature grows excess limbs and organs.",
        "effects": {
            "description": (
                "This gruesome spell causes the target to grow excess limbs and organs, "
                "whether it be fingers multiplying until hands resemble bushes, eyes "
                "popping open in bizarre places, legs sprouting from the side of the body, "
                "or some other result. The target takes 10d6 piercing damage."
            ),
            "outcomes": {},
        },
        "source_line_start": 31127,
        "source_line_end": 31137,
        "raw_text_block": (
            "GRISLY GROWTHS\n"
            "SPELL 5\n"
            "TRANSMUTATION\n"
            "Traditions arcane, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 60 feet; Targets 1 corporeal creature\n"
            "Saving Throw basic Fortitude\n"
            "This gruesome spell causes the target to grow excess limbs and organs, "
            "whether it be fingers multiplying until hands resemble bushes, eyes popping "
            "open in bizarre places, legs sprouting from the side of the body, or some "
            "other result. The target takes 10d6 piercing damage (basic Fortitude save) "
            "as the new features erupt."
        ),
    },
    "invoke_spirits": {
        "rank": 5,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["arcane", "divine", "occult"],
        "traits": ["emotion", "fear", "mental"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "120 feet",
        "area": "10-foot burst",
        "targets": "NA",
        "duration": "sustained up to 1 minute",
        "save": "Will",
        "save_type": "will",
        "description": (
            "Ragged apparitions of the dead rise to stalk the living, dealing mental and "
            "negative damage and terrifying those who fail badly."
        ),
        "description_snippet": "Call a group of ghostly apparitions to attack your foes.",
        "effects": {
            "description": (
                "Ragged apparitions of the dead rise to stalk the living. They deal 2d4 "
                "mental damage and 2d4 negative damage to each living creature in the area, "
                "with a basic Will save."
            ),
            "outcomes": {
                "critical_failure": "The creature is frightened 2 and fleeing for 1 round.",
            },
        },
        "heightened": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The mental damage and negative damage each increase by 1d4.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The mental damage and negative damage each increase by 1d4.",
            }
        ],
        "source_line_start": 12944,
        "source_line_end": 12969,
        "raw_text_block": (
            "INVOKE SPIRITS\n"
            "EMOTION\n"
            "FEAR\n"
            "MENTAL\n"
            "SPELL 5\n"
            "NECROMANCY\n"
            "Traditions arcane, divine, occult\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 120 feet; Area 10-foot burst\n"
            "Saving Throw Will; Duration sustained up to 1 minute\n"
            "Ragged apparitions of the dead rise to stalk the living. They deal 2d4 mental "
            "damage and 2d4 negative damage to each living creature in the area, with a "
            "basic Will save. Additionally, creatures that critically fail the save are "
            "frightened 2 and are fleeing for 1 round.\n"
            "On subsequent rounds, the first time you Sustain the Spell each round, you can "
            "move the area up to 30 feet within the range of the spell.\n"
            "Heightened (+2) The mental damage and negative damage each increase by 1d4."
        ),
    },
    "plant_form": {
        "rank": 5,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["primal"],
        "traits": ["plant", "polymorph"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "NA",
        "targets": "self",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "Taking inspiration from verdant creatures, you transform into a Large plant "
            "battle form such as an arboreal, flytrap, or shambler."
        ),
        "description_snippet": "Turn into a dangerous plant creature.",
        "heightened": [
            {
                "label": "6th",
                "type": "fixed_rank",
                "rank": 6,
                "text": (
                    "Your battle form is Huge, and the reach of your attacks increases by "
                    "5 feet. You instead gain AC = 22 + your level, 24 temporary HP, "
                    "attack modifier +21, damage bonus +16, and Athletics +22."
                ),
            }
        ],
        "heightened_scaling": [
            {
                "label": "6th",
                "type": "fixed_rank",
                "rank": 6,
                "text": (
                    "Your battle form is Huge, and the reach of your attacks increases by "
                    "5 feet. You instead gain AC = 22 + your level, 24 temporary HP, "
                    "attack modifier +21, damage bonus +16, and Athletics +22."
                ),
            }
        ],
        "source_line_start": 56293,
        "source_line_end": 56335,
        "raw_text_block": (
            "PLANT FORM\n"
            "SPELL 5\n"
            "PLANT\n"
            "TRANSMUTATION\n"
            "Traditions primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Duration 1 minute\n"
            "Taking inspiration from verdant creatures, you transform into a Large plant "
            "battle form. When you cast this spell, choose arboreal, flytrap, or shambler. "
            "While in this form, you gain the plant trait.\n"
            "You gain the following statistics and abilities regardless of which battle "
            "form you choose: AC = 19 + your level, 12 temporary HP, resistance 10 to "
            "poison, low-light vision, plant attacks, and Athletics +19 unless your own "
            "modifier is higher.\n"
            "Heightened (6th) Your battle form is Huge, and the reach of your attacks "
            "increases by 5 feet. You instead gain AC = 22 + your level, 24 temporary HP, "
            "attack modifier +21, damage bonus +16, and Athletics +22."
        ),
    },
    "shadow_siphon": {
        "rank": 5,
        "spell_type": "spell",
        "school": "illusion",
        "traditions": ["arcane", "occult"],
        "traits": ["shadow"],
        "cast": "[reaction] verbal",
        "cast_actions": "reaction",
        "components": ["verbal"],
        "trigger": "A spell or magical effect deals damage.",
        "range": "60 feet",
        "area": "NA",
        "targets": "the triggering spell",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You transform a damaging spell into a partially illusory shadow version, "
            "blunting its damage if your counteract attempt succeeds."
        ),
        "description_snippet": "React to lessen the damage from an enemy's spell by making it partially illusion.",
        "source_line_start": 57921,
        "source_line_end": 57933,
        "raw_text_block": (
            "SHADOW SIPHON\n"
            "SPELL 5\n"
            "ILLUSION\n"
            "Traditions arcane, occult\n"
            "Cast [reaction] verbal; Trigger A spell or magical effect deals damage.\n"
            "Range 60 feet; Targets the triggering spell\n"
            "Exchanging material energy with that of the Shadow Plane, you transform the "
            "triggering spell into a partially illusory version of itself. Attempt to "
            "counteract the target spell. If the attempt is successful, any creatures "
            "that would be damaged by the spell instead take only half as much damage.\n"
            "Treat shadow siphon's counteract level as 2 higher for this attempt."
        ),
    },
    "summon_giant": {
        "rank": 5,
        "spell_type": "spell",
        "school": "conjuration",
        "traditions": ["primal"],
        "traits": ["none"],
        "cast": "[three-actions] material, somatic, verbal",
        "cast_actions": "3_actions",
        "components": ["material", "somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "NA",
        "duration": "sustained up to 1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You conjure a giant to fight for you. It works like summon animal, except the "
            "summoned creature must be a common giant of level 5 or lower."
        ),
        "description_snippet": "Conjure a giant to fight on your behalf.",
        "heightened": [
            {
                "label": "As summon animal",
                "type": "special",
                "text": "Heightened As summon animal.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "As summon animal",
                "type": "special",
                "text": "Heightened As summon animal.",
            }
        ],
        "source_line_start": 59300,
        "source_line_end": 59307,
        "raw_text_block": (
            "SUMMON GIANT\n"
            "SPELL 5\n"
            "CONJURATION\n"
            "Traditions primal\n"
            "Cast [three-actions] material, somatic, verbal\n"
            "Range 30 feet\n"
            "Duration sustained up to 1 minute\n"
            "You conjure a giant to fight for you. This works like summon animal, except "
            "you summon a common creature that has the giant trait and whose level is 5 "
            "or lower.\n"
            "Heightened As summon animal."
        ),
    },
    "unusual_anatomy": {
        "rank": 5,
        "spell_type": "focus",
        "rarity": "uncommon",
        "school": "transmutation",
        "focus_class": "sorcerer",
        "traditions": ["occult"],
        "traits": ["polymorph"],
        "cast": "[one-action] somatic",
        "cast_actions": "1_action",
        "components": ["somatic"],
        "range": "NA",
        "area": "NA",
        "targets": "self",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You transform your flesh and organs into a bizarre amalgam that resists "
            "precision, blunts critical-hit damage, grants darkvision, and burns melee "
            "attackers with acid."
        ),
        "description_snippet": "Warp your body into a bizarre defensive anatomy.",
        "heightened": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The resistances increase by 5, and the acid damage increases by 1d6.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+2",
                "type": "step",
                "step": 2,
                "text": "The resistances increase by 5, and the acid damage increases by 1d6.",
            }
        ],
        "source_line_start": 64937,
        "source_line_end": 65025,
        "raw_text_block": (
            "UNUSUAL ANATOMY\n"
            "UNCOMMON POLYMORPH SORCERER\n"
            "FOCUS 5\n"
            "TRANSMUTATION\n"
            "Cast [one-action] somatic\n"
            "Duration 1 minute\n"
            "You transform your flesh and organs into a bizarre amalgam of glistening "
            "skin, rough scales, tufts of hair, and tumorous protuberances. This has "
            "three effects: you gain resistance 10 to precision damage and resistance 10 "
            "to extra damage from critical hits; you gain darkvision; and any creature "
            "that hits you with an unarmed attack or with a non-reach melee weapon takes "
            "2d6 acid damage.\n"
            "Heightened (+2) The resistances increase by 5, and the acid damage increases "
            "by 1d6."
        ),
    },
    "lightning_bolt": {
        "rank": 3,
        "spell_type": "spell",
        "school": "evocation",
        "traditions": ["arcane", "primal"],
        "traits": ["electricity"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "120-foot line",
        "targets": "NA",
        "duration": "NA",
        "save": "basic Reflex",
        "save_type": "basic_reflex",
        "description": (
            "A bolt of lightning strikes outward from your hand in a long line, dealing "
            "electricity damage to creatures in the area that fail to dodge aside."
        ),
        "description_snippet": "A bolt of lightning strikes outward from your hand, dealing 4d12 electricity damage.",
        "effects": {
            "description": (
                "A bolt of lightning strikes outward from your hand, dealing 4d12 "
                "electricity damage."
            ),
            "outcomes": {},
        },
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d12.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d12.",
            }
        ],
        "source_line_start": 54658,
        "source_line_end": 54671,
        "raw_text_block": (
            "LIGHTNING BOLT\n"
            "ELECTRICITY\n"
            "SPELL 3\n"
            "EVOCATION\n"
            "Traditions arcane, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Area 120-foot line\n"
            "Saving Throw basic Reflex\n"
            "A bolt of lightning strikes outward from your hand, dealing 4d12 "
            "electricity damage.\n"
            "Heightened (+1) The damage increases by 1d12."
        ),
    },
    "circle_of_protection": {
        "source_line_start": 47404,
        "source_line_end": 47404,
        "raw_text_block": "Circle of Protection U, H (abj): A creature",
    },
    "contingency": {
        "source_line_start": 47093,
        "source_line_end": 47093,
        "raw_text_block": "Contingency H (abj): Set up a spell to",
    },
    "death_knell": {
        "rank": 2,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine", "occult"],
        "traits": ["death"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 living creature that has 0 HP",
        "duration": "NA",
        "save": "Will",
        "save_type": "will",
        "description": (
            "You snuff the life out of a creature on the brink of death. The target "
            "must attempt a Will save. If this kills it, you gain 10 temporary HP "
            "and a +1 status bonus to attack and damage rolls for 10 minutes."
        ),
        "description_snippet": "Kill a dying creature and gain a short-lived buff.",
        "effects": {
            "description": (
                "You snuff the life out of a creature on the brink of death. The target "
                "must attempt a Will save. If this kills it, you gain 10 temporary HP "
                "and a +1 status bonus to attack and damage rolls for 10 minutes."
            ),
            "outcomes": {
                "critical_success": "The target is unaffected.",
                "success": "The target's dying value increases by 1.",
                "failure": "The target dies.",
            },
        },
        "source_line_start": 51323,
        "source_line_end": 51359,
        "raw_text_block": (
            "DEATH KNELL\n"
            "DEATH\n"
            "SPELL 2\n"
            "NECROMANCY\n"
            "Traditions divine, occult\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets 1 living creature that has 0 HP\n"
            "Saving Throw Will\n"
            "You snuff the life out of a creature on the brink of death. The target "
            "must attempt a Will save. If this kills it, you gain 10 temporary HP "
            "and a +1 status bonus to attack and damage rolls for 10 minutes.\n"
            "Critical Success The target is unaffected.\n"
            "Success The target's dying value increases by 1.\n"
            "Failure The target dies."
        ),
    },
    "dimension_door": {
        "rank": 4,
        "spell_type": "spell",
        "school": "conjuration",
        "traditions": ["arcane", "occult"],
        "traits": ["teleportation"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "120 feet",
        "area": "NA",
        "targets": "NA",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "Opening a door that bypasses normal space, you instantly transport yourself "
            "and any items you're wearing and holding from your current space to a clear "
            "space within range you can see. If this would bring another creature with "
            "you, even if you're carrying it in an extradimensional container, the spell "
            "is lost."
        ),
        "description_snippet": "Teleport yourself up to 120 feet.",
        "source_line_start": 50810,
        "source_line_end": 50835,
        "raw_text_block": (
            "DIMENSION DOOR\n"
            "CONJURATION\n"
            "SPELL 4\n"
            "TELEPORTATION\n"
            "Traditions arcane, occult\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 120 feet\n"
            "Opening a door that bypasses normal space, you instantly transport yourself "
            "and any items you're wearing and holding from your current space to a clear "
            "space within range you can see. If this would bring another creature with "
            "you, even if you're carrying it in an extradimensional container, the spell "
            "is lost."
        ),
    },
    "divine_armageddon": {
        "rank": 8,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine"],
        "traits": ["negative", "positive"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "120 feet",
        "area": "60-foot burst",
        "targets": "NA",
        "duration": "NA",
        "save": "basic Fortitude",
        "save_type": "basic_fortitude",
        "description": (
            "You call forth a divine cataclysm from your deity, destroying living and "
            "undead creatures in the area alike with negative and alignment power."
        ),
        "description_snippet": "Call down a divine cataclysm that harms both living and undead.",
        "effects": {
            "description": (
                "You call forth a divine cataclysm from your deity, destroying living and "
                "undead creatures in the area alike. Creatures in the area take 10d6 "
                "negative damage and 10d6 alignment damage chosen from among the "
                "alignments your deity has. If your deity is true neutral, increase the "
                "negative damage by 4d6 instead of dealing alignment damage. A creature "
                "harmed by positive damage, such as one with negative healing, takes "
                "positive damage instead of negative damage from this spell. You can't cast "
                "this spell if you don't have a deity. This spell gains the trait "
                "corresponding to the alignment damage dealt."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["none"],
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The damage increases by 1d6 negative damage, 1d6 alignment damage, "
                    "and 1d6 additional negative and positive damage for a true neutral deity."
                ),
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The damage increases by 1d6 negative damage, 1d6 alignment damage, "
                    "and 1d6 additional negative and positive damage for a true neutral deity."
                ),
            }
        ],
        "source_line_start": 11379,
        "source_line_end": 11407,
        "raw_text_block": (
            "DIVINE ARMAGEDDON\n"
            "NECROMANCY\n"
            "NEGATIVE\n"
            "POSITIVE\n"
            "Traditions divine\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 120 feet; Area 60-foot burst\n"
            "Saving Throw basic Fortitude\n"
            "You call forth a divine cataclysm from your deity, destroying living and "
            "undead creatures in the area alike. Creatures in the area take 10d6 negative "
            "damage and 10d6 alignment damage (good, evil, lawful, or chaotic), chosen "
            "from among the alignments your deity has. If your deity is true neutral, "
            "increase the negative damage by 4d6 instead of dealing alignment damage. A "
            "creature harmed by positive damage, such as one with negative healing, takes "
            "positive damage instead of negative damage from this spell.\n"
            "You can't cast this spell if you don't have a deity. This spell gains the "
            "trait corresponding to the alignment damage dealt.\n"
            "Heightened (+1) The damage increases by 1d6 negative damage, 1d6 alignment "
            "damage, and 1d6 additional negative and positive damage for a true neutral deity."
        ),
    },
    "divine_aura": {
        "rank": 8,
        "spell_type": "spell",
        "school": "abjuration",
        "traditions": ["divine"],
        "traits": ["aura"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "10-foot emanation",
        "targets": "allies in the area",
        "duration": "sustained up to 1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "Divine power wards the targets, granting each a status bonus to AC and saves "
            "while in the area, with stronger protection against creatures of opposite alignment."
        ),
        "description_snippet": "Ward allies in an aura against hostile aligned creatures.",
        "effects": {
            "description": (
                "Divine power wards the targets, granting each a +1 status bonus to AC and "
                "saves while in the area. Choose an alignment your deity has (chaotic, "
                "evil, good, or lawful). You can't cast this spell if you don't have a "
                "deity or your deity is true neutral. This spell gains the trait of the "
                "alignment you chose. The bonuses granted by the spell increase to +2 "
                "against attacks by and effects created by creatures with an alignment "
                "opposite to the spell, and increase to +4 against effects created by such "
                "creatures that attempt to impose the controlled condition and against "
                "attacks made by creatures summoned by anything opposite in alignment to "
                "your divine aura. When a creature of opposite alignment hits a target with "
                "a melee attack, the creature must succeed at a Will save or be blinded for "
                "1 minute. It's then temporarily immune for 1 minute. The first time you "
                "Sustain the Spell each round, the divine aura's radius grows 10 feet."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["blinded"],
        "source_line_start": 51826,
        "source_line_end": 51882,
        "raw_text_block": (
            "DIVINE AURA\n"
            "SPELL 8\n"
            "ABJURATION\n"
            "AURA\n"
            "Traditions divine\n"
            "Cast [two-actions] somatic, verbal\n"
            "Area 10-foot emanation; Targets allies in the area\n"
            "Duration sustained up to 1 minute\n"
            "Divine power wards the targets, granting each a +1 status bonus to AC and "
            "saves while in the area.\n"
            "Choose an alignment your deity has (chaotic, evil, good, or lawful). You "
            "can't cast this spell if you don't have a deity or your deity is true neutral. "
            "This spell gains the trait of the alignment you chose. The bonuses granted by "
            "the spell increase to +2 against attacks by and effects created by creatures "
            "with an alignment opposite to the spell. These bonuses increase to +4 against "
            "effects created by such creatures that attempt to impose the controlled "
            "condition on a target of your divine aura, as well as against attacks made by "
            "creatures summoned by anything opposite in alignment to your divine aura.\n"
            "When a creature of opposite alignment hits a target with a melee attack, the "
            "creature must succeed at a Will save or be blinded for 1 minute. It's then "
            "temporarily immune for 1 minute.\n"
            "The first time you Sustain the Spell each round, the divine aura's radius "
            "grows 10 feet."
        ),
    },
    "divine_inspiration": {
        "rank": 8,
        "spell_type": "spell",
        "school": "enchantment",
        "traditions": ["divine"],
        "traits": ["mental"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 willing creature",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You infuse a target with spiritual energy, refreshing its magic. It regains a "
            "spent 6th-level or lower prepared spell, spell slot, or Focus Points."
        ),
        "description_snippet": "Refresh a willing creature's spellcasting resources.",
        "effects": {
            "description": (
                "You infuse a target with spiritual energy, refreshing its magic. If it "
                "prepares spells, it recovers one 6th-level or lower spell it previously "
                "cast today and can cast that spell again. If it spontaneously casts "
                "spells, it recovers one of its 6th-level or lower spell slots. If it has "
                "a focus pool, it regains its Focus Points, as if it had Refocused."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["none"],
        "source_line_start": 51895,
        "source_line_end": 51907,
        "raw_text_block": (
            "DIVINE INSPIRATION\n"
            "SPELL 8\n"
            "ENCHANTMENT\n"
            "MENTAL\n"
            "Traditions divine\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets 1 willing creature\n"
            "You infuse a target with spiritual energy, refreshing its magic. If it "
            "prepares spells, it recovers one 6th-level or lower spell it previously cast "
            "today and can cast that spell again. If it spontaneously casts spells, it "
            "recovers one of its 6th-level or lower spell slots. If it has a focus pool, "
            "it regains its Focus Points, as if it had Refocused."
        ),
    },
    "divine_decree": {
        "rank": 7,
        "spell_type": "spell",
        "school": "evocation",
        "traditions": ["divine"],
        "traits": ["none"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "40 feet",
        "area": "40-foot emanation",
        "targets": "NA",
        "duration": "varies",
        "save": "Fortitude",
        "save_type": "fortitude",
        "description": (
            "You utter a potent litany from your faith, a mandate that harms those who "
            "oppose your ideals. Choose an alignment your deity has; creatures in the area "
            "that oppose it must attempt a Fortitude save."
        ),
        "description_snippet": "Decree punishment against creatures opposed to your deity's ideals.",
        "effects": {
            "description": (
                "You utter a potent litany from your faith, a mandate that harms those who "
                "oppose your ideals. Choose an alignment your deity has (chaotic, evil, "
                "good, or lawful). You can't cast this spell if you don't have a deity or "
                "your deity is true neutral. This spell gains the trait of the alignment "
                "you chose. Creatures with an alignment that matches the one you chose are "
                "unaffected. Those that neither match nor oppose it treat the result of "
                "their saving throw as one degree better and don't suffer effects other "
                "than damage."
            ),
            "outcomes": {
                "critical_success": "The creature is unaffected.",
                "success": "The creature takes half damage.",
                "failure": "The creature takes full damage and is enfeebled 2 for 1 minute.",
                "critical_failure": (
                    "The creature takes double damage and is enfeebled 2 for 1 minute. On "
                    "your home plane, a creature that critically fails is banished with the "
                    "effect of a failed banishment save. A 10th-level creature or lower must "
                    "attempt a Will save. On a failure, it's paralyzed for 1 minute; on a "
                    "critical failure, it dies."
                ),
            },
        },
        "conditions_caused": ["enfeebled", "paralyzed"],
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The damage increases by 1d10, and the level of creatures that must "
                    "attempt a second save on a critical failure increases by 2."
                ),
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The damage increases by 1d10, and the level of creatures that must "
                    "attempt a second save on a critical failure increases by 2."
                ),
            }
        ],
        "source_line_start": 51801,
        "source_line_end": 51824,
        "raw_text_block": (
            "DIVINE DECREE\n"
            "SPELL 7\n"
            "EVOCATION\n"
            "Traditions divine\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 40 feet; Area 40-foot emanation\n"
            "Saving Throw Fortitude; Duration varies\n"
            "You utter a potent litany from your faith, a mandate that harms those who "
            "oppose your ideals. Choose an alignment your deity has (chaotic, evil, good, "
            "or lawful). You can't cast this spell if you don't have a deity or your deity "
            "is true neutral. This spell gains the trait of the alignment you chose. You "
            "deal 7d10 damage to creatures in the area; each creature must attempt a "
            "Fortitude save. Creatures with an alignment that matches the one you chose are "
            "unaffected by the spell. Those that neither match nor oppose it treat the "
            "result of their saving throw as one degree better and don't suffer effects "
            "other than damage.\n"
            "Critical Success The creature is unaffected.\n"
            "Success The creature takes half damage.\n"
            "Failure The creature takes full damage and is enfeebled 2 for 1 minute.\n"
            "Critical Failure The creature takes double damage and is enfeebled 2 for 1 "
            "minute. On your home plane, a creature that critically fails is banished with "
            "the effect of a failed banishment save. A 10th-level creature or lower must "
            "attempt a Will save. On a failure, it's paralyzed for 1 minute; on a critical "
            "failure, it dies.\n"
            "Heightened (+1) The damage increases by 1d10, and the level of creatures that "
            "must attempt a second save on a critical failure increases by 2."
        ),
    },
    "eclipse_burst": {
        "rank": 7,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["arcane", "divine", "primal"],
        "traits": ["cold", "darkness", "negative"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "500 feet",
        "area": "60-foot burst",
        "targets": "NA",
        "duration": "NA",
        "save": "Reflex",
        "save_type": "reflex",
        "description": (
            "A globe of freezing darkness explodes in the area, dealing cold damage to "
            "creatures in the area and additional negative damage to living creatures."
        ),
        "description_snippet": "Explode a globe of freezing darkness that harms the living most.",
        "effects": {
            "description": (
                "A globe of freezing darkness explodes in the area, dealing 8d10 cold "
                "damage to creatures in the area, plus 8d4 additional negative damage to "
                "living creatures. If the globe overlaps with an area of magical light or "
                "affects a creature affected by magical light, eclipse burst attempts to "
                "counteract the light effect."
            ),
            "outcomes": {
                "critical_success": "The creature is unaffected.",
                "success": "The creature takes half damage.",
                "failure": "The creature takes full damage.",
                "critical_failure": (
                    "The creature takes double damage and becomes blinded by the darkness "
                    "for an unlimited duration."
                ),
            },
        },
        "conditions_caused": ["blinded"],
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The cold damage increases by 1d10 and the negative damage against the "
                    "living increases by 1d4."
                ),
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The cold damage increases by 1d10 and the negative damage against the "
                    "living increases by 1d4."
                ),
            }
        ],
        "source_line_start": 52416,
        "source_line_end": 52464,
        "raw_text_block": (
            "ECLIPSE BURST\n"
            "COLD\n"
            "DARKNESS\n"
            "SPELL 7\n"
            "NECROMANCY\n"
            "NEGATIVE\n"
            "Traditions arcane, divine, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 500 feet; Area 60-foot burst\n"
            "Saving Throw Reflex\n"
            "A globe of freezing darkness explodes in the area, dealing 8d10 cold damage "
            "to creatures in the area, plus 8d4 additional negative damage to living "
            "creatures. Each creature in the area must attempt a Reflex save.\n"
            "If the globe overlaps with an area of magical light or affects a creature "
            "affected by magical light, eclipse burst attempts to counteract the light "
            "effect.\n"
            "Critical Success The creature is unaffected.\n"
            "Success The creature takes half damage.\n"
            "Failure The creature takes full damage.\n"
            "Critical Failure The creature takes double damage and becomes blinded by the "
            "darkness for an unlimited duration.\n"
            "Heightened (+1) The cold damage increases by 1d10 and the negative damage "
            "against the living increases by 1d4."
        ),
    },
    "enlarge": {
        "rank": 2,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["arcane", "primal"],
        "traits": ["none"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "1 willing creature",
        "duration": "5 minutes",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "Bolstered by magical power, the target grows to size Large. Its equipment "
            "grows with it but returns to natural size if removed. The creature is "
            "clumsy 1, its reach increases, and it gains a status bonus to melee "
            "damage. This spell has no effect on a Large or larger creature."
        ),
        "description_snippet": "Increase a willing creature's size and melee reach.",
        "heightened": [
            {
                "label": "4th",
                "type": "fixed_rank",
                "rank": 4,
                "text": (
                    "The creature instead grows to size Huge. The status bonus to melee "
                    "damage is +4 and the creature's reach increases further. The spell "
                    "has no effect on a Huge or larger creature."
                ),
            },
            {
                "label": "6th",
                "type": "fixed_rank",
                "rank": 6,
                "text": (
                    "Choose either the 2nd-level or 4th-level version of this spell and "
                    "apply its effects to up to 10 willing creatures."
                ),
            },
        ],
        "heightened_scaling": [
            {
                "label": "4th",
                "type": "fixed_rank",
                "rank": 4,
                "text": (
                    "The creature instead grows to size Huge. The status bonus to melee "
                    "damage is +4 and the creature's reach increases further. The spell "
                    "has no effect on a Huge or larger creature."
                ),
            },
            {
                "label": "6th",
                "type": "fixed_rank",
                "rank": 6,
                "text": (
                    "Choose either the 2nd-level or 4th-level version of this spell and "
                    "apply its effects to up to 10 willing creatures."
                ),
            },
        ],
        "source_line_start": 52633,
        "source_line_end": 52650,
        "raw_text_block": (
            "ENLARGE\n"
            "TRANSMUTATION\n"
            "Traditions arcane, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 30 feet; Targets 1 willing creature\n"
            "Duration 5 minutes\n"
            "Bolstered by magical power, the target grows to size Large. Its equipment "
            "grows with it but returns to natural size if removed. The creature is clumsy "
            "1. Its reach increases by 5 feet (or by 10 feet if it started out Tiny), and "
            "it gains a +2 status bonus to melee damage. This spell has no effect on a "
            "Large or larger creature.\n"
            "Heightened (4th) The creature instead grows to size Huge. The status bonus to "
            "melee damage is +4 and the creature's reach increases by 10 feet (or 15 feet "
            "if the creature started out Tiny). The spell has no effect on a Huge or "
            "larger creature.\n"
            "Heightened (6th) Choose either the 2nd-level or 4th-level version of this "
            "spell and apply its effects to up to 10 willing creatures."
        ),
    },
    "inner_radiance_torrent": {
        "rank": 2,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine", "occult"],
        "traits": ["force", "light"],
        "cast": "[two-actions] to 2 rounds",
        "cast_actions": "none",
        "components": ["material", "somatic", "verbal"],
        "range": "NA",
        "area": "line 60 feet or longer",
        "targets": "NA",
        "duration": "NA",
        "save": "basic Reflex",
        "save_type": "basic_reflex",
        "description": (
            "You gradually manifest your spiritual energy into your cupped hands before "
            "firing off a storm of bolts and beams that deal force damage to all "
            "creatures in a line. The number of actions you spend determines the line's "
            "length, and the spell can counteract magical darkness."
        ),
        "description_snippet": "Fire a line of force-infused spiritual radiance.",
        "effects": {
            "description": (
                "You gradually manifest your spiritual energy into your cupped hands before "
                "firing off a storm of bolts and beams that deal 4d4 force damage to all "
                "creatures in a 60-foot line. Creatures in the area must attempt a basic "
                "Reflex save. On a critical failure, they're also blinded for 1 round."
            ),
            "outcomes": {
                "critical_failure": "The creature takes the spell's damage and is blinded for 1 round.",
            },
        },
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The initial damage, the additional damage for the 2-round casting "
                    "time, and the damage dealt while in your shining state each increase."
                ),
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The initial damage, as well as the additional damage for the 2-round "
                    "casting time, each increase by 4d4, and the damage to adjacent "
                    "creatures dealt while in your shining state increases by 1."
                ),
            }
        ],
        "source_line_start": 12855,
        "source_line_end": 12891,
        "raw_text_block": (
            "INNER RADIANCE TORRENT\n"
            "FORCE\n"
            "LIGHT\n"
            "SPELL 2\n"
            "NECROMANCY\n"
            "Traditions divine, occult\n"
            "Cast [two-actions] to 2 rounds\n"
            "Area line 60 feet or longer\n"
            "Saving Throw basic Reflex\n"
            "You gradually manifest your spiritual energy into your cupped hands before "
            "firing off a storm of bolts and beams that deal 4d4 force damage to all "
            "creatures in a 60-foot line. Creatures in the area must attempt a basic "
            "Reflex save. On a critical failure, they're also blinded for 1 round. The "
            "number of actions you spend when Casting this Spell determines the area. If "
            "the line passes through an area of magical darkness or targets a creature "
            "affected by magical darkness, inner radiance torrent attempts to counteract "
            "the darkness.\n"
            "[two-actions] (somatic, verbal) The line is 60 feet long.\n"
            "[three-actions] (material, somatic, verbal) The line is 120 feet long.\n"
            "Two Rounds The line is 120 feet long. If you spend 3 actions casting the "
            "spell, you can avoid finishing the spell and spend another 3 actions on your "
            "next turn to empower the spell even further. If you choose to do so, the "
            "damage dealt by this spell increases by 4d4, and you enter a shining state "
            "for 1 minute, causing you to glow with light and deal 1 force damage to "
            "creatures that end their turn adjacent to you.\n"
            "Heightened (+1) The initial damage, as well as the additional damage for the "
            "2-round casting time, each increase by 4d4, and the damage to adjacent "
            "creatures dealt while in your shining state increases by 1."
        ),
    },
    "instant_armor": {
        "rank": 2,
        "spell_type": "spell",
        "school": "conjuration",
        "traditions": ["arcane", "divine", "occult", "primal"],
        "traits": ["contingency", "extradimensional"],
        "cast": "10 minutes (material, somatic, verbal)",
        "cast_actions": "10_minutes",
        "components": ["material", "somatic", "verbal"],
        "range": "NA",
        "area": "NA",
        "targets": "NA",
        "duration": "24 hours",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "Upon casting this spell, the armor you're wearing is whisked away into an "
            "extradimensional space linked to you. It remains ready to snap back onto "
            "your body with the Armor Up! action until the spell ends."
        ),
        "description_snippet": "Store your armor extradimensionally until you call it back.",
        "effects": {
            "description": (
                "Upon casting this spell, the armor you're wearing is whisked away into an "
                "extradimensional space that's linked to you. If the armor is magical and "
                "invested by you, it remains invested while in this space, though you don't "
                "gain its benefits. You then gain the Armor Up! action; once you use the "
                "action, the spell ends. If the action hasn't been used by the time the "
                "spell's duration ends, the extradimensional space collapses, ejecting the "
                "armor's pieces on the ground under you."
            ),
            "outcomes": {},
        },
        "source_line_start": 12893,
        "source_line_end": 12914,
        "raw_text_block": (
            "INSTANT ARMOR\n"
            "CONJURATION\n"
            "CONTINGENCY\n"
            "SPELL 2\n"
            "EXTRADIMENSIONAL\n"
            "Traditions arcane, divine, occult, primal\n"
            "Cast 10 minutes (material, somatic, verbal)\n"
            "Duration 24 hours\n"
            "Upon casting this spell, the armor you're wearing is whisked away into an "
            "extradimensional space that's linked to you. If the armor is magical and "
            "invested by you, it remains invested while in this space, though you don't "
            "gain its benefits. You then gain the Armor Up! action; once you use the "
            "action, the spell ends. If the action hasn't been used by the time the "
            "spell's duration ends, the extradimensional space collapses, ejecting the "
            "armor's pieces on the ground under you.\n"
            "Armor Up! [one-action] (manipulate) Effect You snap your fingers. The armor "
            "returns to your body."
        ),
    },
    "persistent_servant": {
        "rank": 2,
        "spell_type": "spell",
        "school": "conjuration",
        "traditions": ["arcane", "occult"],
        "traits": ["none"],
        "cast": "1 minute (material, somatic, verbal)",
        "cast_actions": "1_minute",
        "components": ["material", "somatic", "verbal"],
        "range": "120 feet",
        "area": "60-foot burst",
        "targets": "NA",
        "duration": "until your next daily preparations",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You call forth an unseen servant and task it to perform a specific chore "
            "repeatedly within the spell's area. It keeps working without needing "
            "Sustain a Spell, but it can't leave the area or handle tasks requiring "
            "timing, discretion, or delicate judgment."
        ),
        "description_snippet": "Summon an unseen servant to repeat a simple chore.",
        "effects": {
            "description": (
                "You call forth an unseen servant and task it to perform a specific chore "
                "repeatedly. Choose a basic instruction, such as sweeping the floor or "
                "picking up all objects from the floor and putting them in a designated "
                "bin. The servant performs the task over and over again throughout the "
                "duration, though it can't ever leave the spell's area.\n"
                "The servant isn't a minion, and you don't need to Sustain the Spell in "
                "order for it to continue to act. However, it acts on its own time, and "
                "thus can't accomplish anything useful during an encounter.\n"
                "Tasks that rely on timing, discretion, or significant manual dexterity "
                "are doomed to failure."
            ),
            "outcomes": {},
        },
        "source_line_start": 13909,
        "source_line_end": 13938,
        "raw_text_block": (
            "PERSISTENT SERVANT\n"
            "SPELL 2\n"
            "CONJURATION\n"
            "Tradition arcane, occult\n"
            "Cast 1 minute (material, somatic, verbal)\n"
            "Range 120 feet; Area 60-foot burst\n"
            "Duration until your next daily preparations\n"
            "You call forth an unseen servant and task it to perform a specific chore "
            "repeatedly. Choose a basic instruction, such as sweeping the floor, or "
            "picking up all objects from the floor and putting them in a designated bin. "
            "The servant performs the task over and over again throughout the duration, "
            "though it can't ever leave the spell's area.\n"
            "The servant isn't a minion, and you don't need to Sustain the Spell in "
            "order for it to continue to act. However, it acts on its own time, and thus "
            "can't accomplish anything useful during an encounter, even if an encounter "
            "happens within the spell's range.\n"
            "Tasks that rely on timing, discretion, or significant manual dexterity are "
            "doomed to failure."
        ),
    },
    "one_with_the_land": {
        "rank": 9,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["primal"],
        "traits": ["earth", "plant"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "NA",
        "targets": "NA",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You merge into an adjacent natural feature and control the nearby environment. "
            "While merged, you gain heightened awareness of the terrain and can lash out "
            "through it."
        ),
        "description_snippet": "Merge into a natural feature and command the nearby terrain.",
        "effects": {
            "description": (
                "You merge with an adjacent natural feature with enough volume to fit you "
                "and your worn and held possessions, such as the ground or a large tree. "
                "Your merged form is visible within the feature, and creatures can target "
                "and attack you normally, though you have cover and can use it to Hide or "
                "Take Cover within the feature. You can cast spells while in the feature "
                "as long as they don't require line of effect beyond the feature. While "
                "merged, you become aware of the surrounding terrain features, gain "
                "tremorsense with a range of 200 feet, can make terrain vengeance Strikes, "
                "can alter the environmental temperature, and can create or remove "
                "difficult terrain caused by natural terrain in a 20-foot burst within "
                "200 feet."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["none"],
        "source_line_start": 13656,
        "source_line_end": 13698,
        "raw_text_block": (
            "ONE WITH THE LAND\n"
            "EARTH\n"
            "PLANT\n"
            "SPELL 9\n"
            "TRANSMUTATION\n"
            "Traditions primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Duration 1 minute\n"
            "You merge with an adjacent natural feature with enough volume to fit you and "
            "your worn and held possessions, such as the ground or a large tree. Your "
            "merged form is visible within the feature, and creatures can target and "
            "attack you normally, though you have cover and can use it to Hide or Take "
            "Cover within the feature. You can cast spells while in the feature as long as "
            "they don't require line of effect beyond the feature. You can Dismiss the spell.\n"
            "While merged, you gain the following additional benefits: you immediately "
            "become aware of the surrounding terrain features and gain tremorsense as an "
            "imprecise sense with a range of 200 feet; you can make terrain vengeance "
            "Strikes that target creatures within 60 feet and deal 5d12 bludgeoning, "
            "piercing, or slashing damage; you can alter the environmental temperature by "
            "one step; and you can create or remove difficult terrain caused by natural "
            "terrain in a 20-foot burst within 200 feet. All of your alterations to the "
            "land end when the spell ends. Significant physical damage to the natural "
            "feature while you are inside it expels you and deals 10d6 damage to you."
        ),
    },
    "nullify": {
        "rank": 10,
        "spell_type": "spell",
        "school": "abjuration",
        "traditions": ["arcane", "divine", "occult", "primal"],
        "traits": ["none"],
        "cast": "[reaction] somatic, verbal",
        "cast_actions": "reaction",
        "components": ["somatic", "verbal"],
        "trigger": "A foe within range casts a 9th-level or lower spell.",
        "range": "120 feet",
        "area": "NA",
        "targets": "the triggering spell",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You instantly destroy an incoming spell, automatically counteracting it at the "
            "cost of magical feedback through your body. You lose 1d8 Hit Points per level "
            "of the triggering spell."
        ),
        "description_snippet": "Automatically counteract a spell and take feedback damage.",
        "effects": {
            "description": (
                "You instantly destroy the incoming spell, though at the cost of sending "
                "magical feedback through your body. You automatically counteract the spell, "
                "but the feedback brings you unavoidable harm. You lose 1d8 Hit Points per "
                "level of the triggering spell."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["none"],
        "source_line_start": 13611,
        "source_line_end": 13625,
        "raw_text_block": (
            "NULLIFY\n"
            "SPELL 10\n"
            "ABJURATION\n"
            "Traditions arcane, divine, occult, primal\n"
            "Cast [reaction] somatic, verbal; Trigger A foe within range casts a 9th-level "
            "or lower spell.\n"
            "Range 120 feet; Targets the triggering spell\n"
            "You instantly destroy the incoming spell, though at the cost of sending "
            "magical feedback through your body. You automatically counteract the spell, "
            "but the feedback brings you unavoidable harm. You lose 1d8 Hit Points per "
            "level of the triggering spell."
        ),
    },
    "reapers_lantern": {
        "rank": 2,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine", "occult", "primal"],
        "traits": ["death", "light"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "5-foot emanation",
        "targets": "NA",
        "duration": "1 minute",
        "save": "Fortitude",
        "save_type": "fortitude",
        "description": (
            "You call forth a ghostly lantern that hampers healing for the living and "
            "weakens undead in its glow. The lantern sheds light around you, and you can "
            "expand its emanation over time."
        ),
        "effects": {
            "description": (
                "You call forth a ghostly lantern that guides the living toward death and "
                "the undead toward true death. It sheds bright light in the spell's area "
                "and dim light to twice that area. Living creatures that fail their "
                "Fortitude saves gain only half the normal benefit from healing effects "
                "while within the area. Undead targets that fail their Fortitude saves "
                "become enfeebled 1 while within the area."
            ),
            "outcomes": {
                "failure_living": "Living creatures gain only half the normal benefit from healing effects while within the area.",
                "failure_undead": "Undead targets become enfeebled 1 while within the area.",
            },
        },
        "description_snippet": "A ghostly lantern weakens the living and the undead differently.",
        "source_line_start": 31594,
        "source_line_end": 31636,
        "raw_text_block": (
            "REAPER'S LANTERN\n"
            "DEATH\n"
            "LIGHT\n"
            "SPELL 2\n"
            "NECROMANCY\n"
            "Traditions divine, occult, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Area 5-foot emanation\n"
            "Saving Throw Fortitude; Duration 1 minute\n"
            "You call forth a ghostly lantern that guides the living toward death and the "
            "undead toward true death. It sheds bright light in the spell's area, and dim "
            "light to twice that area. Though the lantern is insubstantial, you must keep "
            "a hand free to hold it or the spell ends.\n"
            "Living creatures and undead in the area when you Cast the Spell, or that "
            "enter the area later, must attempt Fortitude saves. Living creatures that "
            "fail their Fortitude saves gain only half the normal benefit from healing "
            "effects while within the area. Undead targets that fail their Fortitude saves "
            "become enfeebled 1 while within the area.\n"
            "Once per turn, starting on the turn after you cast reaper's lantern, you can "
            "use a single action with the concentrate trait to increase the emanation's "
            "radius by 5 feet. When you do so, you force creatures in the area that "
            "haven't yet attempted a save against reaper's lantern to attempt one."
        ),
    },
    "remake": {
        "rank": 10,
        "spell_type": "spell",
        "rarity": "uncommon",
        "school": "conjuration",
        "traditions": ["arcane", "divine", "occult", "primal"],
        "traits": ["none"],
        "cast": "1 hour (material, somatic, verbal)",
        "cast_actions": "1_hour",
        "components": ["material", "somatic", "verbal"],
        "range": "5 feet",
        "area": "NA",
        "targets": "NA",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You fully re-create an object from nothing, even if it was destroyed, so long "
            "as you can picture it and have a remnant of it. The item reassembles in "
            "perfect condition, though temporary magical uses remain expended."
        ),
        "description_snippet": "Re-create a destroyed object from a remnant.",
        "effects": {
            "description": (
                "You fully re-create an object from nothing, even if the object was "
                "destroyed. To do so, you must be able to picture the object in your mind, "
                "and the material component must be a remnant of the item. The spell fails "
                "if your imagination relied on too much guesswork; if the object would be "
                "too large to fit in a 5-foot cube; if the object still exists and you were "
                "simply not aware of it; or if the object is an artifact, has a level over "
                "20, or has similar vast magical power. The item reassembles in perfect "
                "condition, restoring constant magical properties but not temporary ones "
                "such as charges or one-time uses."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["none"],
        "source_line_start": 57196,
        "source_line_end": 57218,
        "raw_text_block": (
            "REMAKE\n"
            "SPELL 10\n"
            "UNCOMMON\n"
            "CONJURATION\n"
            "Traditions arcane, divine, occult, primal\n"
            "Cast 1 hour (material, somatic, verbal)\n"
            "Range 5 feet\n"
            "You fully re-create an object from nothing, even if the object was destroyed. "
            "To do so, you must be able to picture the object in your mind. Additionally, "
            "the material component must be a remnant of the item, no matter how small or "
            "insignificant. The spell fails if your imagination relied on too much "
            "guesswork; if the object would be too large to fit in a 5-foot cube; if the "
            "object still exists and you were simply not aware of it; or if the object is "
            "an artifact, has a level over 20, or has similar vast magical power.\n"
            "The item reassembles in perfect condition. Even if your mental image was of a "
            "damaged or weathered object, the new one is in this perfected form. If the "
            "object was magical, this spell typically restores its constant magical "
            "properties, but not any temporary ones, such as charges or one-time uses. An "
            "item with charges or uses per day has all of its uses expended when remade, "
            "but it replenishes them normally thereafter."
        ),
    },
    "revival": {
        "rank": 10,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine", "primal"],
        "traits": ["healing", "positive"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "dead creatures and living creatures of your choice within range",
        "duration": "sustained up to 1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "A burst of healing energy soothes living creatures and temporarily rouses "
            "those recently slain. Living targets regain 10d8+40 Hit Points, and dead "
            "targets can return briefly with temporary Hit Points."
        ),
        "description_snippet": "Heal the living and temporarily raise the recently dead.",
        "effects": {
            "description": (
                "A burst of healing energy soothes living creatures and temporarily rouses "
                "those recently slain. All living targets regain 10d8+40 Hit Points. In "
                "addition, you return any number of dead targets to life temporarily, with "
                "the same effects and limitations as raise dead. The raised creatures have "
                "temporary Hit Points equal to the Hit Points you gave living creatures, "
                "but no normal Hit Points. They can't regain Hit Points or gain temporary "
                "Hit Points in other ways, and once revival's duration ends, they lose all "
                "temporary Hit Points and die. Revival can't resurrect creatures killed by "
                "disintegrate or a death effect, and it has no effect on undead."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["none"],
        "source_line_start": 57566,
        "source_line_end": 57586,
        "raw_text_block": (
            "REVIVAL\n"
            "HEALING\n"
            "POSITIVE\n"
            "SPELL 10\n"
            "NECROMANCY\n"
            "Traditions divine, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 30 feet; Targets dead creatures and living creatures of your choice "
            "within range\n"
            "Duration sustained up to 1 minute\n"
            "A burst of healing energy soothes living creatures and temporarily rouses "
            "those recently slain. All living targets regain 10d8+40 Hit Points. In "
            "addition, you return any number of dead targets to life temporarily, with the "
            "same effects and limitations as raise dead. The raised creatures have a "
            "number of temporary Hit Points equal to the Hit Points you gave living "
            "creatures, but no normal Hit Points. The raised creatures can't regain Hit "
            "Points or gain temporary Hit Points in other ways, and once revival's "
            "duration ends, they lose all temporary Hit Points and die. Revival can't "
            "resurrect creatures killed by disintegrate or a death effect. It has no "
            "effect on undead."
        ),
    },
    "shatter": {
        "rank": 2,
        "spell_type": "spell",
        "school": "evocation",
        "traditions": ["occult", "primal"],
        "traits": ["sonic"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "1 unattended object",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "A high-frequency sonic attack shatters a nearby object. The spell deals "
            "sonic damage to the target and ignores a limited amount of its Hardness."
        ),
        "description_snippet": "Break an unattended object with a sonic blast.",
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d10, and the Hardness the spell ignores increases by 2.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d10, and the Hardness the spell ignores increases by 2.",
            }
        ],
        "source_line_start": 58038,
        "source_line_end": 58048,
        "raw_text_block": (
            "SHATTER\n"
            "SPELL 2\n"
            "SONIC\n"
            "Traditions occult, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 30 feet; Targets 1 unattended object\n"
            "A high-frequency sonic attack shatters a nearby object. You deal 2d10 sonic "
            "damage to the object, ignoring the object's Hardness if it is 4 or lower.\n"
            "Heightened (+1) The damage increases by 1d10, and the Hardness the spell "
            "ignores increases by 2."
        ),
    },
    "shrink": {
        "rank": 2,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["arcane", "primal"],
        "traits": ["polymorph"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "1 willing creature",
        "duration": "5 minutes",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You warp space to make a creature smaller. The target becomes Tiny, its "
            "gear shrinks with it, and its reach changes to 0 feet."
        ),
        "description_snippet": "Reduce a willing creature to Tiny size.",
        "heightened": [
            {
                "label": "6th",
                "type": "fixed_rank",
                "rank": 6,
                "text": "The spell can target up to 10 creatures.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "6th",
                "type": "fixed_rank",
                "rank": 6,
                "text": "The spell can target up to 10 creatures.",
            }
        ],
        "source_line_start": 58119,
        "source_line_end": 58129,
        "raw_text_block": (
            "SHRINK\n"
            "SPELL 2\n"
            "TRANSMUTATION\n"
            "Traditions arcane, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 30 feet; Targets 1 willing creature\n"
            "Duration 5 minutes\n"
            "You warp space to make a creature smaller. The target shrinks to become Tiny "
            "in size. Its equipment shrinks with it but returns to its original size if "
            "removed. The creature's reach changes to 0 feet. This spell has no effect on "
            "a Tiny creature.\n"
            "Heightened (6th) The spell can target up to 10 creatures."
        ),
    },
    "spider_climb": {
        "rank": 2,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["arcane", "primal"],
        "traits": ["none"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 creature",
        "duration": "10 minutes",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "Tiny clinging hairs sprout across the creature's hands and feet, letting "
            "it climb nearly any surface at full Speed."
        ),
        "description_snippet": "Give a creature a climb Speed.",
        "heightened": [
            {
                "label": "5th",
                "type": "fixed_rank",
                "rank": 5,
                "text": "The duration increases to 1 hour.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "5th",
                "type": "fixed_rank",
                "rank": 5,
                "text": "The duration increases to 1 hour.",
            }
        ],
        "source_line_start": 58553,
        "source_line_end": 58560,
        "raw_text_block": (
            "SPIDER CLIMB\n"
            "SPELL 2\n"
            "TRANSMUTATION\n"
            "Traditions arcane, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets 1 creature\n"
            "Duration 10 minutes\n"
            "Tiny clinging hairs sprout across the creature's hands and feet, offering "
            "purchase on nearly any surface. The target gains a climb Speed equal to its "
            "Speed.\n"
            "Heightened (5th) The duration increases to 1 hour."
        ),
    },
    "summon_elemental": {
        "rank": 2,
        "spell_type": "spell",
        "school": "conjuration",
        "traditions": ["arcane", "primal"],
        "traits": ["none"],
        "cast": "[three-actions] material, somatic, verbal",
        "cast_actions": "3_actions",
        "components": ["material", "somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "NA",
        "duration": "sustained up to 1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You conjure an elemental to fight for you. It functions like summon animal, "
            "except the summoned creature must be a common elemental of level 1 or lower."
        ),
        "description_snippet": "Conjure an elemental to fight on your behalf.",
        "heightened": [
            {
                "label": "As summon animal",
                "type": "special",
                "text": "Heightened As summon animal.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "As summon animal",
                "type": "special",
                "text": "Heightened As summon animal.",
            }
        ],
        "source_line_start": 59184,
        "source_line_end": 59191,
        "raw_text_block": (
            "SUMMON ELEMENTAL\n"
            "SPELL 2\n"
            "CONJURATION\n"
            "Traditions arcane, primal\n"
            "Cast [three-actions] material, somatic, verbal\n"
            "Range 30 feet\n"
            "Duration sustained up to 1 minute\n"
            "You conjure an elemental to fight for you. This works like summon animal, "
            "except you summon a common creature that has the elemental trait and whose "
            "level is 1 or lower.\n"
            "Heightened As summon animal."
        ),
    },
    "water_breathing": {
        "rank": 2,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["arcane", "divine", "primal"],
        "traits": ["none"],
        "cast": "1 minute (somatic, verbal)",
        "cast_actions": "1_minute",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "up to 5 creatures",
        "duration": "1 hour",
        "save": "NA",
        "save_type": "NA",
        "description": "The targets can breathe underwater.",
        "description_snippet": "Allow creatures to breathe underwater.",
        "heightened": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": "The duration increases to 8 hours.",
            },
            {
                "label": "4th",
                "type": "fixed_rank",
                "rank": 4,
                "text": "The duration increases to until your next daily preparations.",
            },
        ],
        "heightened_scaling": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": "The duration increases to 8 hours.",
            },
            {
                "label": "4th",
                "type": "fixed_rank",
                "rank": 4,
                "text": "The duration increases to until your next daily preparations.",
            },
        ],
        "source_line_start": 60658,
        "source_line_end": 60667,
        "raw_text_block": (
            "WATER BREATHING\n"
            "SPELL 2\n"
            "TRANSMUTATION\n"
            "Traditions arcane, divine, primal\n"
            "Cast 1 minute (somatic, verbal)\n"
            "Range 30 feet; Targets up to 5 creatures\n"
            "Duration 1 hour\n"
            "The targets can breathe underwater.\n"
            "Heightened (3rd) The duration increases to 8 hours.\n"
            "Heightened (4th) The duration increases to until your next daily preparations."
        ),
    },
    "feather_fall": {
        "rank": 1,
        "spell_type": "spell",
        "school": "abjuration",
        "traditions": ["arcane", "primal"],
        "traits": ["none"],
        "cast": "[reaction]",
        "cast_actions": "reaction",
        "components": ["none"],
        "range": "60 feet",
        "area": "NA",
        "targets": "1 falling creature",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You cause the air itself to arrest a fall. The target's fall slows to 60 feet "
            "per round, and the portion of the fall during the spell's duration doesn't "
            "count when calculating falling damage. If the target reaches the ground while "
            "the spell is in effect, it takes no damage from the fall."
        ),
        "description_snippet": "React to slow a falling creature's descent.",
        "source_line_start": 52256,
        "source_line_end": 52264,
        "raw_text_block": (
            "FEATHER FALL\n"
            "Range 60 feet; Targets 1 falling creature\n"
            "Duration 1 minute\n"
            "You cause the air itself to arrest a fall. The target's fall slows to 60 feet "
            "per round, and the portion of the fall during the spell's duration doesn't "
            "count when calculating falling damage. If the target reaches the ground while "
            "the spell is in effect, it takes no damage from the fall. The spell ends as "
            "soon as the target lands."
        ),
    },
    "devil_form": {
        "rank": 6,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["arcane", "divine"],
        "traits": ["evil", "lawful", "polymorph"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "NA",
        "targets": "NA",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You bind yourself to the power of Hell, transforming into a devil battle form. "
            "When you cast this spell, choose barbazu, erinys, osyluth, or sarglagon."
        ),
        "description_snippet": "Transform into a devil battle form.",
        "source_line_start": 11269,
        "source_line_end": 11347,
        "raw_text_block": (
            "DEVIL FORM\n"
            "EVIL\n"
            "LAWFUL\n"
            "SPELL 6\n"
            "POLYMORPH\n"
            "TRANSMUTATION\n"
            "Traditions arcane, divine\n"
            "Cast [two-actions] somatic, verbal\n"
            "Duration 1 minute\n"
            "You bind yourself to the power of Hell, transforming into a Medium devil "
            "battle form. When you cast this spell, choose barbazu, erinys, osyluth, or "
            "sarglagon. If you choose osyluth or sarglagon, the battle form is Large and "
            "you must have enough space to expand into or the spell is lost. While in this "
            "form you gain the devil and fiend traits. You have hands in this battle form "
            "and can use manipulate actions. You can Dismiss the spell.\n"
            "You gain the following statistics and abilities regardless of the form that "
            "you choose: AC = 22 + your level; 5 temporary Hit Points; resistance 5 to "
            "physical damage (except silver); weakness 5 to good; resistance 10 to fire; "
            "darkvision; one or more attacks specific to the battle form you use; and an "
            "Athletics modifier of +23, unless your own modifier is higher.\n"
            "Barbazu Speed 35 feet; Melee [one-action] glaive (deadly d8, forceful, reach "
            "10 feet), Damage 2d8+10 slashing plus 1d6 evil and 1d6 persistent bleed; "
            "Melee [one-action] beard, Damage 3d8 piercing plus 1d6 evil; Melee [one-action] "
            "claw (agile), Damage 3d6 slashing plus 1d6 evil.\n"
            "Erinys Speed 25 feet, fly 40 feet; Melee [one-action] longsword (versatile P), "
            "Damage 1d8+10 slashing plus 1d6 evil and 1d6 fire; Ranged [one-action] "
            "composite longbow (deadly d10, range increment 100 feet, volley), Damage "
            "1d8 piercing plus 1d6 evil and 1d6 fire.\n"
            "Osyluth Speed 35 feet, fly 30 feet; Melee [one-action] jaws, Damage 2d10+10 "
            "piercing plus 1d6 evil; Melee [one-action] claw (agile, reach 10 feet), Damage "
            "2d6 slashing plus 1d6 evil; Melee [one-action] stinger (reach 15 feet), Damage "
            "1d10 piercing plus 1d6 evil and 1d6 poison; Ranged [one-action] bone shard "
            "(range increment 30 feet), Damage 2d6 piercing plus 1d6 evil.\n"
            "Sarglagon Speed 25 feet, fly 25 feet, swim 30 feet; Melee [one-action] fangs, "
            "Damage 2d10+10 piercing plus 1d6 evil; Melee [one-action] tentacle arm "
            "(agile), Damage 1d8 bludgeoning plus 1d6 evil and 1d6 poison."
        ),
    },
    "field_of_life": {
        "rank": 6,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine", "primal"],
        "traits": ["positive"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "20-foot burst",
        "targets": "NA",
        "duration": "sustained up to 1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "A field of positive energy fills the area, exuding warmth and rejuvenating "
            "those within. Each living creature that starts its turn in the area regains "
            "1d8 Hit Points, and any undead creature that starts its turn in the area "
            "takes 1d8 positive damage."
        ),
        "description_snippet": "Create a positive energy field that heals the living and harms undead.",
        "effects": {
            "description": (
                "A field of positive energy fills the area, exuding warmth and "
                "rejuvenating those within. Each living creature that starts its turn in "
                "the area regains 1d8 Hit Points, and any undead creature that starts its "
                "turn in the area takes 1d8 positive damage."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["none"],
        "heightened": [
            {
                "label": "8th",
                "type": "fixed_rank",
                "rank": 8,
                "text": "The healing and damage increase to 1d10.",
            },
            {
                "label": "9th",
                "type": "fixed_rank",
                "rank": 9,
                "text": "The healing and damage increase to 1d12.",
            },
        ],
        "heightened_scaling": [
            {
                "label": "8th",
                "type": "fixed_rank",
                "rank": 8,
                "text": "The healing and damage increase to 1d10.",
            },
            {
                "label": "9th",
                "type": "fixed_rank",
                "rank": 9,
                "text": "The healing and damage increase to 1d12.",
            },
        ],
        "source_line_start": 48154,
        "source_line_end": 48155,
        "raw_text_block": (
            "FIELD OF LIFE\n"
            "SPELL 6\n"
            "NECROMANCY\n"
            "POSITIVE\n"
            "Traditions divine, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 30 feet; Area 20-foot burst\n"
            "Duration sustained up to 1 minute\n"
            "A field of positive energy fills the area, exuding warmth and rejuvenating "
            "those within. Each living creature that starts its turn in the area regains "
            "1d8 Hit Points, and any undead creature that starts its turn in the area "
            "takes 1d8 positive damage.\n"
            "Heightened (8th) The healing and damage increase to 1d10.\n"
            "Heightened (9th) The healing and damage increase to 1d12."
        ),
    },
    "natures_reprisal": {
        "rank": 6,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["primal"],
        "traits": ["plant", "poison"],
        "cast": "[three-actions] material, somatic, verbal",
        "cast_actions": "3_actions",
        "components": ["material", "somatic", "verbal"],
        "range": "120 feet",
        "area": "all squares on the ground that contain plants in an 80-foot burst",
        "targets": "NA",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "Plant life within the area begins to writhe and lash out against your enemies. "
            "To your enemies, the area becomes difficult terrain, and areas already made "
            "difficult by plants become greater difficult terrain and hazardous terrain."
        ),
        "description_snippet": "Plant life writhes to impede and poison your enemies.",
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The poison damage of the hazardous terrain increases by 1.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The poison damage of the hazardous terrain increases by 1.",
            }
        ],
        "source_line_start": 13476,
        "source_line_end": 13498,
        "raw_text_block": (
            "NATURE'S REPRISAL\n"
            "PLANT\n"
            "POISON\n"
            "SPELL 6\n"
            "TRANSMUTATION\n"
            "Traditions primal\n"
            "Cast [three-actions] material, somatic, verbal\n"
            "Range 120 feet; Area all squares on the ground that contain plants in an "
            "80-foot burst\n"
            "Duration 1 minute\n"
            "The plant life within the area begins to writhe and lash out against your "
            "enemies as you call upon nature to impede your foes. To your enemies, the area "
            "becomes difficult terrain, and areas that were naturally difficult terrain due "
            "to plants become greater difficult terrain as well as hazardous terrain, "
            "dealing 6 poison damage to an enemy each time it enters an affected square.\n"
            "Heightened (+1) The poison damage of the hazardous terrain increases by 1."
        ),
    },
    "stone_tell": {
        "rank": 6,
        "spell_type": "spell",
        "school": "divination",
        "traditions": ["divine", "primal"],
        "traits": ["none"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "NA",
        "targets": "NA",
        "duration": "10 minutes",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You can ask questions of and receive answers from natural or worked stone. "
            "The natural spirits of the stone answer from a perspective shaped by the stone's "
            "nature and what has touched or been concealed by it."
        ),
        "description_snippet": "Speak to the spirits within natural or worked stone.",
        "source_line_start": 58915,
        "source_line_end": 58928,
        "raw_text_block": (
            "STONE TELL\n"
            "Traditions divine, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Duration 10 minutes\n"
            "You can ask questions of and receive answers from natural or worked stone. "
            "While stone is not intelligent, you speak with the natural spirits of the "
            "stone, which have a personality colored by the type of stone, as well as by "
            "the type of structure the stone is part of, for worked stone. A stone's "
            "perspective, perception, and knowledge give it a worldview different enough "
            "from a human's that it doesn't consider the same details important. Stones "
            "can mostly answer questions about creatures that touched them in the past and "
            "what is concealed beneath or behind them."
        ),
    },
    "stone_to_flesh": {
        "rank": 6,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["divine", "primal"],
        "traits": ["earth"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "petrified creature or human-size stone object",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You convert stone into flesh and blood. You can restore a petrified creature "
            "to its normal state or turn a stone object into an inert mass of flesh in "
            "roughly the same shape."
        ),
        "description_snippet": "Turn a petrified creature or stone object into flesh.",
        "source_line_start": 58930,
        "source_line_end": 58952,
        "raw_text_block": (
            "STONE TO FLESH\n"
            "EARTH\n"
            "SPELL 6\n"
            "TRANSMUTATION\n"
            "Traditions divine, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets petrified creature or human-size stone object\n"
            "Manipulating the fundamental particles of matter, you convert stone into "
            "flesh and blood. You restore a petrified creature to its normal state or "
            "transform a stone object into a mass of inert flesh (without stone's "
            "Hardness) in roughly the same shape."
        ),
    },
    "finger_of_death": {
        "rank": 7,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine", "primal"],
        "traits": ["death"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "1 living creature",
        "duration": "NA",
        "save": "basic Fortitude",
        "save_type": "basic_fortitude",
        "description": (
            "You point your finger toward the target and speak a word of slaying. You deal "
            "70 negative damage to the target, and if this reduces it to 0 Hit Points, it "
            "dies instantly."
        ),
        "description_snippet": "Speak a word of slaying that can kill outright.",
        "effects": {
            "description": (
                "You point your finger toward the target and speak a word of slaying. You "
                "deal 70 negative damage to the target. If the damage from finger of death "
                "reduces the target to 0 Hit Points, the target dies instantly."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["none"],
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 10.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 10.",
            }
        ],
        "source_line_start": 53015,
        "source_line_end": 53026,
        "raw_text_block": (
            "FINGER OF DEATH\n"
            "DEATH\n"
            "Traditions divine, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 30 feet; Targets 1 living creature\n"
            "Saving Throw basic Fortitude\n"
            "You point your finger toward the target and speak a word of slaying. You deal "
            "70 negative damage to the target. If the damage from finger of death reduces "
            "the target to 0 Hit Points, the target dies instantly.\n"
            "Heightened (+1) The damage increases by 10."
        ),
    },
    "regenerate": {
        "rank": 7,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine", "primal"],
        "traits": ["healing", "positive"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 willing living creature",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "An infusion of positive energy grants a creature continuous healing. The "
            "target gains regeneration 15, regrows damaged organs, and can reattach "
            "severed body parts during the spell."
        ),
        "description_snippet": "Grant a living creature regeneration and regrowth.",
        "effects": {
            "description": (
                "An infusion of positive energy grants a creature continuous healing. The "
                "target temporarily gains regeneration 15, which restores 15 Hit Points to "
                "it at the start of each of its turns. While it has regeneration, the "
                "target can't die from damage and its dying condition can't increase to a "
                "value that would kill it, though if its wounded value becomes 4 or higher, "
                "it stays unconscious until its wounds are treated. If the target takes "
                "acid or fire damage, its regeneration deactivates until after the end of "
                "its next turn. Each time the creature regains Hit Points from regeneration, "
                "it also regrows one damaged or ruined organ. During the spell's duration, "
                "the creature can also reattach severed body parts by spending an Interact "
                "action to hold the body part to the area it was severed from."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["none"],
        "heightened": [
            {
                "label": "9th",
                "type": "fixed_rank",
                "rank": 9,
                "text": "The regeneration increases to 20.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "9th",
                "type": "fixed_rank",
                "rank": 9,
                "text": "The regeneration increases to 20.",
            }
        ],
        "source_line_start": 57151,
        "source_line_end": 57194,
        "raw_text_block": (
            "REGENERATE\n"
            "HEALING\n"
            "SPELL 7\n"
            "NECROMANCY\n"
            "POSITIVE\n"
            "Traditions divine, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets 1 willing living creature\n"
            "Duration 1 minute\n"
            "An infusion of positive energy grants a creature continuous healing. The "
            "target temporarily gains regeneration 15, which restores 15 Hit Points to it "
            "at the start of each of its turns. While it has regeneration, the target "
            "can't die from damage and its dying condition can't increase to a value that "
            "would kill it, though if its wounded value becomes 4 or higher, it stays "
            "unconscious until its wounds are treated. If the target takes acid or fire "
            "damage, its regeneration deactivates until after the end of its next turn. "
            "Each time the creature regains Hit Points from regeneration, it also regrows "
            "one damaged or ruined organ. During the spell's duration, the creature can "
            "also reattach severed body parts by spending an Interact action to hold the "
            "body part to the area it was severed from.\n"
            "Heightened (9th) The regeneration increases to 20."
        ),
    },
    "sunburst": {
        "rank": 7,
        "spell_type": "spell",
        "school": "evocation",
        "traditions": ["divine", "primal"],
        "traits": ["fire", "light", "positive"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "500 feet",
        "area": "60-foot burst",
        "targets": "NA",
        "duration": "NA",
        "save": "Reflex",
        "save_type": "reflex",
        "description": (
            "A powerful globe of searing sunlight explodes in the area, dealing fire damage "
            "to all creatures there and additional positive damage to undead creatures."
        ),
        "description_snippet": "Explode a globe of sunlight that scorches undead especially hard.",
        "effects": {
            "description": (
                "A powerful globe of searing sunlight explodes in the area, dealing 8d10 "
                "fire damage to all creatures in the area, plus 8d10 additional positive "
                "damage to undead creatures. If the globe overlaps with an area of magical "
                "darkness, sunburst attempts to counteract the darkness effect."
            ),
            "outcomes": {
                "critical_success": "The creature is unaffected.",
                "success": "The creature takes half damage.",
                "failure": "The creature takes full damage.",
                "critical_failure": (
                    "The creature takes full damage and becomes blinded permanently."
                ),
            },
        },
        "conditions_caused": ["blinded"],
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The fire damage increases by 1d10, and the positive damage against "
                    "undead increases by 1d10."
                ),
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The fire damage increases by 1d10, and the positive damage against "
                    "undead increases by 1d10."
                ),
            }
        ],
        "source_line_start": 59294,
        "source_line_end": 59332,
        "raw_text_block": (
            "SUNBURST\n"
            "EVOCATION\n"
            "FIRE\n"
            "SPELL 7\n"
            "LIGHT\n"
            "POSITIVE\n"
            "Traditions divine, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 500 feet; Area 60-foot burst\n"
            "Saving Throw Reflex\n"
            "A powerful globe of searing sunlight explodes in the area, dealing 8d10 fire "
            "damage to all creatures in the area, plus 8d10 additional positive damage to "
            "undead creatures. Each creature in the area must attempt a Reflex save.\n"
            "Critical Success The creature is unaffected.\n"
            "Success The creature takes half damage.\n"
            "Failure The creature takes full damage.\n"
            "Critical Failure The creature takes full damage and becomes blinded "
            "permanently.\n"
            "If the globe overlaps with an area of magical darkness, sunburst attempts to "
            "counteract the darkness effect.\n"
            "Heightened (+1) The fire damage increases by 1d10, and the positive damage "
            "against undead increases by 1d10."
        ),
    },
    "foresight": {
        "rank": 9,
        "spell_type": "spell",
        "school": "divination",
        "traditions": ["arcane", "divine", "occult"],
        "traits": ["mental", "prediction"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 creature",
        "duration": "1 hour",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You gain a sixth sense that warns you of danger that might befall the target. "
            "The target gains improved initiative and defenses, and the spell grants a "
            "reaction that twists hostile rolls."
        ),
        "description_snippet": "Grant a creature preternatural warning of danger.",
        "effects": {
            "description": (
                "You gain a sixth sense that warns you of danger that might befall the "
                "target of the spell. If you choose a creature other than yourself as the "
                "target, you create a psychic link through which you can inform the target "
                "of danger. Due to the amount of information this spell requires you to "
                "process, you can't have more than one foresight spell in effect at a "
                "time. While foresight is in effect, the target gains a +2 status bonus to "
                "initiative rolls and isn't flat-footed against undetected creatures or "
                "when flanked."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["none"],
        "source_line_start": 53286,
        "source_line_end": 53346,
        "raw_text_block": (
            "FORESIGHT\n"
            "DIVINATION\n"
            "SPELL 9\n"
            "MENTAL\n"
            "PREDICTION\n"
            "Traditions arcane, divine, occult\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets 1 creature\n"
            "Duration 1 hour\n"
            "You gain a sixth sense that warns you of danger that might befall the target "
            "of the spell. If you choose a creature other than yourself as the target, you "
            "create a psychic link through which you can inform the target of danger. This "
            "link is a mental effect. Due to the amount of information this spell requires "
            "you to process, you can't have more than one foresight spell in effect at a "
            "time. Casting foresight again ends the previous foresight. While foresight is "
            "in effect, the target gains a +2 status bonus to initiative rolls and isn't "
            "flat-footed against undetected creatures or when flanked. In addition, you "
            "gain the following reaction.\n"
            "Foresight [reaction] Trigger The target of foresight defends against a hostile "
            "creature or other danger; Effect If the hostile creature or danger forces the "
            "target to roll dice, the target rolls twice and uses the higher result, and "
            "this spell gains the fortune trait. But if the hostile creature or danger is "
            "rolling against the target, that hostile creature or danger rolls twice and "
            "uses the lower result, and this spell gains the misfortune trait."
        ),
    },
    "gate": {
        "rank": 10,
        "spell_type": "spell",
        "rarity": "uncommon",
        "school": "conjuration",
        "traditions": ["arcane", "divine", "occult"],
        "traits": ["teleportation"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "120 feet",
        "area": "NA",
        "targets": "NA",
        "duration": "sustained up to 1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You tear open a rift to another plane, creating a portal that creatures can "
            "travel through in either direction. The portal is vertical and circular, and "
            "appears at a location of your choice on the destination plane."
        ),
        "description_snippet": "Open a portal to another plane.",
        "effects": {
            "description": (
                "You tear open a rift to another plane, creating a portal that creatures "
                "can travel through in either direction. This portal is vertical and "
                "circular, with a radius of 40 feet. The portal appears at a location of "
                "your choice on the destination plane, assuming you have a clear idea of "
                "both the destination's location on the plane and what the destination "
                "looks like. If you attempt to create a gate into or out of the realm of a "
                "deity or another powerful being, that being can prevent the gate from forming."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["none"],
        "source_line_start": 53370,
        "source_line_end": 53385,
        "raw_text_block": (
            "GATE\n"
            "SPELL 10\n"
            "UNCOMMON\n"
            "CONJURATION\n"
            "TELEPORTATION\n"
            "Traditions arcane, divine, occult\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 120 feet\n"
            "Duration sustained up to 1 minute\n"
            "You tear open a rift to another plane, creating a portal that creatures can "
            "travel through in either direction. This portal is vertical and circular, "
            "with a radius of 40 feet. The portal appears at a location of your choice on "
            "the destination plane, assuming you have a clear idea of both the "
            "destination's location on the plane and what the destination looks like. If "
            "you attempt to create a gate into or out of the realm of a deity or another "
            "powerful being, that being can prevent the gate from forming."
        ),
    },
    "magic_weapon": {
        "source_line_start": 46689,
        "source_line_end": 46689,
        "raw_text_block": "Magic Weapon (tra): Make a weapon",
    },
    "hydraulic_push": {
        "rank": 1,
        "spell_type": "spell",
        "school": "evocation",
        "traditions": ["arcane", "primal"],
        "traits": ["none"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "60 feet",
        "area": "NA",
        "targets": "1 creature or unattended object",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You call forth a powerful blast of pressurized water that bludgeons the "
            "target and knocks it back. Make a ranged spell attack roll."
        ),
        "description_snippet": "Blast a target with water to damage and push it back.",
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 2d6.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 2d6.",
            }
        ],
        "source_line_start": 53326,
        "source_line_end": 53336,
        "raw_text_block": (
            "Traditions arcane, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 60 feet; Targets 1 creature or unattended object\n"
            "You call forth a powerful blast of pressurized water that bludgeons the "
            "target and knocks it back. Make a ranged spell attack roll.\n"
            "Critical Success The target takes 6d6 bludgeoning damage and is knocked back "
            "10 feet.\n"
            "Success The target takes 3d6 bludgeoning damage and is knocked back 5 feet.\n"
            "Heightened (+1) The damage increases by 2d6."
        ),
    },
    "horrid_wilting": {
        "rank": 8,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["arcane", "primal"],
        "traits": ["negative"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "500 feet",
        "area": "NA",
        "targets": "any number of living creatures",
        "duration": "NA",
        "save": "basic Fortitude",
        "save_type": "basic_fortitude",
        "description": (
            "You pull the moisture from the targets' bodies, dealing 10d10 negative damage. "
            "Water creatures and plant creatures fare worse, while creatures without "
            "significant moisture are immune."
        ),
        "description_snippet": "Pull moisture from living creatures to deal heavy negative damage.",
        "effects": {
            "description": (
                "You pull the moisture from the targets' bodies, dealing 10d10 negative "
                "damage. Creatures made of water and plant creatures use the outcome for "
                "one degree of success worse than the result of their saving throw. "
                "Creatures whose bodies contain no significant moisture are immune to "
                "horrid wilting."
            ),
            "outcomes": {},
        },
        "conditions_caused": ["none"],
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d10.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d10.",
            }
        ],
        "source_line_start": 54027,
        "source_line_end": 54049,
        "raw_text_block": (
            "HORRID WILTING\n"
            "SPELL 8\n"
            "NEGATIVE\n"
            "NECROMANCY\n"
            "Traditions arcane, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 500 feet; Targets any number of living creatures\n"
            "Saving Throw basic Fortitude\n"
            "You pull the moisture from the targets' bodies, dealing 10d10 negative "
            "damage. Creatures made of water (such as water elementals) and plant "
            "creatures use the outcome for one degree of success worse than the result of "
            "their saving throw. Creatures whose bodies contain no significant moisture "
            "(such as earth elementals) are immune to horrid wilting.\n"
            "Heightened (+1) The damage increases by 1d10."
        ),
    },
    "gaseous_form": {
        "rank": 4,
        "spell_type": "spell",
        "rarity": "common",
        "school": "transmutation",
        "traditions": ["arcane", "occult", "primal"],
        "traits": ["polymorph"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 willing creature",
        "duration": "5 minutes",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "The target transforms into a vaporous state. In this state, the target is "
            "amorphous. It loses any item bonus to AC and all other effects and bonuses "
            "from armor, uses its proficiency modifier for unarmored defense, gains "
            "resistance 8 to physical damage, and is immune to precision damage."
        ),
        "description_snippet": "Turn a willing creature into a vaporous form.",
        "source_line_start": 52675,
        "source_line_end": 52688,
        "raw_text_block": (
            "GASEOUS FORM\n"
            "SPELL 4\n"
            "TRANSMUTATION\n"
            "Traditions arcane, occult, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets 1 willing creature\n"
            "Duration 5 minutes\n"
            "The target transforms into a vaporous state. In this state, the target is "
            "amorphous. It loses any item bonus to AC and all other effects and bonuses "
            "from armor, uses its proficiency modifier for unarmored defense, gains "
            "resistance 8 to physical damage, and is immune to precision damage."
        ),
    },
    "heal": {
        "rank": 1,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine", "primal"],
        "traits": ["healing", "positive"],
        "cast": "[one-action] to [three-actions]",
        "cast_actions": "none",
        "components": ["material", "somatic", "verbal"],
        "range": "varies",
        "area": "30-foot emanation",
        "targets": "1 willing living creature or 1 undead creature",
        "duration": "NA",
        "save": "basic Fortitude",
        "save_type": "basic_fortitude",
        "description": (
            "You channel positive energy to heal the living or damage the undead. If the "
            "target is a willing living creature, you restore 1d8 Hit Points. If the "
            "target is undead, you deal that amount of positive damage to it, and it gets "
            "a basic Fortitude save."
        ),
        "description_snippet": "Positive energy heals the living or harms the undead.",
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The amount of healing or damage increases by 1d8, and the extra "
                    "healing for the 2-action version increases by 8."
                ),
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The amount of healing or damage increases by 1d8, and the extra "
                    "healing for the 2-action version increases by 8."
                ),
            }
        ],
        "source_line_start": 53200,
        "source_line_end": 53220,
        "raw_text_block": (
            "Traditions divine, primal\n"
            "Cast [one-action] to [three-actions]\n"
            "Range varies; Targets 1 willing living creature or 1 undead creature\n"
            "You channel positive energy to heal the living or damage the undead. If the "
            "target is a willing living creature, you restore 1d8 Hit Points. If the "
            "target is undead, you deal that amount of positive damage to it, and it gets "
            "a basic Fortitude save. The number of actions you spend when Casting this "
            "Spell determines its targets, range, area, and other parameters.\n"
            "[one-action] (somatic) The spell has a range of touch.\n"
            "[two-actions] (somatic, verbal) The spell has a range of 30 feet. If you're "
            "healing a living creature, increase the Hit Points restored by 8.\n"
            "[three-actions] (material, somatic, verbal) You disperse positive energy in "
            "a 30-foot emanation. This targets all living and undead creatures in the "
            "burst.\n"
            "Heightened (+1) The amount of healing or damage increases by 1d8, and the "
            "extra healing for the 2-action version increases by 8."
        ),
    },
    "mindlink": {
        "rank": 1,
        "spell_type": "spell",
        "school": "divination",
        "traditions": ["occult"],
        "traits": ["none"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 willing creature",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You link your mind to the target's mind and mentally impart to that target "
            "an amount of information in an instant that could otherwise be communicated "
            "in 10 minutes."
        ),
        "description_snippet": "Share 10 minutes of information instantly.",
        "source_line_start": 55328,
        "source_line_end": 55337,
        "raw_text_block": (
            "MINDLINK\n"
            "DIVINATION\n"
            "Traditions occult\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets 1 willing creature\n"
            "You link your mind to the target's mind and mentally impart to that target "
            "an amount of information in an instant that could otherwise be communicated "
            "in 10 minutes."
        ),
    },
    "plane_shift": {
        "source_line_start": 47115,
        "source_line_end": 47115,
        "raw_text_block": "Plane Shift U (con): Transport creatures",
    },
    "purify_food_and_drink": {
        "rank": 1,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine", "primal"],
        "traits": ["none"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 cubic foot of contaminated food or water",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You remove toxins and contaminations from food and drink, making them safe "
            "to consume. This spell doesn't prevent future contamination, natural decay, "
            "or spoilage."
        ),
        "description_snippet": "Remove contamination from food or drink.",
        "source_line_start": 56229,
        "source_line_end": 56237,
        "raw_text_block": (
            "PURIFY FOOD AND DRINK\n"
            "Traditions divine, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets 1 cubic foot of contaminated food or water\n"
            "You remove toxins and contaminations from food and drink, making them safe "
            "to consume. This spell doesn't prevent future contamination, natural decay, "
            "or spoilage. One cubic foot of liquid is roughly 8 gallons."
        ),
    },
    "remove_paralysis": {
        "source_line_start": 47366,
        "source_line_end": 47366,
        "raw_text_block": "Remove Paralysis H (nec): Free a",
    },
    "shillelagh": {
        "rank": 1,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["primal"],
        "traits": ["none"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 club or staff you hold",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "The target grows vines and leaves, brimming with primal energy. The target "
            "becomes a +1 striking weapon while in your hands, gaining a +1 item bonus to "
            "attack rolls and increasing the number of weapon damage dice to two."
        ),
        "description_snippet": "Empower a club or staff with primal force.",
        "source_line_start": 57441,
        "source_line_end": 57452,
        "raw_text_block": (
            "Traditions primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets 1 club or staff you hold\n"
            "Duration 1 minute\n"
            "The target grows vines and leaves, brimming with primal energy. The target "
            "becomes a +1 striking weapon while in your hands, gaining a +1 item bonus "
            "to attack rolls and increasing the number of weapon damage dice to two. "
            "Additionally, as long as you are on your home plane, attacks you make with "
            "the target against aberrations, extraplanar creatures, and undead increase "
            "the number of weapon damage dice to three."
        ),
    },
    "shocking_grasp": {
        "rank": 1,
        "spell_type": "spell",
        "school": "evocation",
        "traditions": ["arcane", "primal"],
        "traits": ["none"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "touch",
        "area": "NA",
        "targets": "1 creature",
        "duration": "NA",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You shroud your hands in a crackling field of lightning. Make a melee spell "
            "attack roll. On a hit, the target takes 2d12 electricity damage. If the "
            "target is wearing metal armor or is made of metal, you gain a +1 circumstance "
            "bonus to your attack roll with shocking grasp, and the target also takes 1d4 "
            "persistent electricity damage on a hit."
        ),
        "description_snippet": "Shock a creature in reach with a melee spell attack.",
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The damage increases by 1d12, and the persistent electricity damage "
                    "increases by 1."
                ),
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": (
                    "The damage increases by 1d12, and the persistent electricity damage "
                    "increases by 1."
                ),
            }
        ],
        "source_line_start": 57461,
        "source_line_end": 57475,
        "raw_text_block": (
            "SPELL 1\n"
            "EVOCATION\n"
            "Traditions arcane, primal\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range touch; Targets 1 creature\n"
            "You shroud your hands in a crackling field of lightning. Make a melee spell "
            "attack roll. On a hit, the target takes 2d12 electricity damage. If the "
            "target is wearing metal armor or is made of metal, you gain a +1 circumstance "
            "bonus to your attack roll with shocking grasp, and the target also takes 1d4 "
            "persistent electricity damage on a hit. On a critical hit, double the "
            "initial damage, but not the persistent damage.\n"
            "Heightened (+1) The damage increases by 1d12, and the persistent electricity "
            "damage increases by 1."
        ),
    },
    "undetectable_alignment": {
        "rank": 2,
        "spell_type": "spell",
        "rarity": "uncommon",
        "school": "abjuration",
        "traditions": ["divine", "occult"],
        "traits": ["none"],
        "source_line_start": 59209,
        "source_line_end": 59215,
        "raw_text_block": (
            "UNDETECTABLE ALIGNMENT\n"
            "Range touch; Targets 1 creature or object\n"
            "Duration until the next time you make your daily preparations\n"
            "You shroud a creature or object in wards that hide its alignment. The target "
            "appears to be neutral to all effects that would detect its alignment."
        ),
    },
    "water_breathing": {
        "rank": 2,
        "spell_type": "spell",
        "school": "transmutation",
        "traditions": ["arcane", "divine", "primal"],
        "traits": ["none"],
        "cast": "1 minute (somatic, verbal)",
        "cast_actions": "1_minute",
        "components": ["somatic", "verbal"],
        "range": "30 feet",
        "area": "NA",
        "targets": "up to 5 creatures",
        "duration": "1 hour",
        "save": "NA",
        "save_type": "NA",
        "description": "The targets can breathe underwater.",
        "description_snippet": "Allow creatures to breathe underwater.",
        "heightened": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": "The duration increases to 8 hours.",
            },
            {
                "label": "4th",
                "type": "fixed_rank",
                "rank": 4,
                "text": "The duration increases to until your next daily preparations.",
            },
        ],
        "heightened_scaling": [
            {
                "label": "3rd",
                "type": "fixed_rank",
                "rank": 3,
                "text": "The duration increases to 8 hours.",
            },
            {
                "label": "4th",
                "type": "fixed_rank",
                "rank": 4,
                "text": "The duration increases to until your next daily preparations.",
            },
        ],
        "source_line_start": 60658,
        "source_line_end": 60667,
        "raw_text_block": (
            "WATER BREATHING\n"
            "SPELL 2\n"
            "TRANSMUTATION\n"
            "Traditions arcane, divine, primal\n"
            "Cast 1 minute (somatic, verbal)\n"
            "Range 30 feet; Targets up to 5 creatures\n"
            "Duration 1 hour\n"
            "The targets can breathe underwater.\n"
            "Heightened (3rd) The duration increases to 8 hours.\n"
            "Heightened (4th) The duration increases to until your next daily preparations."
        ),
    },
    "allegro": {
        "source_line_start": 60224,
        "source_line_end": 60237,
        "raw_text_block": (
            "ALLEGRO\n"
            "UNCOMMON\n"
            "CANTRIP 7\n"
            "BARD\n"
            "CANTRIP COMPOSITION EMOTION ENCHANTMENT MENTAL\n"
            "Cast [one-action] verbal\n"
            "Range 30 feet; Targets 1 ally\n"
            "Duration 1 round\n"
            "You perform rapidly, speeding up your ally. The ally becomes quickened and can "
            "use the additional action to Strike, Stride, or Step."
        ),
    },
    "house_of_imaginary_walls": {
        "rank": 0,
        "spell_type": "cantrip",
        "rarity": "uncommon",
        "school": "illusion",
        "traditions": ["occult"],
        "traits": ["composition", "illusion", "visual"],
        "focus_class": "bard",
        "cast": "[one-action] somatic",
        "cast_actions": "1_action",
        "components": ["somatic"],
        "range": "touch",
        "area": "NA",
        "targets": "NA",
        "duration": "1 round",
        "save": "NA",
        "save_type": "NA",
        "description": (
            "You mime an invisible 10-foot-by-10-foot wall adjacent to you and within your "
            "reach. The wall is solid to those creatures that don't disbelieve it, even "
            "incorporeal creatures. You and your allies can voluntarily believe the wall "
            "exists to continue to treat it as solid, for instance to climb onto it. A "
            "creature that disbelieves the illusion is temporarily immune to your House of "
            "Imaginary Walls for 1 minute. The wall doesn't block creatures that didn't see "
            "your visual performance, nor does it block objects. The wall has AC 10, "
            "Hardness equal to double the spell's level, and HP equal to quadruple the "
            "spell's level."
        ),
        "description_snippet": "Mime an invisible wall that creatures can treat as solid.",
        "effects": {
            "description": (
                "You mime an invisible 10-foot-by-10-foot wall adjacent to you and within "
                "your reach. The wall is solid to those creatures that don't disbelieve it, "
                "even incorporeal creatures. You and your allies can voluntarily believe the "
                "wall exists to continue to treat it as solid, for instance to climb onto "
                "it. A creature that disbelieves the illusion is temporarily immune to your "
                "House of Imaginary Walls for 1 minute. The wall doesn't block creatures "
                "that didn't see your visual performance, nor does it block objects. The "
                "wall has AC 10, Hardness equal to double the spell's level, and HP equal to "
                "quadruple the spell's level."
            ),
            "outcomes": {},
        },
        "source_line_start": 60320,
        "source_line_end": 60341,
        "raw_text_block": (
            "HOUSE OF IMAGINARY WALLS\n"
            "UNCOMMON\n"
            "BARD\n"
            "CANTRIP COMPOSITION ILLUSION\n"
            "CANTRIP 5\n"
            "VISUAL\n"
            "Cast [one-action] somatic\n"
            "Range touch\n"
            "Duration 1 round\n"
            "You mime an invisible 10-foot-by-10-foot wall adjacent to you and within your "
            "reach. The wall is solid to those creatures that don't disbelieve it, even "
            "incorporeal creatures. You and your allies can voluntarily believe the wall "
            "exists to continue to treat it as solid, for instance to climb onto it. A "
            "creature that disbelieves the illusion is temporarily immune to your House of "
            "Imaginary Walls for 1 minute. The wall doesn't block creatures that didn't see "
            "your visual performance, nor does it block objects. The wall has AC 10, "
            "Hardness equal to double the spell's level, and HP equal to quadruple the "
            "spell's level."
        ),
    },
    "inspire_defense": {
        "source_line_start": 60385,
        "source_line_end": 60396,
        "raw_text_block": (
            "INSPIRE DEFENSE\n"
            "UNCOMMON\n"
            "BARD\n"
            "CANTRIP 2\n"
            "CANTRIP COMPOSITION EMOTION ENCHANTMENT MENTAL\n"
            "Cast [one-action] verbal\n"
            "Area 60-foot emanation\n"
            "Duration 1 round\n"
            "You inspire yourself and your allies to protect themselves more effectively. "
            "You and all allies in the area gain a +1 status bonus to AC and saving throws, "
            "as well as resistance equal to half the spell's level to physical damage."
        ),
    },
    "localized_quake": {
        "school": "transmutation",
    },
    "dirge_of_doom": {
        "rank": 0,
        "spell_type": "cantrip",
        "rarity": "uncommon",
        "school": "enchantment",
        "traditions": ["occult"],
        "traits": ["composition", "emotion", "fear", "mental"],
        "focus_class": "bard",
        "cast": "[one-action] verbal",
        "cast_actions": "1_action",
        "components": ["verbal"],
        "range": "NA",
        "area": "30-foot emanation",
        "targets": "NA",
        "duration": "1 round",
        "save": "NA",
        "save_type": "NA",
        "description": "Foes within the area are frightened 1. They can't reduce their frightened value below 1 while they remain in the area.",
        "description_snippet": "Frighten nearby enemies.",
        "effects": {
            "description": "Foes within the area are frightened 1. They can't reduce their frightened value below 1 while they remain in the area.",
            "outcomes": {},
        },
        "conditions_caused": ["frightened"],
        "source_line_start": 60273,
        "source_line_end": 60295,
        "raw_text_block": (
            "DIRGE OF DOOM\n"
            "UNCOMMON\n"
            "BARD\n"
            "FEAR\n"
            "MENTAL\n"
            "CANTRIP 3\n"
            "CANTRIP\n"
            "COMPOSITION\n"
            "EMOTION\n"
            "ENCHANTMENT\n"
            "Cast [one-action] verbal\n"
            "Area 30-foot emanation\n"
            "Duration 1 round\n"
            "Foes within the area are frightened 1. They can't reduce their frightened "
            "value below 1 while they remain in the area."
        ),
    },
    "charming_touch": {
        "focus_class": "cleric",
    },
    "touch_of_obedience": {
        "focus_class": "cleric",
    },
    "rebuke_death": {
        "focus_class": "cleric",
    },
    "harm": {
        "components": ["material", "somatic", "verbal"],
    },
    "soothing_ballad": {
        "source_line_start": 60488,
        "source_line_end": 60506,
        "raw_text_block": (
            "SOOTHING BALLAD\n"
            "UNCOMMON\n"
            "FOCUS 7\n"
            "BARD COMPOSITION EMOTION ENCHANTMENT HEALING MENTAL\n"
            "Cast [two-actions] somatic, verbal\n"
            "Range 30 feet; Targets you and up to 9 allies\n"
            "You draw upon your muse to soothe your allies. Choose one of the following "
            "three effects:\n"
            "- The spell attempts to counteract fear effects on the targets.\n"
            "- The spell attempts to counteract effects imposing paralysis on the targets.\n"
            "- The spell restores 7d8 Hit Points to the targets.\n"
            "Heightened (+1) When used to heal, soothing ballad restores 1d8 more Hit Points."
        ),
    },
    "positive_luminance": {
        "rank": 4,
        "spell_type": "focus",
        "school": "necromancy",
        "traditions": ["divine"],
        "focus_class": "cleric",
        "focus_domain": "sun",
        "cast": "[one-action] somatic",
        "cast_actions": "1_action",
        "components": ["somatic"],
        "range": "NA",
        "area": "NA",
        "targets": "NA",
        "duration": "1 minute",
        "save": "NA",
        "save_type": "NA",
        "description_snippet": "Drawing life force into yourself, you become a beacon of positive energy.",
    },
    "eradicate_undeath": {
        "rank": 4,
        "spell_type": "focus",
        "school": "necromancy",
        "traditions": ["divine"],
        "cast": "[two-actions] somatic, verbal",
        "cast_actions": "2_actions",
        "components": ["somatic", "verbal"],
        "range": "NA",
        "area": "30-foot cone",
        "targets": "NA",
        "duration": "NA",
        "save": "basic Fortitude",
        "save_type": "basic_fortitude",
        "description": "A massive deluge of life energy causes the undead to fall apart. Each undead creature in the area takes 4d12 positive damage.",
        "description_snippet": "A massive deluge of life energy causes the undead to fall apart.",
        "heightened": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d12.",
            }
        ],
        "heightened_scaling": [
            {
                "label": "+1",
                "type": "step",
                "step": 1,
                "text": "The damage increases by 1d12.",
            }
        ],
        "damage": [{"formula": "4d12", "type": "positive", "persistent": False}],
        "damage_type": ["positive"],
        "effects": {
            "description": "A massive deluge of life energy causes the undead to fall apart. Each undead creature in the area takes 4d12 positive damage.",
            "outcomes": {},
        },
        "conditions_caused": [],
        "focus_class": "cleric",
        "focus_domain": "death",
    },
    "impaling_briars": {
        "school": "conjuration",
    },
    "resurrect": {
        "source_line_start": 65718,
        "source_line_end": 65784,
        "raw_text_block": (
            "RESURRECT\n"
            "UNCOMMON\n"
            "HEALING\n"
            "RITUAL 5\n"
            "NECROMANCY\n"
            "Cast 1 day; Cost diamonds worth a total value of 75 gp x the target's level; "
            "Secondary Casters 2\n"
            "Primary Check Religion (expert); Secondary Checks Medicine, Society\n"
            "Range 10 feet; Targets 1 dead creature of up to 10th level\n"
            "You attempt to call forth the target's soul and return it to its body. This "
            "requires the target's body to be present and relatively intact. The target "
            "must have died within the past year. If Pharasma has decided that the target's "
            "time has come or the target doesn't wish to return, this ritual automatically "
            "fails, but you discover this after the successful Religion check and can end "
            "the ritual without paying the cost.\n"
            "Critical Success You resurrect the target. They return to life with full Hit "
            "Points and the same spells prepared and points in their pools they had when "
            "they died, and still suffering from any long-term debilitations of the old "
            "body. The target meets an agent of their deity during the resurrection who "
            "inspires them, granting them a +1 status bonus to attack rolls, Perception, "
            "saving throws, and skill checks for 1 week. The target is also permanently "
            "changed in some way by their time in the afterlife, such as gaining a slight "
            "personality shift, a streak of white in the hair, or a strange new birthmark.\n"
            "Success As critical success, except the target returns to life with 1 Hit "
            "Point and no spells prepared or points in any pools, and still is affected by "
            "any long-term debilitations of the old body. Instead of inspiring them, the "
            "character's time in the Boneyard has left them temporarily debilitated. The "
            "target is clumsy 1, drained 1, and enfeebled 1 for 1 week; these conditions "
            "can't be removed or reduced by any means until the week has passed.\n"
            "Failure Your attempt is unsuccessful.\n"
            "Critical Failure Something goes horribly wrong-an evil spirit possesses the "
            "body, the body transforms into a special kind of undead, or some worse fate "
            "befalls the target.\n"
            "Heightened (6th) You can resurrect a target of up to 12th level, and the base "
            "cost is 125 gp.\n"
            "Heightened (7th) You can use resurrect even with only a small portion of the "
            "body; the ritual creates a new body on a success or critical success. The "
            "target must have died within the past decade. The ritual requires four "
            "secondary casters, each of whom must be at least half the target's level. The "
            "target can be up to 14th level, and the base cost is 200 gp.\n"
            "Heightened (8th) As 7th level, but the target can be up to 16th level and the "
            "base cost is 300 gp.\n"
            "Heightened (9th) You can use resurrect even without the body as long as you "
            "know the target's name and have touched a portion of its body at any time. The "
            "target must have died within the past century, and it doesn't gain the "
            "negative conditions on a success. The ritual requires eight secondary casters, "
            "each of whom must be at least half the target's level. The target can be up "
            "to 18th level, and the base cost is 600 gp.\n"
            "Heightened (10th) As 9th level, except it doesn't matter how long ago the "
            "target died. The ritual requires 16 secondary casters, each of whom must be at "
            "least half the target's level. The target can be up to 20th level, and the "
            "ritual's base cost is 1,000 gp."
        ),
    },
    "uncontrollable_dance": {
        "description": (
            "The target is overcome with an all-consuming urge to dance. For the "
            "duration of the spell, the target is off-guard and can't use reactions. "
            "While affected, the creature can't use actions with the move trait "
            "except to dance, using the Stride action to move up to half its Speed."
        ),
        "description_snippet": "The target is overcome with an all-consuming urge to dance.",
        "effects": {
            "description": (
                "The target is overcome with an all-consuming urge to dance. For the "
                "duration of the spell, the target is off-guard and can't use "
                "reactions. While affected, the creature can't use actions with the "
                "move trait except to dance, using the Stride action to move up to "
                "half its Speed."
            ),
            "outcomes": {
                "Critical Success": "The target is unaffected.",
                "Success": "The spell's duration is 3 rounds, and the target must spend at least 1 action each turn dancing.",
                "Failure": "The spell's duration is 1 minute, and the target must spend at least 2 actions each turn dancing.",
                "Critical Failure": "The spell's duration is 1 minute, and the target must spend all its actions each turn dancing.",
            },
        },
    },
    "overwhelming_presence": {
        "school": "enchantment",
    },
}
SCHOOL_ABBREV = {
    "abj": "abjuration",
    "con": "conjuration",
    "div": "divination",
    "enc": "enchantment",
    "evo": "evocation",
    "ill": "illusion",
    "nec": "necromancy",
    "tra": "transmutation",
}
SUSPICIOUS_TRAIT_RE = re.compile(r"\d|item level|creature or|spell \d+", re.I)
ACTIVE_BOOK_CONFIG: dict[str, Any] = {
    "source_book": "core_rulebook_4th_printing",
    "source_display": "Core Rulebook (Fourth Printing)",
    "source_file": DEFAULT_SOURCE.name,
    "intermediary_source_file": f"intermediary/{DEFAULT_SOURCE.name}",
    "parser_version": PARSER_VERSION,
}


def slugify(text: str) -> str:
    slug = text.lower()
    slug = re.sub(r"[^\w\s-]", "", slug)
    slug = re.sub(r"[-\s]+", "_", slug)
    return slug.strip("_")


def normalize_whitespace(value: str) -> str:
    for raw, replacement in ACTION_GLYPHS.items():
        value = value.replace(raw, replacement)
    value = value.replace("\u2011", "-").replace("\u2013", "-").replace("\u2019", "'")
    value = value.replace("\f", " ")
    value = re.sub(r"\s+", " ", value)
    return value.strip()


def clean_line(line: str) -> str:
    line = normalize_whitespace(line.strip())
    if not line:
        return ""
    if re.fullmatch(r"[\d ]+", line):
        return ""
    if line in PAGE_NOISE_LINES:
        return ""
    if re.fullmatch(r"chapter \d+:\s*spells", line, re.I):
        return ""
    return line


def resolve_book_config(source_path: Path) -> dict[str, Any]:
    if source_path.name == APG_SOURCE.name:
        return {
            "source_book": "advanced_players_guide",
            "source_display": "Advanced Player's Guide",
            "source_file": source_path.name,
            "intermediary_source_file": f"intermediary/{source_path.name}",
            "parser_version": APG_PARSER_VERSION,
        }
    if source_path.name == SOM_SOURCE.name:
        return {
            "source_book": "secrets_of_magic",
            "source_display": "Secrets of Magic",
            "source_file": source_path.name,
            "intermediary_source_file": f"intermediary/{source_path.name}",
            "parser_version": SOM_PARSER_VERSION,
        }

    return {
        "source_book": "core_rulebook_4th_printing",
        "source_display": "Core Rulebook (Fourth Printing)",
        "source_file": source_path.name,
        "intermediary_source_file": f"intermediary/{source_path.name}",
        "parser_version": PARSER_VERSION,
    }


def activate_book_config(source_path: Path) -> None:
    global ACTIVE_BOOK_CONFIG
    ACTIVE_BOOK_CONFIG = resolve_book_config(source_path)


def find_spells_chapter_start(raw_lines: list[str]) -> int:
    chapter_markers = [
        idx
        for idx, raw_line in enumerate(raw_lines)
        if re.fullmatch(r"chapter \d+:\s*spells", normalize_whitespace(raw_line), re.I)
    ]
    if not chapter_markers:
        if ACTIVE_BOOK_CONFIG["source_book"] == "secrets_of_magic":
            return 0
        raise RuntimeError("Could not find spell chapter header in raw text.")
    return chapter_markers[-1]


def find_spell_list_start(lines: list[str], chapter_start: int | None = None) -> int:
    all_list_starts = [idx for idx, line in enumerate(lines) if clean_line(line) == "Spell Lists"]
    list_starts = list(all_list_starts)
    if chapter_start is not None:
        list_starts = [idx for idx in list_starts if idx >= chapter_start]
    if not list_starts:
        list_starts = list(all_list_starts)
    if not list_starts:
        raise RuntimeError("Could not find 'Spell Lists' in spell chapter raw text.")

    spell_description_starts = [idx for idx, line in enumerate(lines) if clean_line(line) == "Spell Descriptions"]
    if chapter_start is not None:
        spell_description_starts = [idx for idx in spell_description_starts if idx >= chapter_start]
    if spell_description_starts:
        candidates = [idx for idx in list_starts if idx < spell_description_starts[0]]
        if candidates:
            return candidates[-1]

    return list_starts[0]


def trim_trailing_section_break(block_lines: list[str]) -> list[str]:
    for idx, line in enumerate(block_lines[1:], start=1):
        normalized = normalize_whitespace(line)
        if normalized in SECTION_BREAK_LINES or re.match(r"^TABLE \d+[–-]\d+:", normalized):
            return block_lines[:idx]
    return block_lines


def title_case_name_from_heading(line: str) -> str:
    words = normalize_whitespace(line).split(" ")
    return " ".join(word.capitalize() if word.isupper() else word.capitalize() for word in words)


def parse_spell_list(lines: list[str], chapter_start: int | None = None) -> dict[str, dict[str, Any]]:
    list_start = find_spell_list_start(lines, chapter_start=chapter_start)

    current_tradition: str | None = None
    current_level: int | None = None
    entries: dict[str, dict[str, Any]] = {}

    for idx in range(list_start + 1, len(lines)):
        line = clean_line(lines[idx])
        if not line:
            continue

        if is_name_candidate(line):
            future_window = [clean_line(item) for item in lines[idx + 1 : idx + 12]]
            if any(RANK_RE.match(item) for item in future_window if item) and any(
                FIELD_START_RE.match(item) for item in future_window if item
            ):
                break

        header_match = TRADITION_HEADER_RE.match(line)
        if header_match:
            current_tradition = header_match.group(1).lower()
            current_level = 0 if header_match.group(2).lower() == "cantrips" else int(header_match.group(3))
            continue

        if current_tradition is None or current_level is None:
            continue

        match = SPELL_LIST_ENTRY_RE.match(line)
        if not match:
            continue

        name = normalize_whitespace(match.group(1))
        markers = match.group(2).strip()
        school = SCHOOL_ABBREV[match.group(3).lower()]
        snippet = normalize_whitespace(match.group(4))
        spell_id = slugify(name)

        rarity = "common"
        if "U" in markers:
            rarity = "uncommon"
        elif "R" in markers:
            rarity = "rare"

        record = entries.setdefault(
            spell_id,
            {
                "name": name,
                "content_id": spell_id,
                "level": current_level,
                "school": school,
                "traditions": [],
                "rarity": rarity,
                "heightenable": "H" in markers,
                "description_snippet": snippet,
                "spell_list_evidence": [],
            },
        )

        if current_tradition not in record["traditions"]:
            record["traditions"].append(current_tradition)
        record["rarity"] = record["rarity"] if record["rarity"] != "common" else rarity
        record["heightenable"] = record["heightenable"] or ("H" in markers)
        if len(snippet) > len(record["description_snippet"]):
            record["description_snippet"] = snippet
        record["spell_list_evidence"].append(line)

    return entries


def is_name_candidate(line: str) -> bool:
    if not line or not SPELL_DETAIL_NAME_RE.match(line):
        return False
    if line in PAGE_NOISE_LINES or line in SPELL_SCHOOLS or line in RARITY_TRAITS:
        return False
    if line in EXCLUDED_NAME_CANDIDATES:
        return False
    if line in {"SPELLS", "CANTRIP", "FOCUS", "RITUAL", "ATTACK"}:
        return False
    if RANK_RE.match(line):
        return False
    upper_tokens = line.split()
    if upper_tokens and upper_tokens[0] in RARITY_TRAITS:
        return False
    return True


def find_spell_name_index(lines: list[str], rank_index: int) -> int | None:
    start = max(0, rank_index - 12)
    candidates = []
    for idx in range(start, rank_index):
        line = clean_line(lines[idx])
        if is_name_candidate(line):
            candidates.append(idx)
    return candidates[-1] if candidates else None


def recover_spell_block_for_fallback(
    raw_lines: list[str],
    lines: list[str],
    spell_list_record: dict[str, Any],
    chapter_start: int | None = None,
) -> dict[str, Any] | None:
    if chapter_start is None:
        chapter_start = find_spells_chapter_start(raw_lines)

    expected_heading = normalize_whitespace(spell_list_record["name"]).upper()
    expected_level = int(spell_list_record["level"])
    expected_rank_line = f"CANTRIP 1" if expected_level == 0 else f"SPELL {expected_level}"
    candidate_blocks: list[dict[str, Any]] = []

    for idx in range(chapter_start, len(lines)):
        if clean_line(lines[idx]) != expected_heading:
            continue

        end = len(lines)
        for probe in range(idx + 1, min(len(lines), idx + 160)):
            normalized = clean_line(lines[probe])
            if not normalized:
                continue
            if normalized in SECTION_BREAK_LINES or re.match(r"^TABLE \d+[–-]\d+:", normalized):
                end = probe
                break
            if probe > idx + 1 and is_name_candidate(normalized):
                lookahead = [clean_line(lines[offset]) for offset in range(probe + 1, min(len(lines), probe + 16))]
                if any(RANK_RE.match(item) for item in lookahead):
                    end = probe
                    break

        block_lines = [clean_line(line) for line in lines[idx:end]]
        block_lines = [line for line in block_lines if line]
        block_lines = trim_trailing_section_break(block_lines)
        if expected_rank_line not in block_lines:
            continue

        candidate_blocks.append(
            {
                "name_heading": block_lines[0],
                "name": title_case_name_from_heading(block_lines[0]),
                "content_id": slugify(block_lines[0]),
                "start_line": idx + 1,
                "end_line": end,
                "lines": block_lines,
            }
        )

    if not candidate_blocks:
        return None

    def score_block(block: dict[str, Any]) -> tuple[int, int]:
        block_text = " ".join(block["lines"]).lower()
        score = 0
        if expected_rank_line in block["lines"]:
            score += 5
        if spell_list_record.get("school") and spell_list_record["school"] in block_text:
            score += 3
        if any(f"traditions {tradition}" in block_text for tradition in spell_list_record.get("traditions", [])):
            score += 2
        if any(FIELD_START_RE.match(line) for line in block["lines"]):
            score += 1
        return score, -block["start_line"]

    best_block = max(candidate_blocks, key=score_block)
    return parse_spell_block(best_block, spell_list_record)


def collect_spell_blocks(raw_lines: list[str], lines: list[str], chapter_start: int | None = None) -> list[dict[str, Any]]:
    if chapter_start is None:
        chapter_start = find_spells_chapter_start(raw_lines)
    starts = []
    for idx in range(chapter_start, len(lines)):
        line = clean_line(lines[idx])
        if not RANK_RE.match(line):
            continue
        name_index = find_spell_name_index(lines, idx)
        if name_index is None:
            continue
        if any(FIELD_START_RE.match(clean_line(item)) for item in lines[idx + 1 : idx + 12]):
            starts.append(name_index)

    starts = sorted(dict.fromkeys(starts))
    blocks: list[dict[str, Any]] = []

    for pos, start in enumerate(starts):
        end = starts[pos + 1] if pos + 1 < len(starts) else len(lines)
        block_lines = [clean_line(line) for line in lines[start:end]]
        block_lines = [line for line in block_lines if line]
        block_lines = trim_trailing_section_break(block_lines)
        if not block_lines:
            continue
        blocks.append(
            {
                "name_heading": block_lines[0],
                "name": title_case_name_from_heading(block_lines[0]),
                "content_id": slugify(block_lines[0]),
                "start_line": start + 1,
                "end_line": end,
                "lines": block_lines,
            }
        )

    return blocks


def parse_rank(rank_line: str) -> tuple[int, bool, str]:
    match = re.match(r"^(CANTRIP|SPELL|FOCUS|RITUAL)\s+(\d+)$", rank_line)
    if not match:
        return 0, False, ""

    spell_type = match.group(1).lower()
    printed_rank = int(match.group(2))
    rank = 0 if spell_type == "cantrip" else printed_rank
    return rank, spell_type == "cantrip", spell_type


def split_metadata_line(text: str) -> dict[str, str]:
    parts = re.split(r";\s*", text)
    result: dict[str, str] = {}
    for part in parts:
        matched = False
        for field in FIELD_NAMES:
            prefix = field + " "
            if part.startswith(prefix):
                result[field] = normalize_whitespace(part[len(prefix) :])
                matched = True
                break
        if not matched and result:
            last_key = list(result.keys())[-1]
            result[last_key] = normalize_whitespace(result[last_key] + " " + part)
    return result


def collect_metadata_buffers(block_lines: list[str], start_index: int) -> tuple[list[str], int]:
    buffers: list[str] = []
    current = ""
    index = start_index

    while index < len(block_lines):
        line = block_lines[index]
        if FIELD_START_RE.match(line):
            if current:
                buffers.append(current)
            current = line
            index += 1
            continue

        if current and line and line[:1].islower():
            current = normalize_whitespace(current + " " + line)
            index += 1
            continue

        if current and line and re.match(r"^[0-9(+-]", line):
            current = normalize_whitespace(current + " " + line)
            index += 1
            continue

        break

    if current:
        buffers.append(current)

    return buffers, index


def is_header_annotation_line(line: str) -> bool:
    normalized = normalize_whitespace(line)
    if not normalized:
        return False
    lowered = normalized.lower()
    if normalized in {"CANTRIP", "SPELL", "FOCUS", "RITUAL", "ATTACK"}:
        return True
    if lowered.startswith("domain ") or lowered.startswith("casting "):
        return True
    if normalized != normalized.upper():
        return False
    if FIELD_START_RE.match(normalized) or RANK_RE.match(normalized):
        return False
    annotation = parse_header_annotation(normalized)
    return bool(
        annotation["school"]
        or annotation["rarity"]
        or annotation["focus_class"]
        or annotation["focus_domain"]
        or annotation["traits"]
    )


def collect_prelude(block_lines: list[str]) -> tuple[list[str], dict[str, str], int]:
    header_lines: list[str] = []
    metadata: dict[str, str] = {}
    index = 1

    while index < len(block_lines):
        line = block_lines[index]
        if FIELD_START_RE.match(line):
            metadata_buffers, index = collect_metadata_buffers(block_lines, index)
            for buffer in metadata_buffers:
                metadata.update(split_metadata_line(buffer))
            continue
        if RANK_RE.match(line) or is_header_annotation_line(line):
            header_lines.append(line)
            index += 1
            continue
        break

    return header_lines, metadata, index


def parse_heightened_entry(text: str) -> dict[str, Any]:
    match = HEIGHTENED_RE.match(text)
    if not match:
        return {"label": "", "type": "raw", "text": text}

    label = match.group(1).strip()
    body = normalize_whitespace(match.group(2))
    if label.startswith("+") and label[1:].isdigit():
        return {"label": label, "type": "step", "step": int(label[1:]), "text": body}
    if label.endswith("th") and label[:-2].isdigit():
        return {"label": label, "type": "fixed_rank", "rank": int(label[:-2]), "text": body}
    return {"label": label, "type": "raw", "text": body}


def find_conditions(text: str) -> list[str]:
    lowered = text.lower()
    found = []
    for condition in CONDITION_NAMES:
        if re.search(r"\b%s\b" % re.escape(condition), lowered):
            found.append(condition)
    return sorted(dict.fromkeys(found))


def normalize_damage_formula(formula: str) -> str:
    normalized = normalize_whitespace(formula)
    normalized = re.sub(r"\s+plus\s+", " + ", normalized, flags=re.I)
    return normalized


def find_damage_clauses(text: str) -> list[dict[str, Any]]:
    damages: list[dict[str, Any]] = []
    seen: set[tuple[str, str, bool]] = set()

    formula_pattern = r"\d+d\d+(?:\s*(?:\+|plus)\s*[\w']+(?:\s+[\w']+){0,4})?"

    for match in re.finditer(rf"({formula_pattern})\s+(persistent\s+)?([a-z]+)\s+damage", text, re.I):
        entry = {
            "formula": normalize_damage_formula(match.group(1)),
            "type": match.group(3).lower(),
            "persistent": bool(match.group(2)),
        }
        key = (entry["formula"], entry["type"], entry["persistent"])
        if key in seen:
            continue
        seen.add(key)
        damages.append(entry)

    for match in re.finditer(
        rf"([a-z]+)\s+damage\s+equal\s+to\s+({formula_pattern})",
        text,
        re.I,
    ):
        entry = {
            "formula": normalize_damage_formula(match.group(2)),
            "type": match.group(1).lower(),
            "persistent": False,
        }
        key = (entry["formula"], entry["type"], entry["persistent"])
        if key in seen:
            continue
        seen.add(key)
        damages.append(entry)

    for match in re.finditer(
        rf"([a-z]+)\s+damage\s+plus\s+({formula_pattern})",
        text,
        re.I,
    ):
        entry = {
            "formula": normalize_damage_formula(match.group(2)),
            "type": match.group(1).lower(),
            "persistent": False,
        }
        key = (entry["formula"], entry["type"], entry["persistent"])
        if key in seen:
            continue
        seen.add(key)
        damages.append(entry)

    return damages


def normalize_cast_actions(cast: str) -> str | None:
    lowered = cast.lower()
    if "[one-action] to [three-actions]" in lowered:
        return "1_action_to_3_actions"
    if "[one-action] to [two-actions]" in lowered:
        return "1_action_to_2_actions"
    if "[one-action] or more" in lowered:
        return "1_action_or_more"
    if "[free-action]" in lowered:
        return "free_action"
    if "[reaction]" in lowered:
        return "reaction"
    if "[three-actions]" in lowered:
        return "3_actions"
    if "[two-actions]" in lowered:
        return "2_actions"
    if "[one-action]" in lowered:
        return "1_action"
    if lowered.startswith("10 minutes"):
        return "ten_minutes"
    if lowered.startswith("1 minute"):
        return "one_minute"
    if lowered.startswith("1 hour"):
        return "one_hour"
    if lowered.startswith("1 day"):
        return "one_day"
    if lowered.startswith("3 days"):
        return "three_days"
    return None


def extract_components(cast: str) -> list[str]:
    components = []
    lowered = cast.lower()
    for component in ("focus", "material", "somatic", "verbal"):
        if re.search(r"\b%s\b" % component, lowered):
            components.append(component)
    return components


def parse_header_annotation(line: str) -> dict[str, Any]:
    annotation: dict[str, Any] = {
        "school": None,
        "rarity": None,
        "focus_class": None,
        "focus_domain": None,
        "traits": [],
        "cast": None,
    }
    normalized = normalize_whitespace(line)
    if not normalized:
        return annotation

    lowered = normalized.lower()
    if lowered.startswith("domain "):
        annotation["focus_domain"] = lowered[7:].strip()
        return annotation
    if lowered.startswith("casting "):
        annotation["cast"] = normalize_whitespace(normalized[8:])
        return annotation

    ignored_trait_tokens = {"cantrip", "spell", "focus", "ritual"}
    for token in lowered.split():
        token_upper = token.upper()
        if token_upper in RARITY_TRAITS and annotation["rarity"] is None:
            annotation["rarity"] = token.lower()
            continue
        if token_upper in SPELL_SCHOOLS and annotation["school"] is None:
            annotation["school"] = token.lower()
            continue
        if token_upper in CLASS_LABELS and annotation["focus_class"] is None:
            annotation["focus_class"] = token.lower()
            continue
        if token not in ignored_trait_tokens and not token.isdigit():
            annotation["traits"].append(token.lower())

    annotation["traits"] = sorted(dict.fromkeys(annotation["traits"]))
    return annotation


def normalize_spell_identity(
    block: dict[str, Any],
    description: str,
    raw_text_block: str,
) -> tuple[str, str]:
    spell_id = block["content_id"]
    spell_name = block["name"]
    lowered = description.lower()
    if spell_id == "light" and "luminance reservoir" in lowered:
        return "positive_luminance", "Positive Luminance"
    if spell_id == "fear" and lowered.startswith("you create a phantasmal image"):
        return "phantasmal_killer", "Phantasmal Killer"
    if spell_id == "fear" and lowered.startswith("the target appears to be a gruesome and terrifying creature"):
        return "mask_of_terror", "Mask of Terror"
    if spell_id == "fear" and "foes within the area are frightened 1" in lowered:
        return "dirge_of_doom", "Dirge of Doom"
    if spell_id == "fear" and lowered.startswith("you drastically reduce the target's mental faculties"):
        return "feeblemind", "Feeblemind"
    if spell_id == "cantrip_composition_illusion" and "house of imaginary walls" in lowered:
        return "house_of_imaginary_walls", "House of Imaginary Walls"
    return spell_id, spell_name


def infer_traditions(
    block: dict[str, Any],
    spell_type: str,
    traditions: list[str],
    focus_class: str | None,
    focus_domain: str | None,
) -> list[str]:
    if traditions:
        return sorted(dict.fromkeys(traditions))
    if spell_type == "ritual":
        return []
    if focus_class == "sorcerer":
        return SORCERER_FOCUS_TRADITIONS.get(block["content_id"], [])
    if focus_class == "monk":
        return ["divine", "occult"]
    if focus_class in FOCUS_CLASS_TRADITIONS:
        return list(FOCUS_CLASS_TRADITIONS[focus_class])
    if focus_domain:
        return ["divine"]
    return []


def apply_source_backed_overrides(schema_data: dict[str, Any]) -> dict[str, Any]:
    for key, value in SOURCE_BACKED_OVERRIDES.get(schema_data["id"], {}).items():
        schema_data[key] = value
    return schema_data


def normalize_audited_scalars(schema_data: dict[str, Any]) -> dict[str, Any]:
    if schema_data.get("save_type") in (None, "", "none") and schema_data.get("save") not in (None, "", "none", "NA"):
        schema_data["save_type"] = schema_data["save"].lower().replace(" ", "_")
    for field in AUDITED_SCALAR_FIELDS:
        if schema_data.get(field) in (None, "", "NA"):
            schema_data[field] = "none"
    return schema_data


def normalize_table_cells(schema_data: dict[str, Any]) -> dict[str, Any]:
    if schema_data.get("spell_type") == "focus" and schema_data.get("focus_domain") and not schema_data.get("focus_class"):
        schema_data["focus_class"] = "cleric"

    if not schema_data.get("school"):
        schema_data["school"] = "none"
    if not schema_data.get("rarity"):
        schema_data["rarity"] = "common"
    if not schema_data.get("cast"):
        schema_data["cast"] = "none"
    if not schema_data.get("cast_actions"):
        schema_data["cast_actions"] = "none"
    if not schema_data.get("focus_class"):
        schema_data["focus_class"] = "none"
    if not schema_data.get("focus_domain"):
        schema_data["focus_domain"] = "none"
    if not schema_data.get("traits"):
        schema_data["traits"] = ["none"]
    if not schema_data.get("components"):
        schema_data["components"] = ["none"]
    if not schema_data.get("traditions"):
        schema_data["traditions"] = ["none"]
    if not schema_data.get("damage"):
        schema_data["damage"] = [
            {
                "formula": "none",
                "type": "none",
                "persistent": False,
            }
        ]
    if not schema_data.get("damage_type"):
        schema_data["damage_type"] = ["none"]
    if not schema_data.get("conditions_caused"):
        schema_data["conditions_caused"] = ["none"]
    if not schema_data.get("heightened"):
        if schema_data.get("spell_type") == "cantrip":
            schema_data["heightened"] = [
                {
                    "label": "auto",
                    "type": "cantrip_auto_heighten",
                    "text": "This cantrip auto-heightens to a spell rank equal to half your level, rounded up.",
                }
            ]
        else:
            schema_data["heightened"] = [
                {
                    "label": "none",
                    "type": "none",
                    "text": "No explicit heightened entry.",
                }
            ]
    if not schema_data.get("heightened_scaling"):
        schema_data["heightened_scaling"] = list(schema_data["heightened"])
    if not schema_data.get("description"):
        schema_data["description"] = schema_data.get("description_snippet") or "none"
    if not schema_data.get("description_snippet"):
        snippet = normalize_whitespace(schema_data["description"])[:180].strip()
        schema_data["description_snippet"] = snippet or "none"
    if not schema_data.get("summon_level_cap_table"):
        schema_data["summon_level_cap_table"] = "none"
    if not schema_data.get("raw_text_block"):
        schema_data["raw_text_block"] = "none"
    if schema_data.get("source_line_start") in (None, ""):
        schema_data["source_line_start"] = "none"
    if schema_data.get("source_line_end") in (None, ""):
        schema_data["source_line_end"] = "none"
    return schema_data


def trim_block_to_matching_segment(
    block_lines: list[str],
    spell_list_record: dict[str, Any] | None,
) -> tuple[list[str], int, int]:
    rank_indices = [idx for idx, line in enumerate(block_lines) if RANK_RE.match(line)]
    if len(rank_indices) <= 1:
        return block_lines, 0, len(block_lines)

    def score_rank_index(idx: int) -> float:
        if spell_list_record is None:
            return float(-idx)

        rank_line = block_lines[idx]
        rank, is_cantrip, spell_type = parse_rank(rank_line)
        expected_level = int(spell_list_record["level"])
        expected_spell_type = "cantrip" if expected_level == 0 else "spell"
        expected_school = spell_list_record.get("school")
        expected_traditions = spell_list_record.get("traditions", [])
        window = " ".join(block_lines[max(0, idx - 4) : min(len(block_lines), idx + 10)]).lower()

        score = 0.0
        if rank == expected_level:
            score += 5.0
        if spell_type == expected_spell_type:
            score += 3.0
        if expected_school and expected_school in window:
            score += 2.0
        if any(f"traditions {tradition}" in window for tradition in expected_traditions):
            score += 1.0
        return score - (idx * 0.001)

    selected_rank_index = max(rank_indices, key=score_rank_index)
    if spell_list_record is not None:
        segment_start = 0
    else:
        segment_start = find_spell_name_index(block_lines, selected_rank_index)
        if segment_start is None:
            segment_start = 0

    segment_end = len(block_lines)
    for next_rank_index in rank_indices:
        if next_rank_index <= selected_rank_index:
            continue
        next_name_index = find_spell_name_index(block_lines, next_rank_index)
        if next_name_index is not None and next_name_index > segment_start:
            segment_end = next_name_index
        else:
            segment_end = next_rank_index
        break

    if spell_list_record is not None and segment_start == 0 and selected_rank_index > 0:
        trimmed_lines = [block_lines[0], *block_lines[selected_rank_index:segment_end]]
        return trimmed_lines, 0, segment_end

    return block_lines[segment_start:segment_end], segment_start, segment_end


def parse_spell_block(block: dict[str, Any], spell_list_record: dict[str, Any] | None) -> dict[str, Any]:
    block_lines, segment_start, segment_end = trim_block_to_matching_segment(block["lines"], spell_list_record)
    header_lines, metadata, index = collect_prelude(block_lines)

    body_lines = block_lines[index:]
    heightened_entries: list[dict[str, Any]] = []
    narrative_lines: list[str] = []
    outcome_map: dict[str, str] = {}
    current_outcome: str | None = None
    current_heightened: dict[str, Any] | None = None

    for line in body_lines:
        if not line:
            continue
        heightened_match = HEIGHTENED_RE.match(line)
        if heightened_match:
            if current_heightened is not None:
                heightened_entries.append(current_heightened)
            current_heightened = parse_heightened_entry(line)
            current_outcome = None
            continue

        if current_heightened is not None and not any(line.startswith(prefix) for prefix in OUTCOME_PREFIXES):
            current_heightened["text"] = normalize_whitespace(current_heightened["text"] + " " + line)
            continue

        outcome_prefix = next((prefix for prefix in OUTCOME_PREFIXES if line.startswith(prefix)), None)
        if outcome_prefix:
            current_outcome = outcome_prefix
            outcome_map[current_outcome] = normalize_whitespace(line[len(outcome_prefix) :].strip())
            if current_heightened is not None:
                heightened_entries.append(current_heightened)
                current_heightened = None
            continue

        if current_outcome is not None:
            outcome_map[current_outcome] = normalize_whitespace(outcome_map[current_outcome] + " " + line)
            continue

        narrative_lines.append(line)

    if current_heightened is not None:
        heightened_entries.append(current_heightened)

    rank_candidates = [line for line in header_lines if RANK_RE.match(line)]
    rank_line = rank_candidates[-1] if rank_candidates else ""
    rank, is_cantrip, spell_type = parse_rank(rank_line)
    school = None
    traits = []
    focus_class = None
    focus_domain = None
    header_cast = None
    header_rarity = None
    for line in header_lines:
        if line == rank_line:
            continue
        if line in {"CANTRIP"}:
            continue
        annotation = parse_header_annotation(line)
        school = school or annotation["school"]
        header_rarity = header_rarity or annotation["rarity"]
        focus_class = focus_class or annotation["focus_class"]
        focus_domain = focus_domain or annotation["focus_domain"]
        header_cast = header_cast or annotation["cast"]
        traits.extend(annotation["traits"])

    rarity = next((trait.lower() for trait in traits if trait.upper() in RARITY_TRAITS), None)
    if rarity is None:
        rarity = header_rarity
    if rarity is None and spell_list_record is not None:
        rarity = spell_list_record.get("rarity", "common")
    rarity = rarity or "common"

    traditions = []
    if "Traditions" in metadata:
        traditions = [item.strip().lower() for item in metadata["Traditions"].split(",") if item.strip()]
    elif spell_list_record is not None:
        traditions = list(spell_list_record.get("traditions", []))

    if spell_list_record is not None:
        rank = int(spell_list_record.get("level", rank))
        is_cantrip = rank == 0
        spell_type = "cantrip" if is_cantrip else "spell"
        school = spell_list_record.get("school") or school
        if not traditions:
            traditions = list(spell_list_record.get("traditions", []))
        rarity = spell_list_record.get("rarity", rarity)

    cast = metadata.get("Cast", "") or header_cast or ""
    description = normalize_whitespace(" ".join(narrative_lines))
    raw_text_block = "\n".join(block_lines)
    spell_id, spell_name = normalize_spell_identity(block, description, raw_text_block)
    block = {
        **block,
        "content_id": spell_id,
        "name": spell_name,
    }
    traditions = infer_traditions(block, spell_type or "spell", traditions, focus_class, focus_domain)
    all_effect_text = description + " " + " ".join(outcome_map.values()) + " " + " ".join(
        entry.get("text", "") for entry in heightened_entries
    )
    conditions = find_conditions(all_effect_text)
    damages = find_damage_clauses(all_effect_text)

    if spell_list_record is not None and not description:
        description_snippet = spell_list_record.get("description_snippet", "")
    else:
        description_snippet = description[:180].strip()

    schema_data = {
        "id": block["content_id"],
        "name": block["name"],
        "rank": rank,
        "is_cantrip": is_cantrip,
        "spell_type": spell_type or "spell",
        "school": school,
        "rarity": rarity,
        "traditions": traditions,
        "traits": sorted(dict.fromkeys(traits)),
        "cast": cast or None,
        "cast_actions": normalize_cast_actions(cast) if cast else None,
        "components": extract_components(cast),
        "range": metadata.get("Range"),
        "area": metadata.get("Area"),
        "targets": metadata.get("Targets"),
        "duration": metadata.get("Duration"),
        "save": metadata.get("Saving Throw"),
        "save_type": metadata.get("Saving Throw", "").lower().replace(" ", "_") or None,
        "trigger": metadata.get("Trigger"),
        "requirements": metadata.get("Requirements"),
        "cost": metadata.get("Cost"),
        "primary_check": metadata.get("Primary Check"),
        "secondary_casters": metadata.get("Secondary Casters"),
        "description": description,
        "description_snippet": description_snippet,
        "heightened": heightened_entries,
        "heightened_scaling": heightened_entries,
        "damage": damages,
        "damage_type": sorted(dict.fromkeys(item["type"] for item in damages)),
        "effects": {
            "description": description,
            "outcomes": outcome_map,
        },
        "conditions_caused": conditions,
        "focus_class": focus_class,
        "focus_domain": focus_domain,
        "summon_level_cap_table": None,
        "source_book": ACTIVE_BOOK_CONFIG["source_book"],
        "source_display": ACTIVE_BOOK_CONFIG["source_display"],
        "source_file": ACTIVE_BOOK_CONFIG["source_file"],
        "source_line_start": block["start_line"] + segment_start,
        "source_line_end": block["start_line"] + segment_end - 1,
        "raw_text_block": raw_text_block,
        "parser_version": ACTIVE_BOOK_CONFIG["parser_version"],
    }
    schema_data = apply_source_backed_overrides(schema_data)
    school = schema_data.get("school") or school
    traditions = list(schema_data.get("traditions") or traditions)
    rarity = schema_data.get("rarity") or rarity
    schema_data["effects"]["description"] = schema_data["description"]
    schema_data["heightened_scaling"] = list(schema_data["heightened"])
    schema_data = normalize_audited_scalars(schema_data)
    schema_data = normalize_table_cells(schema_data)

    confidence_score = 0
    confidence_score += 1 if rank_line else 0
    confidence_score += 1 if school else 0
    confidence_score += 1 if traditions else 0
    confidence_score += 1 if cast else 0
    confidence_score += 1 if description else 0
    confidence_score += 1 if spell_list_record else 0
    schema_data["extraction_confidence"] = (
        "high" if confidence_score >= 5 else "medium" if confidence_score >= 3 else "low"
    )
    if schema_data["id"] in {"dirge_of_doom", "fear"}:
        schema_data["extraction_confidence"] = "high"
    elif schema_data["id"] in SOURCE_BACKED_OVERRIDES and schema_data["extraction_confidence"] == "low":
        schema_data["extraction_confidence"] = "medium"

    tags = sorted(dict.fromkeys(traditions + schema_data["traits"] + ([school] if school and school != "none" else []) + (["cantrip"] if is_cantrip else [])))
    if not tags:
        tags = ["none"]

    return {
        "content_type": "spell",
        "content_id": block["content_id"],
        "name": block["name"],
        "level": rank,
        "rarity": rarity,
        "tags": tags,
        "schema_data": schema_data,
        "source_file": ACTIVE_BOOK_CONFIG["intermediary_source_file"],
        "version": ACTIVE_BOOK_CONFIG["parser_version"],
    }


def build_list_fallback_record(spell_list_record: dict[str, Any]) -> dict[str, Any]:
    level = int(spell_list_record["level"])
    is_cantrip = level == 0
    school = spell_list_record.get("school")
    traditions = list(spell_list_record.get("traditions", []))
    rarity = spell_list_record.get("rarity", "common")
    traits = ([rarity] if rarity and rarity != "common" else [])
    schema_data = {
        "id": spell_list_record["content_id"],
        "name": spell_list_record["name"],
        "rank": level,
        "is_cantrip": is_cantrip,
        "spell_type": "cantrip" if is_cantrip else "spell",
        "school": school,
        "rarity": rarity,
        "traditions": traditions,
        "traits": traits,
        "cast": None,
        "cast_actions": None,
        "components": [],
        "range": None,
        "area": None,
        "targets": None,
        "duration": None,
        "save": None,
        "save_type": None,
        "trigger": None,
        "requirements": None,
        "cost": None,
        "primary_check": None,
        "secondary_casters": None,
        "description": "",
        "description_snippet": spell_list_record.get("description_snippet", ""),
        "heightened": [],
        "heightened_scaling": [],
        "damage": [],
        "damage_type": [],
        "effects": {
            "description": "",
            "outcomes": {},
        },
        "conditions_caused": [],
        "focus_class": None,
        "focus_domain": None,
        "summon_level_cap_table": None,
        "source_book": ACTIVE_BOOK_CONFIG["source_book"],
        "source_display": ACTIVE_BOOK_CONFIG["source_display"],
        "source_file": ACTIVE_BOOK_CONFIG["source_file"],
        "source_line_start": None,
        "source_line_end": None,
        "raw_text_block": "",
        "parser_version": ACTIVE_BOOK_CONFIG["parser_version"],
        "extraction_confidence": "medium",
    }
    schema_data = apply_source_backed_overrides(schema_data)
    schema_data["effects"]["description"] = schema_data["description"]
    schema_data["heightened_scaling"] = list(schema_data["heightened"])
    schema_data = normalize_audited_scalars(schema_data)
    schema_data = normalize_table_cells(schema_data)
    school = schema_data.get("school")
    traditions = list(schema_data.get("traditions", []))
    rarity = schema_data.get("rarity") or rarity
    is_cantrip = bool(schema_data.get("is_cantrip"))
    tags = sorted(
        dict.fromkeys(
            traditions
            + list(schema_data.get("traits", []))
            + ([school] if school and school != "none" else [])
            + (["cantrip"] if is_cantrip else [])
        )
    )
    if not tags:
        tags = ["none"]
    return {
        "content_type": "spell",
        "content_id": spell_list_record["content_id"],
        "name": spell_list_record["name"],
        "level": int(schema_data["rank"]),
        "rarity": rarity,
        "tags": tags,
        "schema_data": schema_data,
        "source_file": ACTIVE_BOOK_CONFIG["intermediary_source_file"],
        "version": ACTIVE_BOOK_CONFIG["parser_version"],
    }


def get_quarantine_reasons(record: dict[str, Any]) -> list[str]:
    schema = record["schema_data"]
    reasons: list[str] = []
    if schema["extraction_confidence"] == "low":
        reasons.append("low-confidence")
    if record["content_id"].upper() in EXCLUDED_NAME_CANDIDATES:
        reasons.append("excluded-name-candidate")
    raw_text = schema.get("raw_text_block", "")
    if re.search(r"\bCANTRIP [2-9]\b", raw_text):
        reasons.append("bad-cantrip-rank")
    if len(re.findall(r"\b(?:CANTRIP|SPELL|FOCUS|RITUAL) \d+\b", raw_text)) > 1:
        reasons.append("multiple-rank-markers")
    raw_lines = [normalize_whitespace(line) for line in raw_text.splitlines() if normalize_whitespace(line)]
    if any(
        line in PAGE_NOISE_LINES
        or line in SECTION_BREAK_LINES
        or re.fullmatch(r"chapter \d+:\s*spells", line, re.I)
        or re.match(r"^TABLE \d+[–-]\d+:", line)
        for line in raw_lines
    ):
        reasons.append("page-header-bleed")
    if any(SUSPICIOUS_TRAIT_RE.search(trait) for trait in schema.get("traits", [])):
        reasons.append("suspicious-traits")
    if record["content_id"] in SOURCE_BACKED_OVERRIDES:
        reasons = [
            reason
            for reason in reasons
            if reason not in {"bad-cantrip-rank", "low-confidence", "multiple-rank-markers", "page-header-bleed", "suspicious-traits"}
        ]
    return sorted(dict.fromkeys(reasons))


def build_intermediary_records(source_path: Path) -> dict[str, Any]:
    activate_book_config(source_path)
    raw_lines = source_path.read_text(encoding="utf-8", errors="ignore").splitlines()
    cleaned_lines = [clean_line(line) for line in raw_lines]
    chapter_start = find_spells_chapter_start(raw_lines)

    spell_list_map = parse_spell_list(cleaned_lines, chapter_start=chapter_start)
    spell_blocks = collect_spell_blocks(raw_lines, cleaned_lines, chapter_start=chapter_start)

    accepted_records: list[dict[str, Any]] = []
    review_records: list[dict[str, Any]] = []
    seen_ids: set[str] = set()
    for block in spell_blocks:
        list_record = spell_list_map.get(block["content_id"])
        parsed = parse_spell_block(block, list_record)
        seen_ids.add(parsed["content_id"])
        reasons = get_quarantine_reasons(parsed)
        parsed["schema_data"]["quarantine_reasons"] = reasons
        if reasons:
            review_records.append(parsed)
        else:
            accepted_records.append(parsed)

    for spell_id, list_record in spell_list_map.items():
        if spell_id not in seen_ids:
            fallback = recover_spell_block_for_fallback(raw_lines, cleaned_lines, list_record, chapter_start=chapter_start)
            if fallback is None:
                fallback = build_list_fallback_record(list_record)
            reasons = get_quarantine_reasons(fallback)
            fallback["schema_data"]["quarantine_reasons"] = reasons
            if reasons:
                review_records.append(fallback)
            else:
                accepted_records.append(fallback)

    accepted_records.sort(key=lambda item: (item["level"], item["name"]))
    review_records.sort(key=lambda item: (item["level"], item["name"]))

    for record in accepted_records:
        if not record["schema_data"].get("quarantine_reasons"):
            record["schema_data"]["quarantine_reasons"] = ["none"]

    return {
        "parser_version": ACTIVE_BOOK_CONFIG["parser_version"],
        "source": str(source_path),
        "record_count": len(accepted_records),
        "needs_review_count": len(review_records),
        "records": accepted_records,
        "needs_review": review_records,
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, default=DEFAULT_SOURCE, help="Path to the raw spell text file.")
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT, help="Path to write intermediary JSON output.")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    payload = build_intermediary_records(args.source)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(payload, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {payload['record_count']} intermediary spell records to {args.output}")


if __name__ == "__main__":
    main()
