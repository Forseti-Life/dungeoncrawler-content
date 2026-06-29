# Issue #4: Procedural Dungeon Generation - Design Document

## Overview
Design an AI-driven procedural dungeon generation system for Pathfinder 2E that creates unique, balanced dungeon experiences. The system generates rooms on first entry and persists them forever, with theme-based content, difficulty scaling, AI creature personalities, and proper encounter balancing.

## Core Features

### 1. Generate Once, Persist Forever
- Dungeons are procedurally generated when first accessed
- Once generated, dungeon structure and content are saved permanently
- Players can return to the same dungeon and find it unchanged
- Allows for persistent storytelling and player agency

### 2. Theme-Based Content
- **Goblin Warrens**: Cramped tunnels, tribal markings, trap-heavy
- **Undead Crypts**: Stone tombs, religious iconography, necromantic energy
- **Dragon Lairs**: Grand chambers, treasure hoards, elemental themes
- **Elemental Planes**: Environmental hazards, planar creatures
- **Abandoned Mines**: Industrial equipment, resource nodes, cave-ins
- **Wizards' Towers**: Arcane symbols, magical traps, research labs
- **Bandit Hideouts**: Living quarters, supply caches, escape routes

### 3. Difficulty Scaling
- Dungeon difficulty scales with party average level
- Dynamic encounter budgets based on PF2e XP system
- Boss encounters at appropriate threat levels
- Loot quality matches party level

### 4. AI Creature Personalities
- Creatures have AI-generated motivations and behaviors
- Dialogue options for social encounters
- Tactical variations based on creature intelligence
- Dynamic reactions to party actions

### 5. Encounter Balancing
- Uses Pathfinder 2E XP budget system
- Trivial (40 XP), Low (60 XP), Moderate (80 XP), Severe (120 XP), Extreme (160 XP)
- Balances creature levels against party level
- Considers party composition and strengths

---

## Generation Flow Diagram

```
[Party Enters Dungeon Location]
        ↓
[Check if dungeon exists in database]
        ↓
    [Exists?] ──Yes──> [Load existing dungeon from database]
        ↓                           ↓
       No                           ↓
        ↓                           ↓
[GenerationEngine.initialize()]     ↓
        ↓                           ↓
[Select dungeon theme]              ↓
        ↓                           ↓
[Determine dungeon depth]           ↓
        ↓                           ↓
[Generate dungeon structure] ───────┘
        ↓
[For each level:]
    ↓
[Generate room graph]
    ↓
[Place entrance/exit rooms]
    ↓
[Generate room connections]
    ↓
[Apply theme decorations]
    ↓
[Populate encounters]
    ↓
[Place loot/treasure]
    ↓
[Generate AI personalities]
    ↓
[Save to database]
        ↓
[Return dungeon data to game]
        ↓
[Render dungeon to player]
```

### Detailed Generation Steps

#### Step 1: Initialization
1. Receive party data (average level, composition, location)
2. Determine if dungeon should exist at location
3. Check database for existing dungeon
4. If exists, return cached data; otherwise proceed to generation

#### Step 2: Theme Selection
1. Select theme based on:
   - Geographic location (mountains → mines/dragon lairs)
   - Story context (quest objectives)
   - Random weighted selection
   - Party level (higher levels unlock more exotic themes)

#### Step 3: Structure Generation
1. Determine dungeon depth (1-10 levels based on party level)
2. Calculate total room count per level (8-20 rooms)
3. Generate room graph using modified BSP or cellular automata
4. Ensure connectivity (all rooms reachable from entrance)

#### Step 4: Room Population
1. Assign room types (entrance, exit, treasure, boss, normal)
2. Populate encounters using XP budget system
3. Place treasure appropriate to encounters
4. Add environmental features and hazards

#### Step 5: AI Integration
1. Generate creature motivations via AI prompts
2. Create dialogue trees for intelligent creatures
3. Determine patrol patterns and behaviors
4. Set tactical preferences

#### Step 6: Persistence
1. Save dungeon structure to database
2. Save room details and connections
3. Save encounter data and AI personalities
4. Mark dungeon as "generated" with timestamp

---

## Database Schema

### Table: dungeons
```sql
CREATE TABLE dungeons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    theme ENUM('goblin_warren', 'undead_crypt', 'dragon_lair', 'elemental_plane', 
               'abandoned_mine', 'wizard_tower', 'bandit_hideout', 'ancient_ruins') NOT NULL,
    depth_levels INT UNSIGNED NOT NULL, -- How many levels deep (1-10)
    party_level_generated INT UNSIGNED NOT NULL, -- Party level when generated
    location_x INT NOT NULL, -- World map coordinates
    location_y INT NOT NULL,
    location_h3_index VARCHAR(15), -- H3 geospatial index if using hex map
    description TEXT,
    lore TEXT, -- AI-generated backstory
    difficulty_modifier DECIMAL(3,2) DEFAULT 1.0, -- Multiplier for encounter difficulty
    is_cleared BOOLEAN DEFAULT FALSE,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_visited TIMESTAMP NULL,
    metadata JSON, -- Additional theme-specific data
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    INDEX idx_campaign (campaign_id),
    INDEX idx_location (location_x, location_y),
    INDEX idx_h3 (location_h3_index),
    INDEX idx_theme (theme)
);
```

### Table: dungeon_levels
```sql
CREATE TABLE dungeon_levels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dungeon_id BIGINT UNSIGNED NOT NULL,
    level_number INT UNSIGNED NOT NULL, -- 1 = first level, increases going deeper
    name VARCHAR(255), -- "The Goblin King's Throne Room"
    description TEXT,
    difficulty_rating ENUM('trivial', 'low', 'moderate', 'severe', 'extreme') NOT NULL,
    total_xp_budget INT UNSIGNED NOT NULL,
    room_count INT UNSIGNED NOT NULL,
    has_boss BOOLEAN DEFAULT FALSE,
    environmental_hazards JSON, -- Array of hazard types and locations
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dungeon_id) REFERENCES dungeons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dungeon_level (dungeon_id, level_number),
    INDEX idx_dungeon (dungeon_id)
);
```

