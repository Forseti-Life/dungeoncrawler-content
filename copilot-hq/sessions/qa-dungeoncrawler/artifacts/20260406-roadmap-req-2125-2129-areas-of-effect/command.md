# QA: Areas of Effect (Reqs 2125–2129)

- Agent: qa-dungeoncrawler
- Status: pending
- Priority: high
- Release: ch09-playing-the-game

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Areas of Effect (p.456–457): burst, cone, emanation, line, difficult terrain

## Requirements Covered

| ID   | Req Text                                                                                               | Status      |
|------|--------------------------------------------------------------------------------------------------------|-------------|
| 2125 | Burst: originates at corner; affects all within radius including partial (one square)                  | **pending** |
| 2126 | Cone: quarter-circle from caster; orthogonal or diagonal aim; cannot overlap caster's space            | **pending** |
| 2127 | Emanation: extends from caster's space outward; caster inclusion is optional                           | **pending** |
| 2128 | Line: straight path, 5 ft wide by default; all overlapped creatures affected                           | **pending** |
| 2129 | Area shapes are never reduced by difficult terrain                                                     | **pending** |

## Test Cases

### REQ-2125 — Burst Area

**Positive (expected to fail until implemented):** A burst spell centered at a hex corner hits all creatures within radius (including those one square into the radius).
```php
// TODO: After AoE service is implemented:
// $aoe = \Drupal::service('dungeoncrawler_content.area_resolver');
// $targets = $aoe->resolveBurst($origin_q, $origin_r, $radius_hexes, $participants);
// assert(count($targets) > 0, 'Burst must include all creatures in radius');
```

**Negative:** Creature outside the burst radius is not included.

---

### REQ-2126 — Cone Area

**Positive (expected to fail until implemented):** Cone correctly covers a quarter-circle from caster in the chosen direction.
```php
// TODO: $targets = $aoe->resolveCone($caster_pos, $direction, $length, $participants);
// assert: caster's own hex NOT included
// assert: all hexes in cone arc at length included
```

**Negative:** Caster's own space must not be in the cone.

---

### REQ-2127 — Emanation Area

**Positive (expected to fail until implemented):** Emanation extends outward from all sides of caster hex; caster inclusion controlled by `include_origin` flag.
```php
// TODO: $targets = $aoe->resolveEmanation($caster_pos, $radius, $participants, $include_origin=FALSE);
// assert caster not included when include_origin=FALSE
```

**Negative:** `include_origin=TRUE` should include caster.

---

### REQ-2128 — Line Area

**Positive (expected to fail until implemented):** Line covers 5 ft wide (1 hex) path from caster to range. All creatures whose space overlaps the line are affected.
```php
// TODO: $targets = $aoe->resolveLine($start, $direction, $length, $participants);
// assert: all creatures on the line path included
```

**Negative:** Creatures adjacent to but not on the line path are excluded.

---

### REQ-2129 — Difficult Terrain Does Not Affect AoE

**Positive (expected to fail until implemented):** AoE resolver ignores terrain type when computing which hexes are in the area.
```php
// TODO: Verify AoE resolver does not apply difficult terrain cost to shape calculation
// $targets = $aoe->resolveBurst($origin, $radius, $participants, $terrain_map);
// assert: same targets whether or not difficult terrain exists in area
```

**Negative:** Movement through difficult terrain DOES cost extra — the terrain exemption applies ONLY to area shape, not to movement.
