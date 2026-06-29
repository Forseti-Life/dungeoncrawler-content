# Issue #2: Hex Map Rendering System - Design Document

## Overview
Design a performant, interactive hexagonal map rendering system with fog of war, character movement, and real-time updates for the dungeon crawler.

## Design Goals

1. **Flat-Top Hex Grid**: Axial coordinate system (q, r)
2. **Fog of War**: Reveal hexes as party explores
3. **Interactive**: Click/tap to move, inspect, interact
4. **Performant**: Render 1000+ hexes smoothly
5. **Responsive**: Works on desktop and mobile
6. **Layered Rendering**: Terrain, objects, creatures, effects separately
7. **Real-time Updates**: Show party movement, combat, effects

## Coordinate System

### Axial Coordinates (q, r)
```
Flat-Top Hex Layout:

     ___       ___       ___
    /   \     /   \     /   \
___/ 0,-1\___/ 1,-1\___/ 2,-1\___
   \     /   \     /   \     /
    \___/ 0,0 \___/ 1,0 \___/
    /   \     /   \     /   \
___/-1,0 \___/ 0,0 \___/ 1,0 \___
   \     /   \     /   \     /
    \___/-1,1 \___/ 0,1 \___/
    /   \     /   \     /   \
   /     \___/     \___/     \

q = column (increases rightward)
r = row (increases downward-right)
```

### Pixel Coordinate Conversion
```javascript
// Axial to pixel (center of hex)
function axialToPixel(q, r, hexSize) {
  x = hexSize * (3/2 * q)
  y = hexSize * (√3 * (r + q/2))
  return {x, y}
}

// Pixel to axial (which hex contains this point)
function pixelToAxial(x, y, hexSize) {
  q = (2/3 * x) / hexSize
  r = (-1/3 * x + √3/3 * y) / hexSize
  return roundAxial(q, r)  // Round to nearest hex
}

// Round to nearest hex (handle fractional coords)
function roundAxial(q, r) {
  s = -q - r
  rq = round(q)
  rr = round(r)
  rs = round(s)
  
  q_diff = abs(rq - q)
  r_diff = abs(rr - r)
  s_diff = abs(rs - s)
  
  if (q_diff > r_diff && q_diff > s_diff) {
    rq = -rr - rs
  } else if (r_diff > s_diff) {
    rr = -rq - rs
  }
  
  return {q: rq, r: rr}
}
```

### Neighbor Calculation
```javascript
// Six directions for flat-top hexes
const HEX_DIRECTIONS = [
  {q: +1, r:  0}, // E
  {q: +1, r: -1}, // NE
  {q:  0, r: -1}, // NW
  {q: -1, r:  0}, // W
  {q: -1, r: +1}, // SW
  {q:  0, r: +1}  // SE
]

function getNeighbor(hex, direction) {
  return {
    q: hex.q + HEX_DIRECTIONS[direction].q,
    r: hex.r + HEX_DIRECTIONS[direction].r
  }
}

function getNeighbors(hex) {
  return HEX_DIRECTIONS.map(dir => ({
    q: hex.q + dir.q,
    r: hex.r + dir.r
  }))
}
```

### Distance Calculation
```javascript
// Manhattan distance on hex grid
function hexDistance(a, b) {
  return (abs(a.q - b.q) + abs(a.r - b.r) + abs((a.q + a.r) - (b.q + b.r))) / 2
}

// In feet (PF2e uses 5ft per hex)
function hexDistanceInFeet(a, b) {
  return hexDistance(a, b) * 5
}
```

## Database Architecture

