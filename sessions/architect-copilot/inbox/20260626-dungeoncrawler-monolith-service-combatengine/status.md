# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/CombatEngine.php` (~1146 lines) as a mixed-responsibility combat monolith combining:
  1. encounter lifecycle and round/turn transitions,
  2. attack resolution and detection-state mechanics,
  3. participant entity-ref projection and persistence updates.
- Coupling profile:
  - participant `entity_ref` decoding logic was duplicated inline across attack and detection flows,
  - shared JSON decode contract lacked a single extraction seam,
  - monolith size increases drift risk when entity-ref shape handling evolves.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - participant `entity_ref` payloads must decode consistently across combat resolution paths,
  - empty `entity_ref` values must normalize to an empty array,
  - invalid JSON must preserve current hard-failure-adjacent semantics (decode returns `NULL`, no silent recovery).
- Drift risks:
  1. repeated decode branches can diverge between attack and detection paths,
  2. inconsistent fallback semantics can hide data-contract defects,
  3. duplicated decode logic slows safe incremental decomposition.

### 2026-06-29 — Phased extraction strategy
1. **Participant entity-ref decode seam**
   - extract one helper for participant `entity_ref` decoding.
2. **Attack/detection path convergence**
   - route both attack resolution and detection accessors through the shared seam.
3. **Detection-state boundary segmentation**
   - continue isolating detection persistence/read contracts from broader combat flow.
4. **Lifecycle/computation hard boundaries**
   - keep lifecycle mutation and combat computation concerns explicit.
5. **Service thinning**
   - preserve public facade while reducing inline parse/normalization duplication.

### 2026-06-29 — Conformance safeguards
- Preserve empty `entity_ref` behavior as `[]`.
- Preserve invalid JSON decode semantics (`NULL`) without introducing fallback recovery.
- Preserve detection-state read/write contracts and existing call flow behavior.
- Preserve hard-failure/no-swallow posture.

### 2026-06-29 — Test/conformance coverage gaps
- Existing unit coverage did not isolate participant `entity_ref` decode contract.
- Missing prior to this increment:
  1. direct unit contract for valid `entity_ref` JSON decode,
  2. direct unit contract for empty/unset decode behavior,
  3. direct unit contract for invalid JSON decode behavior.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `decodeParticipantEntityRef(...)`,
  - rewired `resolveAttack(...)`, `getDetectionState(...)`, and `setDetectionState(...)` to use the shared helper.
- Added targeted unit coverage in `CombatEngineTest`:
  - `testDecodeParticipantEntityRefDecodesValidJson`,
  - `testDecodeParticipantEntityRefReturnsEmptyArrayWhenUnset`,
  - `testDecodeParticipantEntityRefReturnsNullOnInvalidJson`.
- Pushed in `dungeoncrawler-content` commit: `5c415129e5`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