### Table: dungeon_rooms
```sql
CREATE TABLE dungeon_rooms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dungeon_level_id BIGINT UNSIGNED NOT NULL,
    room_number INT UNSIGNED NOT NULL,
    room_type ENUM('entrance', 'exit', 'normal', 'treasure', 'boss', 'trap', 
                   'puzzle', 'rest', 'shrine', 'library') NOT NULL,
    name VARCHAR(255), -- "The Hall of Echoing Screams"
    description TEXT, -- AI-generated room description
    size_category ENUM('tiny', 'small', 'medium', 'large', 'huge') NOT NULL,
    dimensions_x INT UNSIGNED, -- Width in 5ft squares
    dimensions_y INT UNSIGNED, -- Length in 5ft squares
    features JSON, -- Arrays of features: [{"type": "pillar", "x": 3, "y": 5}, ...]
    illumination ENUM('bright', 'dim', 'dark') DEFAULT 'dim',
    terrain JSON, -- Difficult terrain, water, etc.
    is_discovered BOOLEAN DEFAULT FALSE,
    is_cleared BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dungeon_level_id) REFERENCES dungeon_levels(id) ON DELETE CASCADE,
    UNIQUE KEY unique_level_room (dungeon_level_id, room_number),
    INDEX idx_level (dungeon_level_id),
    INDEX idx_type (room_type)
);
```

### Table: room_connections
```sql
CREATE TABLE room_connections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dungeon_level_id BIGINT UNSIGNED NOT NULL,
    from_room_id BIGINT UNSIGNED NOT NULL,
    to_room_id BIGINT UNSIGNED NOT NULL,
    connection_type ENUM('door', 'corridor', 'stairs_down', 'stairs_up', 
                         'secret_door', 'portal', 'chute', 'ladder') NOT NULL,
    is_locked BOOLEAN DEFAULT FALSE,
    lock_difficulty INT UNSIGNED, -- DC to pick/break
    is_trapped BOOLEAN DEFAULT FALSE,
    trap_id BIGINT UNSIGNED NULL, -- Reference to trap details
    is_hidden BOOLEAN DEFAULT FALSE, -- For secret doors
    perception_dc INT UNSIGNED, -- DC to notice hidden connection
    description TEXT,
    FOREIGN KEY (dungeon_level_id) REFERENCES dungeon_levels(id) ON DELETE CASCADE,
    FOREIGN KEY (from_room_id) REFERENCES dungeon_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (to_room_id) REFERENCES dungeon_rooms(id) ON DELETE CASCADE,
    INDEX idx_level (dungeon_level_id),
    INDEX idx_from_room (from_room_id),
    INDEX idx_to_room (to_room_id)
);
```

### Table: dungeon_encounters
```sql
CREATE TABLE dungeon_encounters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dungeon_room_id BIGINT UNSIGNED NOT NULL,
    encounter_name VARCHAR(255),
    difficulty ENUM('trivial', 'low', 'moderate', 'severe', 'extreme') NOT NULL,
    xp_value INT UNSIGNED NOT NULL, -- Total XP for this encounter
    creatures JSON, -- Array of creature definitions
    /*
    Example creatures JSON:
    [
        {
            "creature_id": "goblin_warrior",
            "name": "Snaggle the Sneaky",
            "level": 1,
            "count": 3,
            "starting_positions": [[2,3], [4,3], [2,5]],
            "personality": "cowardly",
            "tactics": "hit_and_run",
            "ai_motivation": "Protecting stolen goods"
        }
    ]
    */
    environmental_conditions JSON, -- Hazards, lighting, terrain
    treasure JSON, -- Loot that appears when encounter is defeated
    is_defeated BOOLEAN DEFAULT FALSE,
    turn_order JSON, -- Initiative tracking
    encounter_state JSON, -- HP, conditions, positions of creatures
    ai_conversation_context TEXT, -- For AI-driven NPC interactions
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dungeon_room_id) REFERENCES dungeon_rooms(id) ON DELETE CASCADE,
    INDEX idx_room (dungeon_room_id),
    INDEX idx_defeated (is_defeated)
);
```

### Table: dungeon_loot
```sql
CREATE TABLE dungeon_loot (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dungeon_room_id BIGINT UNSIGNED NULL, -- NULL if in encounter table
    encounter_id BIGINT UNSIGNED NULL,
    loot_type ENUM('gold', 'item', 'equipment', 'consumable', 'gem', 'art') NOT NULL,
    item_name VARCHAR(255),
    item_id VARCHAR(100), -- Reference to item database
    quantity INT UNSIGNED DEFAULT 1,
    value_gp DECIMAL(10,2), -- Gold piece value
    description TEXT,
    is_looted BOOLEAN DEFAULT FALSE,
    looted_by_character_id BIGINT UNSIGNED NULL,
    looted_at TIMESTAMP NULL,
    rarity ENUM('common', 'uncommon', 'rare', 'unique') DEFAULT 'common',
    FOREIGN KEY (dungeon_room_id) REFERENCES dungeon_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (encounter_id) REFERENCES dungeon_encounters(id) ON DELETE CASCADE,
    INDEX idx_room (dungeon_room_id),
    INDEX idx_encounter (encounter_id),
    INDEX idx_looted (is_looted)
);
```

### Table: creature_ai_personalities
```sql
CREATE TABLE creature_ai_personalities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    encounter_id BIGINT UNSIGNED NOT NULL,
    creature_name VARCHAR(255) NOT NULL,
    personality_traits JSON,
    /*
    Example personality_traits JSON:
    {
        "primary_trait": "cunning",
        "secondary_traits": ["greedy", "cowardly"],
        "motivation": "Protecting his treasure hoard from intruders",
        "backstory": "Once a proud goblin chief, now reduced to petty thievery",
        "speech_pattern": "speaks in third person, refers to himself as 'Snaggle'",
        "voice_tone": "high-pitched, whining"
    }
    */
    dialogue_tree JSON, -- Possible conversation branches
    tactical_preferences JSON,
    /*
    Example tactical_preferences JSON:
    {
        "combat_style": "ranged",
        "preferred_targets": ["spellcasters", "weakened_enemies"],
        "retreat_threshold": 0.25, // Flees when at 25% HP
        "uses_environment": true,
        "calls_for_help": true
    }
    */
    relationships JSON, -- Relationships with other creatures in encounter
    ai_context TEXT, -- Additional context for AI prompt generation
    generated_by_ai BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (encounter_id) REFERENCES dungeon_encounters(id) ON DELETE CASCADE,
    INDEX idx_encounter (encounter_id)
);
```

---

## GenerationEngine Service Pseudocode

### Main Generation Service

