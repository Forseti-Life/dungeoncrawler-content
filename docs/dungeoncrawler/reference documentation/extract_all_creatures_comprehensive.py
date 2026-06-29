#!/usr/bin/env python3
"""
Comprehensive PF2E Creature Extractor
Extracts creature names, levels, and metadata from Bestiary books.
"""

import json
import re
from pathlib import Path
from collections import defaultdict

# Source books to process
SOURCES = [
    {
        "file": "PF2E Bestiary 1.txt",
        "slug": "bestiary_1",
        "display": "Bestiary 1"
    },
    {
        "file": "PF2E Bestiary 2.txt",
        "slug": "bestiary_2",
        "display": "Bestiary 2"
    },
    {
        "file": "PF2E Bestiary 3.txt",
        "slug": "bestiary_3",
        "display": "Bestiary 3"
    }
]

# Creature type keywords for classification
CREATURE_TYPES = {
    'aberration': ['aberration'],
    'animal': ['animal'],
    'beast': ['beast'],
    'celestial': ['celestial', 'angel'],
    'construct': ['construct', 'golem'],
    'dragon': ['dragon'],
    'elemental': ['elemental'],
    'fey': ['fey'],
    'fiend': ['fiend', 'demon', 'devil', 'daemon'],
    'humanoid': ['humanoid', 'human', 'elf', 'dwarf', 'halfling', 'gnome'],
    'ooze': ['ooze'],
    'plant': ['plant'],
    'undead': ['undead', 'zombie', 'skeleton', 'vampire', 'ghost', 'wraith']
}

# Generic terms to exclude
NOISE_TERMS = {
    "Perception", "Languages", "Skills", "Str", "Dex", "Con", "Int", "Wis", "Cha",
    "AC", "HP", "Speed", "Melee", "Ranged", "Damage", "Effect", "Trigger",
    "Requirements", "Frequency", "Actions", "Reactions", "Range", "Area",
    "Saving Throw", "Duration", "Target", "Traits", "Source", "Page",
    "Creature", "CREATURE", "Level", "LEVEL", "Description", "Abilities"
}


def slugify(text):
    """Convert text to valid creature_id slug."""
    slug = text.lower()
    slug = re.sub(r'[^\w\s-]', '', slug)
    slug = re.sub(r'[-\s]+', '_', slug)
    return slug.strip('_')


def extract_level(text):
    """Extract creature level from text."""
    # Look for "CREATURE LEVEL" pattern
    match = re.search(r'CREATURE\s+(\d+)', text, re.IGNORECASE)
    if match:
        return int(match.group(1))
    
    # Look for standalone level indicator
    match = re.search(r'\bLEVEL\s+(\d+)', text, re.IGNORECASE)
    if match:
        return int(match.group(1))
    
    # Look for level in stat block format
    match = re.search(r'\blevel\s+(\d+)', text, re.IGNORECASE)
    if match:
        return int(match.group(1))
    
    return None


def classify_creature_type(name, context=""):
    """Classify creature into type categories."""
    name_lower = name.lower()
    context_lower = context.lower()
    combined = name_lower + " " + context_lower
    
    for creature_type, keywords in CREATURE_TYPES.items():
        for keyword in keywords:
            if keyword in combined:
                return creature_type
    
    return "creature"


def is_noise(text):
    """Check if text is likely noise/stat block element."""
    text = text.strip()
    
    if not text or len(text) < 2 or len(text) > 60:
        return True
    
    if text in NOISE_TERMS:
        return True
    
    # Must start with capital or be all caps
    if not (text[0].isupper() or text.isupper()):
        return True
    
    # Skip stat block elements
    if re.match(r'^\d+\s*(hp|HP|AC|ft|feet)', text, re.IGNORECASE):
        return True
    
    # Skip ability score lines
    if re.match(r'^(Str|Dex|Con|Int|Wis|Cha)\s+', text, re.IGNORECASE):
        return True
    
    return False


def is_valid_creature_name(text):
    """Validate if text is likely a creature name."""
    text = text.strip()
    
    # Length constraints
    if len(text) < 3 or len(text) > 50:
        return False
    
    # Must be title case or have reasonable capitalization
    if not text[0].isupper():
        return False
    
    # Should not be all uppercase unless very short
    if text.isupper() and len(text) > 20:
        return False
    
    # Should not start with common stat block words
    stat_prefixes = ['Perception', 'Languages', 'Skills', 'AC ', 'HP ', 'Speed',
                     'Melee', 'Ranged', 'Actions', 'Reactions']
    for prefix in stat_prefixes:
        if text.startswith(prefix):
            return False
    
    # Should not be a number or contain mostly numbers
    if re.match(r'^\d+$', text):
        return False
    
    return True


