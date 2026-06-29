#!/usr/bin/env python3
"""
Extract PF2E spells from spell list sections.
Uses the structured spell list format: "Spell Name H (school): description"
"""

import json
import re
from pathlib import Path
from collections import defaultdict

# Source books to process
SOURCES = [
    {
        "file": "PF2E Core Rulebook - Fourth Printing.txt",
        "slug": "core_rulebook_4th_printing",
        "display": "Core Rulebook (4th Printing)"
    },
    {
        "file": "PF2E Secrets of Magic.txt",
        "slug": "secrets_of_magic",
        "display": "Secrets of Magic"
    },
    {
        "file": "PF2E Advanced Players Guide.txt",
        "slug": "advanced_players_guide",
        "display": "Advanced Player's Guide"
    }
]

# School abbreviations
SCHOOL_MAP = {
    'abj': 'abjuration',
    'con': 'conjuration',
    'div': 'divination',
    'enc': 'enchantment',
    'evo': 'evocation',
    'ill': 'illusion',
    'nec': 'necromancy',
    'tra': 'transmutation'
}

# Tradition markers (for identifying spell list sections)
TRADITION_MARKERS = ['Arcane', 'Divine', 'Occult', 'Primal']

# Level markers (handle multiple formats)
LEVEL_PATTERNS = {
    r'CANTRIPS$': 0,
    r'Cantrips$': 0,
    r'1ST-LEVEL SPELLS': 1,
    r'1st-Level Spells': 1,
    r'2ND-LEVEL SPELLS': 2,
    r'2nd-Level Spells': 2,
    r'3RD-LEVEL SPELLS': 3,
    r'3rd-Level Spells': 3,
    r'4TH-LEVEL SPELLS': 4,
    r'4th-Level Spells': 4,
    r'5TH-LEVEL SPELLS': 5,
    r'5th-Level Spells': 5,
    r'6TH-LEVEL SPELLS': 6,
    r'6th-Level Spells': 6,
    r'7TH-LEVEL SPELLS': 7,
    r'7th-Level Spells': 7,
    r'8TH-LEVEL SPELLS': 8,
    r'8th-Level Spells': 8,
    r'9TH-LEVEL SPELLS': 9,
    r'9th-Level Spells': 9,
    r'10TH-LEVEL SPELLS': 10,
    r'10th-Level Spells': 10
}


def slugify(text):
    """Convert text to valid spell_id slug."""
    slug = text.lower()
    slug = re.sub(r'[^\w\s-]', '', slug)
    slug = re.sub(r'[-\s]+', '_', slug)
    return slug.strip('_')


def extract_spell_from_line(line, current_level, current_tradition):
    """
    Extract spell information from a spell list line.
    Format: "Spell Name H (school): description" or "Spell NameH (school): description"
    """
    # Match pattern: spell name, optional H/U/R (with or without space), school abbreviation, colon, description
    pattern = r'^([A-Z][^(]+?)([HUR,\s]*)\s*\((\w+)\):\s*(.+)$'
    match = re.match(pattern, line)
    
    if not match:
        return None
    
    name = match.group(1).strip()
    heighten_markers = match.group(2).strip()
    school_abbr = match.group(3).lower()
    description = match.group(4).strip()
    
    # Skip if name is too short or looks like noise
    if len(name) < 3 or name.isupper():
        return None
    
    # Map school abbreviation
    school = SCHOOL_MAP.get(school_abbr, school_abbr)
    
    # Check for heightening
    is_heightenable = 'H' in heighten_markers
    
    # Parse rarity
    rarity = 'common'
    if 'U' in heighten_markers:
        rarity = 'uncommon'
    elif 'R' in heighten_markers:
        rarity = 'rare'
    
    return {
        'name': name,
        'spell_id': slugify(name),
        'spell_level': current_level,
        'school': school,
        'traditions': [current_tradition.lower()] if current_tradition else [],
        'heightenable': is_heightenable,
        'rarity': rarity,
        'description_snippet': description[:100]
    }


def extract_spells_from_file(filepath, source_info):
    """Extract all spells from spell list sections."""
    print(f"\nProcessing: {source_info['display']}")
    
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        lines = f.readlines()
    
    spells = []
    current_level = None
    current_tradition = None
    
    in_spell_list_section = False
    
    for line_num, line in enumerate(lines, 1):
        line = line.strip()
        
        # Check if we're entering a spell list section
        if 'Spell Lists' in line or 'SPELL LISTS' in line:
            in_spell_list_section = True
            continue
        
        # Check for tradition markers (handle both "Arcane" and "ARCANE SPELL LIST")
        for tradition in TRADITION_MARKERS:
            if tradition.upper() in line.upper():
                # Check if it's a spell section (not class feature)
                if any(pattern in line for pattern, _ in LEVEL_PATTERNS.items()):
                    current_tradition = tradition
                elif 'Spells' in line or 'Cantrips' in line or 'SPELL LIST' in line.upper():
                    current_tradition = tradition
                break
        
        # Check for level markers
        for pattern, level in LEVEL_PATTERNS.items():
            if re.search(pattern, line):
                if current_tradition:  # Only track level if we're in a tradition section
                    current_level = level
                break
        
        # Try to extract spell from line
        if current_level is not None and current_tradition:
            spell_data = extract_spell_from_line(line, current_level, current_tradition)
            if spell_data:
                spell_data['source_file'] = source_info['file']
                spell_data['source_slug'] = source_info['slug']
                spell_data['source_display'] = source_info['display']
                spell_data['line'] = line_num
                spell_data['evidence'] = line[:200]
                spells.append(spell_data)
    
    print(f"  Extracted {len(spells)} spells")
    return spells