```php
class DungeonGenerationEngine {
    
    private SchemaLoader $schemaLoader;
    private AIService $aiService;
    private EncounterBalancer $encounterBalancer;
    private RoomConnectionAlgorithm $roomConnector;
    
    /**
     * Generate a complete dungeon
     * 
     * @param Campaign $campaign
     * @param int $locationX - World X coordinate
     * @param int $locationY - World Y coordinate
     * @param int $partyLevel - Average party level
     * @param array $partyComposition - Party details for balancing
     * @return Dungeon
     */
    public function generateDungeon(
        Campaign $campaign,
        int $locationX,
        int $locationY,
        int $partyLevel,
        array $partyComposition
    ): Dungeon {
        
        // Step 1: Check if dungeon already exists
        existingDungeon = database.findDungeon(campaign, locationX, locationY)
        if (existingDungeon) {
            return existingDungeon
        }
        
        // Step 2: Select theme
        theme = this.selectTheme(locationX, locationY, partyLevel)
        
        // Step 3: Determine dungeon depth
        depth = this.calculateDungeonDepth(partyLevel)
        
        // Step 4: Generate dungeon entity
        dungeon = new Dungeon()
        dungeon.campaign_id = campaign.id
        dungeon.name = this.generateDungeonName(theme)
        dungeon.theme = theme
        dungeon.depth_levels = depth
        dungeon.party_level_generated = partyLevel
        dungeon.location_x = locationX
        dungeon.location_y = locationY
        dungeon.lore = this.generateDungeonLore(theme, partyLevel)
        
        database.save(dungeon)
        
        // Step 5: Generate each level
        for (levelNum = 1 to depth) {
            level = this.generateLevel(dungeon, levelNum, partyLevel, partyComposition)
            database.save(level)
        }
        
        return dungeon
    }
    
    /**
     * Select appropriate theme based on location and level
     */
    private function selectTheme(int $x, int $y, int $partyLevel): string {
        // Use location-based selection with weighted randomness
        locationBiomes = getLocationBiome(x, y)
        
        themeWeights = {
            'goblin_warren': locationBiomes.includes('forest', 'hills') ? 30 : 10,
            'undead_crypt': locationBiomes.includes('graveyard', 'ruins') ? 40 : 15,
            'dragon_lair': partyLevel >= 5 ? 20 : 5,
            'abandoned_mine': locationBiomes.includes('mountains') ? 35 : 10,
            'wizard_tower': partyLevel >= 3 ? 15 : 5,
            'bandit_hideout': partyLevel <= 5 ? 25 : 10,
            'ancient_ruins': partyLevel >= 7 ? 25 : 5,
            'elemental_plane': partyLevel >= 10 ? 30 : 0
        }
        
        return weightedRandom(themeWeights)
    }
    
    /**
     * Calculate dungeon depth based on party level
     */
    private function calculateDungeonDepth(int $partyLevel): int {
        if (partyLevel <= 2) return random(1, 2)
        if (partyLevel <= 5) return random(2, 4)
        if (partyLevel <= 10) return random(3, 6)
        if (partyLevel <= 15) return random(4, 8)
        return random(5, 10)
    }
    
    /**
     * Generate a single dungeon level
     */
    private function generateLevel(
        Dungeon $dungeon,
        int $levelNum,
        int $partyLevel,
        array $partyComposition
    ): DungeonLevel {
        
        // Create level entity
        level = new DungeonLevel()
        level.dungeon_id = dungeon.id
        level.level_number = levelNum
        level.name = this.generateLevelName(dungeon.theme, levelNum)
        
        // Determine difficulty (deeper = harder)
        difficultyMultiplier = 1.0 + (levelNum - 1) * 0.15
        level.difficulty_rating = this.calculateDifficultyRating(partyLevel, difficultyMultiplier)
        
        // Calculate XP budget for level
        level.total_xp_budget = this.calculateLevelXPBudget(partyLevel, levelNum)
        
        // Determine room count
        level.room_count = random(8, 20)
        
        // Boss on final level or every 2-3 levels
        level.has_boss = (levelNum == dungeon.depth_levels) || (levelNum % 3 == 0)
        
        database.save(level)
        
        // Generate rooms
        rooms = this.generateRooms(level, dungeon.theme, partyLevel)
        
        // Connect rooms
        connections = this.roomConnector.connectRooms(rooms, level)
        
        // Populate encounters
        this.populateEncounters(level, rooms, partyLevel, partyComposition)
        
        // Place loot
        this.placeLoot(level, rooms, partyLevel)
        
        return level
    }
    
    /**
     * Generate rooms for a level
     */
    private function generateRooms(
        DungeonLevel $level,
        string $theme,
        int $partyLevel
    ): array {
        
        rooms = []
        
        // Generate room graph using BSP or cellular automata
        roomGraph = this.generateRoomGraph(level.room_count)
        
        for (i = 0 to level.room_count - 1) {
            room = new DungeonRoom()
            room.dungeon_level_id = level.id
            room.room_number = i
            
            // First room is always entrance, last is always exit
            if (i == 0) {
                room.room_type = 'entrance'
            } else if (i == level.room_count - 1) {
                room.room_type = 'exit'
            } else if (level.has_boss && i == level.room_count - 2) {
                room.room_type = 'boss'
            } else {
                room.room_type = this.selectRoomType(theme, partyLevel)
            }
            
            // Generate room details using AI
            roomDetails = this.aiService.generateRoomDescription(
                theme, room.room_type, partyLevel
            )
            
            room.name = roomDetails.name
            room.description = roomDetails.description
            room.size_category = this.selectRoomSize(room.room_type)
            room.dimensions_x = random(4, 12)
            room.dimensions_y = random(4, 12)
            room.illumination = this.selectIllumination(theme, room.room_type)
            room.features = this.generateRoomFeatures(theme, room)
            
            database.save(room)
            rooms.push(room)
        }
        
        return rooms
    }
    
    /**
     * Populate encounters in rooms
     */
    private function populateEncounters(
        DungeonLevel $level,
        array $rooms,
        int $partyLevel,
        array $partyComposition
    ): void {
        
        remainingXPBudget = level.total_xp_budget
        
        foreach (rooms as room) {
            // Not every room needs an encounter
            if (room.room_type in ['entrance', 'rest', 'exit']) {
                continue
            }
            
            // Determine encounter difficulty
            if (room.room_type == 'boss') {
                encounterDifficulty = 'severe' // or 'extreme'
            } else {
                encounterDifficulty = this.selectEncounterDifficulty(remainingXPBudget)
            }
            
            // Generate encounter
            encounter = this.encounterBalancer.createEncounter(
                partyLevel,
                partyComposition,
                encounterDifficulty,
                level.dungeon.theme
            )
            
            if (encounter) {
                encounter.dungeon_room_id = room.id
                database.save(encounter)
                
                remainingXPBudget -= encounter.xp_value
                
                // Generate AI personalities for creatures
                this.generateCreaturePersonalities(encounter)
            }
        }
    }
    
    /**
     * Generate AI personalities for creatures in encounter
     */
    private function generateCreaturePersonalities(Encounter $encounter): void {
        
        creatures = json_decode(encounter.creatures)
        
        foreach (creatures as creature) {
            personality = new CreatureAIPersonality()
            personality.encounter_id = encounter.id
            personality.creature_name = creature.name
            
            // Use AI to generate personality
            aiPrompt = this.buildPersonalityPrompt(creature, encounter)
            aiResponse = this.aiService.generatePersonality(aiPrompt)
            
            personality.personality_traits = aiResponse.personality_traits
            personality.dialogue_tree = aiResponse.dialogue_tree
            personality.tactical_preferences = aiResponse.tactical_preferences
            personality.ai_context = aiResponse.context
            
            database.save(personality)
        }
    }
    
    /**
     * Place treasure in rooms
     */
    private function placeLoot(
        DungeonLevel $level,
        array $rooms,
        int $partyLevel
    ): void {
        
        treasureBudget = this.calculateTreasureBudget(partyLevel, level.level_number)
        
        foreach (rooms as room) {
            if (room.room_type in ['treasure', 'boss']) {
                // Generate valuable loot
                loot = this.generateLoot(partyLevel, treasureBudget * 0.3, 'valuable')
                foreach (loot as item) {
                    item.dungeon_room_id = room.id
                    database.save(item)
                }
            } else if (random(1, 100) <= 30) {
                // 30% chance for minor loot in normal rooms
                loot = this.generateLoot(partyLevel, treasureBudget * 0.05, 'minor')
                foreach (loot as item) {
                    item.dungeon_room_id = room.id
                    database.save(item)
                }
            }
        }
    }
}
```

