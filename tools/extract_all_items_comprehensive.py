#!/usr/bin/env python3
"""
Extract PF2E item inventories from raw source-book text into an intermediary JSON.

This is intentionally an audit/intermediary extractor, not the final canonical item
template generator. It pulls likely item rows from the raw reference-book text so
we can track book coverage until we finish richer item extraction.
"""

from __future__ import annotations

import argparse
import json
import re
from collections import defaultdict
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
DOCS_ROOT = ROOT.parent / "forseti-docs/dungeoncrawler/reference documentation"
DEFAULT_OUTPUT = ROOT / "content/intermediary/comprehensive_items_intermediary.json"
PARSER_VERSION = "item-comprehensive-v3"
GENERATED_CORE_APG_INVENTORY = DOCS_ROOT / "generated_item_inventory_core_apg.json"
COMPREHENSIVE_INVENTORY = DOCS_ROOT / "comprehensive_item_inventory.json"

SOURCES = [
    {
        "file": "PF2E Core Rulebook - Fourth Printing.txt",
        "slug": "core_rulebook_4th_printing",
        "display": "Core Rulebook (4th Printing)",
    },
    {
        "file": "PF2E Advanced Players Guide.txt",
        "slug": "advanced_players_guide",
        "display": "Advanced Player's Guide",
    },
    {
        "file": "PF2E Secrets of Magic.txt",
        "slug": "secrets_of_magic",
        "display": "Secrets of Magic",
    },
    {
        "file": "PF2E Guns and Gears.txt",
        "slug": "guns_and_gears",
        "display": "Guns & Gears",
    },
    {
        "file": "PF2E Gamemastery Guide.txt",
        "slug": "gamemastery_guide",
        "display": "Gamemastery Guide",
    },
    {
        "file": "PF2E Gods and Magic.txt",
        "slug": "gods_and_magic",
        "display": "Gods & Magic",
    },
]

ITEM_SECTION_MARKERS = (
    "WEAPONS",
    "ARMOR",
    "SHIELDS",
    "EQUIPMENT",
    "ADVENTURING GEAR",
    "ALCHEMICAL ITEMS",
    "MAGIC ITEMS",
    "CONSUMABLE ITEMS",
    "CONSUMABLES",
    "RUNES",
    "SPELLHEARTS",
    "TALISMANS",
    "GRIMOIRES",
    "SNARES",
    "TOOLS",
    "FIREARMS",
    "AMMUNITION",
    "GEAR",
)

SECTION_BREAK_MARKERS = (
    "SPELLS",
    "SPELL DESCRIPTIONS",
    "FEATS",
    "SKILLS",
    "CLASSES",
    "ANCESTRIES",
    "ARCHETYPES",
    "RITUALS",
)

PRICE_RE = re.compile(r"(?<!\w)(\d+(?:,\d+)?)\s*(gp|sp|cp|pp)\b", re.I)
ITEM_MARKER_RE = re.compile(r"^ITEM\s+(\d+)\s*[:\-]?\s*(.*)$", re.I)
LEVEL_IN_NAME_RE = re.compile(r"\(level\s+(\d+)\)", re.I)
RARITY_RE = re.compile(r"\b(common|uncommon|rare|unique)\b", re.I)
UPPERCASE_NAME_RE = re.compile(r"^[A-Z0-9'’&,\-–+./\s]+$")

STOPWORD_SET = {"of", "the", "a", "an", "and", "or", "for", "with", "to"}

HARD_NOISE_PREFIXES = (
    "type ",
    "level ",
    "cast ",
    "heightened",
    "requirements",
    "usage ",
    "activate ",
    "starting money",
    "money left",
    "money leftover",
    "leftover",
    "options ",
    "huge creature",
    "instructions for ",
    "craft requirements",
    "and ",
    "with ",
    "value of",
    "street performance",
)

HARD_NOISE_EXACT = {
    "consumable",
    "held",
    "potion",
    "poison",
    "rune",
    "spellheart",
    "staff",
    "talisman",
    "tattoo",
    "eidolon",
    "catalyst",
    "grimoire",
    "weapon",
    "worn",
    "wand",
    "shield",
    "bomb",
    "apex",
    "stage play",
}

