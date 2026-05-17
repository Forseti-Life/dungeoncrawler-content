#!/usr/bin/env python3
"""
Look up PF2E equipment metadata from Archives of Nethys and emit a canonical item stub.

This is a maintenance helper for missing equipment in content/items/. It does not
change runtime behavior or make AoN a live dependency. Instead, it fetches AoN's
public search metadata for a named item and maps the stable fields we can trust
into a schema-compatible local JSON stub that can be reviewed and committed.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from datetime import datetime, UTC
from pathlib import Path
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUTPUT_DIR = ROOT / "content/items"
ELASTIC_URL = "https://elasticsearch.aonprd.com/aon/_search?stats=aggregations"
ALLOWED_CATEGORIES = {"equipment", "weapon", "armor", "shield"}
DESCRIPTION_SPLIT_MARKERS = (
    "\n<title level=\"2\">",
    "\n<column gap=\"tiny\">",
    "\n<document level=\"2\"",
)
PRICE_COMPONENT_RE = re.compile(r"(\d+(?:,\d+)?)\s*(cp|sp|gp|pp)\b", re.IGNORECASE)
LINK_RE = re.compile(r"\[([^\]]+)\]\([^)]+\)")
TAG_RE = re.compile(r"<[^>]+>")
WHITESPACE_RE = re.compile(r"\s+")


def slugify(text: str) -> str:
    text = text.replace("’", "'").replace("‘", "'")
    text = re.sub(r"[^\w\s-]", "", text.lower())
    text = re.sub(r"[-\s]+", "_", text)
    return text.strip("_")


def normalize_name(text: str) -> str:
    return re.sub(r"[^a-z0-9]+", "", text.lower())


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Look up an AoN equipment item and emit a canonical item JSON stub.")
    parser.add_argument("name", help="Item name to search for, e.g. 'Backpack' or 'Longsword'.")
    parser.add_argument(
        "--write",
        action="store_true",
        help="Write the generated stub to content/items/<slug>.json instead of printing only.",
    )
    parser.add_argument(
        "--output",
        type=Path,
        help="Explicit output path. Defaults to content/items/<slug>.json when --write is set.",
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="Overwrite an existing output file.",
    )
    parser.add_argument(
        "--limit",
        type=int,
        default=10,
        help="Number of AoN search hits to inspect.",
    )
    return parser.parse_args()


def post_json(url: str, payload: dict[str, Any]) -> dict[str, Any]:
    request = Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    with urlopen(request, timeout=30) as response:
        return json.loads(response.read().decode("utf-8"))


def search_aon_equipment(name: str, limit: int) -> list[dict[str, Any]]:
    query = {
        "size": max(1, limit),
        "query": {
            "multi_match": {
                "query": name,
                "fields": [
                    "name^8",
                    "item_category^4",
                    "category^4",
                    "markdown^2",
                    "text",
                ],
                "operator": "and",
            }
        },
    }
    payload = post_json(ELASTIC_URL, query)
    hits = payload.get("hits", {}).get("hits", [])
    return [hit for hit in hits if str(hit.get("_source", {}).get("category", "")).lower() in ALLOWED_CATEGORIES]


def choose_best_hit(name: str, hits: list[dict[str, Any]]) -> dict[str, Any] | None:
    if not hits:
        return None

    normalized_query = normalize_name(name)

    def score(hit: dict[str, Any]) -> tuple[int, int, str, float]:
        source = hit.get("_source", {})
        normalized_hit_name = normalize_name(str(source.get("name", "")))
        exact = int(normalized_hit_name == normalized_query)
        remastered = int("player core" in str(source.get("primary_source", "")).lower() or "remastered" in str(source.get("primary_source_raw", "")).lower())
        release_date = str(source.get("release_date", ""))
        search_score = float(hit.get("_score", 0))
        return (exact, remastered, release_date, search_score)

    ranked = sorted(hits, key=score, reverse=True)
    best = ranked[0]
    if normalize_name(str(best.get("_source", {}).get("name", ""))) == normalized_query:
        return best

    return best


def clean_markdown_text(text: str) -> str:
    text = LINK_RE.sub(r"\1", text)
    text = TAG_RE.sub(" ", text)
    text = text.replace("**", " ").replace("__", " ")
    text = text.replace("\u2011", "-").replace("\u2019", "'")
    return WHITESPACE_RE.sub(" ", text).strip()


def extract_primary_description(source: dict[str, Any]) -> str:
    markdown = str(source.get("markdown", "")).strip()
    if "---" in markdown:
        description = markdown.split("---", 1)[1]
        for marker in DESCRIPTION_SPLIT_MARKERS:
            if marker in description:
                description = description.split(marker, 1)[0]
        cleaned = clean_markdown_text(description)
        if cleaned:
            return cleaned[:2000]

    summary = clean_markdown_text(str(source.get("summary", "")).replace("...", ""))
    return summary[:2000]


def parse_price_components(price_raw: str, price_cp: int | None) -> dict[str, int]:
    components = {"cp": 0, "sp": 0, "gp": 0, "pp": 0}
    for amount, unit in PRICE_COMPONENT_RE.findall(price_raw):
        components[unit.lower()] += int(amount.replace(",", ""))

    if any(components.values()):
        return components

    if price_cp is None:
        return components

    remaining = int(price_cp)
    components["pp"], remaining = divmod(remaining, 1000)
    components["gp"], remaining = divmod(remaining, 100)
    components["sp"], remaining = divmod(remaining, 10)
    components["cp"] = remaining
    return components


def normalize_bulk(source: dict[str, Any]) -> str | None:
    bulk_raw = source.get("bulk_raw")
    if isinstance(bulk_raw, str) and bulk_raw.strip():
        return bulk_raw.strip().replace(" ", "")

    bulk = source.get("bulk")
    if bulk is None:
        return None
    if bulk == 0:
        return "-"
    if bulk == 0.1:
        return "L"
    if isinstance(bulk, float) and bulk.is_integer():
        return str(int(bulk))
    return str(bulk)


def normalize_hands(value: Any) -> str | None:
    if value is None:
        return None
    hands = str(value).strip()
    return hands if hands in {"0", "1", "1+", "2"} else None


def normalize_traits(source: dict[str, Any]) -> list[str]:
    raw_traits = source.get("trait_raw")
    if not isinstance(raw_traits, list):
        return []
    traits = [str(trait).strip() for trait in raw_traits if str(trait).strip()]
    return sorted(dict.fromkeys(traits))


def parse_damage_type(source: dict[str, Any]) -> str | None:
    damage = str(source.get("damage", "")).upper()
    if damage.endswith(" B"):
        return "bludgeoning"
    if damage.endswith(" P"):
        return "piercing"
    if damage.endswith(" S"):
        return "slashing"

    types = [str(entry).lower() for entry in source.get("damage_type", []) if str(entry).strip()]
    for candidate in ("bludgeoning", "piercing", "slashing"):
        if candidate in types:
            return candidate
    return None


def parse_shield_bt(hp_raw: str) -> int | None:
    match = re.search(r"\((\d+)\)", hp_raw)
    return int(match.group(1)) if match else None


def infer_item_type(source: dict[str, Any], traits: list[str]) -> str:
    category = str(source.get("category", "")).lower()
    item_category = str(source.get("item_category", "")).lower()

    if category == "weapon":
        return "weapon"
    if category == "armor":
        return "armor"
    if category == "shield":
        return "shield"
    if "artifact" in item_category:
        return "artifact"
    if any(token in item_category for token in ("consumable", "ammunition", "potion", "elixir", "talisman")):
        return "consumable"
    if any("invested" in trait.lower() for trait in traits):
        return "worn_item"
    if any(token in item_category for token in ("held", "grimoire")):
        return "held_item"
    return "adventuring_gear"


def infer_item_category(item_type: str) -> str:
    return item_type if item_type in {"weapon", "armor", "shield", "consumable"} else "adventuring_gear"


def build_weapon_stats(source: dict[str, Any], traits: list[str]) -> dict[str, Any]:
    damage_type = parse_damage_type(source)
    damage_die = source.get("damage_die")
    if damage_type is None or not isinstance(damage_die, int):
        raise ValueError("AoN result does not contain enough weapon metadata to build weapon_stats.")

    return {
        "category": str(source.get("weapon_category", "martial")).lower(),
        "group": str(source.get("weapon_group", "")).lower(),
        "damage": {
            "dice_count": 1,
            "die_size": f"d{damage_die}",
            "damage_type": damage_type,
        },
        "range": None,
        "reload": None,
        "weapon_traits": traits,
    }


def build_armor_stats(source: dict[str, Any]) -> dict[str, Any]:
    speed_penalty_raw = str(source.get("speed_penalty", "")).strip()
    speed_penalty = 0
    if speed_penalty_raw and speed_penalty_raw != "\u2014":
        match = re.search(r"-?(\d+)", speed_penalty_raw)
        if match:
            speed_penalty = -int(match.group(1))

    return {
        "category": str(source.get("armor_category", "light")).lower(),
        "ac_bonus": int(source.get("ac", 0)),
        "dex_cap": int(source.get("dex_cap", 0)),
        "check_penalty": int(source.get("check_penalty", 0)),
        "speed_penalty": speed_penalty,
        "strength": int(source["strength"]) if source.get("strength") is not None else None,
        "armor_group": str(source.get("armor_group", "")).lower(),
    }


def build_shield_stats(source: dict[str, Any]) -> dict[str, Any]:
    hp_raw = str(source.get("hp_raw", ""))
    bt = parse_shield_bt(hp_raw)
    if bt is None:
        raise ValueError("AoN result does not contain shield broken threshold metadata.")

    return {
        "ac_bonus": int(source.get("ac", 0)),
        "hardness": int(source.get("hardness", 0)),
        "hp": int(source.get("hp", 0)),
        "bt": bt,
        "speed_penalty": 0,
    }


def build_consumable_stats(source: dict[str, Any]) -> dict[str, Any]:
    return {
        "consumable_type": "consumable",
        "activate": {
            "actions": "1",
            "components": ["interact"],
        },
        "effect": extract_primary_description(source),
        "duration": "varies",
    }


def build_canonical_stub(source: dict[str, Any]) -> dict[str, Any]:
    traits = normalize_traits(source)
    item_type = infer_item_type(source, traits)
    now = datetime.now(UTC).replace(microsecond=0).isoformat().replace("+00:00", "Z")

    stub: dict[str, Any] = {
        "schema_version": "1.0.0",
        "item_id": slugify(str(source["name"])),
        "name": str(source["name"]),
        "item_type": item_type,
        "level": int(source.get("level", 0)),
        "rarity": str(source.get("rarity", "common")).lower(),
        "traits": traits,
        "description": extract_primary_description(source),
        "price": parse_price_components(str(source.get("price_raw", "")), source.get("price")),
        "created_at": now,
        "updated_at": now,
        "item_category": infer_item_category(item_type),
    }

    bulk = normalize_bulk(source)
    if bulk is not None:
        stub["bulk"] = bulk

    hands = normalize_hands(source.get("hands"))
    if hands is not None:
        stub["hands"] = hands

    if item_type == "weapon":
        stub["weapon_stats"] = build_weapon_stats(source, traits)
    elif item_type == "armor":
        stub["armor_stats"] = build_armor_stats(source)
    elif item_type == "shield":
        stub["shield_stats"] = build_shield_stats(source)
    elif item_type == "consumable":
        stub["consumable_stats"] = build_consumable_stats(source)

    return stub


def output_path_for(args: argparse.Namespace, stub: dict[str, Any]) -> Path:
    if args.output is not None:
        return args.output
    return DEFAULT_OUTPUT_DIR / f"{stub['item_id']}.json"


def main() -> int:
    args = parse_args()

    try:
        hits = search_aon_equipment(args.name, args.limit)
    except (HTTPError, URLError, TimeoutError) as exc:
        print(f"AoN lookup failed: {exc}", file=sys.stderr)
        return 1

    hit = choose_best_hit(args.name, hits)
    if hit is None:
        print(f"No AoN equipment results found for {args.name!r}.", file=sys.stderr)
        return 1

    source = hit["_source"]
    stub = build_canonical_stub(source)

    if args.write:
        path = output_path_for(args, stub)
        if path.exists() and not args.force:
            print(f"Refusing to overwrite existing file without --force: {path}", file=sys.stderr)
            return 1
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(stub, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        print(f"Wrote canonical item stub for {source['name']} to {path}")
        print(f"AoN source: https://2e.aonprd.com{source.get('url', '')}")
        return 0

    print(json.dumps({
        "selected_name": source.get("name"),
        "selected_url": f"https://2e.aonprd.com{source.get('url', '')}",
        "selected_source": source.get("primary_source_raw"),
        "stub": stub,
    }, indent=2, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