---

## Room Connection Algorithm

### Algorithm: Modified Delaunay Triangulation with Pruning

This algorithm ensures all rooms are connected while maintaining interesting dungeon layouts.

```php
class RoomConnectionAlgorithm {
    
    /**
     * Connect rooms in a dungeon level
     * 
     * @param array $rooms - Array of DungeonRoom objects
     * @param DungeonLevel $level
     * @return array - Array of RoomConnection objects
     */
    public function connectRooms(array $rooms, DungeonLevel $level): array {
        
        connections = []
        
        // Step 1: Assign 2D coordinates to rooms (for graph algorithms)
        roomPositions = this.assignRoomPositions(rooms)
        
        // Step 2: Create Delaunay triangulation
        // This creates a graph where rooms are well-connected
        triangulation = this.delaunayTriangulation(roomPositions)
        
        // Step 3: Extract Minimum Spanning Tree (MST)
        // Ensures all rooms are reachable with minimum connections
        mst = this.kruskalMST(triangulation)
        
        // Step 4: Add back some triangulation edges for loops
        // Makes dungeon less linear, adds shortcuts and alternate paths
        additionalEdges = this.selectAdditionalEdges(triangulation, mst, 0.15) // 15% of removed edges
        
        allEdges = mst.concat(additionalEdges)
        
        // Step 5: Create connection objects
        foreach (edge in allEdges) {
            connection = new RoomConnection()
            connection.dungeon_level_id = level.id
            connection.from_room_id = edge.from_room.id
            connection.to_room_id = edge.to_room.id
            
            // Determine connection type based on room types and theme
            connection.connection_type = this.selectConnectionType(
                edge.from_room,
                edge.to_room,
                level.dungeon.theme
            )
            
            // Randomly add locks, traps, or secret doors
            if (random(1, 100) <= 20) { // 20% chance
                connection.is_locked = true
                connection.lock_difficulty = this.calculateLockDC(level)
            }
            
            if (random(1, 100) <= 15) { // 15% chance
                connection.is_trapped = true
            }
            
            if (random(1, 100) <= 10) { // 10% chance for secret doors
                connection.is_hidden = true
                connection.perception_dc = 15 + level.level_number
            }
            
            database.save(connection)
            connections.push(connection)
        }
        
        // Step 6: Validate connectivity
        if (!this.validateAllRoomsReachable(rooms, connections)) {
            throw new Exception("Room graph is not fully connected!")
        }
        
        return connections
    }
    
    /**
     * Assign 2D positions to rooms for graph algorithms
     */
    private function assignRoomPositions(array $rooms): array {
        positions = []
        
        // Use grid-based positioning with some randomness
        gridSize = ceil(sqrt(count(rooms)))
        
        foreach (rooms as index => room) {
            x = (index % gridSize) * 10 + random(-2, 2)
            y = floor(index / gridSize) * 10 + random(-2, 2)
            
            positions[room.id] = {x: x, y: y, room: room}
        }
        
        return positions
    }
    
    /**
     * Perform Delaunay triangulation on room positions
     */
    private function delaunayTriangulation(array $positions): array {
        // Use standard Delaunay triangulation algorithm
        // Libraries: delaunator (JS), scipy.spatial.Delaunay (Python)
        
        edges = []
        
        // Pseudo-implementation (use actual library in practice)
        triangles = DelaunayTriangulator.triangulate(positions)
        
        foreach (triangle in triangles) {
            // Each triangle creates 3 edges
            edges.push({from: triangle.p1, to: triangle.p2, weight: distance(triangle.p1, triangle.p2)})
            edges.push({from: triangle.p2, to: triangle.p3, weight: distance(triangle.p2, triangle.p3)})
            edges.push({from: triangle.p3, to: triangle.p1, weight: distance(triangle.p3, triangle.p1)})
        }
        
        // Remove duplicate edges
        edges = this.removeDuplicateEdges(edges)
        
        return edges
    }
    
    /**
     * Kruskal's algorithm for Minimum Spanning Tree
     */
    private function kruskalMST(array $edges): array {
        // Sort edges by weight (distance)
        edges.sort((a, b) => a.weight - b.weight)
        
        mst = []
        disjointSet = new DisjointSet()
        
        foreach (edge in edges) {
            // If adding this edge doesn't create a cycle, add it
            if (!disjointSet.connected(edge.from, edge.to)) {
                mst.push(edge)
                disjointSet.union(edge.from, edge.to)
            }
        }
        
        return mst
    }
    
    /**
     * Select additional edges to add back for loops
     */
    private function selectAdditionalEdges(
        array $allEdges,
        array $mst,
        float $percentage
    ): array {
        
        // Get edges not in MST
        removedEdges = array_diff(allEdges, mst)
        
        // Calculate how many to add back
        addBackCount = ceil(count(removedEdges) * percentage)
        
        // Randomly select edges
        shuffle(removedEdges)
        
        return array_slice(removedEdges, 0, addBackCount)
    }
    
    /**
     * Validate all rooms are reachable from entrance
     */
    private function validateAllRoomsReachable(array $rooms, array $connections): bool {
        // Use BFS/DFS from entrance room
        visited = new Set()
        queue = [rooms[0]] // Start with entrance
        
        while (!queue.isEmpty()) {
            current = queue.shift()
            visited.add(current.id)
            
            // Find all rooms connected to current
            foreach (connection in connections) {
                if (connection.from_room_id == current.id && !visited.has(connection.to_room_id)) {
                    nextRoom = rooms.find(r => r.id == connection.to_room_id)
                    queue.push(nextRoom)
                } else if (connection.to_room_id == current.id && !visited.has(connection.from_room_id)) {
                    nextRoom = rooms.find(r => r.id == connection.from_room_id)
                    queue.push(nextRoom)
                }
            }
        }
        
        return visited.size() == rooms.length
    }
}
```

