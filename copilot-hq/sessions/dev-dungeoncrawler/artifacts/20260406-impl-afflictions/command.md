# Implement: Afflictions System (Poison, Disease, Curse, Virulent)

- Release: ch09-playing-the-game
- Feature: dc-cr-afflictions
- Status: pending
- Priority: high
- Agent: dev-dungeoncrawler

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Afflictions (p.457–458)

## Requirements: 2135–2144 (all pending)

## What Exists
- `ConditionManager.php`: conditions exist but no staged affliction tracking
- No `AfflictionManager` or affliction-specific service exists
- Persistent damage is tracked in `combat_conditions` table but not staged

## Required Implementation

### Create `AfflictionManager.php`

Manages multi-stage afflictions (poison, disease, curse, radiation).

Key methods:
```php
// Apply affliction from initial exposure. Rolls the initial save.
// Returns result with new stage (0=unaffected, 1=stage1, 2=stage2 on crit fail, etc.)
public function applyAffliction(int $participant_id, array $affliction_def, int $encounter_id): array;

// Process periodic save at end of stage interval.
// Returns new stage and whether affliction ended.
public function processPeriodicSave(int $participant_id, int $affliction_id, int $encounter_id): array;

// Check re-exposure rules. Curses/diseases: no effect. Poisons: advance stage on fail.
public function handleReExposure(int $participant_id, int $affliction_id, array $affliction_def, string $save_degree): array;
```

### DB: `combat_afflictions` table

```sql
CREATE TABLE combat_afflictions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  encounter_id INT NOT NULL,
  participant_id INT NOT NULL,
  affliction_type VARCHAR(32) NOT NULL,  -- poison, disease, curse
  affliction_name VARCHAR(128) NOT NULL,
  affliction_level INT DEFAULT 1,
  save_type VARCHAR(16) NOT NULL,       -- Fortitude, Reflex, Will
  save_dc INT NOT NULL,
  current_stage INT DEFAULT 1,
  max_stage INT NOT NULL,
  onset VARCHAR(64) NULL,
  onset_elapsed TINYINT DEFAULT 0,
  max_duration INT NULL,
  duration_elapsed INT DEFAULT 0,
  is_virulent TINYINT DEFAULT 0,
  consecutive_successes INT DEFAULT 0,  -- for virulent tracking
  stages_json LONGTEXT NOT NULL,        -- JSON array of stage definitions
  status VARCHAR(16) DEFAULT 'active',  -- active, ended
  created INT NOT NULL,
  updated INT NOT NULL
);
```

### Periodic Save Stage Logic (req 2139)
```php
$stage_delta = match($save_degree) {
  'critical_success' => $is_virulent ? -1 : -2,
  'success'          => -1,
  'failure'          => +1,
  'critical_failure' => +2,
};
// Virulent: consecutive_successes must reach 2 for reduction to apply
// Below 0: affliction ends; above max: apply max stage effects again
```

## Acceptance Criteria
1. Initial exposure: success=stage 0 (unaffected), crit fail=stage 2
2. Onset field delays first-stage effects
3. Periodic saves produce correct stage changes per degree
4. Stage 0 → affliction removed
5. Stage > max → max stage effects repeated, not further advancement
6. Disease/curse re-exposure while afflicted → no change
7. Poison re-exposure fail → +1 stage (crit fail +2), duration unchanged
8. Virulent: two consecutive saves to drop 1 stage; crit success = −1 only

## Definition of Done
- [ ] `AfflictionManager.php` created
- [ ] `combat_afflictions` table created via Drupal update hook
- [ ] Service registered in `services.yml`
- [ ] Conditions from affliction stages applied via `ConditionManager`
- [ ] QA evidence updated for reqs 2135–2144
- [ ] `dc_requirements` 2135–2144 updated to `implemented` via MySQL
