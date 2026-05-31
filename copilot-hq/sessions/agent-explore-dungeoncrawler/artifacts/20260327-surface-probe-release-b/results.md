# Surface Probe: 20260326-dungeoncrawler-release-b

- Probe date: 2026-03-27T12:36Z
- Release: 20260326-dungeoncrawler-release-b
- Feature shipped: dc-cr-clan-dagger
- BASE_URL: http://localhost:8080
- Probe type: manual curl

## Results

| Route | Expected | Actual | Status |
|---|---|---|---|
| `/` | 200 | 200 | ✅ PASS |
| `/dungeoncrawler/traits` | 403 (auth-gated) | 403 | ✅ PASS |
| `/api/character/1/traits` | 403 (auth-gated) | 403 | ✅ PASS |
| `/ancestries` | 200 | 200 | ✅ PASS |
| `/ancestries/dwarf` | 200 | 200 | ✅ PASS |
| `/equipment` | 200 | 200 | ✅ PASS (30 items) |
| `/equipment/clan-dagger` | 200 (shipped item) | 404 | ❌ FAIL |
| `/classes/dwarf/starting-equipment` | 200 (route defined in routing.yml) | 404 | ❌ FAIL |
| `/ancestries/dwarf/starting-equipment` | 404 (no such route) | 404 | ℹ️ expected |
| `/dungeoncrawler/character/create` | 404 (pending dev) | 404 | ✅ expected |
| `/dungeoncrawler/character/leveling` | 404 (pending dev) | 404 | ✅ expected |

## Equipment catalog check

- `/equipment` returns 30 items (club, dagger, spear, staff, crossbow, longsword, shortsword, rapier, shortbow, battleaxe, greataxe, longbow, mace, leather-armor, studded-leather, chain-shirt, scale-mail, breastplate, hide-armor, full-plate, backpack, bedroll, rope, torch, rations-week, waterskin, chalk, flint-steel, lantern-hooded, oil-pint)
- `clan-dagger` is NOT present in the catalog.

## Gaps identified

### GAP-PROBE-26B-01: clan-dagger absent from equipment catalog
- Feature `dc-cr-clan-dagger` is marked `status: shipped` in `features/dc-cr-clan-dagger/feature.md`.
- `/equipment/clan-dagger` returns 404.
- `clan-dagger` does not appear in the `/equipment` catalog (30 items, none ancestry-specific).
- The feature brief requires: content entity `equipment`, `id: clan-dagger`, `type: weapon (dagger)`, `cost: 0`, `flags: [ancestral, unsellable_strong_taboo]`.
- The dev verification method was `drush ev` — this may not have checked the REST endpoint.
- Severity: medium — item is inaccessible via API; character creation auto-grant may or may not work.

### GAP-PROBE-26B-02: `/classes/dwarf/starting-equipment` returns 404
- Route is defined in `dungeoncrawler_content.routing.yml` as `dungeoncrawler_content.api.class_starting_equipment: /classes/{id}/starting-equipment`.
- `/classes/dwarf/starting-equipment` returns 404 (not 403).
- This may indicate the route controller is unimplemented or the `dwarf` slug is not a valid class ID (it is an ancestry).
- May be out of scope for dc-cr-clan-dagger (clan-dagger is ancestry-granted, not class-granted), but the gap is worth noting as a potential dead route.

## Summary
- 2 failures: `clan-dagger` absent from live equipment catalog; starting-equipment route 404.
- All prior shipped routes confirmed stable (traits, ancestries, character creation/leveling still expected 404).