GENERIC_HEADERS = {
    "WEAPONS",
    "ARMOR",
    "EQUIPMENT",
    "ITEMS",
    "NAME",
    "PRICE",
    "BULK",
    "LEVEL",
    "TRAITS",
    "DAMAGE",
    "RANGE",
    "RELOAD",
    "GROUP",
    "CATEGORY",
}

TITLE_NOISE = {
    "UNCOMMON",
    "COMMON",
    "RARE",
    "UNIQUE",
    "MAGICAL",
    "DIVINE",
    "ARCANE",
    "OCCULT",
    "PRIMAL",
    "ALCHEMICAL",
    "CONSUMABLE",
    "DRUG",
    "POISON",
    "INVESTED",
    "INTELLIGENT",
    "CURSED",
    "ABJURATION",
    "ARTIFACT",
    "CONJURATION",
    "CURSE",
    "CURSED",
    "DIVINATION",
    "ENCHANTMENT",
    "EVOCATION",
    "FIRE",
    "ILLUSION",
    "ITEM",
    "MISFORTUNE",
    "NECROMANCY",
    "TRANSMUTATION",
    "AURA",
    "ACCESS",
    "USAGE",
    "ACTIVATE",
    "PRICE",
    "TOOLS",
    "INTRODUCTION",
    "GAMEMASTERY GUIDE",
    "GODS &",
    "MAGIC",
    "OVERVIEW",
    "GODS OF THE",
    "INNER SEA",
    "DEMIGODS",
    "DIVINITIES",
    "APPENDIX",
    "INDEX AND",
    "GLOSSARY",
    "LG",
    "NG",
    "CG",
    "LN",
    "N",
    "CN",
    "LE",
    "NE",
    "CE",
    "ACID",
    "AIR",
    "AMMUNITION",
    "APEX",
    "AUDITORY",
    "CATALYST",
    "CHAOTIC",
    "CLOCKWORK",
    "COLD",
    "COMPANION",
    "CONSUMABLES",
    "DARKNESS",
    "EARTH",
    "ELECTRICITY",
    "ELIXIR",
    "ELIXIRS",
    "EMOTION",
    "EXTRADIMENSIONAL",
    "FEAR",
    "FOCUSED",
    "FORCE",
    "FORTUNE",
    "GADGET",
    "GEARS",
    "GOOD",
    "MENTAL",
    "NEGATIVE",
    "PLANT",
    "POSITIVE",
}


def slugify(text: str) -> str:
    text = text.replace("’", "'").replace("‘", "'")
    text = re.sub(r"[^\w\s-]", "", text.lower())
    text = re.sub(r"[-\s]+", "_", text)
    return text.strip("_")


def extract_price_gp(text: str) -> float | None:
    match = PRICE_RE.search(text)
    if not match:
        return None

    amount = int(match.group(1).replace(",", ""))
    unit = match.group(2).lower()
    if unit == "cp":
        return amount / 100
    if unit == "sp":
        return amount / 10
    if unit == "pp":
        return amount * 10
    return float(amount)


def infer_level(name: str, line: str) -> int:
    marker_match = ITEM_MARKER_RE.match(line)
    if marker_match:
        return int(marker_match.group(1))

    level_match = LEVEL_IN_NAME_RE.search(name)
    if level_match:
        return int(level_match.group(1))

    return 0


def infer_rarity(text: str) -> str:
    match = RARITY_RE.search(text)
    return match.group(1).lower() if match else "common"


def classify_item_type(name: str, context: str = "") -> str:
    combined = f"{name} {context}".lower()

    if any(keyword in combined for keyword in ("shield", "buckler")):
        return "shield"
    if any(keyword in combined for keyword in (
        "sword", "axe", "bow", "crossbow", "dagger", "mace", "spear",
        "staff", "club", "hammer", "blade", "pistol", "musket",
        "firearm", "gun", "arquebus", "blunderbuss", "rifle", "shot",
        "weapon", "arrow", "bolt", "dogslicer", "kukri", "katana",
        "flickmace", "nunchaku", "sai", "scythe", "wakizashi",
    )):
        return "weapon"
    if any(keyword in combined for keyword in (
        "armor", "mail", "plate", "leather", "hide", "chain",
        "breastplate", "clothing",
    )):
        return "armor"
    if any(keyword in combined for keyword in (
        "potion", "elixir", "bomb", "flask", "poison", "oil", "snare",
        "talisman", "spellheart", "grimoire", "rune", "wand",
    )):
        return "consumable"

    return "adventuring_gear"


