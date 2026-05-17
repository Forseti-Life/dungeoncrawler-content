#!/usr/bin/env python3
"""
Export AoN equipment-family metadata for bulk fallback testing.

This script queries Archives of Nethys' public search index for item-name families
like swords, boots, belts, and gauntlets, then emits both the raw AoN metadata and
the mapped canonical stub shape we would use to seed content/items/.
"""

from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any

from aon_equipment_lookup import ALLOWED_CATEGORIES, build_canonical_stub, post_json


ELASTIC_URL = "https://elasticsearch.aonprd.com/aon/_search?stats=aggregations"
DEFAULT_FAMILIES = {
    "swords": "sword",
    "boots": "boot",
    "belts": "belt",
    "gauntlets": "gauntlet",
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export AoN metadata for named equipment families.")
    parser.add_argument(
        "--output",
        type=Path,
        required=True,
        help="Path to write the JSON export.",
    )
    parser.add_argument(
        "--family",
        action="append",
        help="Optional family override in the form label=term. Can be repeated.",
    )
    parser.add_argument(
        "--page-size",
        type=int,
        default=200,
        help="AoN page size per search request.",
    )
    return parser.parse_args()


def parse_families(args: argparse.Namespace) -> dict[str, str]:
    if not args.family:
        return dict(DEFAULT_FAMILIES)

    families: dict[str, str] = {}
    for entry in args.family:
        if "=" not in entry:
            raise ValueError(f"Invalid family override {entry!r}; expected label=term.")
        label, term = entry.split("=", 1)
        label = label.strip()
        term = term.strip()
        if not label or not term:
            raise ValueError(f"Invalid family override {entry!r}; expected label=term.")
        families[label] = term
    return families


def fetch_family_hits(term: str, page_size: int) -> list[dict[str, Any]]:
    collected: list[dict[str, Any]] = []
    offset = 0

    while True:
        query = {
            "from": offset,
            "size": page_size,
            "query": {
                "bool": {
                    "must": [
                        {
                            "query_string": {
                                "query": f"name:*{term}*",
                                "default_operator": "AND",
                            }
                        }
                    ],
                    "filter": [
                        {
                            "terms": {
                                "category": sorted(ALLOWED_CATEGORIES),
                            }
                        }
                    ],
                }
            },
            "sort": [
                {"release_date": {"order": "desc"}},
                {"_score": "desc"},
            ],
        }

        response = post_json(ELASTIC_URL, query)
        hits = response.get("hits", {}).get("hits", [])
        if not hits:
            break

        collected.extend(hits)
        if len(hits) < page_size:
            break
        offset += page_size

    deduped: dict[str, dict[str, Any]] = {}
    for hit in collected:
        deduped[str(hit.get("_id"))] = hit
    return list(deduped.values())


def build_export_payload(families: dict[str, str], page_size: int) -> dict[str, Any]:
    payload: dict[str, Any] = {
        "source": "Archives of Nethys",
        "source_url": "https://2e.aonprd.com/Equipment.aspx",
        "families": {},
        "stats": {
            "total_records": 0,
            "by_family": {},
        },
    }

    total_records = 0
    for label, term in families.items():
        hits = fetch_family_hits(term, page_size)
        records: list[dict[str, Any]] = []
        for hit in hits:
            source = hit.get("_source", {})
            records.append({
                "query_term": term,
                "aon_id": hit.get("_id"),
                "aon_url": f"https://2e.aonprd.com{source.get('url', '')}",
                "name": source.get("name"),
                "category": source.get("category"),
                "item_category": source.get("item_category"),
                "primary_source": source.get("primary_source_raw"),
                "release_date": source.get("release_date"),
                "price_raw": source.get("price_raw"),
                "rarity": source.get("rarity"),
                "level": source.get("level"),
                "bulk_raw": source.get("bulk_raw", source.get("bulk")),
                "traits": source.get("trait_raw", []),
                "summary": source.get("summary"),
                "canonical_stub": build_canonical_stub(source),
            })

        payload["families"][label] = {
            "query_term": term,
            "record_count": len(records),
            "records": records,
        }
        payload["stats"]["by_family"][label] = len(records)
        total_records += len(records)

    payload["stats"]["total_records"] = total_records
    return payload


def main() -> int:
    args = parse_args()
    families = parse_families(args)
    payload = build_export_payload(families, args.page_size)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(payload, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {payload['stats']['total_records']} AoN family records to {args.output}")
    for family, count in payload["stats"]["by_family"].items():
        print(f"{family}: {count}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
