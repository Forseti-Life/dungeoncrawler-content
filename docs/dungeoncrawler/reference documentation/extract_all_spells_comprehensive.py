#!/usr/bin/env python3
"""
Comprehensive PF2E Spell Extractor
Extracts spell names and basic metadata from PF2E source books.
"""

import json
import re
from pathlib import Path
from collections import defaultdict

# Source books to process (spell-focused)
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

# Spell schools
SPELL_SCHOOLS = [
    "abjuration", "conjuration", "divination", "enchantment",
    "evocation", "illusion", "necromancy", "transmutation"
]

# Spell traditions
SPELL_TRADITIONS = ["arcane", "divine", "occult", "primal"]

# Level patterns
SPELL_LEVEL_PATTERN = r'(?:SPELL|Spell|Cantrip)\s+(\d+|Cantrip)'

# Noise patterns
NOISE_PATTERNS = [
    r'^(Chapter|Page|Table|Figure|Part|Section|Appendix)',
    r'^(Spells|Spell List|Focus Spells|Rituals)',
    r'^\d+\s*$',
    r'^[IVX]+\s*$',
]

GENERIC_TERMS = {
    "Spell", "Spells", "Cantrip", "Cantrips", "Focus", "Ritual",
    "Cast", "Casting", "Range", "Area", "Targets", "Duration",
    "Saving Throw", "Heightened", "Trigger", "Requirements",
    "Traditions", "School", "Actions", "Components"
}


def slugify(text):
    """Convert text to valid spell_id slug."""
    slug = text.lower()
    slug = re.sub(r'[^\w\s-]', '', slug)
    slug = re.sub(r'[-\s]+', '_', slug)
    return slug.strip('_')


def is_noise(text):
    """Check if text is likely noise/section header."""
    text = text.strip()
    
    if not text or len(text) < 3 or len(text) > 60:
        return True
    
    for pattern in NOISE_PATTERNS:
        if re.match(pattern, text, re.IGNORECASE):
            return True
    
    if text in GENERIC_TERMS:
        return True
    
    if text.upper() in GENERIC_TERMS:
        return True
    
    # Must be title case
    if not text[0].isupper():
        return True
    
    # Filter if all uppercase single word (likely header)
    if text.isupper() and len(text.split()) == 1:
        return True
    
    return False


def extract_spell_level(text, context_lines):
    """Extract spell level from text and context."""
    # Check for cantrip
    if re.search(r'\bCantrip\b', text, re.IGNORECASE):
        return 0
    
    # Check current line
    match = re.search(SPELL_LEVEL_PATTERN, text, re.IGNORECASE)
    if match:
        level_str = match.group(1)
        if level_str.lower() == 'cantrip':
            return 0
        return int(level_str)
    
    # Check context
    for line in context_lines[-10:]:
        match = re.search(SPELL_LEVEL_PATTERN, line, re.IGNORECASE)
        if match:
            level_str = match.group(1)
            if level_str.lower() == 'cantrip':
                return 0
            return int(level_str)
    
    return None


def extract_school(context):
    """Extract spell school from context."""
    context_lower = context.lower()
    for school in SPELL_SCHOOLS:
        if school in context_lower:
            return school
    return None


def extract_traditions(context):
    """Extract spell traditions from context."""
    found_traditions = []
    context_lower = context.lower()
    for tradition in SPELL_TRADITIONS:
        if tradition in context_lower:
            found_traditions.append(tradition)
    return found_traditions if found_traditions else None


def extract_spells_from_file(filepath, source_info):
    """Extract all spells from a single source file."""
    print(f"\nProcessing: {source_info['display']}")
    
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
    
    lines = content.split('\n')
    spells = []
    
    context_window = []
    in_spell_section = False
    
    for line_num, line in enumerate(lines, 1):
        line = line.strip()
        
        # Track spell sections
        if re.search(r'\bSPELL\s+\d+', line, re.IGNORECASE):
            in_spell_section = True
            context_window = []
        
        # Keep context window
        context_window.append(line)
        if len(context_window) > 20:
            context_window.pop(0)
        
        # Skip clear noise
        if is_noise(line):
            continue
        
        # Extract spells with SPELL marker
        spell_match = re.match(r'^SPELL\s+(\d+|Cantrip)\s*[:\-]?\s*(.+?)(?:\s+\[(.+?)\])?$', line, re.IGNORECASE)
        if spell_match:
            level_str = spell_match.group(1)
            level = 0 if level_str.lower() == 'cantrip' else int(level_str)
            name = spell_match.group(2).strip()
            traits = spell_match.group(3) if spell_match.group(3) else ""
            
            if not is_noise(name) and len(name) >= 3:
                context_text = ' '.join(context_window[-10:])
                spells.append({
                    'name': name,
                    'spell_level': level,
                    'line': line_num,
                    'method': 'SPELL_marker',
                    'evidence': line[:200],
                    'traits': traits,
                    'school': extract_school(context_text),
                    'traditions': extract_traditions(context_text),
                    'context': context_text
                })
                continue
        
        # Extract title-case spell names near level indicators
        if in_spell_section and line and line[0].isupper():
            level = extract_spell_level(line, context_window)
            if level is not None:
                # Clean potential spell name
                name = re.sub(r'\s+SPELL\s+\d+.*$', '', line, flags=re.IGNORECASE)
                name = re.sub(r'\s+Cantrip.*$', '', name, flags=re.IGNORECASE)
                name = re.sub(r'\s+\[.+\]$', '', name)
                name = name.strip()
                
                if not is_noise(name) and len(name) >= 3:
                    words = name.split()
                    if len(words) <= 6:
                        context_text = ' '.join(context_window[-10:])
                        spells.append({
                            'name': name,
                            'spell_level': level,
                            'line': line_num,
                            'method': 'level_adjacent',
                            'evidence': line[:200],
                            'traits': '',
                            'school': extract_school(context_text),
                            'traditions': extract_traditions(context_text),
                            'context': context_text
                        })
    
    print(f"  Raw extractions: {len(spells)}")
    return spells