### hexmap_state Table
```sql
CREATE TABLE dungeoncrawler_hexmap_state (
  id INT PRIMARY KEY AUTO_INCREMENT,
  dungeon_level_id INT NOT NULL,         -- FK to dungeon_levels
  party_id INT NOT NULL,                 -- FK to parties
  hex_q INT NOT NULL,                    -- Axial Q coordinate
  hex_r INT NOT NULL,                    -- Axial R coordinate
  terrain_type VARCHAR(50),              -- 'floor', 'wall', 'door', 'pit', etc
  room_id INT,                           -- FK to rooms (if part of room)
  visibility VARCHAR(20) NOT NULL,       -- 'hidden', 'revealed', 'visible'
  /*
    hidden: Never seen (fog of war)
    revealed: Seen before (gray/memory)
    visible: Currently visible (full color)
  */
  elevation INT DEFAULT 0,               -- Height in feet
  difficult_terrain BOOLEAN DEFAULT FALSE,
  features JSON,                         -- Additional hex features
  /*
  features: {
    "door": {"type": "wooden", "locked": true, "open": false},
    "trap": {"id": 123, "detected": false},
    "item": {"id": 456},
    "blood_splatter": true
  }
  */
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_hex (dungeon_level_id, party_id, hex_q, hex_r),
  INDEX idx_visibility (dungeon_level_id, party_id, visibility),
  INDEX idx_room (room_id)
)
```

### hexmap_objects Table
```sql
CREATE TABLE dungeoncrawler_hexmap_objects (
  id INT PRIMARY KEY AUTO_INCREMENT,
  hex_id INT NOT NULL,                   -- FK to hexmap_state
  object_type VARCHAR(50) NOT NULL,      -- 'creature', 'item', 'effect', 'marker'
  object_id INT NOT NULL,                -- FK to creatures, items, etc
  position_offset JSON,                  -- Fine positioning within hex
  /*
  position_offset: {
    "offset_x": 0.25,  // -0.5 to 0.5 (fraction of hex width)
    "offset_y": -0.1   // -0.5 to 0.5 (fraction of hex height)
  }
  */
  z_index INT DEFAULT 0,                 -- Rendering order
  visible BOOLEAN DEFAULT TRUE,
  metadata JSON,                         -- Object-specific data
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_hex (hex_id),
  INDEX idx_object (object_type, object_id)
)
```

### hexmap_line_of_sight Table (cached visibility calculations)
```sql
CREATE TABLE dungeoncrawler_hexmap_los (
  id INT PRIMARY KEY AUTO_INCREMENT,
  dungeon_level_id INT NOT NULL,
  from_q INT NOT NULL,
  from_r INT NOT NULL,
  to_q INT NOT NULL,
  to_r INT NOT NULL,
  has_line_of_sight BOOLEAN NOT NULL,
  blocking_hex_q INT,                    -- Where LOS blocked
  blocking_hex_r INT,
  calculated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_los (dungeon_level_id, from_q, from_r, to_q, to_r),
  INDEX idx_from (dungeon_level_id, from_q, from_r)
)
```

## Service Layer Design

### HexMapRenderer Service
```php
class HexMapRenderer {
  
  /**
   * Get renderable hex map data for frontend
   * 
   * @param int $dungeon_level_id
   * @param int $party_id
   * @param array $viewport - Optional viewport bounds {center: {q, r}, radius: int}
   * @return array - Complete map data
   *   [
   *     'hexes' => [
   *       ['q' => 0, 'r' => 0, 'terrain' => 'floor', 'visibility' => 'visible', ...],
   *       ...
   *     ],
   *     'objects' => [
   *       ['hex_q' => 0, 'hex_r' => 0, 'type' => 'creature', 'id' => 123, ...],
   *       ...
   *     ],
   *     'party_position' => ['q' => 5, 'r' => 3],
   *     'light_sources' => [
   *       ['q' => 5, 'r' => 3, 'radius' => 4, 'type' => 'torch']
   *     ],
   *     'viewport_bounds' => ['min_q' => -5, 'max_q' => 15, 'min_r' => -3, 'max_r' => 12]
   *   ]
   */
  public function getMapData(int $dungeon_level_id, int $party_id, array $viewport = NULL): array
  
  /**
   * Get only updated hexes since timestamp (for real-time sync)
   * 
   * @param int $dungeon_level_id
   * @param int $party_id
   * @param string $since_timestamp
   * @return array - Changed hexes and objects
   */
  public function getMapUpdates(int $dungeon_level_id, int $party_id, string $since_timestamp): array
  
  /**
   * Render hex to SVG path
   * 
   * @param int $q
   * @param int $r
   * @param float $hex_size - Size in pixels
   * @return string - SVG path data
   */
  public function hexToSVGPath(int $q, int $r, float $hex_size): string
}
```

