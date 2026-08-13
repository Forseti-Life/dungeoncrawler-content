# Rules Validation Target State and Implementation Plan

## Scope

This plan extends the current validator subsystem to cover:

1. **Skill references** across spells/feats/actions.
2. **Condition references** and effect lifecycle contracts.
3. **Consumable mechanics contracts** for item behavior safety.
4. **Validation profiles** for intermediary ingest vs canonical registry.

Goals:

- Eliminate runtime ambiguity caused by loosely structured content.
- Enforce canonical identifiers and bounded enums before content is usable.
- Keep validation centralized under `StateValidationService` while delegating domain checks to focused validator services.
- Avoid fallback behavior; invalid contracts fail explicit gates.

## Current Gaps (confirmed)

- No dedicated validator for skill references embedded in feat/spell/action payloads.
- Spell `conditions_caused` values are not validated against canonical condition/effect definitions.
- Consumable records (`item_type=consumable`) lack a strict required `consumable_stats` contract.
- Canonical item validation applied to intermediary payloads produces false failures because the shapes are intentionally different.

## Target State Architecture

### 1) Domain Validators

Add new services:

- `SkillReferenceValidatorService`
- `ConditionReferenceValidatorService`
- `ConsumableContractValidatorService`
- `ValidationProfileResolverService`

Each returns:

```php
['valid' => bool, 'errors' => string[]]
```

No silent coercion. IDs are normalized or rejected with explicit errors.

### 2) Validation Profiles

Two explicit profiles:

1. `intermediary_ingest`
2. `canonical_registry`

Profile determines:

- Allowed/required fields
- Unknown-field policy
- Strictness for parser metadata
- Which cross-reference checks are enforced

`StateValidationService` becomes the facade that dispatches to profile-aware validators.

### 3) Canonical Registries and Reference Resolution

Authoritative sources:

- Skills: canonical skill ID allowlist (new source of truth file/service).
- Conditions/effects: `EffectDefinitionRegistryService` + alias map for legacy tokens.
- Consumable mechanics: canonical payload schema in item contracts.

Cross-reference checks must verify existence, not just string shape.

### 4) Pipeline Gate Points

Enforce validation at:

1. Content import/ingest stage (intermediary profile).
2. Promotion to canonical registry (canonical profile).
3. Runtime action execution admission for spell/feat/consumable payloads.

Failure behavior:

- Block promotion/execution.
- Persist structured error report with content_id and failure list.

## Contract Targets

### A. Skill Reference Contract

Validate every skill-bearing field (examples: `skill`, `assurance_per_skill`, action prerequisites):

- Must map to canonical skill ID (`acrobatics`, `arcana`, etc.).
- Optional proficiency values must be in enum (`untrained`, `trained`, `expert`, `master`, `legendary`).
- Arrays must dedupe and reject empty strings.

### B. Condition/Effect Reference Contract

For spell/feat/action condition payloads:

- `conditions_caused[*]` must resolve to canonical condition ID or registered alias.
- Lifecycle fields (if present) must map to known trigger values (ex: `next_daily_preparations`).
- Stack/value semantics must be explicit for variable conditions (example: `frightened` value payload).

### C. Consumable Contract

For `item_type=consumable` require `consumable_stats` object with:

- `activation.action_cost` (bounded enum/int)
- `uses`/`charges` semantics
- `effect_type` (healing, temp_hp, condition_apply, resource_restore, etc.)
- Typed effect payload keyed by `effect_type`
- Optional save/DC fields when applicable
- Optional expiration/lifecycle trigger when persistent effects are applied

Reject consumables with prose-only mechanics in canonical profile.

### D. Intermediary vs Canonical Shape Contract

`intermediary_ingest`:

- Allows parser metadata (`parser_version`, `source_book`, extraction fields).
- Requires minimal identity + parse-safe mechanics.

`canonical_registry`:

- Rejects parser-only fields.
- Requires canonical field names, canonical IDs, and typed mechanics payloads.

## Implementation Plan

## Phase 1 — Foundation (profile + shared references)

1. Add `ValidationProfileResolverService`.
2. Add canonical skill allowlist source and loader service.
3. Add condition alias resolver backed by `EffectDefinitionRegistryService`.
4. Wire services in `dungeoncrawler_content.services.yml`.

Deliverables:

- Profile enum/constants.
- Reusable canonical reference loaders.
- Unit tests for profile and resolver behavior.

## Phase 2 — Skill + Condition validators

1. Implement `SkillReferenceValidatorService`.
2. Implement `ConditionReferenceValidatorService`.
3. Integrate into `SpellFeatActionDataValidatorService`.
4. Add `StateValidationService` report endpoints:
   - `validateCanonicalSkillReferenceContracts()`
   - `validateCanonicalConditionReferenceContracts()`

Deliverables:

- Validation errors pinpointing field paths.
- Contract report pages analogous to current spell/feat/action reports.

## Phase 3 — Consumable validator + item integration

1. Implement `ConsumableContractValidatorService`.
2. Integrate into item validation flow (`validateCanonicalItemDefinition`).
3. Add profile-aware behavior to avoid intermediary false failures.
4. Add dedicated consumable contract report endpoint.

Deliverables:

- Canonical consumable shape enforced.
- Intermediary ingest profile still usable for parser outputs.

## Phase 4 — Runtime gate enforcement

1. Add validator checks before runtime action application for consumable/spell/feat execution payloads.
2. Add explicit hard-fail responses and structured logs.
3. Add migration/audit command to report non-compliant canonical rows.

Deliverables:

- Runtime safety gate active.
- Clear audit report for remediation backlog.

## Testing Plan

Unit tests:

- Valid/invalid skill references, including casing, alias, duplicates.
- Valid/invalid condition tokens and lifecycle trigger values.
- Consumable payload permutations by `effect_type`.
- Profile behavior: same payload pass in intermediary profile and fail in canonical profile where expected.

Integration tests:

- Canonical report endpoints return expected totals and error rows.
- Runtime action gate rejects invalid payloads with deterministic error contracts.

Contract tests:

- Golden fixtures for spell/feat/item payloads in both profiles.
- Regression fixture for the known invalid spell categories (`cast_actions`, invalid tradition tokens).

## Migration and Rollout

1. **Audit only mode** (non-blocking) for one cycle; gather failure inventory.
2. Bulk normalization of top failure clusters (skills, condition aliases, consumable stats).
3. Enable blocking in canonical promotion path.
4. Enable blocking in runtime action path.

No legacy fallback branch after blocking is enabled.

## Definition of Done

- Validators implemented and wired through `StateValidationService`.
- Profile-aware validation active for intermediary and canonical paths.
- Skill/condition/consumable reports available and tested.
- Runtime gate blocks invalid spell/feat/consumable payloads.
- Existing canonical corpus either normalized or explicitly quarantined with remediation tickets.

