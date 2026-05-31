# Implementation Notes — dc-apg-spells

## Commit
- `3a864b002` — feat(dungeoncrawler): implement APG spells catalog (dc-apg-spells)

## What was implemented

### APG_SPELLS constant in CharacterManager.php
- Keyed by tradition (`arcane`, `divine`, `occult`, `primal`), then by level key (`1st`–`9th` + `cantrips`)
- Multi-tradition spells are stored once per tradition key so tradition-based lookups require no join logic
- All entries include: `id`, `name`, `level`, `school`, `cast`, `components`, `traditions`, range/area/targets, `duration`, `save`, `traits`, `description`

### 8 detailed spells with extended metadata

| Spell | Key metadata fields |
|---|---|
| Animate Dead | `summon_level_cap_table` (rank → max creature level per AC Edge-1), `edge_case` note |
| Blood Vendetta | `trigger`, `eligible_caster_note` (must be able to bleed), `save_outcomes` assoc array, `heightened_scaling` |
| Deja Vu | `state_machine` (`record_turn` / `replay_turn`), `stupefied_fallback`, occult-only |
| Final Sacrifice | `minion_killed_note`, `cold_water_override`, `evil_trait_condition`, `temp_control_fails: TRUE`, `heightened_scaling` |
| Heat Metal | `target_types` (unattended/worn_carried/metal_creature), `release_escape_note`, `persistent_fire_bound: TRUE`, `save_outcomes`, `heightened_scaling` |
| Mad Monkeys | `mode_is_fixed_at_cast: TRUE`, `calm_emotions_overlay`, `modes` array (3 full mode objects with per-mode `save_outcomes`) |
| Pummeling Rubble | `save_outcomes` with push distances, `heightened_scaling` |
| Vomit Swarm | `sickened_on_fail: TRUE`, `heightened_scaling` |

### heightened_scaling format
```php
'heightened_scaling' => [
  'step' => 1,           // every N ranks
  'effect' => '...',     // human-readable description of the scaling effect
],
```

### Additional APG spells included
agonizing-despair, bind-undead, cannibalize-magic, chilling-darkness, devour-life, dreaming-potential, enthrall, executioners-eyes, goblin-pox, horrid-wilting, howling-blizzard, humanoid-form, mantle-of-the-magma-heart, never-mind, pillars-of-sand, shadow-blast, shape-stone, spirit-blast, spiritual-anamnesis, summon-construct, summon-elemental, summon-entity, unfathomable-song, vampiric-exsanguination, warp-mind

## Design decisions

### Multi-tradition storage per key
Storing each spell once per tradition key (e.g., `animate-dead` appears under both `arcane` and `divine`) was chosen over a single cross-referenced lookup because:
- Tradition-based queries hit a single key with no secondary lookups
- Consistent with how the existing CRB `SPELLS` constant is structured
- Acceptable memory overhead since the constant is static

### summon_level_cap_table for Animate Dead
A rank-indexed array maps each heighten rank (1–10) to the maximum creature level summoned. This gives the calculator direct table lookup instead of computing from formula, matching AC Edge-1 requirements exactly.

## Verification
- `php -l CharacterManager.php` → no syntax errors
- `cd /var/www/html/dungeoncrawler && ./vendor/bin/drush cr` → Cache rebuild complete