def extract_creatures_from_file(filepath, source_info):
    """Extract all creatures from a single Bestiary file."""
    print(f"\nProcessing: {source_info['display']}")
    
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
    
    lines = content.split('\n')
    creatures = []
    
    creature_headers = []
    context_window = []
    
    # First pass: identify creature headers (usually all caps or title with level)
    for line_num, line in enumerate(lines, 1):
        line = line.strip()
        
        # Keep sliding window for context
        context_window.append(line)
        if len(context_window) > 5:
            context_window.pop(0)
        
        # Look for creature headers with level indicators
        if re.search(r'CREATURE\s+\d+', line, re.IGNORECASE):
            # Extract creature name before the level marker
            match = re.match(r'^(.+?)\s+CREATURE\s+\d+', line, re.IGNORECASE)
            if match:
                name = match.group(1).strip()
                level = extract_level(line)
                
                if is_valid_creature_name(name):
                    creatures.append({
                        'name': name,
                        'level': level,
                        'line': line_num,
                        'method': 'creature_level_marker',
                        'evidence': line[:150],
                        'context': ' '.join(context_window[-3:])
                    })
                    creature_headers.append(name.lower())
                    continue
        
        # Look for standalone creature names (title case lines between sections)
        if line and line[0].isupper() and len(line.split()) <= 5:
            if is_valid_creature_name(line) and not is_noise(line):
                # Check if next few lines have level indicator
                level = None
                for i in range(1, min(5, len(lines) - line_num)):
                    next_line = lines[line_num + i - 1]
                    level = extract_level(next_line)
                    if level is not None:
                        break
                
                # Only add if we found a level or if it looks like a variant
                name_lower = line.lower()
                is_variant = any(base in name_lower for base in creature_headers)
                
                if level is not None or is_variant:
                    creatures.append({
                        'name': line,
                        'level': level if level is not None else 1,
                        'line': line_num,
                        'method': 'title_case_with_level',
                        'evidence': line[:150],
                        'context': ' '.join(context_window[-3:])
                    })
    
    print(f"  Raw extractions: {len(creatures)}")
    return creatures


def deduplicate_creatures(creatures):
    """Remove duplicate creatures, keeping best evidence."""
    seen = {}
    
    for creature in creatures:
        name = creature['name']
        slug = slugify(name)
        
        if slug not in seen:
            seen[slug] = creature
        else:
            # Keep the one with level information
            if creature.get('level') and not seen[slug].get('level'):
                seen[slug] = creature
            # Or keep the one with more evidence
            elif len(creature.get('evidence', '')) > len(seen[slug].get('evidence', '')):
                seen[slug] = creature
    
    return list(seen.values())


def main():
    base_dir = Path(__file__).parent
    all_creatures = []
    
    stats = {
        'by_source': {},
        'total_raw': 0,
        'total_deduplicated': 0
    }
    
    # Extract from all sources
    for source in SOURCES:
        filepath = base_dir / source['file']
        
        if not filepath.exists():
            print(f"⚠️  Skipping missing file: {source['file']}")
            continue
        
        creatures = extract_creatures_from_file(filepath, source)
        
        # Add source metadata
        for creature in creatures:
            creature['source_file'] = source['file']
            creature['source_slug'] = source['slug']
            creature['source_display'] = source['display']
        
        all_creatures.extend(creatures)
        stats['by_source'][source['slug']] = len(creatures)
        stats['total_raw'] += len(creatures)
    
    # Deduplicate across all sources
    print(f"\n{'='*60}")
    print(f"Deduplicating creatures...")
    unique_creatures = deduplicate_creatures(all_creatures)
    stats['total_deduplicated'] = len(unique_creatures)
    
    # Sort by name
    unique_creatures.sort(key=lambda x: x['name'])
    
    # Generate output
    output = {
        "extraction_date": "2026-02-18",
        "sources": [s['display'] for s in SOURCES],
        "stats": stats,
        "creature_count": len(unique_creatures),
        "creatures": []
    }
    
    for creature in unique_creatures:
        output['creatures'].append({
            "name": creature['name'],
            "creature_id": slugify(creature['name']),
            "level": creature.get('level', 1),
            "creature_type": classify_creature_type(creature['name'], creature.get('context', '')),
            "source_book": creature['source_slug'],
            "source_display": creature['source_display'],
            "references": [{
                "source_file": creature['source_file'],
                "line": creature['line'],
                "method": creature['method'],
                "evidence": creature['evidence']
            }]
        })
    
    # Write output
    output_file = base_dir / 'comprehensive_creature_inventory.json'
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(output, f, indent=2, ensure_ascii=False)
    
    # Print summary
    print(f"\n{'='*60}")
    print(f"EXTRACTION COMPLETE")
    print(f"{'='*60}")
    print(f"Total raw extractions: {stats['total_raw']}")
    print(f"Deduplicated creatures: {stats['total_deduplicated']}")
    print(f"\nBy source:")
    for slug, count in stats['by_source'].items():
        source_name = next(s['display'] for s in SOURCES if s['slug'] == slug)
        print(f"  {source_name}: {count} creatures")
    
    # Level distribution
    level_dist = defaultdict(int)
    for creature in unique_creatures:
        level = creature.get('level', 1)
        level_dist[level] += 1
    
    print(f"\nLevel distribution (top 10):")
    for level, count in sorted(level_dist.items(), key=lambda x: -x[1])[:10]:
        print(f"  Level {level}: {count} creatures")
    
    print(f"\nOutput saved to: {output_file}")
    print(f"{'='*60}\n")


if __name__ == '__main__':
    main()