### Alternative Algorithm: Binary Space Partitioning (BSP)

BSP is excellent for creating more organic, cave-like dungeons.

```php
/**
 * Generate dungeon using BSP algorithm
 */
public function generateBSPDungeon(int $width, int $height, int $minRoomSize): array {
    
    // Create root partition (entire dungeon area)
    root = new Partition(0, 0, width, height)
    
    // Recursively split partitions
    this.splitPartition(root, minRoomSize)
    
    // Create rooms in leaf partitions
    rooms = this.createRoomsInLeaves(root)
    
    // Create corridors between sibling partitions
    corridors = this.createCorridors(root)
    
    return {
        rooms: rooms,
        corridors: corridors
    }
}

private function splitPartition(Partition $partition, int $minSize): void {
    // Stop if too small
    if (partition.width < minSize * 2 || partition.height < minSize * 2) {
        return
    }
    
    // Randomly choose horizontal or vertical split
    splitHorizontally = random(0, 1) == 0
    
    if (splitHorizontally) {
        splitY = random(partition.y + minSize, partition.y + partition.height - minSize)
        partition.child1 = new Partition(partition.x, partition.y, partition.width, splitY - partition.y)
        partition.child2 = new Partition(partition.x, splitY, partition.width, partition.height - (splitY - partition.y))
    } else {
        splitX = random(partition.x + minSize, partition.x + partition.width - minSize)
        partition.child1 = new Partition(partition.x, partition.y, splitX - partition.x, partition.height)
        partition.child2 = new Partition(splitX, partition.y, partition.width - (splitX - partition.x), partition.height)
    }
    
    // Recursively split children
    this.splitPartition(partition.child1, minSize)
    this.splitPartition(partition.child2, minSize)
}
```

---

## Encounter Balancing Service

### EncounterBalancer Pseudocode

```php
class EncounterBalancer {
    
    private CreatureDatabase $creatureDb;
    
    /**
     * XP Budget by difficulty (PF2e standard)
     */
    const XP_BUDGETS = [
        'trivial' => 40,
        'low' => 60,
        'moderate' => 80,
        'severe' => 120,
        'extreme' => 160
    ];
    
    /**
     * XP cost by creature level relative to party level
     * 
     * Party Level - Creature Level = XP Cost
     * -4 = 10 XP
     * -3 = 15 XP
     * -2 = 20 XP
     * -1 = 30 XP
     *  0 = 40 XP (same level)
     * +1 = 60 XP
     * +2 = 80 XP
     * +3 = 120 XP
     * +4 = 160 XP
     */
    const XP_BY_LEVEL_DIFF = [
        -4 => 10,
        -3 => 15,
        -2 => 20,
        -1 => 30,
        0 => 40,
        1 => 60,
        2 => 80,
        3 => 120,
        4 => 160
    ];
    
    /**
     * Create a balanced encounter
     * 
     * @param int $partyLevel
     * @param array $partyComposition - Party size and roles
     * @param string $difficulty - 'trivial', 'low', 'moderate', 'severe', 'extreme'
     * @param string $theme - Dungeon theme to select appropriate creatures
     * @return Encounter
     */
    public function createEncounter(
        int $partyLevel,
        array $partyComposition,
        string $difficulty,
        string $theme
    ): Encounter {
        
        xpBudget = self::XP_BUDGETS[$difficulty]
        partySize = count($partyComposition)
        
        // Adjust budget for party size (4 is baseline)
        xpBudget = this.adjustBudgetForPartySize(xpBudget, partySize)
        
        // Select creatures that fit theme
        availableCreatures = this.creatureDb.getCreaturesByTheme($theme)
        
        // Filter creatures within reasonable level range
        levelRange = this.getCreatureLevelRange($partyLevel, $difficulty)
        availableCreatures = availableCreatures.filter(
            c => c.level >= levelRange.min && c.level <= levelRange.max
        )
        
        // Build encounter using knapsack-like algorithm
        creatures = this.selectCreaturesForBudget(
            availableCreatures,
            xpBudget,
            partyLevel,
            partyComposition
        )
        
        // Create encounter object
        encounter = new Encounter()
        encounter.encounter_name = this.generateEncounterName(creatures, theme)
        encounter.difficulty = difficulty
        encounter.xp_value = this.calculateTotalXP(creatures, partyLevel)
        encounter.creatures = json_encode(creatures)
        
        return encounter
    }
    
    /**
     * Adjust XP budget based on party size
     */
    private function adjustBudgetForPartySize(int $budget, int $partySize): int {
        if (partySize < 4) {
            // Reduce budget for smaller parties
            multiplier = partySize / 4.0
            return floor(budget * multiplier)
        } else if (partySize > 4) {
            // Increase budget for larger parties
            multiplier = partySize / 4.0
            return ceil(budget * multiplier)
        }
        
        return budget
    }
    
    /**
     * Get appropriate creature level range for encounter
     */
    private function getCreatureLevelRange(int $partyLevel, string $difficulty): object {
        if (difficulty == 'trivial') {
            return {min: max(1, partyLevel - 4), max: partyLevel - 2}
        } else if (difficulty == 'low') {
            return {min: max(1, partyLevel - 3), max: partyLevel - 1}
        } else if (difficulty == 'moderate') {
            return {min: max(1, partyLevel - 2), max: partyLevel + 1}
        } else if (difficulty == 'severe') {
            return {min: partyLevel - 1, max: partyLevel + 2}
        } else { // extreme
            return {min: partyLevel, max: partyLevel + 4}
        }
    }
    
    /**
     * Select creatures to fill XP budget (knapsack algorithm)
     */
    private function selectCreaturesForBudget(
        array $availableCreatures,
        int $budget,
        int $partyLevel,
        array $partyComposition
    ): array {
        
        selectedCreatures = []
        remainingBudget = budget
        
        // Sort creatures by XP cost descending
        sortedCreatures = this.sortByXPCost(availableCreatures, partyLevel)
        
        // Try to add creatures until budget is filled
        maxAttempts = 100
        attempts = 0
        
        while (remainingBudget > 10 && attempts < maxAttempts) {
            attempts++
            
            // Select a random creature that fits budget
            affordableCreatures = sortedCreatures.filter(
                c => this.getCreatureXPCost(c, partyLevel) <= remainingBudget
            )
            
            if (affordableCreatures.isEmpty()) {
                break // No more creatures fit
            }
            
            // Weighted random selection (prefer appropriate level)
            creature = this.weightedRandomCreature(affordableCreatures, partyLevel)
            
            // Calculate XP cost
            xpCost = this.getCreatureXPCost(creature, partyLevel)
            
            // Add creature to encounter
            selectedCreatures.push({
                creature_id: creature.id,
                name: this.generateCreatureName(creature),
                level: creature.level,
                count: 1, // Can be increased for groups
                xp_cost: xpCost
            })
            
            remainingBudget -= xpCost
        }
        
        // Optimize: if we have similar creatures, group them
        selectedCreatures = this.groupSimilarCreatures(selectedCreatures)
        
        return selectedCreatures
    }
    
    /**
     * Get XP cost of creature based on level difference
     */
    private function getCreatureXPCost(Creature $creature, int $partyLevel): int {
        levelDiff = $partyLevel - $creature.level
        
        // Clamp to -4 to +4 range
        levelDiff = max(-4, min(4, levelDiff))
        
        return self::XP_BY_LEVEL_DIFF[$levelDiff]
    }
}
```

