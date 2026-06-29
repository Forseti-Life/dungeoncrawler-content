# QA: Line of Effect and Line of Sight (Reqs 2130–2134)

- Agent: qa-dungeoncrawler
- Status: pending
- Priority: high
- Release: ch09-playing-the-game

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Line of Effect (p.457), Line of Sight (p.457)

## Requirements Covered

| ID   | Req Text                                                                                        | Status      |
|------|-------------------------------------------------------------------------------------------------|-------------|
| 2130 | Most effects require line of effect (unblocked path). Solid barriers block; semi-solid do not  | **pending** |
| 2131 | 1-foot gap is typically sufficient to maintain line of effect (GM adjudicates)                  | **pending** |
| 2132 | Area effects require line of effect from origin to each target                                  | **pending** |
| 2133 | Line of sight: clear path + ability to sense (darkness blocks without darkvision)               | **pending** |
| 2134 | Solid barriers block LoS; portcullises and non-solid obstacles do not                           | **pending** |

## Test Cases (all pending until implemented)

### REQ-2130 — Line of Effect

**Positive:** Attack between two hexes with no solid obstacle returns line_of_effect=TRUE.
```php
// TODO: $los = \Drupal::service('dungeoncrawler_content.los_service');
// assert($los->hasLineOfEffect($attacker_pos, $target_pos, $terrain_map) === TRUE);
```

**Negative:** Solid wall between attacker and target returns line_of_effect=FALSE.

---

### REQ-2131 — 1-Foot Gap

**Positive:** A terrain tile flagged as 'semi_solid' (portcullis) does not block line of effect.
```php
// TODO: assert($los->hasLineOfEffect($pos1, $pos2, ['semi_solid_obstacle']) === TRUE);
```

**Negative:** A tile flagged as 'solid' blocks line of effect.

---

### REQ-2132 — Area Effects Require LoE to Each Target

**Positive:** Burst spell only hits targets for which line of effect exists from origin.
```php
// TODO: Target behind solid wall in burst radius is excluded from targets list
```

**Negative:** Target in burst radius WITH line of effect IS included.

---

### REQ-2133 — Line of Sight: Darkness and Vision

**Positive:** Attacker in darkness without darkvision cannot target a creature (LoS=FALSE).
```php
// TODO: assert($los->hasLineOfSight($attacker, $target, $lighting='darkness') === FALSE);
```

**Negative:** Attacker with darkvision in darkness retains line of sight.

---

### REQ-2134 — Solid vs Non-Solid Obstacles

**Positive:** Portcullis tile (is_solid=FALSE) does not block LoS.
**Negative:** Wall tile (is_solid=TRUE) blocks LoS.
