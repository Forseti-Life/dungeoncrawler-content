#!/usr/bin/env python3
"""
Comprehensive PF2E Item Extractor
Extracts all items from all available PF2E source books.
"""

import json
import re
from pathlib import Path
from collections import defaultdict

# Source books to process (equipment-focused)
SOURCES = [
    {
        "file": "PF2E Core Rulebook - Fourth Printing.txt",
        "slug": "core_rulebook_4th_printing",
        "display": "Core Rulebook (4th Printing)"
    },
    {
        "file": "PF2E Advanced Players Guide.txt",
        "slug": "advanced_players_guide",
        "display": "Advanced Player's Guide"
    },
    {
        "file": "PF2E Secrets of Magic.txt",
        "slug": "secrets_of_magic",
        "display": "Secrets of Magic"
    },
    {
        "file": "PF2E Guns and Gears.txt",
        "slug": "guns_and_gears",
        "display": "Guns & Gears"
    },
    {
        "file": "PF2E Gamemastery Guide.txt",
        "slug": "gamemastery_guide",
        "display": "Gamemastery Guide"
    },
    {
        "file": "PF2E Gods and Magic.txt",
        "slug": "gods_and_magic",
        "display": "Gods & Magic"
    }
]

# Item section keywords to focus extraction
ITEM_SECTIONS = [
    "WEAPONS", "ARMOR", "EQUIPMENT", "ITEMS", "ADVENTURING GEAR",
    "ALCHEMICAL ITEMS", "MAGICAL ITEMS", "CONSUMABLES", "TOOLS",
    "TREASURE", "ARTIFACTS", "RELICS", "RUNES", "TALISMANS"
]

# Noise patterns to exclude
NOISE_PATTERNS = [
    r'^(Chapter|Page|Table|Figure|Part|Section|Appendix)',
    r'^(The|A|An)\s+[A-Z]',  # Common article starts
    r'^(Introduction|Overview|Rules|Sidebar)',
    r'^\d+\s*$',  # Pure numbers
    r'^[IVX]+\s*$',  # Roman numerals
    r'^\s*$',  # Empty
]

# Generic terms to exclude
GENERIC_TERMS = {
    "Weapons", "Armor", "Equipment", "Items", "Type", "Price", "Bulk",
    "Level", "Traits", "Hands", "Range", "Reload", "Damage", "Category",
    "Group", "Description", "Usage", "Activate", "Effect", "Duration",
    "Saving Throw", "Page", "Table", "Chapter", "Index", "Glossary",
    "Common", "Uncommon", "Rare", "Unique", "Magical", "Divine", "Arcane",
    "Primal", "Occult", "Weapon Traits", "Armor Traits", "General",
    "ITEM", "ITEMS", "NAME", "COST", "WEIGHT", "AC BONUS", "Ammunition",
    "Apex", "Bomb"
}

# Noise prefixes to exclude
NOISE_PREFIXES = [
    "Bulk ", "Craft Requirements", "As the ", "If the ", "When the ",
    "Ammunition for ", "Cost ", "Weight ", "Hands ", "Range ",
    "You can ", "This ", "A creature ", "The ", "An "
]


def slugify(text):
    """Convert text to valid item_id slug."""
    slug = text.lower()
    slug = re.sub(r'[^\w\s-]', '', slug)
    slug = re.sub(r'[-\s]+', '_', slug)
    return slug.strip('_')


