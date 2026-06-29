#!/usr/bin/env python3
"""
Generate creature registry templates from filtered creature inventory.
"""

import json
from pathlib import Path

def generate_creature_templates():
    """Generate creature registry templates."""
    
    # Load filtered creatures
    base_dir = Path(__file__).parent
    with open(base_dir / 'comprehensive_creature_inventory_filtered.json', 'r') as f:
        inventory = json.load(f)
    
    print(f"Generating templates for {inventory['creature_count']} creatures...")
    
    # Generate registry template for creatures
    registry_rows = []
    for creature in inventory['creatures']:
        row = {
            "content_type": "creature",
            "content_id": creature['creature_id'],
            "name": creature['name'],
            "level": creature.get('level', 1),
            "rarity": "common",
            "tags": [
                "creature",
                creature['creature_type'],
                creature['source_book']
            ],
            "schema_data": {
                "creature_id": creature['creature_id'],
                "creature_type": creature['creature_type'],
                "source_book": creature['source_book'],
                "traits": creature.get('traits', '').split() if creature.get('traits') else []
            },
            "version": "1.0.0"
        }
        
        registry_rows.append(row)
    
    # Load existing registry to append creatures
    registry_path = Path(__file__).parent.parent.parent.parent / 'sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/examples/templates/dungeoncrawler_content_registry/default_registry_examples.json'
    
    with open(registry_path, 'r', encoding='utf-8') as f:
        existing_registry = json.load(f)
    
    # Add creatures to existing registry
    existing_registry['rows'].extend(registry_rows)
    
    # Write updated registry
    with open(registry_path, 'w', encoding='utf-8') as f:
        json.dump(existing_registry, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Added {len(registry_rows)} creature rows to registry")
    print(f"   Total registry rows: {len(existing_registry['rows'])}")
    print(f"   Saved to: {registry_path.relative_to(Path.cwd().parent.parent.parent.parent)}")
    
    # Summary statistics
    print(f"\n{'='*60}")
    print(f"CREATURE TEMPLATE GENERATION COMPLETE")
    print(f"{'='*60}")
    print(f"Creatures added: {len(registry_rows)}")
    print(f"\nBreakdown by type:")
    types = {}
    for creature in inventory['creatures']:
        t = creature['creature_type']
        types[t] = types.get(t, 0) + 1
    for t, count in sorted(types.items(), key=lambda x: -x[1]):
        print(f"  {t}: {count}")
    print(f"\nBreakdown by source:")
    sources = {}
    for creature in inventory['creatures']:
        s = creature['source_display']
        sources[s] = sources.get(s, 0) + 1
    for s, count in sorted(sources.items(), key=lambda x: -x[1]):
        print(f"  {s}: {count}")
    print(f"{'='*60}\n")

if __name__ == '__main__':
    generate_creature_templates()