def item_category_for_type(item_type: str) -> str:
    if item_type in {"weapon", "armor", "shield"}:
        return item_type
    if item_type == "consumable":
        return "consumable"
    return "gear"


def normalize_candidate_name(name: str) -> str:
    name = name.replace("’", "'").replace("‘", "'").strip()
    name = name.replace("\x08", "")
    name = re.sub(r"^(ITEM|Item)\s+\d+\s*[:\-]?\s*", "", name)
    name = re.sub(r"\s+(Page|Pg|p\.?)\s*\d+.*$", "", name, flags=re.I)
    return " ".join(name.split())


def is_noise(name: str) -> bool:
    raw = name.strip()
    if raw == "" or len(raw) < 3 or len(raw) > 90:
        return True
    if raw.upper() in GENERIC_HEADERS:
        return True

    lowered = raw.lower()
    if lowered in HARD_NOISE_EXACT:
        return True
    if any(lowered.startswith(prefix) for prefix in HARD_NOISE_PREFIXES):
        return True
    if ";" in raw:
        return True
    if ":" in raw:
        return True
    if raw.isupper() and len(raw) > 12:
        return True
    if raw.endswith((" worth at least", " Price", " gp", " sp", " cp")):
        return True
    if " gp +" in raw.lower():
        return True
    if " worth" in lowered or " costs" in lowered or " tips" in lowered:
        return True
    if "(" in raw and ")" not in raw:
        return True
    if raw[0].islower():
        return True
    if raw.endswith(("of", "the", "a", "an", "with", "for", "to")):
        return True
    if raw.startswith("1d") and "blowgun darts" not in lowered:
        return True
    if ("×" in raw or "x" in raw.lower()) and re.search(r"\b\d+d\d+\b", raw, flags=re.IGNORECASE):
        return True

    words = raw.split()
    if len(words) > 10:
        return True
    normalized_words = [word for word in re.split(r"[\s'’&,\-–+./]+", raw.upper()) if word]
    if normalized_words and all(word in TITLE_NOISE or word in GENERIC_HEADERS for word in normalized_words):
        return True
    lowercase_non_stop = sum(
        1 for word in words[1:]
        if word.islower() and word.lower() not in STOPWORD_SET
    )
    if len(words) >= 4 and lowercase_non_stop > len(words) / 2:
        return True

    return False


def is_uppercase_title_candidate(line: str) -> bool:
    candidate = normalize_candidate_name(line)
    if candidate == "" or len(candidate) < 3 or len(candidate) > 80:
        return False
    if any(char.isdigit() for char in candidate):
        return False
    if ";" in candidate or ":" in candidate:
        return False
    if not UPPERCASE_NAME_RE.match(candidate):
        return False
    if candidate.upper() in GENERIC_HEADERS or candidate.upper() in TITLE_NOISE:
        return False
    words = [word for word in re.split(r"[\s'’&,\-–+./]+", candidate.upper()) if word]
    meaningful_words = [word for word in words if word not in STOPWORD_SET and word not in TITLE_NOISE]
    if not meaningful_words:
        return False
    if candidate.startswith(("Price ", "Usage ", "Access ", "Activate ")):
        return False
    return not is_noise(candidate.title())


def find_nearby_title(lines: list[str], item_index: int) -> str | None:
    for offset in range(1, 41):
        previous_index = item_index - offset
        if previous_index < 0:
            break
        previous = lines[previous_index].strip()
        if is_uppercase_title_candidate(previous):
            return normalize_candidate_name(previous.title())
    return None


def has_item_block_signals(lines: list[str], item_index: int) -> bool:
    start = max(0, item_index - 2)
    end = min(len(lines), item_index + 9)
    block = " ".join(lines[start:end])
    return any(signal in block for signal in ("Price ", "Usage ", "Activate ", "Bulk ", "Access "))