### HexMapVisibility Service
```php
class HexMapVisibility {
  
  /**
   * Update fog of war based on party position and light
   * 
   * @param int $dungeon_level_id
   * @param int $party_id
   * @param array $party_position - {q: int, r: int}
   * @param int $vision_radius - In hexes
   * @return array - Newly revealed hex coordinates
   */
  public function updateFogOfWar(int $dungeon_level_id, int $party_id, array $party_position, int $vision_radius): array
  
  /**
   * Check line of sight between two hexes
   * 
   * @param int $dungeon_level_id
   * @param array $from - {q: int, r: int}
   * @param array $to - {q: int, r: int}
   * @return bool - TRUE if line of sight exists
   */
  public function hasLineOfSight(int $dungeon_level_id, array $from, array $to): bool
  
  /**
   * Get all hexes visible from position
   * Uses Bresenham line algorithm for each hex in radius
   * 
   * @param int $dungeon_level_id
   * @param array $position - {q: int, r: int}
   * @param int $radius - Vision radius in hexes
   * @return array - Array of visible hex coordinates [{q, r}, ...]
   */
  public function getVisibleHexes(int $dungeon_level_id, array $position, int $radius): array
  
  /**
   * Bresenham line algorithm for hexes
   * Returns all hexes along line from start to end
   * 
   * @param array $start - {q: int, r: int}
   * @param array $end - {q: int, r: int}
   * @return array - Hexes along line
   */
  protected function hexLine(array $start, array $end): array
}
```

### HexMapInteraction Service
```php
class HexMapInteraction {
  
  /**
   * Handle player click/tap on hex
   * 
   * @param int $dungeon_level_id
   * @param int $party_id
   * @param array $hex - {q: int, r: int}
   * @param string $action - 'move', 'inspect', 'attack', 'interact'
   * @return array - Result of action
   *   [
   *     'success' => bool,
   *     'message' => string,
   *     'updates' => array,  // Map updates to apply
   *     'roll_required' => bool,
   *     'options' => array   // Available actions
   *   ]
   */
  public function handleHexAction(int $dungeon_level_id, int $party_id, array $hex, string $action): array
  
  /**
   * Calculate move path from current position to destination
   * Returns path or null if unreachable
   * 
   * @param int $dungeon_level_id
   * @param array $start - {q: int, r: int}
   * @param array $end - {q: int, r: int}
   * @param int $movement_speed - In hexes (feet / 5)
   * @return array|null - Path as array of hexes, or null
   */
  public function calculateMovementPath(int $dungeon_level_id, array $start, array $end, int $movement_speed): ?array
  
  /**
   * Get available actions for hex
   * 
   * @param int $dungeon_level_id
   * @param int $party_id
   * @param array $hex - {q: int, r: int}
   * @return array - Available actions
   *   [
   *     ['action' => 'move', 'icon' => 'walk', 'label' => 'Move Here'],
   *     ['action' => 'attack', 'icon' => 'sword', 'label' => 'Attack Goblin', 'target_id' => 123],
   *     ...
   *   ]
   */
  public function getHexActions(int $dungeon_level_id, int $party_id, array $hex): array
}
```

## Frontend Architecture

### Technology Stack
```
Rendering: SVG (initial), Canvas (performance upgrade)
Framework: Vanilla JS with Drupal.behaviors
State Management: Simple object store with pub/sub
Interaction: Pointer events (mouse + touch)
Updates: AJAX polling or WebSockets
```