---

## AI Integration Points

### AI Prompt Templates

#### 1. Dungeon Lore Generation

```
PROMPT: generate_dungeon_lore

System: You are a creative Pathfinder 2E game master generating dungeon backstories.

User: Generate a compelling backstory for a {theme} dungeon at party level {partyLevel}.

Theme: {theme}
Party Level: {partyLevel}
Location: {locationDescription}

Provide:
1. A one-paragraph backstory (100-150 words)
2. Why adventurers would explore this dungeon
3. The primary antagonist or threat
4. 2-3 interesting historical facts

Format your response as JSON:
{
    "name": "Dungeon name",
    "backstory": "...",
    "motivation": "Why adventurers care",
    "antagonist": "Primary threat",
    "history": ["fact 1", "fact 2", "fact 3"]
}
```

#### 2. Room Description Generation

```
PROMPT: generate_room_description

System: You are a creative Pathfinder 2E game master describing dungeon rooms.

User: Describe a {roomType} room in a {theme} dungeon.

Theme: {theme}
Room Type: {roomType}
Room Size: {sizeCategory}
Lighting: {illumination}
Party Level: {partyLevel}

Provide:
1. Room name (5-10 words, evocative)
2. Description (50-100 words)
3. Notable features (2-4 items)
4. Sensory details (what players see, hear, smell)
5. Any hints about dangers or treasures

Format as JSON:
{
    "name": "Room name",
    "description": "Full description...",
    "features": ["feature 1", "feature 2", ...],
    "sensory": {
        "visual": "...",
        "auditory": "...",
        "olfactory": "..."
    },
    "hints": ["hint 1", "hint 2"]
}
```

#### 3. Creature Personality Generation

```
PROMPT: generate_creature_personality

System: You are a creative Pathfinder 2E game master creating memorable NPC personalities.

User: Create a personality for a {creatureName} ({creatureType}, level {level}) in a {theme} encounter.

Creature: {creatureName}
Type: {creatureType}
Level: {level}
Theme: {theme}
Intelligence: {intelligence}
Encounter Context: {encounterContext}

Provide:
1. Primary personality trait
2. 2-3 secondary traits
3. Motivation for being here
4. Brief backstory (30-50 words)
5. Speech pattern / mannerisms
6. Tactical preferences in combat

Format as JSON:
{
    "primary_trait": "...",
    "secondary_traits": ["trait1", "trait2"],
    "motivation": "...",
    "backstory": "...",
    "speech_pattern": "...",
    "tactics": {
        "combat_style": "melee|ranged|support|mixed",
        "preferred_targets": ["target_type1", "target_type2"],
        "retreat_threshold": 0.25,
        "uses_environment": true|false,
        "calls_for_help": true|false
    }
}
```

#### 4. Dialogue Tree Generation

```
PROMPT: generate_dialogue_tree

System: You are a creative Pathfinder 2E game master writing NPC dialogue.

User: Create a dialogue tree for {creatureName} who {personality}.

Creature: {creatureName}
Personality: {personalityTraits}
Motivation: {motivation}
Intelligence: {intelligence}
Party Relationship: {relationship} (hostile/neutral/friendly)

Provide 3-5 dialogue branches with player options:
- Greeting/Introduction
- Information gathering
- Negotiation
- Intimidation
- Combat taunt

Format as JSON:
{
    "greeting": {
        "npc_says": "...",
        "player_options": [
            {"text": "...", "leads_to": "..."},
            {"text": "...", "leads_to": "..."}
        ]
    },
    "branches": [...]
}
```

#### 5. Encounter Name Generation