def normalize_artifact_item(item: dict[str, Any], source: dict[str, str]) -> dict[str, Any] | None:
    name = normalize_candidate_name(str(item.get("name", "")).replace("’", "'"))
    if is_noise(name):
        return None

    return {
        "name": name,
        "line": int(((item.get("references") or [{}])[0].get("line") or 0)),
        "method": str(((item.get("references") or [{}])[0].get("method") or "artifact")),
        "evidence": str(((item.get("references") or [{}])[0].get("evidence") or name)),
        "context": "",
        "price_gp": item.get("price_gp"),
        "level": int(item.get("level") or infer_level(name, "")),
        "rarity": infer_rarity(f"{item.get('rarity', '')} {name}"),
        "source_file": str(((item.get("references") or [{}])[0].get("source_file") or source["file"])),
        "source_book": source["slug"],
        "source_display": source["display"],
    }


def extract_items_from_artifacts() -> tuple[list[dict[str, Any]], set[str]]:
    extracted: list[dict[str, Any]] = []
    covered_sources: set[str] = set()
    source_lookup = {source["slug"]: source for source in SOURCES}

    if GENERATED_CORE_APG_INVENTORY.exists():
        with GENERATED_CORE_APG_INVENTORY.open("r", encoding="utf-8") as handle:
            payload = json.load(handle)
        for item in payload.get("items", []):
            refs = item.get("references") or []
            source_slug = "advanced_players_guide" if any("Advanced Players Guide" in str(ref.get("file", "")) for ref in refs) else "core_rulebook_4th_printing"
            normalized = normalize_artifact_item(item, source_lookup[source_slug])
            if normalized is not None:
                extracted.append(normalized)
        covered_sources.update({"core_rulebook_4th_printing", "advanced_players_guide"})

    if COMPREHENSIVE_INVENTORY.exists():
        with COMPREHENSIVE_INVENTORY.open("r", encoding="utf-8") as handle:
            payload = json.load(handle)
        for item in payload.get("items", []):
            source_slug = str(item.get("source_book", ""))
            if source_slug in {"core_rulebook_4th_printing", "advanced_players_guide"}:
                continue
            source = source_lookup.get(source_slug)
            if source is None:
                continue
            normalized = normalize_artifact_item(item, source)
            if normalized is not None:
                extracted.append(normalized)
                covered_sources.add(source_slug)

    return extracted, covered_sources


def extract_items_from_file(filepath: Path, source: dict[str, str]) -> list[dict[str, Any]]:
    with filepath.open("r", encoding="utf-8", errors="ignore") as handle:
        lines = handle.read().splitlines()

    extracted: list[dict[str, Any]] = []
    in_item_section = False
    context_window: list[str] = []

    for index, raw_line in enumerate(lines):
        line_number = index + 1
        line = raw_line.strip()
        upper = line.upper()

        if any(marker in upper for marker in ITEM_SECTION_MARKERS):
            in_item_section = True
            context_window = []
            continue
        if upper in SECTION_BREAK_MARKERS or (line.isupper() and len(line) > 20 and not any(marker in upper for marker in ITEM_SECTION_MARKERS)):
            in_item_section = False

        context_window.append(line)
        context_window = context_window[-8:]

        if not line:
            continue

        item_marker = ITEM_MARKER_RE.match(line)
        if item_marker:
            name = normalize_candidate_name(item_marker.group(2))
            if is_noise(name) or not has_item_block_signals(lines, index):
                nearby_title = find_nearby_title(lines, index)
                if nearby_title is not None:
                    name = nearby_title
            if not is_noise(name):
                extracted.append({
                    "name": name,
                    "line": line_number,
                    "method": "item_marker",
                    "evidence": line[:220],
                    "context": " ".join(context_window[-3:]),
                    "price_gp": extract_price_gp(line),
                    "level": int(item_marker.group(1)),
                    "rarity": infer_rarity(line),
                    "source_file": source["file"],
                    "source_book": source["slug"],
                    "source_display": source["display"],
                })
            continue

        if not in_item_section or not PRICE_RE.search(line):
            continue

        name_part = PRICE_RE.split(line, maxsplit=1)[0].strip()
        name = normalize_candidate_name(name_part)
        if is_noise(name):
            continue

        extracted.append({
            "name": name,
            "line": line_number,
            "method": "price_adjacent",
            "evidence": line[:220],
            "context": " ".join(context_window[-3:]),
            "price_gp": extract_price_gp(line),
            "level": infer_level(name, line),
            "rarity": infer_rarity(line),
            "source_file": source["file"],
            "source_book": source["slug"],
            "source_display": source["display"],
        })

    return extracted


