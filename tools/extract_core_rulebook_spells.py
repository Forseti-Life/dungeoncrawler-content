#!/usr/bin/env python3
"""
Extract PF2E Core Rulebook spells into a library-row intermediary format.

This parser reads the raw text extraction for the PF2E Core Rulebook and emits
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
TRADITION_HEADER_RE = re.compile(r"^(Arcane|Divine|Occult|Primal)\s+(Cantrips|[1-9]\w*-Level Spells)$", re.I)
SPELL_LIST_ENTRY_RE = re.compile(r"^([A-Z][A-Za-z0-9'’,\- ]+?)([HUR,\s]*)\s*\((abj|con|div|enc|evo|ill|nec|tra)\):\s*(.+)$")
SPELL_DETAIL_NAME_RE = re.compile(r"^[A-Z][A-Z0-9'’,\- ]{1,80}$")
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
    "RANGER",
    "SORCERER",
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
    },
    "localized_quake": {
        "school": "transmutation",
    },
    "dirge_of_doom": {
        "rank": 0,
        "spell_type": "cantrip",
        "school": "enchantment",
        "traditions": ["occult"],
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
    if line.startswith("Chapter 7: Spells"):
        return ""
    return line


def title_case_name_from_heading(line: str) -> str:
    words = normalize_whitespace(line).split(" ")
    return " ".join(word.capitalize() if word.isupper() else word.capitalize() for word in words)


def parse_spell_list(lines: list[str]) -> dict[str, dict[str, Any]]:
    list_start = next((idx for idx, line in enumerate(lines) if line == "Spell Lists"), None)
    if list_start is None:
        raise RuntimeError("Could not find 'Spell Lists' in Core Rulebook raw text.")

    current_tradition: str | None = None
    current_level: int | None = None
    entries: dict[str, dict[str, Any]] = {}
    pending_line = ""

    for idx in range(list_start + 1, len(lines)):
        line = clean_line(lines[idx])
        if not line:
            pending_line = ""
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
            level_label = header_match.group(2).lower()
            current_level = 0 if "cantrip" in level_label else int(level_label[0])
            pending_line = ""
            continue

        if current_tradition is None or current_level is None:
            continue

        candidate = f"{pending_line} {line}".strip() if pending_line else line
        match = SPELL_LIST_ENTRY_RE.match(candidate)
        if not match:
            pending_line = candidate if ":" not in candidate and len(candidate.split()) < 18 else ""
            continue

        pending_line = ""
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
        record["spell_list_evidence"].append(candidate)

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


def collect_spell_blocks(raw_lines: list[str], lines: list[str]) -> list[dict[str, Any]]:
    chapter_markers = [
        idx
        for idx, raw_line in enumerate(raw_lines)
        if normalize_whitespace(raw_line).startswith("Chapter 7: Spells")
    ]
    chapter_start = chapter_markers[-1] if chapter_markers else None
    if chapter_start is None:
        raise RuntimeError("Could not find 'Chapter 7: Spells' in Core Rulebook raw text.")

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
        if token != "cantrip":
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
    if schema_data.get("save_type") in (None, "") and schema_data.get("save") not in (None, "", "NA"):
        schema_data["save_type"] = schema_data["save"].lower().replace(" ", "_")
    for field in AUDITED_SCALAR_FIELDS:
        if schema_data.get(field) in (None, ""):
            schema_data[field] = "NA"
    return schema_data


def normalize_table_cells(schema_data: dict[str, Any]) -> dict[str, Any]:
    if schema_data.get("spell_type") == "focus" and schema_data.get("focus_domain") and not schema_data.get("focus_class"):
        schema_data["focus_class"] = "cleric"

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
    return schema_data


def parse_spell_block(block: dict[str, Any], spell_list_record: dict[str, Any] | None) -> dict[str, Any]:
    block_lines = block["lines"]
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
        "source_book": "core_rulebook_4th_printing",
        "source_display": "Core Rulebook (Fourth Printing)",
        "source_file": DEFAULT_SOURCE.name,
        "source_line_start": block["start_line"],
        "source_line_end": block["end_line"],
        "raw_text_block": raw_text_block,
        "parser_version": PARSER_VERSION,
    }
    schema_data = apply_source_backed_overrides(schema_data)
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

    tags = sorted(dict.fromkeys(traditions + schema_data["traits"] + ([school] if school else []) + (["cantrip"] if is_cantrip else [])))

    return {
        "content_type": "spell",
        "content_id": block["content_id"],
        "name": block["name"],
        "level": rank,
        "rarity": rarity,
        "tags": tags,
        "schema_data": schema_data,
        "source_file": f"intermediary/{DEFAULT_SOURCE.name}",
        "version": PARSER_VERSION,
    }


def build_list_fallback_record(spell_list_record: dict[str, Any]) -> dict[str, Any]:
    level = int(spell_list_record["level"])
    is_cantrip = level == 0
    school = spell_list_record.get("school")
    traditions = list(spell_list_record.get("traditions", []))
    rarity = spell_list_record.get("rarity", "common")
    traits = ([rarity] if rarity and rarity != "common" else [])
    tags = sorted(dict.fromkeys(traditions + traits + ([school] if school else []) + (["cantrip"] if is_cantrip else [])))
    return {
        "content_type": "spell",
        "content_id": spell_list_record["content_id"],
        "name": spell_list_record["name"],
        "level": level,
        "rarity": rarity,
        "tags": tags,
        "schema_data": {
            "id": spell_list_record["content_id"],
            "name": spell_list_record["name"],
            "rank": level,
            "is_cantrip": is_cantrip,
            "spell_type": "cantrip" if is_cantrip else "spell",
            "school": school,
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
            "source_book": "core_rulebook_4th_printing",
            "source_display": "Core Rulebook (Fourth Printing)",
            "source_file": DEFAULT_SOURCE.name,
            "source_line_start": None,
            "source_line_end": None,
            "raw_text_block": "",
            "parser_version": PARSER_VERSION,
            "extraction_confidence": "low",
        },
        "source_file": f"intermediary/{DEFAULT_SOURCE.name}",
        "version": PARSER_VERSION,
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
    if "Core Rulebook" in raw_text or "SPELLS" in raw_text:
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
    raw_lines = source_path.read_text(encoding="utf-8", errors="ignore").splitlines()
    cleaned_lines = [clean_line(line) for line in raw_lines]

    spell_list_map = parse_spell_list(cleaned_lines)
    spell_blocks = collect_spell_blocks(raw_lines, cleaned_lines)

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
            fallback["schema_data"]["quarantine_reasons"] = ["low-confidence"]
            review_records.append(fallback)

    accepted_records.sort(key=lambda item: (item["level"], item["name"]))
    review_records.sort(key=lambda item: (item["level"], item["name"]))

    for record in accepted_records:
        if not record["schema_data"].get("quarantine_reasons"):
            record["schema_data"]["quarantine_reasons"] = ["none"]

    return {
        "parser_version": PARSER_VERSION,
        "source": str(source_path),
        "record_count": len(accepted_records),
        "needs_review_count": len(review_records),
        "records": accepted_records,
        "needs_review": review_records,
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, default=DEFAULT_SOURCE, help="Path to the Core Rulebook raw text file.")
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