### Component Structure
```javascript
/**
 * Main HexMap component
 */
DungeonCrawler.HexMap = {
  config: {
    hexSize: 30,              // Pixels
    containerSelector: '#hex-map-container',
    apiEndpoint: '/api/hexmap'
  },
  
  state: {
    dungeonLevelId: null,
    partyId: null,
    hexes: new Map(),         // Map of "q,r" => hex data
    objects: [],              // Objects on map
    partyPosition: {q: 0, r: 0},
    viewport: {
      centerQ: 0,
      centerR: 0,
      zoom: 1.0,
      panX: 0,
      panY: 0
    },
    selectedHex: null,
    hoveredHex: null
  },
  
  /**
   * Initialize hex map
   */
  init: function(dungeonLevelId, partyId) {},
  
  /**
   * Load map data from API
   */
  loadMapData: function() {},
  
  /**
   * Render entire map
   */
  render: function() {},
  
  /**
   * Render single hex
   */
  renderHex: function(hex) {},
  
  /**
   * Handle hex click
   */
  onHexClick: function(q, r) {},
  
  /**
   * Handle hex hover
   */
  onHexHover: function(q, r) {},
  
  /**
   * Update viewport (pan/zoom)
   */
  updateViewport: function(centerQ, centerR, zoom) {},
  
  /**
   * Center viewport on hex
   */
  centerOnHex: function(q, r) {},
  
  /**
   * Poll for updates
   */
  pollUpdates: function() {},
  
  /**
   * Apply map updates
   */
  applyUpdates: function(updates) {}
}
```

### Rendering Pipeline
```javascript
/**
 * SVG hex rendering
 */
function renderHexSVG(hex, hexSize) {
  // Calculate pixel position
  const pos = axialToPixel(hex.q, hex.r, hexSize)
  
  // Generate hex path (pointy corners)
  const points = []
  for (let i = 0; i < 6; i++) {
    const angle = (Math.PI / 3) * i
    const x = pos.x + hexSize * Math.cos(angle)
    const y = pos.y + hexSize * Math.sin(angle)
    points.push(`${x},${y}`)
  }
  
  // Build SVG element
  const hexElement = document.createElementNS('http://www.w3.org/2000/svg', 'polygon')
  hexElement.setAttribute('points', points.join(' '))
  hexElement.setAttribute('data-q', hex.q)
  hexElement.setAttribute('data-r', hex.r)
  
  // Style based on visibility and terrain
  let cssClass = `hex hex-${hex.terrain} hex-${hex.visibility}`
  hexElement.setAttribute('class', cssClass)
  
  // Fog of war opacity
  if (hex.visibility === 'hidden') {
    hexElement.style.opacity = 0
  } else if (hex.visibility === 'revealed') {
    hexElement.style.opacity = 0.5  // Previously seen
  } else {
    hexElement.style.opacity = 1.0  // Currently visible
  }
  
  return hexElement
}

/**
 * Layered rendering (bottom to top)
 */
function renderMap(state) {
  const svg = document.querySelector('#hex-map-svg')
  svg.innerHTML = ''  // Clear
  
  // Layer 1: Terrain hexes
  const terrainLayer = document.createElementNS('http://www.w3.org/2000/svg', 'g')
  terrainLayer.setAttribute('id', 'terrain-layer')
  
  for (const [key, hex] of state.hexes) {
    if (hex.visibility !== 'hidden') {
      terrainLayer.appendChild(renderHexSVG(hex, state.config.hexSize))
    }
  }
  svg.appendChild(terrainLayer)
  
  // Layer 2: Grid lines
  const gridLayer = renderGridLines(state)
  svg.appendChild(gridLayer)
  
  // Layer 3: Objects (items, traps)
  const objectLayer = renderObjects(state)
  svg.appendChild(objectLayer)
  
  // Layer 4: Creatures
  const creatureLayer = renderCreatures(state)
  svg.appendChild(creatureLayer)
  
  // Layer 5: Effects (spells, auras)
  const effectLayer = renderEffects(state)
  svg.appendChild(effectLayer)
  
  // Layer 6: Party markers
  const partyLayer = renderParty(state)
  svg.appendChild(partyLayer)
  
  // Layer 7: UI overlays (selection, hover)
  const uiLayer = renderUI(state)
  svg.appendChild(uiLayer)
}
```