def deduplicate_items(items: list[dict[str, Any]]) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    grouped: dict[str, dict[str, Any]] = {}
    needs_review: list[dict[str, Any]] = []

    for item in items:
        content_id = slugify(item["name"])
        if content_id == "":
            continue

        if item["name"].lower() in HARD_NOISE_EXACT:
            needs_review.append(item)
            continue

        record = grouped.get(content_id)
        reference = {
            "source_file": item["source_file"],
            "line": item["line"],
            "method": item["method"],
            "evidence": item["evidence"],
        }
        if record is None:
            grouped[content_id] = {
                "content_type": "item",
                "content_id": content_id,
                "name": item["name"],
                "level": item["level"],
                "rarity": item["rarity"],
                "tags": [item["source_book"], classify_item_type(item["name"], item["context"])],
                "schema_data": {
                    "id": content_id,
                    "item_id": content_id,
                    "content_id": content_id,
                    "name": item["name"],
                    "level": item["level"],
                    "rarity": item["rarity"],
                    "item_type": classify_item_type(item["name"], item["context"]),
                    "item_category": item_category_for_type(classify_item_type(item["name"], item["context"])),
                    "price_gp": item["price_gp"],
                    "source_book": item["source_book"],
                    "source_display": item["source_display"],
                    "parser_version": PARSER_VERSION,
                    "extraction_method": item["method"],
                    "references": [reference],
                },
                "source_file": f"intermediary/{item['source_file']}",
                "version": PARSER_VERSION,
            }
            continue

        schema = record["schema_data"]
        schema["references"].append(reference)
        record["tags"] = sorted(set(record["tags"] + [item["source_book"], classify_item_type(item["name"], item["context"])]))
        if schema.get("price_gp") is None and item["price_gp"] is not None:
            schema["price_gp"] = item["price_gp"]
        if item["level"] > schema.get("level", 0):
            schema["level"] = item["level"]
            record["level"] = item["level"]

    records = sorted(grouped.values(), key=lambda record: record["name"].lower())
    return records, needs_review


def build_output(records: list[dict[str, Any]], needs_review: list[dict[str, Any]]) -> dict[str, Any]:
    stats_by_source: defaultdict[str, int] = defaultdict(int)
    for record in records:
        for tag in record.get("tags", []):
            if tag in {source["slug"] for source in SOURCES}:
                stats_by_source[tag] += 1

    return {
        "parser_version": PARSER_VERSION,
        "sources": [source["display"] for source in SOURCES],
        "record_count": len(records),
        "needs_review_count": len(needs_review),
        "stats": {
            "by_source": dict(sorted(stats_by_source.items())),
        },
        "records": records,
        "needs_review": [
            {
                "name": item["name"],
                "source_book": item["source_book"],
                "source_display": item["source_display"],
                "line": item["line"],
                "method": item["method"],
                "evidence": item["evidence"],
            }
            for item in needs_review
        ],
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Extract PF2E item inventories into an intermediary JSON artifact.")
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT, help="Path to write the intermediary JSON.")
    return parser.parse_args()


def main() -> int:
    args = parse_args()

    all_items: list[dict[str, Any]] = []
    extracted_source_counts: dict[str, int] = {}
    for source in SOURCES:
        filepath = DOCS_ROOT / source["file"]
        if not filepath.exists():
            print(f"Skipping missing file: {filepath}")
            continue
        extracted = extract_items_from_file(filepath, source)
        extracted_source_counts[source["slug"]] = len(extracted)
        print(f"{source['display']}: extracted {len(extracted)} raw candidates")
        all_items.extend(extracted)

    artifact_items, _ = extract_items_from_artifacts()
    artifact_fallbacks = 0
    for item in artifact_items:
        if extracted_source_counts.get(item["source_book"], 0) > 0:
            continue
        all_items.append(item)
        artifact_fallbacks += 1
    if artifact_fallbacks:
        print(f"Supplementing with {artifact_fallbacks} artifact-backed fallback candidates")

    records, needs_review = deduplicate_items(all_items)
    output = build_output(records, needs_review)

    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open("w", encoding="utf-8") as handle:
        json.dump(output, handle, indent=2, ensure_ascii=False)
        handle.write("\n")

    print(f"Wrote {len(records)} item records to {args.output}")
    print(f"Needs review: {len(needs_review)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
