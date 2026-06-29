#!/usr/bin/env python3
"""
Generate comprehensive registry and item instance templates from extracted items.
"""

import json
from pathlib import Path

def generate_templates():
    """Generate both registry and item instance templates."""
    
    # Load extracted items
    base_dir = Path(__file__).parent
    with open(base_dir / 'comprehensive_item_inventory.json', 'r') as f:
        inventory = json.load(f)
    
    print(f"Generating templates for {inventory['item_count']} items...")
    
    # Generate registry template
    registry_rows = []
    for item in inventory['items']:
        row = {
            "content_type": "item",
            "content_id": item['item_id'],
            "name": item['name'],
            "level": 1,  # Default, can be refined later
            "rarity": "common",
            "tags": [
                "item",
                item['item_type'],
                item['source_book']
            ],
            "schema_data": {
                "item_id": item['item_id'],
                "item_type": item['item_type'],
                "source_book": item['source_book']
            },
            "version": "1.0.0"
        }
        
        # Add price if available
        if item.get('price_gp'):
            row['schema_data']['price_gp'] = item['price_gp']
        
        registry_rows.append(row)
    
    # Generate item instance template
    instance_rows = []
    for item in inventory['items']:
        row = {
            "item_instance_id": f"{item['item_id']}_default",
            "item_id": item['item_id'],
            "location_type": "library",
            "location_ref": "comprehensive_pf2e_library",
            "quantity": 1,
            "state_data": {
                "condition": "new",
                "availability": "template"
            },
            "source_books": [item['source_book']],
            "reference_info": item['references']
        }
        
        instance_rows.append(row)
    
    # Write registry template
    registry_template = {"rows": registry_rows}
    registry_path = Path(__file__).parent.parent.parent.parent / 'sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/examples/templates/dungeoncrawler_content_registry/default_registry_examples.json'
    
    with open(registry_path, 'w', encoding='utf-8') as f:
        json.dump(registry_template, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Generated registry template: {len(registry_rows)} rows")
    print(f"   Saved to: {registry_path.relative_to(Path.cwd().parent.parent.parent.parent)}")
    
    # Write item instance template
    instance_template = {"rows": instance_rows}
    instance_path = Path(__file__).parent.parent.parent.parent / 'sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/examples/templates/dungeoncrawler_content_item_instances/default_item_instance_templates.json'
    
    with open(instance_path, 'w', encoding='utf-8') as f:
        json.dump(instance_template, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Generated item instance template: {len(instance_rows)} rows")
    print(f"   Saved to: {instance_path.relative_to(Path.cwd().parent.parent.parent.parent)}")
    
    # Summary statistics
    print(f"\n{'='*60}")
    print(f"TEMPLATE GENERATION COMPLETE")
    print(f"{'='*60}")
    print(f"Registry rows: {len(registry_rows)}")
    print(f"Item instance rows: {len(instance_rows)}")
    print(f"\nBreakdown by type:")
    types = {}
    for item in inventory['items']:
        t = item['item_type']
        types[t] = types.get(t, 0) + 1
    for t, count in sorted(types.items()):
        print(f"  {t}: {count}")
    print(f"\nBreakdown by source:")
    sources = {}
    for item in inventory['items']:
        s = item['source_display']
        sources[s] = sources.get(s, 0) + 1
    for s, count in sorted(sources.items(), key=lambda x: -x[1])[:6]:
        print(f"  {s}: {count}")
    print(f"{'='*60}\n")

if __name__ == '__main__':
    generate_templates()