### Interaction Handlers
```javascript
/**
 * Mouse/touch interaction
 */
function setupInteraction(mapElement) {
  let isDragging = false
  let dragStart = {x: 0, y: 0}
  
  // Click/tap on hex
  mapElement.addEventListener('click', function(e) {
    if (isDragging) return
    
    const hex = getHexFromPoint(e.clientX, e.clientY)
    if (hex) {
      DungeonCrawler.HexMap.onHexClick(hex.q, hex.r)
    }
  })
  
  // Hover over hex
  mapElement.addEventListener('mousemove', function(e) {
    if (isDragging) {
      // Pan map
      const dx = e.clientX - dragStart.x
      const dy = e.clientY - dragStart.y
      DungeonCrawler.HexMap.state.viewport.panX += dx
      DungeonCrawler.HexMap.state.viewport.panY += dy
      DungeonCrawler.HexMap.render()
      dragStart = {x: e.clientX, y: e.clientY}
    } else {
      const hex = getHexFromPoint(e.clientX, e.clientY)
      if (hex && (hex.q !== DungeonCrawler.HexMap.state.hoveredHex?.q || hex.r !== DungeonCrawler.HexMap.state.hoveredHex?.r)) {
        DungeonCrawler.HexMap.onHexHover(hex.q, hex.r)
      }
    }
  })
  
  // Start drag
  mapElement.addEventListener('mousedown', function(e) {
    isDragging = true
    dragStart = {x: e.clientX, y: e.clientY}
  })
  
  // End drag
  mapElement.addEventListener('mouseup', function(e) {
    isDragging = false
  })
  
  // Zoom with mouse wheel
  mapElement.addEventListener('wheel', function(e) {
    e.preventDefault()
    const delta = e.deltaY < 0 ? 1.1 : 0.9
    DungeonCrawler.HexMap.state.viewport.zoom *= delta
    DungeonCrawler.HexMap.render()
  })
}

/**
 * Convert screen coordinates to hex
 */
function getHexFromPoint(screenX, screenY) {
  const svg = document.querySelector('#hex-map-svg')
  const pt = svg.createSVGPoint()
  pt.x = screenX
  pt.y = screenY
  
  const svgP = pt.matrixTransform(svg.getScreenCTM().inverse())
  
  return pixelToAxial(svgP.x, svgP.y, DungeonCrawler.HexMap.config.hexSize)
}
```

## API Endpoints

### GET /api/hexmap/{dungeon_level_id}/{party_id}
```
Response:
{
  "hexes": [
    {
      "q": 0,
      "r": 0,
      "terrain": "floor",
      "visibility": "visible",
      "room_id": 5,
      "features": {}
    },
    ...
  ],
  "objects": [
    {
      "hex_q": 2,
      "hex_r": 3,
      "type": "creature",
      "id": 123,
      "name": "Goblin Warrior"
    },
    ...
  ],
  "party_position": {"q": 5, "r": 3},
  "light_sources": [
    {"q": 5, "r": 3, "radius": 4, "type": "torch"}
  ]
}
```

### GET /api/hexmap/{dungeon_level_id}/{party_id}/updates?since={timestamp}
```
Response:
{
  "updated_hexes": [...],
  "updated_objects": [...],
  "removed_objects": [123, 456],
  "party_position": {"q": 6, "r": 3},
  "timestamp": "2026-02-12T10:30:45Z"
}
```

### POST /api/hexmap/{dungeon_level_id}/{party_id}/action
```
Request:
{
  "action": "move",
  "hex": {"q": 6, "r": 4},
  "character_id": 789
}

Response:
{
  "success": true,
  "message": "Moved to (6, 4)",
  "movement_path": [
    {"q": 5, "r": 3},
    {"q": 6, "r": 3},
    {"q": 6, "r": 4}
  ],
  "revealed_hexes": [
    {"q": 7, "r": 4},
    {"q": 7, "r": 5}
  ],
  "updates": {
    "party_position": {"q": 6, "r": 4},
    "fog_of_war_changed": true
  }
}
```

## CSS Styling

### Hex Terrain Types
```css
.hex {
  stroke: #333;
  stroke-width: 1;
  cursor: pointer;
  transition: opacity 0.3s;
}

.hex-floor {
  fill: #d4d4d4;
}

.hex-wall {
  fill: #555;
  cursor: not-allowed;
}

.hex-door {
  fill: #8b4513;
}

.hex-pit {
  fill: #1a1a1a;
}

.hex-water {
  fill: #4682b4;
}

.hex-difficult {
  fill: #d4d4d4;
  stroke-dasharray: 3, 3;
}

/* Visibility states */
.hex-hidden {
  display: none;
}

.hex-revealed {
  opacity: 0.5;
  filter: grayscale(0.7);
}

.hex-visible {
  opacity: 1.0;
}

/* Interaction states */
.hex:hover {
  stroke: #ffcc00;
  stroke-width: 2;
}

.hex-selected {
  stroke: #ff6600;
  stroke-width: 3;
}

.hex-path {
  fill: #90ee90;
  opacity: 0.6;
}

.hex-target {
  fill: #ff6666;
  opacity: 0.6;
}
```

