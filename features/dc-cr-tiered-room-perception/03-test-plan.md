# Test Plan: dc-cr-tiered-room-perception

## Coverage summary

- AC items: 7
- Planned test cases: 10
- Suites: PHPUnit unit + functional, targeted JS/hexmap coverage, manual exploration smoke check
- Security: no exemption; hidden sensory detail must remain protected on failed checks

---

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` (unit) | PHPUnit unit | tier threshold resolution, persistence rules, no-leak failure handling |
| `module-test-suite` (functional) | PHPUnit functional | room payloads, authenticated probe route, room-scoped persistence |
| `hexmap-js` | targeted JS test | client request/response handling and render behavior for optional sensory reveals |
| manual exploration smoke | browser/manual | confirm authored room feels usable and baseline exploration remains fast |

---

## Test cases

### TC-TRP-01 — Baseline room description still renders without probing
- Suite: functional
- Expected: room load shows baseline sight and sound details with no Perception action required
- AC: AC-1, AC-7

### TC-TRP-02 — Room without optional sensory tiers behaves like current exploration
- Suite: functional
- Expected: no broken affordance or missing narrative appears when the room has no authored optional senses
- AC: AC-2, AC-7

### TC-TRP-03 — Smell tier reveals on successful lower-threshold check
- Suite: unit/functional
- Expected: successful lower-tier probe returns the authored smell detail and marks it revealed for the room state
- AC: AC-2, AC-3, AC-4

### TC-TRP-04 — Higher tier requires a higher Perception result
- Suite: unit
- Expected: a result that reveals smell does not automatically reveal touch/texture or atmosphere/mood unless their thresholds are also met
- AC: AC-3

### TC-TRP-05 — Failed probe does not leak hidden sensory text
- Suite: unit/functional
- Expected: failure response contains no authored hidden detail text in payload or render output
- AC: AC-5

### TC-TRP-06 — Previously revealed room detail persists across repeat renders
- Suite: functional
- Expected: once a tier is revealed, reloading or re-rendering the same room state shows that revealed detail without forcing an immediate re-roll
- AC: AC-4

### TC-TRP-07 — Revealed detail does not bleed into another room
- Suite: functional
- Expected: sensory detail unlocked in room A is not shown when entering room B unless room B has separately revealed data
- AC: AC-4

### TC-TRP-08 — Client can request supported sensory probes from the room shell
- Suite: JS
- Expected: the hexmap or room shell sends the expected sense-tier request for the active room and renders the response in the correct UI region
- AC: AC-6

### TC-TRP-09 — Unauthorized or invalid probe request is rejected explicitly
- Suite: functional
- Expected: requests with invalid room/session/character context fail explicitly and do not mutate revealed-state data
- AC: AC-6

### TC-TRP-10 — Manual authored-room smoke test feels additive, not mandatory
- Suite: manual exploration smoke
- Expected: a player can traverse normally on baseline sight/sound alone, while a high-Perception scout can unlock richer room flavor without stalling the session
- AC: AC-1, AC-3, AC-7

---

## Definition of done

- [ ] Baseline room descriptions remain intact without probing
- [ ] Optional sensory layer metadata is covered
- [ ] Escalating threshold behavior is covered
- [ ] No-leak failure behavior is covered
- [ ] Room-scoped persistence is covered
- [ ] Client request/render behavior is covered
- [ ] Manual exploration smoke confirms the feature feels additive rather than tedious
