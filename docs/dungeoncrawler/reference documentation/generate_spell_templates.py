#!/usr/bin/env python3
"""
Generate Drupal template files for PF2E spell registry.
Appends spells to existing registry template.
"""

import json
from pathlib import Path
from collections import defaultdict

def main():
    base_dir = Path(__file__).parent
    
    # Load clean spell inventory
    spell_file = base_dir / 'comprehensive_spell_inventory_clean.json'
    print(f"Loading spells from: {spell_file}")
    
    with open(spell_file, 'r', encoding='utf-8') as f:
        spell_data = json.load(f)
    
    spells = spell_data['spells']
    print(f"Loaded {len(spells)} spells")
    
    # Template paths  
    workspace_root = base_dir.parent.parent.parent
    template_base = workspace_root / 'sites' / 'dungeoncrawler' / 'web' / 'modules' / 'custom' / 'dungeoncrawler_content' / 'config' / 'examples' / 'templates'
    registry_dir = template_base / 'dungeoncrawler_content_registry'
    registry_file = registry_dir / 'default_registry_examples.json'
    
    # Load existing registry
    print(f"\nLoading existing registry from: {registry_file}")
    with open(registry_file, 'r', encoding='utf-8') as f:
        registry_data = json.load(f)
    
    # Handle both list and {"rows": [...]} formats
    if isinstance(registry_data, dict) and 'rows' in registry_data:
        existing_registry = registry_data['rows']
    elif isinstance(registry_data, list):
        existing_registry = registry_data
    else:
        print("Warning: Unknown registry format, starting fresh")
        existing_registry = []
    
    print(f"Existing registry entries: {len(existing_registry)}")
    
    # Generate spell registry entries
    spell_registry = []
    stats = defaultdict(int)
    
    for spell in spells:
        # Create registry entry
        registry_entry = {
            "content_type": "spell",
            "content_id": spell['spell_id'],
            "name": spell['name'],
            "level": spell['spell_level'],
            "rarity": spell.get('rarity', 'common'),
            "tags": [
                spell['school'],
                *spell['traditions'],
                f"level_{spell['spell_level']}"
            ],
            "schema_data": {
                "spell_level": spell['spell_level'],
                "school": spell['school'],
                "traditions": spell['traditions'],
                "heightenable": spell.get('heightenable', False),
                "rarity": spell.get('rarity', 'common'),
                "source_book": spell.get('source_slug', 'unknown'),
                "source_display": spell.get('source_display', 'Unknown'),
                "description_snippet": spell.get('description_snippet', '')
            }
        }
        
        spell_registry.append(registry_entry)
        
        # Track stats
        stats['total'] += 1
        stats[f"level_{spell['spell_level']}"] += 1
        stats[spell['school']] += 1
        for tradition in spell['traditions']:
            stats[f"tradition_{tradition}"] += 1
    
    # Append to existing registry
    combined_registry = existing_registry + spell_registry
    
    # Write combined registry in {"rows": [...]} format
    output_data = {"rows": combined_registry}
    
    print(f"\nWriting combined registry ({len(combined_registry)} total entries)...")
    with open(registry_file, 'w', encoding='utf-8') as f:
        json.dump(output_data, f, indent=2, ensure_ascii=False)
    
    # Print summary
    print(f"\n{'='*60}")
    print(f"SPELL TEMPLATE GENERATION COMPLETE")
    print(f"{'='*60}")
    print(f"Previous registry entries: {len(existing_registry)}")
    print(f"New spell entries: {len(spell_registry)}")
    print(f"Total registry entries: {len(combined_registry)}")
    print(f"\nSpell breakdown:")
    print(f"  Total spells: {stats['total']}")
    print(f"\n  By level:")
    for level in range(11):
        key = f"level_{level}"
        if key in stats:
            level_name = "Cantrips" if level == 0 else f"Level {level}"
            print(f"    {level_name}: {stats[key]}")
    print(f"\n  By tradition:")
    for tradition in ['arcane', 'divine', 'occult', 'primal']:
        key = f"tradition_{tradition}"
        if key in stats:
            print(f"    {tradition.capitalize()}: {stats[key]}")
    print(f"\n  By school (top 5):")
    schools = [(school, count) for school, count in stats.items() 
               if school in ['abjuration', 'conjuration', 'divination', 
                           'enchantment', 'evocation', 'illusion', 
                           'necromancy', 'transmutation']]
    schools.sort(key=lambda x: -x[1])
    for school, count in schools[:5]:
        print(f"    {school}: {count}")
    
    print(f"\nRegistry file updated: {registry_file}")
    print(f"{'='*60}\n")


if __name__ == '__main__':
    main()