def is_noise(text):
    """Check if text is likely noise/section header."""
    text = text.strip()
    
    if not text or len(text) < 3 or len(text) > 80:
        return True
    
    for pattern in NOISE_PATTERNS:
        if re.match(pattern, text, re.IGNORECASE):
            return True
    
    if text in GENERIC_TERMS:
        return True
    
    # Check noise prefixes
    for prefix in NOISE_PREFIXES:
        if text.startswith(prefix):
            return True
    
    # Must be title case or have price indicator
    if not (text[0].isupper() or 'gp' in text.lower() or 'sp' in text.lower()):
        return True
    
    # Must have at least one alphabetic character that's not all caps
    if text.isupper() and len(text) > 20:
        return True
    
    # Filter partial sentences (lowercase words after first)
    words = text.split()
    if len(words) > 3:
        lowercase_count = sum(1 for w in words[1:] if w.islower() and w not in ['of', 'the', 'a', 'an', 'and', 'or', 'for', 'with'])
        if lowercase_count > len(words) / 2:
            return True
    
    return False


def extract_price(text):
    """Extract price in gold pieces from text."""
    # Look for price patterns: "5 gp", "100 sp", "1,000 gp"
    match = re.search(r'(\d+(?:,\d+)?)\s*(gp|sp|cp)', text, re.IGNORECASE)
    if match:
        amount = int(match.group(1).replace(',', ''))
        unit = match.group(2).lower()
        
        if unit == 'sp':
            return amount / 10
        elif unit == 'cp':
            return amount / 100
        else:
            return amount
    return None


def classify_item_type(name, context=""):
    """Classify item into weapon/armor/gear categories."""
    name_lower = name.lower()
    context_lower = context.lower()
    combined = name_lower + " " + context_lower
    
    # Weapon indicators
    weapon_keywords = [
        'sword', 'axe', 'bow', 'crossbow', 'dagger', 'mace', 'spear',
        'staff', 'club', 'hammer', 'blade', 'gun', 'pistol', 'rifle',
        'weapon', 'arrow', 'bolt', 'ammunition'
    ]
    
    # Armor indicators
    armor_keywords = [
        'armor', 'shield', 'mail', 'plate', 'leather', 'hide', 'chain',
        'breastplate', 'helmet', 'gauntlet', 'greaves'
    ]
    
    # Magic item indicators
    magic_keywords = [
        'ring', 'amulet', 'wand', 'staff', 'rod', 'orb', 'talisman',
        'rune', 'potion', 'elixir', 'scroll', 'magical'
    ]
    
    for keyword in weapon_keywords:
        if keyword in combined:
            return "weapon"
    
    for keyword in armor_keywords:
        if keyword in combined:
            return "armor"
    
    for keyword in magic_keywords:
        if keyword in combined:
            return "magic_item"
    
    return "adventuring_gear"


def extract_items_from_file(filepath, source_info):
    """Extract all items from a single source file."""
    print(f"\nProcessing: {source_info['display']}")
    
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
    
    lines = content.split('\n')
    items = []
    
    # Multi-pass extraction
    in_item_section = False
    context_window = []
    
    for line_num, line in enumerate(lines, 1):
        line = line.strip()
        
        # Track if we're in an item-focused section
        if any(section in line.upper() for section in ITEM_SECTIONS):
            in_item_section = True
            context_window = []
            continue
        
        # Exit item sections on major headers
        if line.isupper() and len(line) > 20 and not any(section in line for section in ITEM_SECTIONS):
            in_item_section = False
        
        # Keep sliding window of context
        context_window.append(line)
        if len(context_window) > 10:
            context_window.pop(0)
        
        # Skip clear noise
        if is_noise(line):
            continue
        
        # Extract items with ITEM marker
        if re.match(r'^ITEM\s+\d+', line, re.IGNORECASE):
            match = re.search(r'ITEM\s+\d+\s*[:\-]?\s*(.+?)(?:\s+Price\s+(.+))?$', line, re.IGNORECASE)
            if match:
                name = match.group(1).strip()
                price_text = match.group(2) if match.group(2) else ""
                
                if not is_noise(name) and len(name) >= 3:
                    items.append({
                        'name': name,
                        'line': line_num,
                        'method': 'ITEM_marker',
                        'evidence': line[:200],
                        'price': extract_price(price_text),
                        'context': ' '.join(context_window[-3:])
                    })
                    continue
        
        # Extract items with price adjacency
        if re.search(r'\d+\s*(gp|sp|cp)', line, re.IGNORECASE) and in_item_section:
            # Split on price to get potential item name
            parts = re.split(r'\s+(\d+(?:,\d+)?\s*(?:gp|sp|cp))', line, flags=re.IGNORECASE)
            if parts and len(parts[0].strip()) >= 3:
                name = parts[0].strip()
                
                # Clean up common prefixes/suffixes
                name = re.sub(r'^(ITEM|Item|Equipment|Weapon|Armor)\s*\d*\s*[:\-]?\s*', '', name)
                name = re.sub(r'\s+(Page|Pg|p\.?)\s*\d+.*$', '', name)
                name = name.strip()
                
                # Additional validation for price-adjacent items
                if not is_noise(name) and len(name) >= 3 and name[0].isupper():
                    # Must have reasonable word structure
                    words = name.split()
                    if len(words) <= 10 and not name.endswith('worth at least'):
                        items.append({
                            'name': name,
                            'line': line_num,
                            'method': 'price_adjacent',
                            'evidence': line[:200],
                            'price': extract_price(line),
                            'context': ' '.join(context_window[-3:])
                        })
    
    print(f"  Raw extractions: {len(items)}")
    return items


