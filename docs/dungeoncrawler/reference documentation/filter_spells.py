#!/usr/bin/env python3
"""
Filter spell extraction to remove noise and false positives.
Keeps only actual spell names.
"""

import json
import re
from pathlib import Path

# Patterns that indicate NOT a spell
EXCLUDE_PATTERNS = [
    r'^CANTRIP\s+\d+',  # "CANTRIP 1", "CANTRIP 2"
    r'^SPELL\s+\d+',    # "SPELL 1", "SPELL 2"
    r'^CANTRIP\s+[A-Z\s]+$',  # "CANTRIP CASTIN", "CANTRIP DECK"
    r'^Area\s+',        # "Area 10-foot emanation"
    r'^Range\s+',       # "Range 30 feet"
    r'^Duration\s+',    # "Duration 1 minute"
    r'^Targets?\s+',    # "Target 1 creature"
    r'^Saving Throw',   # "Saving Throw Fortitude"
    r'^Prerequisites',  # Prerequisites
    r'^Requirements',   # Requirements
    r'^Trigger',        # Trigger
    r'^Cost',           # Cost
    r'^Components',     # Components
    r'^When\s+',        # "When casting a"
    r'^You\s+',         # "You gain the inspire"
    r'^\d+\s+\w+\s+\w+\s+\w+\s+\w+',  # Very long sequences starting with number
    r'^[A-Z\s]+$',      # All caps (likely headers)
    r'deck\s+\(',       # "Cantrip deck (5-pack)"
    r'pack\)',          # "(full pack)"
    r':\s+\w+$',        # "Cantrip: tanglefoot"
]

# Terms that are never spell names
EXCLUDE_TERMS = {
    "Ancestries &", "Prerequisites", "Requirements", "Trigger",
    "UNCOMMON BARD", "Area", "Range", "Duration", "Targets", "Target",
    "Saving Throw", "Components", "Cost", "Cantrip Expansion",
    "Heightened", "Critical Success", "Success", "Failure", "Critical Failure"
}

# Common spell name prefixes (valid)
VALID_PREFIXES = {
    "Acid", "Air", "Burning", "Charm", "Cone", "Create", "Detect", 
    "Divine", "Fear", "Fiery", "Flame", "Heal", "Identify", "Illusory",
    "Magic", "Mage", "Phantom", "Ray", "Shocking", "Summon", "True",
    "Wall", "Web"
}


def is_noise(spell):
    """Check if spell entry is noise/false positive."""
    name = spell['name']
    
    # Check exclude patterns
    for pattern in EXCLUDE_PATTERNS:
        if re.match(pattern, name, re.IGNORECASE):
            return True
    
    # Check exclude terms
    if name in EXCLUDE_TERMS:
        return True
    
    # Names starting with "CANTRIP" are usually noise
    if name.startswith("CANTRIP") and name != "CANTRIP":
        return True
    
    # Names with lowercase start (except special cases)
    if name[0].islower() and not name.startswith("ghost"):
        return True
    
    # Very short names (< 3 chars)
    if len(name) < 3:
        return True
    
    # Names ending with incomplete words
    if name.endswith(("CASTIN", "COMPOSITI")):
        return True
    
    # All uppercase multi-word headers
    if name.isupper() and len(name.split()) > 2:
        return True
    
    # Contains parentheses (usually metadata, not spell names)
    if '(' in name and not any(name.startswith(prefix) for prefix in VALID_PREFIXES):
        return True
    
    return False


def extract_real_spell_name(spell):
    """Try to extract actual spell name from noisy text."""
    name = spell['name']
    
    # Remove "Cantrip " prefix if it exists
    name = re.sub(r'^Cantrip\s+', '', name, flags=re.IGNORECASE)
    
    # Remove trailing trait lists in ALL CAPS
    name = re.sub(r'\s+[A-Z]{4,}(\s+[A-Z]{4,})*$', '', name)
    
    # Remove "SPELL X:" prefix
    name = re.sub(r'^SPELL\s+\d+:\s*', '', name, flags=re.IGNORECASE)
    
    # Clean up whitespace
    name = ' '.join(name.split())
    
    return name.strip()


def main():
    base_dir = Path(__file__).parent
    input_file = base_dir / 'comprehensive_spell_inventory.json'
    output_file = base_dir / 'comprehensive_spell_inventory_filtered.json'
    
    print("Loading spell inventory...")
    with open(input_file, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    original_count = len(data['spells'])
    print(f"Original spell count: {original_count}")
    
    # Filter spells
    filtered_spells = []
    removed_spells = []
    
    for spell in data['spells']:
        if is_noise(spell):
            removed_spells.append(spell['name'])
        else:
            # Try to clean the name
            cleaned_name = extract_real_spell_name(spell)
            if cleaned_name and not is_noise({'name': cleaned_name}):
                spell['name'] = cleaned_name
                # Update spell_id to match cleaned name
                spell['spell_id'] = cleaned_name.lower().replace(' ', '_').replace("'", '')
                filtered_spells.append(spell)
            else:
                removed_spells.append(spell['name'])
    
    # Update data
    data['spells'] = filtered_spells
    data['spell_count'] = len(filtered_spells)
    data['stats']['filtered_out'] = len(removed_spells)
    data['stats']['final_count'] = len(filtered_spells)
    
    # Recalculate stats
    data['stats']['by_level'] = {}
    data['stats']['by_school'] = {}
    
    for spell in filtered_spells:
        level = spell.get('spell_level', 'unknown')
        school = spell.get('school', 'unknown')
        
        data['stats']['by_level'][level] = data['stats']['by_level'].get(level, 0) + 1
        data['stats']['by_school'][school] = data['stats']['by_school'].get(school, 0) + 1
    
    # Save filtered data
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
    
    print(f"\n{'='*60}")
    print(f"FILTERING COMPLETE")
    print(f"{'='*60}")
    print(f"Original: {original_count} spell entries")
    print(f"Filtered: {len(filtered_spells)} clean spells")
    print(f"Removed: {len(removed_spells)} false positives")
    print(f"Quality: {len(filtered_spells)/original_count*100:.1f}%")
    print(f"\nRemoved samples (first 20):")
    for name in removed_spells[:20]:
        print(f"  - {name}")
    print(f"\nOutput saved to: {output_file}")
    print(f"{'='*60}\n")


if __name__ == '__main__':
    main()
