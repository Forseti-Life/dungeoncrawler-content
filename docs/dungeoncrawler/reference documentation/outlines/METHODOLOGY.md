# Methodology for PF2E Reference Documentation Outlines

## Data Source
The outlines were extracted from text files located in the `docs/dungeoncrawler/reference documentation/` directory. Each text file was derived from PDF conversions of official Pathfinder Second Edition publications by Paizo Inc.

## Extraction Approach
The extraction process followed these steps:

1. **Identification of Table of Contents**: Located the "Table of Contents" section in each .txt file using `grep` to find the exact line number.
   
2. **Section Extraction**: Used `sed` to extract the relevant portion of each file containing the table of contents and chapter listings.

3. **Structure Analysis**: Analyzed the extracted sections to identify:
   - Main chapters and their numbering
   - Section descriptions
   - Page numbers (when available)
   - Organizational patterns specific to each book type

4. **Content Organization**: Grouped content by major sections, noting differences between:
   - **Rulebooks** (Core Rulebook, Advanced Players Guide, Gamemastery Guide, Gods and Magic, Guns and Gears, Secrets of Magic): Organized by numbered chapters with descriptive content
   - **Bestiaries** (Bestiary 1, 2, 3): Organized primarily as alphabetical creature listings with minimal chapter structure

## Rationale for Structure

### Chapter-Based Books
For rulebooks, the outline structure preserved:
- The chapter numbering system (Chapters 1-11 typically)
- Brief descriptions that accompanied each chapter in the original TOC
- Major subsections when they represented distinct thematic areas

This approach was chosen because these books function as reference material where readers need to quickly locate specific rules categories.

### Bestiary Books
For bestiaries, the outline captured:
- The introductory matter
- The main "Monsters A-Z" section with representative creature examples
- The appendix sections (ability glossaries, traits, rituals, creature organization systems)

This structure was selected because bestiaries are primarily lookup references organized alphabetically rather than thematically.

## Limitations

1. **Text Extraction Quality**: The original PDF-to-text conversion may have introduced formatting inconsistencies, affecting the precise capture of hierarchies and indentation.

2. **Ambiguous Headings**: Some subsections in the extracted text lacked clear hierarchical markers, making it difficult to distinguish between major sections and minor subdivisions.

3. **Missing Visual Context**: The text files lack the visual formatting (fonts, sizes, colors) present in the PDFs, which typically help distinguish heading levels.

4. **Incomplete Coverage**: The outlines focus on the table of contents structure and may not capture all organizational elements present within chapter bodies.

5. **Creature Listing Brevity**: For bestiaries containing hundreds of creatures, only representative samples were included in the outlines to maintain conciseness while demonstrating the alphabetical organization pattern.

## Standards Applied

- **Past Tense**: All descriptive text was written in past tense as specified in requirements
- **Consistency**: A uniform outline format was maintained across all documents
- **Conciseness**: Descriptions were kept brief while preserving essential information about section content
- **Completeness**: All .txt files in the specified directory were processed without omission