def deduplicate_spells(spells):
    """Remove duplicate spells, keeping best evidence."""
    seen = {}
    
    for spell in spells:
        name = spell['name']
        slug = slugify(name)
        
        if slug not in seen:
            seen[slug] = spell
        else:
            # Keep spell with more metadata
            if spell['method'] == 'SPELL_marker' and seen[slug]['method'] != 'SPELL_marker':
                seen[slug] = spell
            elif spell.get('school') and not seen[slug].get('school'):
                seen[slug] = spell
    
    return list(seen.values())


def main():
    base_dir = Path(__file__).parent
    all_spells = []
    
    print(f"Starting spell extraction...")
    print(f"Base directory: {base_dir}")
    
    stats = {
        'by_source': {},
        'by_level': defaultdict(int),
        'by_school': defaultdict(int),
        'total_raw': 0,
        'total_deduplicated': 0
    }
    
    # Extract from all sources
    for source in SOURCES:
        filepath = base_dir / source['file']
        
        if not filepath.exists():
            print(f"⚠️  Skipping missing file: {source['file']}")
            continue
        
        spells = extract_spells_from_file(filepath, source)
        
        # Add source metadata
        for spell in spells:
            spell['source_file'] = source['file']
            spell['source_slug'] = source['slug']
            spell['source_display'] = source['display']
            
            # Track stats
            if spell.get('spell_level') is not None:
                stats['by_level'][spell['spell_level']] += 1
            if spell.get('school'):
                stats['by_school'][spell['school']] += 1
        
        all_spells.extend(spells)
        stats['by_source'][source['slug']] = len(spells)
        stats['total_raw'] += len(spells)
    
    # Deduplicate
    print(f"\n{'='*60}")
    print(f"Deduplicating spells...")
    unique_spells = deduplicate_spells(all_spells)
    stats['total_deduplicated'] = len(unique_spells)
    
    # Sort by level, then name
    unique_spells.sort(key=lambda x: (x.get('spell_level') or 0, x['name']))
    
    # Generate output
    output = {
        "extraction_date": "2026-02-19",
        "sources": [s['display'] for s in SOURCES],
        "stats": {
            'total_raw': stats['total_raw'],
            'total_deduplicated': stats['total_deduplicated'],
            'by_source': stats['by_source'],
            'by_level': dict(sorted(stats['by_level'].items())),
            'by_school': dict(sorted(stats['by_school'].items(), key=lambda x: -x[1]))
        },
        "spell_count": len(unique_spells),
        "spells": []
    }
    
    for spell in unique_spells:
        output['spells'].append({
            "name": spell['name'],
            "spell_id": slugify(spell['name']),
            "spell_level": spell.get('spell_level'),
            "school": spell.get('school'),
            "traditions": spell.get('traditions'),
            "source_book": spell['source_slug'],
            "source_display": spell['source_display'],
            "traits": spell.get('traits', ''),
            "references": [{
                "source_file": spell['source_file'],
                "line": spell['line'],
                "method": spell['method'],
                "evidence": spell['evidence']
            }]
        })
    
    # Write output
    output_file = base_dir / 'comprehensive_spell_inventory.json'
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(output, f, indent=2, ensure_ascii=False)
    
    # Print summary
    print(f"\n{'='*60}")
    print(f"EXTRACTION COMPLETE")
    print(f"{'='*60}")
    print(f"Total raw extractions: {stats['total_raw']}")
    print(f"Deduplicated spells: {stats['total_deduplicated']}")
    print(f"\nBy source:")
    for slug, count in stats['by_source'].items():
        source_name = next(s['display'] for s in SOURCES if s['slug'] == slug)
        print(f"  {source_name}: {count} spells")
    print(f"\nBy level:")
    for level, count in sorted(stats['by_level'].items()):
        level_name = "Cantrip" if level == 0 else f"Level {level}"
        print(f"  {level_name}: {count}")
    print(f"\nBy school (top 5):")
    for school, count in sorted(stats['by_school'].items(), key=lambda x: -x[1])[:5]:
        print(f"  {school}: {count}")
    print(f"\nOutput saved to: {output_file}")
    print(f"{'='*60}\n")


if __name__ == '__main__':
    main()
