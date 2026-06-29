#!/usr/bin/env python3
"""
Filter out non-creature entries from comprehensive_creature_inventory.json
"""

import json
import re
from pathlib import Path

# Terms that are definitely NOT creatures (traits, descriptors, etc.)
EXCLUDE_TERMS = {
    "A-C", "D-F", "G-I", "J-L", "M-O", "P-R", "S-U", "V-Z",
    "ABERRATION", "ACID", "AIR", "AMPHIBIOUS", "AQUATIC", 
    "ANDROID", "AASIMAR", "AGATHION", "ANGEL", "ANIMAL", "ARCHON",
    "CELESTIAL", "CONSTRUCT", "DEMON", "DEVIL", "DRAGON", "EARTH",
    "ELEMENTAL", "ETHEREAL", "FEY", "FIEND", "FIRE", "FUNGUS",
    "GIANT", "HUMANOID", "MONITOR", "OOZE", "PLANT", "UNDEAD", "WATER",
    "DWARF", "ELF", "GNOME", "GOBLIN", "HALFLING", "HUMAN", "ORC",
    "PROTEAN", "PSYCHOPOMP", "AEON", "AZATA", "BEAST", "ASTRAL",
    "TIEFLING", "HALF-ELF", "HALF-ORC",
    "APPENDIX", "BESTIARY 1", "BESTIARY 2", "BESTIARY 3", "APPENDIX",
}

# Patterns that indicate non-creatures
EXCLUDE_PATTERNS = [
    r'^Creating\s',  # "Creating Graveknights", "Creating a Ravener"
    r'^Consume\s',   # "Consume Soul [free-action]"
    r'\[.*action.*\]',  # Action entries
    r'DC\s+\d+',     # DC checks
    r'^\d+d\d+',     # Dice rolls
    r'^Tactics\s',
    r'^Base Statistics',
]

def is_valid_creature(creature):
    """Determine if entry is a real creature."""
    name = creature['name'].upper()
    
    # Exclude known non-creatures
    if name in EXCLUDE_TERMS:
        return False
    
    # Check exclude patterns
    for pattern in EXCLUDE_PATTERNS:
        if re.search(pattern, creature['name'], re.IGNORECASE):
            return False
    
    # Must have a valid level
    if creature['level'] is None:
        return False
    
    # Single word all-caps is likely a trait, not a creature
    words = creature['name'].split()
    if len(words) == 1 and creature['name'].isupper() and len(creature['name']) < 8:
        return False
    
    # Multi-word creatures should have proper capitalization
    if len(words) > 1:
        # At least first word should be capitalized
        if not words[0][0].isupper():
            return False
    
    return True

def main():
    base_dir = Path(__file__).parent
    
    # Load original extraction
    with open(base_dir / 'comprehensive_creature_inventory.json', 'r') as f:
        data = json.load(f)
    
    original_count = len(data['creatures'])
    
    # Filter creatures
    filtered_creatures = [c for c in data['creatures'] if is_valid_creature(c)]
    
    # Update data
    data['creatures'] = filtered_creatures
    data['creature_count'] = len(filtered_creatures)
    data['filtering'] = {
        'original_count': original_count,
        'filtered_count': len(filtered_creatures),
        'removed_count': original_count - len(filtered_creatures)
    }
    
    # Save filtered version
    output_file = base_dir / 'comprehensive_creature_inventory_filtered.json'
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
    
    print(f"Filtering complete:")
    print(f"  Original: {original_count} creatures")
    print(f"  Filtered: {len(filtered_creatures)} creatures")
    print(f"  Removed: {original_count - len(filtered_creatures)} non-creatures")
    print(f"\nSample creatures (first 20):")
    for creature in filtered_creatures[:20]:
        print(f"  - {creature['name']} (Level {creature['level']}, {creature['creature_type']})")
    print(f"\nOutput saved to: {output_file}")

if __name__ == '__main__':
    main()