```
PROMPT: generate_encounter_name

System: You are a creative Pathfinder 2E game master naming encounters.

User: Create an evocative encounter name for a group containing: {creatureList}

Theme: {theme}
Difficulty: {difficulty}
Creatures: {creatureList}

Provide a memorable encounter name (5-10 words) that captures the essence and threat level.

Examples:
- "The Goblin King's Throne Guard"
- "Ambush at the Collapsed Bridge"
- "The Wailing Spirits of Tomb Level 3"

Just respond with the name, no JSON needed.
```

---

## Persistence Strategy

### Save/Load Flow

```
┌─────────────────────────────────────────────┐
│         Dungeon Generation Request          │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
         ┌────────────────┐
         │ Check Database │
         └────────┬───────┘
                  │
         ┌────────┴────────┐
         │                 │
      [Exists]          [Not Exists]
         │                 │
         │                 ▼
         │      ┌──────────────────┐
         │      │ Generate Dungeon │
         │      └────────┬─────────┘
         │               │
         │               ▼
         │      ┌─────────────────┐
         │      │   Save to DB    │
         │      │  ┌───────────┐  │
         │      │  │ dungeons  │  │
         │      │  ├───────────┤  │
         │      │  │ levels    │  │
         │      │  ├───────────┤  │
         │      │  │ rooms     │  │
         │      │  ├───────────┤  │
         │      │  │connections│  │
         │      │  ├───────────┤  │
         │      │  │encounters │  │
         │      │  ├───────────┤  │
         │      │  │ loot      │  │
         │      │  ├───────────┤  │
         │      │  │ ai_data   │  │
         │      │  └───────────┘  │
         │      └────────┬────────┘
         │               │
         └───────────────┘
                  │
                  ▼
         ┌────────────────┐
         │ Return Dungeon │
         │      Data      │
         └────────────────┘
```

### State Management

#### Dungeon State Transitions

```
NEVER_VISITED → DISCOVERED → IN_PROGRESS → CLEARED → REVISITABLE
```

**State Details:**

1. **NEVER_VISITED**
   - Dungeon location exists but not yet generated
   - Database: NULL entry

2. **DISCOVERED**
   - Party has reached dungeon entrance
   - Dungeon structure generated and saved
   - No rooms explored yet

3. **IN_PROGRESS**
   - Party has entered dungeon
   - Some rooms explored, some encounters defeated
   - State saved in `dungeon_rooms.is_discovered` and `dungeon_encounters.is_defeated`

4. **CLEARED**
   - All encounters defeated
   - Boss defeated (if applicable)
   - Dungeon marked as `dungeons.is_cleared = TRUE`

5. **REVISITABLE**
   - Party can return to cleared dungeon
   - Structure remains the same
   - Encounters do not respawn (unless GM manually resets)

### Caching Strategy

```php
class DungeonCache {
    
    /**
     * Cache hot dungeon data in memory for active sessions
     */
    private array $activeDungeons = [];
    
    /**
     * Get dungeon from cache or database
     */
    public function getDungeon(int $dungeonId): Dungeon {
        // Check cache first
        if (isset($this->activeDungeons[$dungeonId])) {
            return $this->activeDungeons[$dungeonId];
        }
        
        // Load from database
        dungeon = database.findDungeon($dungeonId)
        
        // Eagerly load related data
        dungeon.levels = database.getDungeonLevels($dungeonId)
        
        foreach (dungeon.levels as level) {
            level.rooms = database.getLevelRooms(level.id)
            level.encounters = database.getLevelEncounters(level.id)
        }
        
        // Cache it
        $this->activeDungeons[$dungeonId] = dungeon
        
        return dungeon
    }
    
    /**
     * Update dungeon state and sync to database
     */
    public function updateDungeonState(int $dungeonId, array $stateChanges): void {
        dungeon = this.getDungeon($dungeonId)
        
        // Apply state changes
        foreach (stateChanges as change) {
            if (change.type == 'room_discovered') {
                room = dungeon.findRoom(change.roomId)
                room.is_discovered = true
                database.update('dungeon_rooms', change.roomId, {is_discovered: true})
            }
            
            if (change.type == 'encounter_defeated') {
                encounter = dungeon.findEncounter(change.encounterId)
                encounter.is_defeated = true
                database.update('dungeon_encounters', change.encounterId, {is_defeated: true})
            }
            
            if (change.type == 'loot_taken') {
                loot = dungeon.findLoot(change.lootId)
                loot.is_looted = true
                loot.looted_by_character_id = change.characterId
                loot.looted_at = now()
                database.update('dungeon_loot', change.lootId, {
                    is_looted: true,
                    looted_by_character_id: change.characterId,
                    looted_at: now()
                })
            }
        }
        
        // Update cache
        $this->activeDungeons[$dungeonId] = dungeon
    }
    
    /**
     * Invalidate cache when session ends
     */
    public function clearCache(int $dungeonId): void {
        unset($this->activeDungeons[$dungeonId])
    }
}
```

---

## Integration with Existing Systems

### 1. Integration with Character System

```php
// When party enters dungeon
function enterDungeon(Campaign $campaign, Party $party, int $locationX, int $locationY): Dungeon {
    
    // Calculate party average level
    partyLevel = party.getAverageLevel()
    partyComposition = party.getComposition()
    
    // Get or generate dungeon
    dungeon = DungeonGenerationEngine.generateDungeon(
        campaign,
        locationX,
        locationY,
        partyLevel,
        partyComposition
    )
    
    // Update party location
    party.current_dungeon_id = dungeon.id
    party.current_level = 1
    party.current_room_id = dungeon.levels[0].rooms[0].id // Entrance room
    
    database.save(party)
    
    return dungeon
}
```

### 2. Integration with Combat System

```php
// When encounter is triggered
function triggerEncounter(Party $party, Room $room): Combat {
    
    // Get encounter for room
    encounter = database.findEncounterByRoom(room.id)
    
    if (!encounter || encounter.is_defeated) {
        return null // No encounter or already defeated
    }
    
    // Load creature AI personalities
    aiPersonalities = database.getCreaturePersonalities(encounter.id)
    
    // Initialize combat
    combat = new Combat()
    combat.encounter_id = encounter.id
    combat.party = party
    combat.creatures = json_decode(encounter.creatures)
    combat.ai_personalities = aiPersonalities
    
    // Roll initiative
    combat.rollInitiative()
    
    return combat
}
```

### 3. Integration with H3 Geolocation System

