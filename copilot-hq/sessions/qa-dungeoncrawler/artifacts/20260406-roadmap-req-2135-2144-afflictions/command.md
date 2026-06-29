# QA: Afflictions (Reqs 2135–2144)

- Agent: qa-dungeoncrawler
- Status: pending
- Priority: high
- Release: ch09-playing-the-game

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Afflictions (p.457–458): poison, disease, curse, onset, stages, virulent

## Requirements Covered

| ID   | Req Text                                                                                               | Status      |
|------|--------------------------------------------------------------------------------------------------------|-------------|
| 2135 | Afflictions have: name, traits, level, save type/DC, optional onset, optional max duration, stages    | **pending** |
| 2136 | Initial save: success=unaffected, crit fail=stage 2 (not stage 1)                                     | **pending** |
| 2137 | Onset: first stage effects don't activate until onset time elapses                                     | **pending** |
| 2138 | Periodic saves at end of each stage interval                                                           | **pending** |
| 2139 | Periodic save: crit success=−2 stages, success=−1, failure=+1, crit failure=+2                         | **pending** |
| 2140 | Below stage 1 = affliction ends; exceeding max stage repeats max stage effects                         | **pending** |
| 2141 | Conditions from affliction may persist beyond affliction end per normal condition rules                | **pending** |
| 2142 | Curses/diseases: re-exposure while afflicted has no effect                                             | **pending** |
| 2143 | Poisons: re-exposure failure during active poison advances stage +1 (crit fail: +2); max duration unchanged | **pending** |
| 2144 | Virulent: two consecutive successes to reduce stage by 1; crit success = only −1 (not −2)             | **pending** |

## Test Cases (all pending until AfflictionManager implemented)

### REQ-2135 — Affliction Data Structure

**Positive:** An affliction definition has: name, level, save_type, save_dc, stages array (each with effects), optional onset, optional max_duration.
```php
// TODO: $affliction = ['name'=>'Sewer Plague','level'=>1,'save_type'=>'Fortitude','save_dc'=>14,
//   'onset'=>'1d4 days','stages'=>[['effects'=>['enfeebled 1'],...],['effects'=>['enfeebled 2','drained 1'],...]]];
// Verify AfflictionManager can process this structure
```

### REQ-2136 — Initial Save Outcomes

**Positive:** Initial exposure save: success = no affliction. Crit failure = start at stage 2.
```php
// TODO: $am->applyAffliction($participant, $affliction, $save_degree='critical_failure');
// assert initial stage = 2
```

### REQ-2139 — Periodic Save Stage Changes

**Positive:** All four degrees produce correct stage change.
```php
// TODO: assert crit_success → stage - 2
// assert success → stage - 1
// assert failure → stage + 1
// assert crit_failure → stage + 2
```

### REQ-2144 — Virulent Afflictions

**Positive:** Virulent affliction requires two consecutive successes to reduce by 1 stage.
```php
// TODO: Single success doesn't reduce stage; second consecutive success does
// Crit success on virulent = only −1 stage, not −2
```

**Negative:** Non-virulent: single success = −1 stage, crit success = −2 stages.
