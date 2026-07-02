# DungeonCrawler Asset Credits

This file tracks all third-party assets used in the DungeonCrawler game, including their sources, creators, and licenses.

## Audio Assets

### Music

#### Epic Fantasy Music
- **Source**: [OpenGameArt.org](https://opengameart.org/content/epic-fantasy-music)
- **Creator**: [Creator Name from OGA]
- **License**: [To be determined from source page]
- **Files**: 
  - `epic-fantasy-battle.mp3` (planned)
  - `epic-fantasy-exploration.mp3` (planned)
  - `epic-fantasy-town.mp3` (planned)
- **Usage**: Background music for dungeon exploration and combat
- **Modifications**: None / [List any modifications]

### Sound Effects
_No SFX assets added yet_

## Image Assets

### Sprites

#### Creatures
_No creature sprites added yet_

#### Characters
_No character sprites added yet_

#### Items
_No item sprites added yet_

### Tiles

#### Terrain
_No terrain tiles added yet_

#### Objects
_No object tiles added yet_

### UI Elements
_No UI elements added yet_

## Rendering Engine

### PixiJS
- **Source**: [PixiJS GitHub](https://github.com/pixijs/pixijs)
- **Version**: 7.3.2
- **License**: MIT License
- **Usage**: 2D WebGL rendering engine for hex map and game graphics
- **CDN**: https://cdn.jsdelivr.net/npm/pixi.js@7.3.2/dist/pixi.min.js

## Fonts

_No custom fonts added yet_

## Attribution Format

When adding new assets, use this template:

```markdown
#### Asset Name
- **Source**: [Link to original source]
- **Creator**: [Artist/creator name]
- **License**: [License type and link]
- **Files**: 
  - `filename1.ext`
  - `filename2.ext`
- **Usage**: [How asset is used in game]
- **Modifications**: [None or list changes made]
```

## License Compliance

### Allowed Licenses
- **CC0 (Public Domain)**: No attribution required, but we provide it anyway
- **CC-BY 3.0/4.0**: Attribution required
- **OGA-BY 3.0**: Attribution required (OpenGameArt specific)
- **MIT/BSD**: Attribution required
- **GPL**: Compatible with open source project

### Prohibited Licenses
- Commercial-only licenses (this is an open project)
- No-derivatives licenses (may need to modify assets)
- Licenses with unclear terms

## How to Add Credits

1. Download asset and place in appropriate `/assets/` subdirectory
2. Add entry to this file with all required information
3. Update the `/credits` page via `CreditsController.php`
4. Commit changes with: `feat(assets): Add [asset name] with credits`

## Last Updated

**Date**: 2026-02-12
**By**: Keith Aumiller
**Assets**: 1 (PixiJS engine only)
