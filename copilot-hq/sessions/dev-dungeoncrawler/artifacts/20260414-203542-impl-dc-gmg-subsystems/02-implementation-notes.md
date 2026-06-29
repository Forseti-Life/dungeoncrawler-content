# dc-gmg-subsystems — Implementation Notes

## Commit
`22621f01f`

## Files Created
| File | Purpose |
|---|---|
| `src/Service/SubsystemService.php` | Subsystem session management with domain logic for all 8 types |
| `src/Service/VariantRulesService.php` | Variant rule flags, compatibility checks, ABP/PWL tables |
| `src/Controller/SubsystemController.php` | 19 REST endpoints |

## Files Modified
| File | Change |
|---|---|
| `dungeoncrawler_content.routing.yml` | +19 routes |
| `dungeoncrawler_content.services.yml` | `dungeoncrawler_content.subsystem` + `dungeoncrawler_content.variant_rules` |
| `dungeoncrawler_content.install` | Hook 10047: `dc_subsystem_session` + `dc_variant_rule` tables |

## DB Tables Created (hook 10047)
- `dc_subsystem_session` — stores subsystem session per campaign; `subsystem_type` VARCHAR(32), `status` (active/resolved), `progress_state` JSON; indexed by campaign/status/type
- `dc_variant_rule` — per-campaign variant rule feature flags; unique on (campaign_id, rule_name); `config_json` for custom overrides (e.g. ABP table overrides)

## Subsystem Types Implemented

| Type | Win Condition | Fail Condition |
|---|---|---|
| `chase` | Catcher reaches stage 0 OR escapee reaches max_stages | Party's opposing side reaches threshold |
| `influence` | All NPCs reach their influence threshold | Any NPC exceeded resistance limit → hostile |
| `research` | Cumulative points ≥ target_tier_points | rounds_used ≥ round_limit AND points < target |
| `infiltration` | objective_reached = true | awareness ≥ detection_threshold |
| `reputation` | reputation ≥ win_threshold | reputation ≤ fail_threshold |
| `vehicle` | destination_reached AND vehicle_hp > 0 | vehicle_hp ≤ 0 |
| `hexploration` | All target hexes explored | party_incapacitated = true |
| `duel` | opponent_hp ≤ 0 (combat) / pc_score ≥ win_score (skill/honor) | pc_hp ≤ 0 (combat) / pc_score ≤ fail_score |

## Turn Processing Design
- `takeTurn()` dispatches to a private per-type processor then appends a turn_log entry
- After state mutation, `checkWinCondition()` and `checkFailCondition()` are evaluated and returned in the response so callers can immediately determine if the subsystem concluded
- Progress state is server-side only; clients send `action` payloads; no state accepted from client except action parameters

## Influence Domain Notes
- Preferred skills: +1 on success, +2 (crit_success × 2) on critical success
- Opposed skills: -1 on failure, -2 on critical failure
- resistance_limit defaults to -3; exceeding triggers `hostile = TRUE` → fail condition

## Research Domain Notes
- `cap_per_entry` (default 10) prevents infinite grinding on a single topic
- Tier unlock thresholds stored in `tier_thresholds` within progress_state (configurable via initiate config)
- `unlocked_tiers` recalculated on every turn

## Vehicle Domain Notes
- HP thresholds: <50% HP → 25% speed reduction + maneuverability_penalty +2; <25% HP → 50% speed reduction + maneuverability_penalty +4
- Collision damage = ceiling((base_size_dice × speed) / 30)
- `passenger_action` turn type passes through without touching vehicle state

## Variant Rules Design
- ABP table is a PHP constant; campaign-specific overrides stored in `config_json['abp_overrides']` and merged at read time
- `checkCompatibility()` checks active rules for the campaign against `COMPATIBILITY_NOTES`; returns warnings (not hard blocks) for ABP+PWL combination
- PWL `getPwlBonus()` always appends a note that NPC DCs must also strip the level component — this is the most critical edge case per AC

## CSRF / Permission Split
- `administer dungeoncrawler content`: subsystem initiate, resolve, variant rule set
- `access dungeoncrawler characters`: subsystem turn (player-scoped), all reads
- All POST/PATCH routes use `_csrf_request_header_mode: TRUE`

## KB references
- No prior lesson found for subsystem framework; this is the first implementation.