def deduplicate_items(items):
    """Remove duplicate items, keeping best evidence."""
    seen = {}
    
    for item in items:
        name = item['name']
        slug = slugify(name)
        
        if slug not in seen:
            seen[slug] = item
        else:
            # Keep the one with more evidence
            if len(item.get('evidence', '')) > len(seen[slug].get('evidence', '')):
                seen[slug] = item
            elif item.get('price') and not seen[slug].get('price'):
                seen[slug] = item
    
    return list(seen.values())


def main():
    base_dir = Path(__file__).parent
    all_items = []
    
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
        
        items = extract_items_from_file(filepath, source)
        
        # Add source metadata
        for item in items:
            item['source_file'] = source['file']
            item['source_slug'] = source['slug']
            item['source_display'] = source['display']
        
        all_items.extend(items)
        stats['by_source'][source['slug']] = len(items)
        stats['total_raw'] += len(items)
    
    # Deduplicate across all sources
    print(f"\n{'='*60}")
    print(f"Deduplicating items...")
    unique_items = deduplicate_items(all_items)
    stats['total_deduplicated'] = len(unique_items)
    
    # Sort by name
    unique_items.sort(key=lambda x: x['name'])
    
    # Generate output
    output = {
        "extraction_date": "2026-02-18",
        "sources": [s['display'] for s in SOURCES],
        "stats": stats,
        "item_count": len(unique_items),
        "items": []
    }
    
    for item in unique_items:
        output['items'].append({
            "name": item['name'],
            "item_id": slugify(item['name']),
            "item_type": classify_item_type(item['name'], item.get('context', '')),
            "price_gp": item.get('price'),
            "source_book": item['source_slug'],
            "source_display": item['source_display'],
            "references": [{
                "source_file": item['source_file'],
                "line": item['line'],
                "method": item['method'],
                "evidence": item['evidence']
            }]
        })
    
    # Write output
    output_file = base_dir / 'comprehensive_item_inventory.json'
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(output, f, indent=2, ensure_ascii=False)
    
    # Print summary
    print(f"\n{'='*60}")
    print(f"EXTRACTION COMPLETE")
    print(f"{'='*60}")
    print(f"Total raw extractions: {stats['total_raw']}")
    print(f"Deduplicated items: {stats['total_deduplicated']}")
    print(f"\nBy source:")
    for slug, count in stats['by_source'].items():
        source_name = next(s['display'] for s in SOURCES if s['slug'] == slug)
        print(f"  {source_name}: {count} items")
    print(f"\nOutput saved to: {output_file}")
    print(f"{'='*60}\n")


if __name__ == '__main__':
    main()
