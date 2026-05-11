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
    "disrupting_weapons": {
        "rank": 1,
        "spell_type": "spell",
        "school": "necromancy",
        "traditions": ["divine"],
        "traits": ["positive"],
        "description": "Weapons deal positive damage to undead.",
        "description_snippet": "Weapons deal positive damage to undead.",
        "source_line_start": 47301,
        "source_line_end": 47302,
        "raw_text_block": "Disrupting Weapons H (nec): Weapons deal positive damage to undead.",
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
        "description": "Stupefy a creature permanently.",
        "description_snippet": "Stupefy a creature permanently.",
        "source_line_start": 47060,
        "source_line_end": 47061,
        "raw_text_block": "Feeblemind (enc): Stupefy a creature permanently.",
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
        "source_line_start": 46638,
        "source_line_end": 46638,
        "raw_text_block": "Alarm H (abj): Be alerted if a creature",
    },
    "aerial_form": {
        "source_line_start": 46889,
        "source_line_end": 46889,
        "raw_text_block": "Aerial Form H (tra): Turn into a flying",
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
        "source_line_start": 46641,
        "source_line_end": 46641,
        "raw_text_block": "Burning Hands H (evo): A small cone of",
    },
    "cataclysm": {
        "source_line_start": 47205,
        "source_line_end": 47205,
        "raw_text_block": "Cataclysm (evo): Call an instant,",
    },
    "chilling_darkness": {
        "source_line_start": 47400,
        "source_line_end": 47400,
        "raw_text_block": "Chilling Darkness H (evo): Ray of",
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
        "source_line_start": 47348,
        "source_line_end": 47348,
        "raw_text_block": "Death Knell (nec): Finish off a creature",
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
    "eclipse_burst": {
        "source_line_start": 47101,
        "source_line_end": 47101,
        "raw_text_block": "Eclipse Burst H (nec): A globe of",
    },
    "feather_fall": {
        "source_line_start": 46655,
        "source_line_end": 46655,
        "raw_text_block": "Feather Fall (abj): React to slow a",
    },
    "field_of_life": {
        "source_line_start": 47531,
        "source_line_end": 47531,
        "raw_text_block": "Field of Life H (nec): Create a positive",
    },
    "finger_of_death": {
        "source_line_start": 47576,
        "source_line_end": 47576,
        "raw_text_block": "Finger of Death H (nec): Point at a",
    },
    "magic_weapon": {
        "source_line_start": 46689,
        "source_line_end": 46689,
        "raw_text_block": "Magic Weapon (tra): Make a weapon",
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
    "plane_shift": {
        "source_line_start": 47115,
        "source_line_end": 47115,
        "raw_text_block": "Plane Shift U (con): Transport creatures",
    },
    "remove_paralysis": {
        "source_line_start": 47366,
        "source_line_end": 47366,
        "raw_text_block": "Remove Paralysis H (nec): Free a",
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
        "source_line_start": 46784,
        "source_line_end": 46784,
        "raw_text_block": "Water Breathing H (tra): Allow creatures",
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