## Performance Optimizations

### Viewport Culling
```javascript
/**
 * Only render hexes in viewport
 */
function getVisibleHexes(state) {
  const viewport = state.viewport
  const hexSize = state.config.hexSize
  const margin = 2  // Extra hexes beyond viewport
  
  // Calculate viewport bounds in hex coords
  const minQ = Math.floor(viewport.centerQ - (window.innerWidth / (hexSize * 1.5) / viewport.zoom)) - margin
  const maxQ = Math.ceil(viewport.centerQ + (window.innerWidth / (hexSize * 1.5) / viewport.zoom)) + margin
  const minR = Math.floor(viewport.centerR - (window.innerHeight / (hexSize * Math.sqrt(3)) / viewport.zoom)) - margin
  const maxR = Math.ceil(viewport.centerR + (window.innerHeight / (hexSize * Math.sqrt(3)) / viewport.zoom)) + margin
  
  const visible = []
  for (const [key, hex] of state.hexes) {
    if (hex.q >= minQ && hex.q <= maxQ && hex.r >= minR && hex.r <= maxR) {
      visible.push(hex)
    }
  }
  return visible
}
```

### Dirty Rect Rendering
```javascript
/**
 * Only re-render changed hexes
 */
function renderDirtyHexes(changedHexes) {
  for (const hex of changedHexes) {
    const element = document.querySelector(`[data-q="${hex.q}"][data-r="${hex.r}"]`)
    if (element) {
      // Update existing element
      element.setAttribute('class', getHexClass(hex))
      element.style.opacity = getHexOpacity(hex)
    } else {
      // Add new element
      const parent = document.querySelector('#terrain-layer')
      parent.appendChild(renderHexSVG(hex, state.config.hexSize))
    }
  }
}
```

### Canvas Fallback
```javascript
/**
 * Switch to Canvas for large maps (>500 hexes)
 */
function renderToCanvas(state) {
  const canvas = document.querySelector('#hex-map-canvas')
  const ctx = canvas.getContext('2d')
  
  // Clear
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  
  // Apply viewport transform
  ctx.save()
  ctx.translate(state.viewport.panX, state.viewport.panY)
  ctx.scale(state.viewport.zoom, state.viewport.zoom)
  
  // Render visible hexes
  const visible = getVisibleHexes(state)
  for (const hex of visible) {
    if (hex.visibility !== 'hidden') {
      drawHexCanvas(ctx, hex, state.config.hexSize)
    }
  }
  
  ctx.restore()
}
```

## Testing Scenarios

1. **Coordinate Conversion**: Test axial ↔ pixel conversions
2. **Fog of War**: Reveal hexes as party moves
3. **Line of Sight**: Walls block visibility
4. **Pathfinding**: Calculate valid movement paths
5. **Performance**: Render 1000 hexes smoothly
6. **Touch Screen**: Mobile interaction works
7. **Zoom/Pan**: Viewport controls smooth
8. **Real-time Updates**: Apply changes without flicker

## Implementation Phases

**Phase 1**: Database schema and services
**Phase 2**: Basic SVG hex rendering
**Phase 3**: Coordinate system and viewport
**Phase 4**: Fog of war and visibility
**Phase 5**: Interaction handlers (click, hover)
**Phase 6**: Movement and pathfinding
**Phase 7**: Real-time updates (polling)
**Phase 8**: Performance optimization
**Phase 9**: Canvas rendering option
**Phase 10**: Mobile optimization

## Open Questions

1. WebSockets vs polling for real-time updates?
2. Pre-generate visibility maps or calculate on-the-fly?
3. Client-side pathfinding or server-side?
4. Animated movement or instant?
5. Support for multiple party members moving separately?
6. Zoom limits (min/max)?
7. Minimap overview?
8. Save viewport position per user?
