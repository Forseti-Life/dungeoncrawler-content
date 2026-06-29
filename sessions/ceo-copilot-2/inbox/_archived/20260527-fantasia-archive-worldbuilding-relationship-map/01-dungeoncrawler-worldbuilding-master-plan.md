# Dungeoncrawler worldbuilding relationship-management master plan

- Owner: ceo-copilot-2
- Scope: `dc-cr-world-codex-graph` + `dc-cr-social-relationship-loyalty`
- Status: active planning

## Objective

Turn the worldbuilding intake into one coherent Dungeoncrawler workstream that delivers:

1. a canonical campaign-scoped world codex
2. a dependent PF2e-inspired social relationship system
3. explicit sequencing so the social layer does not outrun codex identity and world-state contracts

## Governing decisions

- **Landing target:** Dungeoncrawler
- **Foundational feature:** `dc-cr-world-codex-graph`
- **Dependent feature:** `dc-cr-social-relationship-loyalty`
- **Authority rule:** codex owns canonical world records, typed world relationships, inline references, and runtime subject ids
- **Dependency rule:** the social system consumes codex/runtime subject ids and does not invent a parallel identity model

## Workstream phases

### Phase 1 — Codex canonical model

Primary outputs:

- `dc_world_records`
- `dc_world_record_relationships`
- `dc_world_record_tags`
- `dc_world_inline_references`
- `dc_world_subject_registry`

Done when:

- codex schema contract is accepted
- relationship taxonomy is accepted
- subject-id contract is accepted
- first-slice routes for records, relationships, and subject resolution are accepted

Relevant artifacts:

- `features/dc-cr-world-codex-graph/03-schema-contract.md`
- `features/dc-cr-world-codex-graph/05-endpoint-contracts.md`
- `features/dc-cr-world-codex-graph/06-subject-id-contract.md`
- `features/dc-cr-world-codex-graph/07-relationship-taxonomy.md`
- `features/dc-cr-world-codex-graph/09-migration-and-cutover-plan.md`

### Phase 2 — Codex retrieval and linking behavior

Primary outputs:

- hierarchy and tag retrieval behavior
- relationship-aware search posture
- validated inline-reference storage and failure rules
- worked scenarios proving authoring/search shape

Done when:

- hierarchy and tags are explicitly non-interchangeable in retrieval
- unresolved inline references fail explicitly
- relationship-aware search examples are covered by worked scenarios and API contract

Relevant artifacts:

- `features/dc-cr-world-codex-graph/01-acceptance-criteria.md`
- `features/dc-cr-world-codex-graph/04-readiness-matrix.md`
- `features/dc-cr-world-codex-graph/08-worked-scenarios.md`

### Phase 3 — Social canonical model on top of codex identities

Primary outputs:

- `dc_social_relationships`
- `dc_social_influence_profiles`
- `dc_social_reputation_tracks`
- `dc_social_mutation_events`

Done when:

- subject-id dependency on the codex/runtime registry is explicit and unambiguous
- attitude, trust, loyalty, and reputation are separated contractually
- social taxonomy and default/transition controls are accepted

Relevant artifacts:

- `features/dc-cr-social-relationship-loyalty/03-schema-contract.md`
- `features/dc-cr-social-relationship-loyalty/04-transition-and-defaults.md`
- `features/dc-cr-social-relationship-loyalty/06-relationship-taxonomy.md`
- `features/dc-cr-social-relationship-loyalty/09-subject-id-contract.md`

### Phase 4 — Social mutation and runtime consumption

Primary outputs:

- validated mutation event contract
- effective-social-posture computation
- AI GM / quest / storyline integration rules
- threshold-aware loyalty/trust/reputation behavior

Done when:

- runtime consumers read a shared effective-social-posture service
- mutation provenance is explicit and auditable
- worked scenarios cover rescue, betrayal, institutional friendliness, and faction spillover

Relevant artifacts:

- `features/dc-cr-social-relationship-loyalty/01-acceptance-criteria.md`
- `features/dc-cr-social-relationship-loyalty/05-readiness-matrix.md`
- `features/dc-cr-social-relationship-loyalty/07-worked-scenarios.md`
- `features/dc-cr-social-relationship-loyalty/08-endpoint-contracts.md`

### Phase 5 — Authoring, inspector, and graph-adjacent UX

Primary outputs:

- codex inspector and browse surfaces
- social inspector / GM authoring surfaces
- deferred graph/relationship-map UX planning

Done when:

- underlying contracts are proven stable
- no UI is forcing schema invention during implementation

## Immediate priority order

1. Lock codex completeness first.
2. Confirm social completeness only where it depends on codex subject-id and world-state rules.
3. Do not treat relationship-map UX as slice-1 scope.

## Completeness checklist

### Codex

- [x] feature brief
- [x] acceptance criteria
- [x] implementation notes
- [x] schema contract
- [x] readiness matrix
- [x] endpoint contracts
- [x] subject-id contract
- [x] relationship taxonomy
- [x] worked scenarios

### Social

- [x] feature brief
- [x] acceptance criteria
- [x] implementation notes
- [x] schema contract
- [x] transition/defaults
- [x] readiness matrix
- [x] relationship taxonomy
- [x] worked scenarios
- [x] endpoint contracts
- [x] subject-id contract

## Remaining review targets

The remaining incompleteness is no longer missing artifact files. It is now in the form of unresolved plan-validation work:

1. architect review of service/table boundaries
2. migration/cutover decision for moving from current generic relationship ids to codex record ids and runtime subject ids
3. QA confirmation that the readiness matrices are sufficient for slice-1 implementation

Initial live-code validation is now captured in:

- `02-dungeoncrawler-service-boundary-validation.md`

The current migration/cutover design is now captured in:

- `features/dc-cr-world-codex-graph/09-migration-and-cutover-plan.md`

## Non-goals for current review pass

- no implementation code changes yet
- no release activation yet
- no relationship-map UI parity work
