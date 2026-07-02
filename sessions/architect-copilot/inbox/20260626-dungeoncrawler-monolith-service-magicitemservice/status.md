# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/MagicItemService.php` (~1921 lines) as a mixed-responsibility PF2e magic-item monolith spanning:
  1. investment/activation and daily state flows,
  2. runes/precious materials/staff/wand systems,
  3. poison/consumable/snare interaction pipelines.
- Coupling profile:
  - contact/ingested poison application paths duplicated pending-save queue writes,
  - repeated queue payload assembly increased drift risk for poison save contracts.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - poison applications must enqueue deterministic pending-save payload shape (`type`, `poison_data`),
  - queue operations must append (not overwrite) existing pending saves,
  - contact and ingested poison paths must stay behaviorally aligned except for save type.
- Drift risks:
  1. duplicated queue writes can diverge on payload keys/order,
  2. branch drift can silently desynchronize poison trigger semantics.

### 2026-06-29 — Phased extraction strategy
1. **Poison queue seam**
   - extract one helper to enqueue pending poison-save payloads.
2. **Callsite convergence**
   - route contact and ingested poison application through shared queue helper.
3. **Coverage lock**
   - add focused tests for queue payload shape and append behavior.
4. **Service thinning continuation**
   - continue decomposing other magic-item subdomains in subsequent increments.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure/no-swallow posture.
- Preserve existing poison application return payloads.
- Preserve pending-save append semantics and poison type distinctions.

### 2026-06-29 — Test/conformance coverage gaps
- Existing MagicItemService tests focused on snare flows and did not directly lock poison pending-save queue contracts.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `enqueuePendingPoisonSave(...)`,
  - rewired `applyContactPoison(...)` and `applyIngestedPoison(...)` to consume shared queue helper.
- Added targeted unit coverage in `MagicItemServicePoisonTest`:
  - contact poison queue payload contract,
  - ingested poison append behavior with pre-existing pending saves.
- Pushed in `dungeoncrawler-content` commit: `6e1eaddc7a`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
