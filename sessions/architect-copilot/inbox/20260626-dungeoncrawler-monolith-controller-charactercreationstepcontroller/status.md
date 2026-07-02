# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Controller decomposition audit
- Audited `src/Controller/CharacterCreationStepController.php` (1463 lines) as a mixed-responsibility controller with five distinct contract surfaces:
  1. Draft/session routing (`start`, `step`, draft-resume and step-gate redirects).
  2. AJAX mutation pipeline (`saveStep`, 170 lines) handling CSRF, access, validation, persistence, finalization, and response mapping.
  3. Step-domain mutation engine (`updateStepData`, 263 lines) handling ancestry/class/ability/equipment/feat synthesis.
  4. Step validation engine (`validateStepRequirements`, 168 lines) with per-step rule matrix.
  5. Option/catalog projection helpers (ancestries, alignments, equipment-template extraction, feat projection).
- Coupling profile:
  - Direct DB orchestration in-controller (`createDraft`, registry catalog query) alongside `CharacterManager` writes.
  - Cross-domain side effects in one request path (ability recomputation, feat synthesis, pending-selection gating, portrait generation, status transitions).
  - Mixed response contracts in one class (`RedirectResponse`, `JsonResponse`, form rendering).

### 2026-06-29 — Contract map and drift risks
- Core contracts currently intertwined:
  - canonical wizard payload normalization + mirror sync,
  - per-step required-field enforcement,
  - step mutation semantics (especially ancestry/class/equipment),
  - completion gate (`wizard_complete`, pending selection grants),
  - finalization side effects (status flip + portrait summary + redirect).
- Drift risks identified:
  1. Validation and mutation logic are duplicated by concern and tightly coupled to raw form payload structure, increasing risk of divergence between step UI payloads and persisted canonical shape.
  2. Step-2 ancestry mutation path carries reversal state (`_prev_ancestry`, `_prev_ancestry_free_boosts`) inline in mutable payload, making replay/edit semantics fragile when extraction is done piecemeal.
  3. Step-7 equipment resolution has catalog-source branching (registry templates vs hardcoded fallback) plus inventory/currency assembly in-controller, which can drift from shared inventory contracts.
  4. Completion/finalization path mixes pending-selection gate, campaign-source canonicalization, and portrait generation in one branch, making side-effect ordering easy to regress.

### 2026-06-29 — Phased extraction strategy
1. **Request guard + actor context extraction**
   - Extract CSRF/access/campaign-scope guard and draft resolution into a request guard/context resolver.
2. **Step validation extraction**
   - Move `validateStepRequirements` into a dedicated step-rule validator service with explicit step contracts.
3. **Step mutation extraction**
   - Split `updateStepData` into step-specific mutators (`Step2AncestryMutator`, `Step4ClassMutator`, `Step7EquipmentMutator`, etc.) coordinated by one orchestrator.
4. **Catalog/provider extraction**
   - Move equipment/ancestry/alignment option and template-catalog assembly into dedicated option-provider services.
5. **Completion/finalization extraction**
   - Isolate pending-selection checks, wizard-complete gate, status transition, canonical-source enforcement, and portrait generation orchestration into a completion service with deterministic ordering.
6. **Controller thinning**
   - Reduce controller to route facade: parse request, delegate to services, map service result to HTTP response.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure behavior for CSRF/access/campaign-flow violations (no fallback save paths).
- Preserve canonical payload normalization order: mutation -> ability recompute -> wizard sync -> completion gate -> persistence.
- Preserve completion guard semantics requiring no pending selection grants before status finalization.
- Preserve step-specific validation cardinality/uniqueness constraints (ancestry boosts, background boosts, free boosts, class key ability).
- Preserve step-7 currency/inventory contract and deterministic catalog projection order.

### 2026-06-29 — Test/conformance coverage gaps
- Existing coverage exists but is shallow relative to hotspot risk:
  - Unit: `tests/src/Unit/Controller/CharacterCreationStepControllerTest.php` currently asserts campaign-scoped AJAX rejection only.
  - Functional: `tests/src/Functional/Controller/CharacterCreationStepControllerTest.php` covers route/access/shell flow, not mutation/validation invariants.
- Missing conformance tests that should be frozen before extraction:
  1. Step-2 ancestry re-selection reversal and free-boost replay invariants.
  2. Step-4 spell payload/class feature projection invariants.
  3. Step-7 equipment catalog branch parity (registry source vs fallback) and currency/inventory totals.
  4. Step-8 completion gating with unresolved `selection_grants` and status-transition ordering.
  5. Canonical payload sync/hardening invariants after each persisted step.

### 2026-06-29 — Completion
- Delivered full controller decomposition map, ordered extraction phases, contract drift inventory, and conformance-gap plan.
- Acceptance criteria met for this inbox planning item.
