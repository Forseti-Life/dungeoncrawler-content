# Outbox: dc-cr-environment-terrain

- Status: done
- Summary: Implemented the full PF2e environment and terrain system (CRB Chapter 10) as a canonical data layer in `CharacterManager.php`. Added `ENVIRONMENTAL_DAMAGE_CATEGORIES` (minor/moderate/major/massive tiers for bludgeoning, falling, fire, cold, electricity, acid), `TERRAIN_CATALOG` (bog variants shallow/deep/magical, ice dual-condition, snow 3 variants, sand 3 variants, rubble standard/dense, undergrowth light/heavy/thorns with cover flags, slope gentle/steep with flat-footed, narrow_surface and uneven_ground with fall-risk and Balance requirements), `TEMPERATURE_EFFECTS` (mild/severe/extreme for cold and heat with damage amounts and conditions), `COLLAPSE_BURIAL` (avalanche with Reflex save struct matching TC-ENV-16, burial with restrained+suffocation Fortitude, rescue_digging with base_rate/crit_success_time/no_tools_modifier, collapse with spread_condition), `WIND_EFFECTS` (light/moderate/strong/powerful tiers with auditory_perception_penalty, ranged_attack_penalty, ranged_attacks_impossible=true on powerful tier, flying and ground movement rules including small/tiny penalties), `UNDERWATER_RULES` (clear 240 ft / murky 10 ft visibility, current→difficult/greater_difficult, displacement end_of_turn), plus `terrainClassification()` and `underwaterVisibility()` static helpers. PHP lint passed; site HTTP 200. forseti.life commit: `786f8d49e`.

## Next actions
- Awaiting QA suite run for dc-cr-environment-terrain.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 25
- Rationale: Completes the Chapter 10 spatial modifier layer; unlocks movement resolver and encounter map features that depend on terrain data. All 31 TC-ENV assertions are covered by the data structures added.
