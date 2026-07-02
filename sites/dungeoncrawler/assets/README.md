# DungeonCrawler Game Assets

This directory contains all game assets for the DungeonCrawler module including audio, images, sprites, and other media.

## Directory Structure

```
assets/
├── audio/
│   ├── music/              # Background music tracks
│   └── sfx/                # Sound effects (combat, UI, ambient)
├── images/
│   ├── sprites/
│   │   ├── creatures/      # Monster and NPC sprites
│   │   ├── characters/     # Player character sprites
│   │   └── items/          # Item and equipment sprites
│   ├── tiles/
│   │   ├── terrain/        # Floor, wall, and terrain tiles
│   │   └── objects/        # Doors, chests, traps, furniture
│   └── ui/                 # UI elements, icons, buttons
```

## Asset Guidelines

### Audio Files
- **Format**: MP3 or OGG for web compatibility
- **Bitrate**: 192 kbps for music, 128 kbps for SFX
- **Naming**: lowercase-with-dashes.ext
- **Music**: Loop-friendly tracks for continuous play
- **SFX**: Short, punchy sound effects (< 5 seconds)

### Image Files
- **Format**: PNG with transparency for sprites, WEBP for tiles
- **Sprites**: 64x64 or 128x128 pixels standard size
- **Tiles**: Match hex size (40px default, scalable)
- **Naming**: descriptive-name-state.png (e.g., goblin-idle.png)
- **Sheets**: Use sprite sheets with JSON manifests for animations

### Attribution Requirements
All assets must include:
1. Original source URL
2. Creator/artist name
3. License type (CC0, CC-BY, OGA-BY, etc.)
4. Modifications made (if any)

See [/credits](../../credits) page for full attribution list.

## Asset Loading

Assets are loaded via PixiJS AssetLoader in [hexmap.js](../../modules/custom/dungeoncrawler_content/js/hexmap.js):

```javascript
// Load assets before rendering
await PIXI.Assets.load([
  '/sites/dungeoncrawler/assets/images/sprites/creatures/goblin.png',
  '/sites/dungeoncrawler/assets/images/tiles/terrain/floor-stone.png'
]);
```

## Performance Optimization

- Use sprite sheets for animated characters (reduces HTTP requests)
- Compress images with tools like TinyPNG
- Use WEBP format for browsers that support it
- Lazy load assets not immediately visible
- Cache textures in PIXI.utils.TextureCache

## Adding New Assets

1. Place file in appropriate directory
2. Add attribution to [CREDITS.md](./CREDITS.md)
3. Update asset manifest if using loader
4. Test display in hex map demo at `/hexmap`
5. Commit with message: `feat(assets): Add [asset-name]`

## Current Assets

### Music
- None yet (planned: Epic Fantasy Music from OpenGameArt)

### Sprites
- None yet (placeholder sprites incode)

### Tiles
- None yet (using PixiJS Graphics API for hex tiles)

## Future Enhancements

- [ ] Asset preloader with progress bar
- [ ] Sprite sheet generator integration
- [ ] Asset version management
- [ ] CDN integration for large assets
- [ ] Audio sprite sheets for SFX
- [ ] Texture atlas generation

## Credits

See [/credits](../../credits) for full attribution and licensing information.