```php
// Map dungeon entrances to H3 hexes
function placeDungeonOnHexMap(Dungeon $dungeon): void {
    
    // Get H3 index for dungeon location
    h3Index = H3Geolocation.getH3Index(
        dungeon.location_x,
        dungeon.location_y,
        resolution: 9 // Adjust based on map scale
    )
    
    // Update dungeon with H3 index
    dungeon.location_h3_index = h3Index
    database.save(dungeon)
    
    // Add dungeon marker to hex map
    HexMap.addMarker(h3Index, {
        type: 'dungeon',
        dungeon_id: dungeon.id,
        icon: this.getDungeonIcon(dungeon.theme),
        name: dungeon.name,
        difficulty: dungeon.calculateAverageDifficulty()
    })
}
```

---

## Testing & Validation

### Unit Tests

```php
class DungeonGenerationEngineTest extends TestCase {
    
    public function testDungeonGeneration() {
        engine = new DungeonGenerationEngine()
        campaign = factory(Campaign::class)->create()
        
        dungeon = engine.generateDungeon(
            campaign,
            locationX: 100,
            locationY: 200,
            partyLevel: 5,
            partyComposition: [...]
        )
        
        // Assert dungeon was created
        $this->assertNotNull(dungeon.id)
        $this->assertEquals(campaign.id, dungeon.campaign_id)
        $this->assertGreaterThan(0, dungeon.depth_levels)
        
        // Assert levels were created
        levels = database.getDungeonLevels(dungeon.id)
        $this->assertCount(dungeon.depth_levels, levels)
        
        // Assert rooms exist and are connected
        foreach (levels as level) {
            rooms = database.getLevelRooms(level.id)
            $this->assertGreaterThan(0, count(rooms))
            
            connections = database.getLevelConnections(level.id)
            $this->assertTrue(this.validateConnectivity(rooms, connections))
        }
    }
    
    public function testEncounterBalancing() {
        balancer = new EncounterBalancer()
        
        encounter = balancer.createEncounter(
            partyLevel: 5,
            partyComposition: [...],
            difficulty: 'moderate',
            theme: 'goblin_warren'
        )
        
        // Assert encounter XP is correct
        $this->assertGreaterThanOrEqual(80, encounter.xp_value)
        $this->assertLessThanOrEqual(100, encounter.xp_value) // Allow 20% variance
        
        // Assert creatures are appropriate level
        creatures = json_decode(encounter.creatures)
        foreach (creatures as creature) {
            $this->assertGreaterThanOrEqual(3, creature.level) // Within range
            $this->assertLessThanOrEqual(7, creature.level)
        }
    }
    
    public function testRoomConnectivity() {
        connector = new RoomConnectionAlgorithm()
        rooms = factory(DungeonRoom::class, 15)->create()
        level = factory(DungeonLevel::class)->create()
        
        connections = connector.connectRooms(rooms, level)
        
        // Assert all rooms are reachable
        $this->assertTrue(connector.validateAllRoomsReachable(rooms, connections))
        
        // Assert minimum connections (N-1 for spanning tree)
        $this->assertGreaterThanOrEqual(count(rooms) - 1, count(connections))
    }
}
```

---

## Performance Considerations

### 1. Generation Time
- **Target**: < 5 seconds for full dungeon generation
- **Optimization**:
  - Pre-compute room templates
  - Cache creature databases
  - Use efficient graph algorithms
  - Parallelize AI prompt generation

### 2. Database Size
- **Estimate**: ~50KB per dungeon level
- **A 5-level dungeon**: ~250KB
- **Mitigation**:
  - Compress JSON fields
  - Archive old/unused dungeons
  - Use database partitioning for large campaigns

### 3. Memory Usage
- **Cache active dungeons only** (dungeons being explored)
- **Lazy load** room details as party explores
- **Unload** dungeon data when party leaves

---

## Future Enhancements

### Phase 2 Features
1. **Dynamic Dungeon Evolution**
   - Dungeons change over time if not cleared
   - Creatures reproduce or call reinforcements
   - Environmental changes (flooding, collapse)

2. **Procedural Puzzle Generation**
   - Logic puzzles
   - Environmental puzzles
   - Trap sequences

3. **Dungeon Factions**
   - Multiple creature groups with different goals
   - Faction reputation system
   - Dynamic alliances and betrayals

4. **Themed Event Rooms**
   - Shrines with divine tests
   - Arcane laboratories with magical experiments
   - Trading posts with neutral NPCs

5. **Megadungeons**
   - Persistent dungeons with 20+ levels
   - Multiple entrances/exits
   - Cross-level connections
   - Settlement areas (safe zones)

---

## API Endpoints (for frontend integration)

### GET /api/dungeons/{dungeonId}
```
Response:
{
    "id": 42,
    "name": "The Goblin King's Warren",
    "theme": "goblin_warren",
    "depth_levels": 3,
    "party_level_generated": 5,
    "description": "...",
    "lore": "...",
    "is_cleared": false,
    "levels": [...]
}
```

### POST /api/dungeons/generate
```
Request:
{
    "campaign_id": 1,
    "location_x": 100,
    "location_y": 200,
    "party_level": 5,
    "party_composition": [...]
}

Response:
{
    "dungeon": {...},
    "generation_time_ms": 3420
}
```

### GET /api/dungeons/{dungeonId}/levels/{levelNumber}
```
Response:
{
    "level_number": 1,
    "name": "The Entrance Hall",
    "description": "...",
    "difficulty_rating": "moderate",
    "rooms": [...],
    "connections": [...]
}
```

### POST /api/dungeons/{dungeonId}/state
```
Request:
{
    "changes": [
        {"type": "room_discovered", "roomId": 5},
        {"type": "encounter_defeated", "encounterId": 12},
        {"type": "loot_taken", "lootId": 45, "characterId": 7}
    ]
}

Response:
{
    "success": true,
    "dungeon_state": {...}
}
```

---

## Conclusion

This design provides a comprehensive, AI-driven procedural dungeon generation system for Pathfinder 2E that:

✅ Generates dungeons once and persists them forever  
✅ Provides theme-based content with rich variety  
✅ Scales difficulty appropriately with party level  
✅ Creates memorable AI-driven creature personalities  
✅ Balances encounters using official PF2e XP budgets  
✅ Ensures all rooms are connected via graph algorithms  
✅ Integrates with existing systems (characters, combat, hex maps)  

**Next Steps:**
1. Review and approve this design
2. Create database migrations for new tables
3. Implement GenerationEngine service
4. Integrate AI prompt system
5. Build frontend dungeon renderer
6. Comprehensive testing with various party levels and themes
