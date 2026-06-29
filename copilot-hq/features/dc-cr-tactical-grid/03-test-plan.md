# Test Plan: dc-cr-tactical-grid

## Coverage summary
- AC items: ~18 (grid data model, movement/reach, areas of effect, flanking, cover, difficult terrain)
- Test cases: 13 (TC-GRD-01–13)
- Suites: playwright (encounter)
- Security: Grid positions server-validated; no client-side position spoofing

---

## TC-GRD-01 — Grid data model: 5-ft squares, (row, column) coordinates
- Description: Combat grid uses 5-ft squares identified by (row, column); creature positions stored as grid_position
- Suite: playwright/encounter
- Expected: grid.square_size = 5 ft; each creature has grid_position {row, col}; initialized at encounter start
- AC: AC-001

## TC-GRD-02 — Large+ creatures: all occupied squares tracked
- Description: Large+ creatures occupy multiple squares; all squares tracked in creature record
- Suite: playwright/encounter
- Expected: large_creature.occupied_squares = array of {row, col}; all squares blocked for movement through
- AC: AC-001

## TC-GRD-03 — Stride: 5-ft increments up to Speed; difficult terrain costs 2 squares per square
- Description: Stride movement in 5-ft increments; difficult terrain: 2 ft of movement per 5-ft square entered
- Suite: playwright/encounter
- Expected: movement_cost per square = 5 ft normal, 10 ft in difficult; total movement ≤ Speed; excess blocked
- AC: AC-002

## TC-GRD-04 — Melee reach: standard 5 ft (adjacent) and reach weapon 10 ft (2 squares)
- Description: Standard melee reach = adjacent square (5 ft); reach weapons extend to 10 ft (2 squares)
- Suite: playwright/encounter
- Expected: melee attack with standard weapon checks adjacent squares only; reach weapon checks 2-square range
- AC: AC-002

## TC-GRD-05 — Attack of Opportunity trigger: identified when moving away from threatener
- Description: When creature moves away from a threatening enemy, system identifies if AoO reaction can trigger
- Suite: playwright/encounter
- Expected: creature moves from threatened square → AoO_available flag set for relevant enemy; reaction prompt shown
- AC: AC-002

## TC-GRD-06 — Burst area: all squares within radius from origin point
- Description: Burst spell origin point set; all squares within burst radius included as targets
- Suite: playwright/encounter
- Expected: burst origin selected; target squares = all within radius; edge squares determined by center-to-center measurement
- AC: AC-003

## TC-GRD-07 — Cone area: 90-degree angle from caster square
- Description: Cone from caster square in chosen direction; 90-degree spread; all squares in cone affected
- Suite: playwright/encounter
- Expected: cone direction selected; affected area = 90° from origin; squares outside 90° not included
- AC: AC-003

## TC-GRD-08 — Line area: each square along line path checked
- Description: Line spell drawn from caster in chosen direction; each square on path checked for occupying creatures
- Suite: playwright/encounter
- Expected: line drawn; each occupied square on path targeted; no wraparound; stops at range limit
- AC: AC-003

## TC-GRD-09 — Flanking: two allies on opposite sides grant +2 circumstance to attacks
- Description: Two allies directly opposite (same row or column center) grant flanking (+2 circumstance to attack rolls)
- Suite: playwright/encounter
- Expected: ally1 and ally2 on opposite sides → attacker.flanking_bonus = +2 circ; single ally = no bonus
- AC: AC-004

## TC-GRD-10 — Flanking with different sizes: primary square used for position check
- Description: For creatures of different sizes, flanking uses the creature's primary square
- Suite: playwright/encounter
- Expected: large creature: flanking check uses primary (anchor) square; smaller attackers position relative to anchor
- AC: AC-004

## TC-GRD-11 — Cover: standard (+2 AC/Reflex) and greater cover (+4 AC/Reflex)
- Description: Creature/terrain between attacker and target grants cover; greater cover (+4) for more complete blocking
- Suite: playwright/encounter
- Expected: cover assessed on attack; standard cover = +2 AC/Reflex; greater cover = +4; no cover = no bonus
- AC: AC-005

## TC-GRD-12 — Prone + cover interaction: handled correctly
- Description: Prone condition interacts correctly with cover rules
- Suite: playwright/encounter
- Expected: prone condition + cover = correct combined modifier (per rules); neither overrides the other incorrectly
- AC: AC-005

## TC-GRD-13 — Hazardous terrain: triggers damage on entry
- Description: Hazardous terrain square triggers terrain damage when creature enters
- Suite: playwright/encounter
- Expected: creature enters hazardous square → terrain_damage fires; server-validated (not client-side)
- AC: AC-006, Security AC