def merge_spell_traditions(spells):
    """Merge spells from different traditions (same spell, multiple lists)."""
    spell_dict = {}
    
    for spell in spells:
        spell_id = spell['spell_id']
        
        if spell_id not in spell_dict:
            spell_dict[spell_id] = spell
        else:
            # Merge traditions
            existing = spell_dict[spell_id]
            for tradition in spell['traditions']:
                if tradition not in existing['traditions']:
                    existing['traditions'].append(tradition)
            
            # Keep more complete data (prefer entries with descriptions)
            if len(spell.get('description_snippet', '')) > len(existing.get('description_snippet', '')):
                existing['description_snippet'] = spell['description_snippet']
            
            # Track multiple sources
            if 'sources' not in existing:
                existing['sources'] = [existing['source_slug']]
            if spell['source_slug'] not in existing['sources']:
                existing['sources'].append(spell['source_slug'])
    
    return list(spell_dict.values())


def main():
    base_dir = Path(__file__).parent
    all_spells = []
    
    stats = {
        'by_source': {},
        'by_level': defaultdict(int),
        'by_school': defaultdict(int),
        'by_tradition': defaultdict(int),
        'total_raw': 0,
        'total_merged': 0
    }
    
    # Extract from all sources
    for source in SOURCES:
        filepath = base_dir / source['file']
        
        if not filepath.exists():
            print(f"⚠️  Skipping missing file: {source['file']}")
            continue
        
        spells = extract_spells_from_file(filepath, source)
        
        # Track stats
        for spell in spells:
            stats['by_level'][spell['spell_level']] += 1
            stats['by_school'][spell['school']] += 1
            for tradition in spell['traditions']:
                stats['by_tradition'][tradition] += 1
        
        all_spells.extend(spells)
        stats['by_source'][source['slug']] = len(spells)
        stats['total_raw'] += len(spells)
    
    # Merge spells across traditions
    print(f"\n{'='*60}")
    print(f"Merging spell traditions...")
    merged_spells = merge_spell_traditions(all_spells)
    stats['total_merged'] = len(merged_spells)
    
    # Sort by level, then name
    merged_spells.sort(key=lambda x: (x['spell_level'], x['name']))
    
    # Generate output
    output = {
        "extraction_date": "2026-02-19",
        "extraction_method": "spell_list_parsing",
        "sources": [s['display'] for s in SOURCES],
        "stats": {
            'total_raw': stats['total_raw'],
            'total_merged': stats['total_merged'],
            'by_source': stats['by_source'],
            'by_level': dict(sorted(stats['by_level'].items())),
            'by_school': dict(sorted(stats['by_school'].items(), key=lambda x: -x[1])),
            'by_tradition': dict(stats['by_tradition'])
        },
        "spell_count": len(merged_spells),
        "spells": merged_spells
    }
    
    # Write output
    output_file = base_dir / 'comprehensive_spell_inventory_clean.json'
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(output, f, indent=2, ensure_ascii=False)
    
    # Print summary
    print(f"\n{'='*60}")
    print(f"EXTRACTION COMPLETE")
    print(f"{'='*60}")
    print(f"Total raw extractions: {stats['total_raw']}")
    print(f"Unique spells (merged): {stats['total_merged']}")
    print(f"\nBy source:")
    for slug, count in stats['by_source'].items():
        source_name = next(s['display'] for s in SOURCES if s['slug'] == slug)
        print(f"  {source_name}: {count} spell entries")
    print(f"\nBy level:")
    for level in range(11):
        if level in stats['by_level']:
            level_name = "Cantrips" if level == 0 else f"Level {level}"
            print(f"  {level_name}: {stats['by_level'][level]} entries")
    print(f"\nBy tradition:")
    for tradition, count in sorted(stats['by_tradition'].items()):
        print(f"  {tradition}: {count}")
    print(f"\nBy school (top 5):")
    for school, count in sorted(stats['by_school'].items(), key=lambda x: -x[1])[:5]:
        print(f"  {school}: {count}")
    print(f"\nOutput saved to: {output_file}")
    print(f"{'='*60}\n")


if __name__ == '__main__':
    main()
