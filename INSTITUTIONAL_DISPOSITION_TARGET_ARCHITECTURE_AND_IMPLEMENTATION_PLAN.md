# Institutional Disposition Target Architecture and Implementation Plan

**Status:** Proposed target architecture  
**Audience:** CEO, Architect, Codex implementation agent  
**Purpose:** Define the authoritative architecture for institutional disposition inside the existing numeric disposition subsystem, and break implementation into manageable, authority-safe chunks.

---

## Executive Summary

The existing disposition system is already **resolver-centric**:

- actor baseline disposition is a writer-owned fact,
- actor-to-actor relationship attitude is a writer-owned fact,
- scene/context inputs are writer-owned facts,
- `DispositionResolverService` is the **behavioral authority** that computes effective actor-to-actor disposition.

Institutional disposition must extend that architecture rather than create a parallel one.

The target model is:

```text
actor institution memberships
+ actor institution sentiments
+ institution->institution matrix
-> institution score assembly
-> DispositionResolverService
-> canonical resolved actor-target DTO
```

The missing architectural seam today is a dedicated **institution score assembly layer** that combines:

1. actor-held attitudes toward institutions, and
2. canonical institution-to-institution relationships

into one numeric `institution_score` that the resolver can consume.

---

## Current State

### What is already authoritative

| Concern | Current authority | Notes |
| --- | --- | --- |
| Actor baseline disposition | `ActorDispositionService` | Stored writer fact |
| Actor -> actor directed relationship | `RelationshipAttitudeService` | Stored writer fact |
| Final actor-target behavior | `DispositionResolverService` | Canonical behavioral output |
| Resolver contract | `DispositionAuthorityContract` | Canonical score/label/threshold helpers |

### What exists for institutional data

| Concern | Current authority | Storage | Status |
| --- | --- | --- | --- |
| Institution catalog / identity | `CampaignSubjectRegistryService` | `dc_campaign_subject_registry` | Present |
| Actor -> institution membership | `InstitutionMembershipService` | `dc_campaign_relationships` (`institution_member`) | Present |
| Actor -> institution sentiment | `InstitutionMembershipService` | `dc_campaign_relationships` (`institution_sentiment`) | Present |
| Institution hierarchy | `CampaignSubjectRegistryService` | `dc_campaign_relationships` (`institution_parent`) | Present |
| Institution -> institution disposition matrix | None authoritative yet | Partial experimental work only | Missing |

### How institutional score currently works

`RelationshipsMatrixReadModelService::buildInstitutionAdjustmentBreakdown()` currently:

1. resolves the source actor identity,
2. loads the **source actor's institution sentiments**,
3. loads the **target actor's institution memberships**,
4. matches those by institution subject/domain,
5. reduces them into a weighted `institution_score`,
6. passes that score into `DispositionResolverService`.

That means the current live architecture is:

```text
actor-held institution sentiments
+ target memberships
-> institution score
-> resolver
```

This is incomplete relative to the intended model because it does **not** include a canonical institution-to-institution lookup.

---

## Architectural Problems To Solve

### 1. Missing authority seam

There is no single authoritative service for:

```text
institutional inputs -> institution_score -> resolver context
```

That logic is partially embedded in `RelationshipsMatrixReadModelService`, which makes it hard to reuse consistently across runtime consumers.

### 2. Authority split risk

Recent experimental work started adding **institution -> institution** neutral edges directly in `CampaignSubjectRegistryService`.

That work is directionally useful, but if continued as-is it creates a second institutional authority path without wiring it into the resolver flow.

### 3. Semantic overloading

`institution_sentiment` currently means:

1. actor -> institution attitude

The experimental work started using the same shape for:

2. institution -> institution attitude

Those should not share the same semantic contract without explicit layering.

### 4. Read-side duplication

The relationships matrix read model currently performs institutional score assembly inline instead of delegating to a dedicated shared service.

---

## Target Architecture

## Core Rule

**Writers persist source facts. Assemblers combine facts. The resolver remains the only behavioral authority.**

### Target score domain

Planned target score range for disposition-related calculations:

```text
-1000 .. 1000
```

This target range should replace the earlier `-100 .. 100` contract during implementation of the next architecture phase.

### Target responsibility map

| Layer | Target responsibility |
| --- | --- |
| `CampaignSubjectRegistryService` | Canonical institution identity and hierarchy only |
| `InstitutionMembershipService` | Actor memberships + actor-held institution sentiments |
| `InstitutionDispositionMatrixService` (new) | Canonical institution->institution disposition matrix |
| `InstitutionDispositionScoreAssemblerService` (new) | **Single centralized authority** for combining actor sentiment + target memberships + institution matrix into one numeric institutional adjustment |
| `DispositionResolverService` | Consume assembled `institution_score` and compute final actor-target disposition |

### Target read flow

```text
source actor
  -> actor baseline disposition
  -> actor institution memberships
  -> actor institution sentiments

target actor
  -> target institution memberships

institution graph
  -> canonical institution->institution matrix edges

InstitutionDispositionScoreAssemblerService
  -> institution_score
  -> institution_breakdown
  -> authority metadata

DispositionResolverService
  -> effective_disposition_score
  -> effective_disposition_label
  -> policy_flags
  -> canonical resolver DTO
```

The institutional contribution must be centralized through **one shared assembler service** before it reaches the resolver. No read model or consumer should compute institutional contribution independently.

### Target write flow

```text
actor creation / sync
  -> institution subjects ensured
  -> actor memberships ensured
  -> actor institution sentiments seeded

quest/storyline mutation
  -> institution matrix edge created or updated

actor-specific events
  -> actor-held institution sentiment mutated

resolver consumers
  -> never write
  -> only read assembler output + resolver DTO
```

---

## Relational Target Model

## Keep

### `dc_campaign_subject_registry`

Canonical institution subject catalog.

Use for:

- subject identity,
- domain,
- display name,
- normalized label,
- provenance,
- hierarchy membership.

### `dc_campaign_relationships`

Continue using this as the canonical edge store, but with **clear relationship-type separation**.

## Relationship types

### 1. Actor membership

```text
source_type: campaign_character | campaign_npc
target_type: institution
relationship_type: institution_member
```

Meaning: actor belongs to institution.

### 2. Actor-held institution sentiment

```text
source_type: campaign_character | campaign_npc
target_type: institution
relationship_type: institution_sentiment
```

Meaning: actor's own disposition toward an institution.

### 3. Institution hierarchy

```text
source_type: institution
target_type: institution
relationship_type: institution_parent
```

Meaning: taxonomy or parent-child organization.

### 4. Institution matrix edge (**new canonical type**)

```text
source_type: institution
target_type: institution
relationship_type: institution_disposition
```

Meaning: canonical institution-to-institution disposition lookup.

**Important:** do **not** reuse actor `institution_sentiment` for this layer.  
The matrix layer needs its own explicit relationship type so authority and query semantics stay clear.

## Matrix edge state payload

Recommended `relationship_state` shape:

```json
{
  "edge_kind": "institution_disposition_matrix",
  "score": 0,
  "knowledge_state": "known",
  "seed_source": "institution_matrix_default",
  "seed_profile_key": "known-neutral-default",
  "review_status": "unreviewed",
  "mutation_state": "seeded",
  "mutation_count": 0,
  "rationale": "",
  "source_scope": "institution_matrix"
}
```

---

## New Services

## 1. `InstitutionDispositionMatrixService`

### Purpose

Own institution->institution matrix reads/writes.

### Responsibilities

- load matrix edge for one institution pair,
- upsert matrix edge with explicit score,
- seed neutral defaults,
- backfill ancestry/profession default matrix pairs,
- expose explainable matrix row details.

### Non-responsibilities

- must not compute final actor disposition,
- must not know actor baseline disposition,
- must not mutate actor-held institution sentiments.

## 2. `InstitutionDispositionScoreAssemblerService`

### Purpose

Produce one canonical institutional adjustment for the resolver.

### Responsibilities

- load source actor institution memberships,
- load source actor institution sentiments,
- load target actor institution memberships,
- load canonical institution->institution matrix edges,
- combine actor-specific and matrix-level signals,
- return:
  - `institution_score`
  - `institution_breakdown`
  - `authority`
  - `equation`

### Explicit non-responsibilities

- do **not** persist/materialize assembled institutional contribution in this phase
- do **not** let consumers compute their own institutional math
- do **not** bypass the resolver

### Required output contract

```php
[
  'score' => 0,
  'weighted_score' => 0,
  'breakdown' => [...],
  'authority' => [
    'actor_sentiment' => 'institution_membership_service',
    'institution_matrix' => 'institution_disposition_matrix_service',
    'assembler' => 'institution_disposition_score_assembler',
  ],
]
```

This assembled value remains a **computed read-time product** for now, not a stored canonical authority record.

## Resolver Integration Target

`DispositionResolverService` should **not** query institution graph edges directly.

Instead:

1. caller (or read model) asks `InstitutionDispositionScoreAssemblerService` for the institutional adjustment,
2. assembler returns numeric `institution_score`,
3. caller passes that score into resolver scene context,
4. resolver remains unchanged in responsibility and still produces the final DTO.

This preserves the current architectural invariant:

```text
writers/assemblers provide facts
resolver provides behavior
```

---

## Required Refactor of Existing Read Path

### Current location of embedded institutional logic

`RelationshipsMatrixReadModelService::buildInstitutionAdjustmentBreakdown()`

### Target state

That service should stop computing institutional logic directly and instead call:

```php
$institution = $this->institutionDispositionScoreAssemblerService
  ->buildActorTargetInstitutionAdjustment($campaign_id, $source_ref, $target_ref);
```

That same assembler can later be reused by:

- room chat resolution,
- encounter tactical context,
- GM actor runtime,
- any future social simulation consumers.

---

## Default Matrix Policy

### Initial target policy

Until explicit review happens:

- all **ancestry -> profession** pairs default to **neutral**
- all **profession -> ancestry** pairs default to **neutral**

This gives the system a complete baseline without inventing affinity/hostility prematurely.

### Profession-to-profession default policy

For the current analysis direction, profession-domain defaults should remain mostly neutral.

Baseline rule:

```text
profession -> profession = 0
```

Current exceptions:

```text
profession -> Rogue = -5
profession -> Witch = -5
profession -> Cleric = +5
profession -> Bard = +5
```

Interpretation:

- all professions are slightly wary of `Rogue` and `Witch`
- all professions are slightly favorable toward `Cleric` and `Bard`
- all other profession-to-profession pairs remain neutral unless explicitly authored later

For now, this should be applied to the canonical class-profession set only. Non-core profession labels such as fallback/problem values should remain neutral unless explicitly normalized and authored later.

### Why only ancestry <-> profession first

This is the smallest useful slice because:

- both domains already exist in current institutional disposition thinking,
- both already contribute to actor institutional modeling,
- the pair count is manageable,
- it avoids prematurely expanding to political/cultural/religious dimensions before the authority path is stable.

### Deferred matrix domains

Do not include in first implementation:

- settlement
- allegiance
- government
- security
- religion
- employer
- education
- noble
- criminal
- culture

Those should come later after the core authority seam is proven.

---

## Formula and Authority Appendix

## Institutional score assembly formula

The institutional subsystem should output exactly one `institution_score` for the resolver.

That score should be assembled from two independently meaningful components:

1. **Actor-specific institutional sentiment**
2. **Institution-to-institution matrix influence**

### Component definitions

#### A. Actor-specific institutional sentiment component

Meaning:

- how the source actor feels about the target actor's institutions directly
- derived from actor-held `institution_sentiment` edges and the target actor's memberships

#### B. Institution matrix component

Meaning:

- how the source actor's institutions tend to relate to the target actor's institutions
- derived from canonical `institution_disposition` edges between institution subjects

### Recommended phase-1/phase-2 formula

```text
institution_score =
clamp(
  round(
    (actor_sentiment_component * 0.65)
    + (institution_matrix_component * 0.35)
  ),
  -1000,
  1000
)
```

### Why this weighting

- actor-specific sentiment should dominate because it is the more specific signal
- institution matrix should shape the result without overruling actor-specific evidence by default
- this preserves personalized variation while still allowing shared institutional meaning
- part scores must remain explicit and inspectable because the relationships tab already depends on explainable score composition

### Sub-component formulas

#### Actor sentiment component

For each target membership:

```text
effective_membership_sentiment =
  actor_sentiment_score
  * domain_weight
  * knowledge_weight
```

Recommended defaults:

| Field | Default |
| --- | --- |
| `political` domain weight | `0.50` |
| `ancestry` domain weight | `0.30` |
| `class` domain weight | `0.20` |
| known knowledge weight | `1.00` |
| unknown knowledge weight | `0.35` |

Then:

```text
actor_sentiment_component =
  weighted_average(all effective_membership_sentiment values)
```

#### Institution matrix component

For each `(source membership, target membership)` pair:

```text
effective_matrix_pair_score =
  institution_matrix_score
  * source_membership_weight
  * target_membership_weight
  * matrix_confidence_weight
```

Recommended defaults:

| Field | Default |
| --- | --- |
| ancestry membership weight | `0.50` |
| profession membership weight | `0.50` |
| reviewed matrix edge confidence | `1.00` |
| seeded neutral default confidence | `0.50` |

Then:

```text
institution_matrix_component =
  weighted_average(all effective_matrix_pair_score values)
```

### Missing edge behavior

Missing matrix edges should be treated as:

```text
score = 0
knowledge_state = known
seed_profile_key = missing-neutral-default
matrix_state = defaulted
```

This keeps the system behavior-safe while still exposing that the result is default-derived.

## Authority precedence rules

### Rule 1 - Resolver always wins behaviorally

No consumer may act on raw institutional signals directly.

All behavior must flow through:

```text
institutional inputs -> assembler -> institution_score -> resolver DTO
```

### Rule 2 - Actor-specific signal outranks matrix default

If actor-specific institutional sentiment exists, it should weigh more heavily than matrix defaults.

After the **first explicit actor-specific mutation**, actor-specific institutional sentiment should be treated as the authoritative override for that actor/institution pair unless a higher-order resolver rule explicitly says otherwise.

### Rule 3 - Matrix rows are reference defaults until mutated

The institution matrix is a reference table instantiated per campaign.

There is no approval workflow in this subsystem.

Matrix rows should therefore move through only these meaningful states:

1. `defaulted` - instantiated neutral reference row
2. `mutated` - explicitly changed after campaign instantiation

### Rule 4 - Missing information is neutral, not hostile

Absence of a matrix row or actor sentiment must never silently imply hostility.

### Rule 5 - Direct actor->actor relationship remains more specific than institutional effects

Institutional score is an input into the resolver, not a replacement for actor relationship edges.

---

## Matrix Edge Contract Detail

## Canonical relationship identity

Institution matrix edges should use:

```text
source_type = institution
target_type = institution
relationship_type = institution_disposition
```

### Relationship state fields

| Field | Purpose |
| --- | --- |
| `edge_kind` | Must equal `institution_disposition_matrix` |
| `score` | Canonical numeric value `-1000..1000` |
| `knowledge_state` | Usually `known` |
| `seed_source` | `institution_matrix_default` or explicit authoring source |
| `seed_profile_key` | `known-neutral-default`, `missing-neutral-default`, etc. |
| `matrix_state` | `defaulted` or `mutated` |
| `mutation_state` | `seeded` or `mutated` |
| `mutation_count` | Number of explicit changes |
| `rationale` | Human-readable explanation |
| `authority_scope` | Must equal `institution_matrix` |
| `touched_at` | Timestamp of explicit review/mutation |
| `mutated_by_uid` | Actor/GM/user id when applicable |

### Reverse edge policy

Reverse edges should be **explicitly persisted**, not inferred on read.

Reason:

- asymmetric institution relationships are valid,
- mutation semantics remain clearer,
- migration/backfill logic is easier to reason about,
- explainability is simpler.

---

## Read Path Contract Detail

## `InstitutionDispositionScoreAssemblerService` output

Recommended canonical output:

```php
[
  'score' => 0,
  'weighted_score' => 0,
  'breakdown' => [
    'actor_sentiment' => [...],
    'institution_matrix' => [...],
  ],
  'components' => [
    'actor_sentiment_component' => 0,
    'institution_matrix_component' => 0,
  ],
  'weights' => [
    'actor_sentiment_component' => 0.65,
    'institution_matrix_component' => 0.35,
  ],
  'equation' => '...',
  'authority' => [
    'actor_sentiment' => 'institution_membership_service',
    'institution_matrix' => 'institution_disposition_matrix_service',
    'assembler' => 'institution_disposition_score_assembler',
  ],
]
```

Each sub-score used in the final institutional calculation must remain visible in read output so the relationships tab and future explainability surfaces can show:

- actor-held sentiment contribution
- institution-matrix contribution
- domain weights
- knowledge weights
- final assembled institutional score

### Concrete service contracts

These should now be treated as the intended public contracts unless later requirements force a change.

#### `InstitutionDispositionMatrixService`

Recommended public surface:

```php
loadInstitutionDisposition(
  int $campaign_id,
  string $source_subject_id,
  string $target_subject_id
): array

upsertInstitutionDisposition(
  int $campaign_id,
  string $source_subject_id,
  string $target_subject_id,
  int $score,
  array $context = []
): int

ensureDefaultInstitutionDisposition(
  int $campaign_id,
  string $source_subject_id,
  string $target_subject_id
): int

seedNeutralDefaultsForNewCampaign(
  int $campaign_id
): array

mutateInstitutionDisposition(
  int $campaign_id,
  string $source_subject_id,
  string $target_subject_id,
  int $score,
  array $context = []
): int
```

#### `InstitutionDispositionScoreAssemblerService`

Recommended public surface:

```php
buildActorTargetInstitutionAdjustment(
  int $campaign_id,
  string $source_actor_ref,
  string $target_actor_ref
): array
```

This service should internally:

1. resolve source actor institutional memberships,
2. resolve source actor institutional sentiments,
3. resolve target actor institutional memberships,
4. resolve institution->institution matrix rows for relevant ancestry/profession pairs,
5. assemble one final `institution_score`,
6. return a full explainability breakdown for the relationships tab.

#### Consumer contract

Consumers should only depend on:

```php
[
  'score' => int,
  'weighted_score' => int,
  'components' => array,
  'breakdown' => array,
  'weights' => array,
  'equation' => string,
  'authority' => array,
]
```

No consumer should read institution matrix edges directly.

## Consumers that should use this seam

Phase 1 consumers:

1. `RelationshipsMatrixReadModelService`

Phase 2+ consumers:

1. room chat hostility/adaptation surfaces
2. encounter tactical context assembly
3. GM actor context projection
4. any future institution-aware social UI

---

## Write Path Rules

## Who writes what

| Surface | Allowed writer |
| --- | --- |
| Actor memberships | `InstitutionMembershipService` |
| Actor-held institution sentiment | `InstitutionMembershipService` |
| Institution matrix edges | `InstitutionDispositionMatrixService` |
| Final actor-target disposition | No writer; resolver only |

## Mutation rules

1. **Actor-specific sentiment** may be changed by narrative/runtime events.
2. **Institution matrix edges** may only be changed after campaign instantiation by explicit mutation flows.
3. Seeded neutral matrix edges must be distinguishable from mutated matrix edges.
4. Matrix backfills must be idempotent.

Mutation authority is expected to come from:

- actor-triggered runtime events,
- GM decisions,
- explicit trigger-driven mutation workflows already being built in the disposition subsystem.

### Encounter/round mutation scope

Actor-level disposition mutation should be evaluated as part of the **encounter action / turn / round pipeline**.

Target flow:

```text
encounter action executed
-> action classified against disposition mutation scope
-> matching trigger resolved
-> actor/relationship mutation written
-> later reads observe new state through normal resolver flow
```

Recommended insertion point:

- encounter action execution result handling,
- immediately after authoritative action resolution,
- before downstream consumers rely on updated social state.

Concretely, this should be evaluated from actions flowing through the encounter stack such as:

- `EncounterActionExecutor`
- `EncounterPhaseHandler`
- any canonical combat/damage application seam they already own

### Actor mutation scope

The actor-level mutation scope should include at minimum:

1. direct violence against a target,
2. damage-causing actions,
3. negative-effect spell casting against a target,
4. explicit aid/help actions,
5. theft/betrayal style events,
6. threat/intimidation style events,
7. diplomacy/social success/failure events.

### Institutional mutation scope

Institution-level matrix mutation should **not** happen as a side effect of normal combat turns.

Institutional mutation should only occur through:

- quest-level scripted/narrative implementations,
- explicit institutional storyline outcomes,
- curated mutation workflows that intentionally alter the reference matrix.

That keeps institution matrix authority rare, deliberate, and separate from ordinary actor-level runtime volatility.

Defaults:

- if there is **no actor->actor record**, disposition defaults to neutral
- if there is **no actor->institution sentiment record**, sentiment defaults to neutral
- if there is **no institution->institution matrix record**, matrix contribution defaults to neutral

---

## Migration and Backfill Rules

### Scope for first cut

- only `ancestry <-> profession`
- no same-domain matrix pairs required in phase 1
- no political/cultural expansion yet
- no historical campaign backfill

### Rollout guidance

Do **not** run an old-campaign backfill pass.

Initial rollout should instead target:

1. new matrix-aware writes going forward,
2. new campaigns created after cutover,
3. targeted/manual enablement only where explicitly requested later.

### Duplicate prevention

Use deterministic relationship ids:

```text
institution--{source_subject_id}--institution_disposition--institution--{target_subject_id}
```

### Safe rerun rule

If an existing edge is:

- `mutation_state = seeded`
- `matrix_state = defaulted`

then reruns may update/normalize it.

If an existing edge is:

- `mutation_state = mutated`
- `matrix_state = mutated`

then reruns must not overwrite it.

Because old campaigns are out of scope for this phase, rerun logic mainly applies to:

- repeated creation-time seeding for new campaigns,
- test fixtures,
- explicit manual rollout commands.

---

## Acceptance Scenario Matrix

Codex should implement tests covering these scenarios:

| Scenario | Expected result |
| --- | --- |
| Actor sentiment positive, matrix neutral | positive institutional component, actor signal dominates |
| Actor sentiment neutral, matrix negative | negative institutional component from matrix |
| Actor sentiment positive, matrix negative | blended result, actor signal usually still dominant |
| No actor sentiment, matrix neutral | neutral institutional component |
| No actor sentiment, no matrix edge | neutral institutional component with default provenance |
| Target has multiple memberships | weighted aggregate with explainable breakdown |
| Unknown target membership knowledge | reduced contribution via knowledge weight |
| Actor-specific mutation after neutral default | actor mutation persists and is not overwritten |
| Matrix mutation after default instantiation | mutated matrix persists and is not overwritten |

---

## Remaining Decisions Before Codex Execution

The architecture is now structurally defined, but these items still need explicit values before Codex should implement beyond Phase 1.

### 1. Exact institutional formula constants

Use the currently documented formula constants **as-is for now**:

- actor-sentiment component weight
- institution-matrix component weight
- per-domain weights
- known vs unknown knowledge weights
- seeded/default matrix confidence weights

These are accepted as the working Phase 1/Phase 2 constants unless later product review changes them.

### 2. Trigger catalog for mutation

Still to finalize:

- exact event names for every encounter/social action family beyond the currently obvious set
- exact mutation-classifier contract inputs/outputs
- exact institutional mutation quest hooks

This remains part of the implementation plan and is not a separate architecture track.

### 3. Phase 1 Codex brief lock

Before handoff, Codex should receive a narrow task brief limited to:

1. add `institution_disposition` relationship type,
2. add `InstitutionDispositionMatrixService`,
3. seed ancestry↔profession neutral defaults for new campaigns only,
4. add contract coverage,
5. do **not** wire resolver consumers yet.

### 4. Ancestry analysis backlog

We are intentionally limiting the current system scope to:

- `ancestry`
- `profession`

Future work must include an ancestry analysis pass to define:

- historical alliances,
- inherited biases,
- default cross-ancestry priors,
- where those priors belong in the institution matrix versus actor-specific mutation.

Current recommended ancestry priors for the future matrix are:

| Ancestry | Non-neutral recommended biases |
| --- | --- |
| Human | `+5 Halfling`, `+5 Half-Elf`, `-5 Goblin`, `-5 Orc` |
| Elf | `+5 Leshy`, `+5 Half-Elf`, `-5 Dwarf`, `-5 Goblin`, `-5 Orc`, `-5 Kobold` |
| Dwarf | `+5 Human`, `+5 Halfling`, `-5 Elf`, `-5 Goblin`, `-5 Orc`, `-5 Kobold` |
| Gnome | `+5 Halfling`, `+5 Leshy`, `+5 Goblin`, `-5 Orc` |
| Goblin | `+5 Kobold`, `+5 Ratfolk`, `-5 Human`, `-5 Elf`, `-5 Dwarf`, `-5 Halfling` |
| Halfling | `+5 Human`, `+5 Gnome`, `+5 Dwarf`, `-5 Goblin`, `-5 Orc` |
| Half-Elf | `+5 Human`, `+5 Elf`, `-5 Goblin`, `-5 Orc` |
| Half-Orc | `+5 Human`, `+5 Orc`, `-5 Elf`, `-5 Dwarf`, `-5 Halfling` |
| Leshy | `+5 Elf`, `+5 Gnome`, `+5 Catfolk`, `-5 Goblin` |
| Orc | `+5 Half-Orc`, `+5 Human`, `-5 Elf`, `-5 Dwarf`, `-5 Goblin`, `-5 Halfling` |
| Catfolk | `+5 Tengu`, `+5 Halfling`, `-5 Ratfolk`, `-5 Kobold` |
| Kobold | `+5 Goblin`, `+5 Ratfolk`, `-5 Elf`, `-5 Dwarf`, `-5 Catfolk` |
| Ratfolk | `+5 Goblin`, `+5 Kobold`, `+5 Tengu`, `-5 Catfolk` |
| Tengu | `+5 Catfolk`, `+5 Ratfolk`, `+5 Human`, `-5 Orc` |

Universal rule:

```text
all ancestries -> Undead = -200
```

All unspecified ancestry->ancestry pairs should remain `0`.

### 5. Materialization/persistence revisit

Deferred intentionally.

For now:

- centralize institutional contribution calculation,
- expose it clearly through the assembler contract,
- do **not** store/materialize the assembled institutional score.

We should only revisit persistence later if runtime cost or repeated read pressure proves it necessary.

### 6. Quest/storyline institutional mutation placeholder

Placeholder rule for now:

- institution matrix mutation is allowed only from explicit quest/storyline implementations,
- no generic combat-turn mutation path may write institution matrix edges,
- the first implementation should reserve a hook point only, not a broad automatic institutional mutation system.

---

## Trigger Catalog and Immediate Hostility Rules

The previous trigger work already exists in:

- `DispositionTriggerCatalog`
- `DispositionTriggerService`
- `ActorDispositionService`
- `RelationshipAttitudeService::applyRelationshipDispositionDelta()`

### Current gap

The trigger catalog and trigger normalization service exist, but the **relationship-delta path is not yet wired end-to-end into a shared trigger consumer**. Right now the catalog is more complete than its runtime integration.

That means Phase 0/1 implementation work must preserve this rule:

```text
trigger catalog exists
!=
trigger mutation is fully integrated
```

### Neutral default rule

For most actors toward most other actors and institutions:

```text
no record = neutral
```

This applies to:

- actor -> actor
- actor -> institution
- institution -> institution

### Violent action rule

Violent actions must be explicit trigger-catalog items and must immediately force hostile sentiment toward the direct target.

These events include at minimum:

- `attack`
- `damage`
- `harm`
- `negative_effect_spell`

### Required trigger outcome

For a direct violent action against a target:

```text
directed target relationship score -> -100 immediately
```

That is stronger than a simple negative delta and should be modeled as an **immediate relationship override contract**, not merely a soft decrement.

### Required trigger contract extension

The trigger contract should support explicit override fields in addition to deltas:

```php
[
  'event_type' => 'attack',
  'actor_delta' => -15,
  'relationship_delta' => -100,
  'relationship_score_override' => -100,
  'durable' => true,
  'repeat_window_sec' => 1800,
]
```

### Trigger responsibilities

| Trigger category | Actor baseline effect | Actor->actor effect | Actor->institution effect |
| --- | --- | --- | --- |
| small talk / conversation | minor positive or none | minor positive | none by default |
| diplomacy / help / gift | modest positive | positive | none unless institution-specific context exists |
| threat / intimidation | modest negative | negative | possible negative only if institution authority is explicitly invoked |
| theft / betrayal | negative | strong negative | optional institution mutation when theft targets an institution-owned asset |
| attack / damage / negative spell | negative self-bias allowed | **immediate -100 toward direct target** | optional institution mutation only when institutional ownership/affiliation is explicit |

### Consumer contract for violent actions

When a violent action event is processed:

1. actor baseline disposition may be adjusted via `actor_delta`
2. direct relationship to the target must be set to hostile immediately via `relationship_score_override = -100`
3. downstream resolver consumers must then observe hostility through normal score-first relationship reads

This preserves the authority rule:

```text
trigger -> writer mutation
writer state -> resolver input
resolver -> behavior
```

It does **not** allow triggers to bypass the resolver.

### Required shared mutation classifier

Codex should plan for a shared mutation-classification seam rather than hardcoding trigger checks in many action handlers.

Recommended target:

```php
DispositionMutationClassifierService::classifyActionMutationScope(
  int $campaign_id,
  string $action_type,
  array $action_context
): array
```

Expected output:

```php
[
  'matched' => true,
  'event_type' => 'attack',
  'target_scope' => 'direct_actor',
  'apply_actor_disposition' => true,
  'apply_relationship_disposition' => true,
  'apply_institution_disposition' => false,
  'trigger_context' => [...],
]
```

This lets the encounter turn/round phase bounce authoritative action results against one mutation scope service before writing disposition effects.

---

## Implementation Plan for Codex

## Phase 0 - Freeze authority and remove ambiguity

### Goal

Stop parallel subsystem drift before additional code lands.

### Work

1. Treat the current `CampaignSubjectRegistryService` institution-matrix changes as **experimental**.
2. Do **not** keep institution->institution edges under `relationship_type = institution_sentiment`.
3. Add architecture comments stating:
   - actor sentiment is actor-specific,
   - institution matrix is shared canonical lookup,
   - resolver remains behavioral authority.

### Acceptance

- no new code continues the split-authority path,
- target relationship-type naming is decided.

---

## Phase 1 - Introduce canonical institution matrix service

### Goal

Create `InstitutionDispositionMatrixService`.

### Work

1. Add service class and DI wiring.
2. Add methods:
   - `loadInstitutionDispositionEdge(...)`
   - `upsertInstitutionDispositionEdge(...)`
   - `seedNeutralDefaultsForCampaign(...)`
3. Persist matrix edges under:
   - `source_type = institution`
   - `target_type = institution`
   - `relationship_type = institution_disposition`
4. Add targeted contract tests.

### Files

- `src/Service/InstitutionDispositionMatrixService.php` (new)
- `dungeoncrawler_content.services.yml`
- tests (new contract coverage)

### Acceptance

- ancestry/profession matrix edges can be seeded and read,
- no actor-layer services are modified yet.

---

## Phase 2 - Extract institution score assembly from matrix read model

### Goal

Create a reusable institutional adjustment assembler.

### Work

1. Add `InstitutionDispositionScoreAssemblerService`.
2. Move current institutional breakdown logic out of `RelationshipsMatrixReadModelService`.
3. Extend assembler logic to incorporate:
   - actor-held institution sentiments,
   - target memberships,
   - institution->institution matrix lookups.

### Suggested composition formula

```text
institution_score =
  combine(
    actor_specific_sentiment_to_target_memberships,
    institution_matrix_sentiment_between_source_memberships_and_target_memberships
  )
```

Exact weighting should be explicit and tested.

### Acceptance

- `RelationshipsMatrixReadModelService` delegates institution score assembly,
- assembler returns score + breakdown + equation.

---

## Phase 3 - Backfill neutral institutional matrix

### Goal

Populate ancestry/profession neutral defaults safely.

### Work

1. Backfill all existing ancestry/profession institution subjects by campaign.
2. Seed both directions:
   - ancestry -> profession
   - profession -> ancestry
3. Mark all seeded rows as:
   - neutral
   - known
   - unreviewed

### Acceptance

- every campaign with ancestry/profession subjects has complete neutral cross-domain pairs,
- idempotent reruns do not create duplicates.

---

## Phase 4 - Wire resolver consumers to the assembler

### Goal

Make the institutional score path canonical everywhere.

### Work

1. Update `RelationshipsMatrixReadModelService`.
2. Identify any other read paths that manually derive institutional effects.
3. Route them through the assembler.
4. Keep `DispositionResolverService` interface stable if possible.

### Acceptance

- one canonical path exists for institutional score assembly,
- no consumer re-derives institution math independently.

---

## Phase 5 - Review/admin tooling

### Goal

Enable explicit review of matrix rows.

### Work

1. Add review UI/API for institution disposition matrix edges.
2. Expose review status and rationale.
3. Add bulk review workflows for ancestry/profession matrix values.

### Acceptance

- seeded neutral defaults can be promoted/mutated intentionally,
- mutations are distinguishable from defaults.

---

## Phase 6 - Cleanup and authority lock

### Goal

Remove ambiguity and lock contracts.

### Work

1. Ensure `CampaignSubjectRegistryService` is no longer pretending to own matrix semantics.
2. Ensure actor and institution sentiment edge types are distinct.
3. Add end-to-end contract tests asserting:
   - actor sentiment contributes,
   - institution matrix contributes,
   - resolver is final behavioral authority.

### Acceptance

- authority boundaries are explicit in code and tests,
- architecture is explainable from service boundaries alone.

---

## Codex Implementation Rules

Codex should follow these rules during implementation:

1. **Do not bypass the resolver.**
2. **Do not let read models invent their own institutional scoring formulas.**
3. **Do not overload `institution_sentiment` for institution->institution edges.**
4. **Do not mix actor-specific writes with institution-matrix writes in the same service.**
5. **Prefer additive extraction/refactor over large rewrites.**
6. **Keep contract tests updated at every phase.**

---

## Immediate Next Step

The next Codex task should be:

> Implement **Phase 1** only: introduce `InstitutionDispositionMatrixService` with a distinct `institution_disposition` relationship type, add neutral ancestry/profession seeding, and add contract coverage. Do not wire resolver consumers yet.

## Phase 1 Codex Brief

Use this brief for the first Codex implementation package:

### Objective

Add the canonical institution-level matrix substrate without changing resolver consumers yet.

### Scope

1. Introduce `InstitutionDispositionMatrixService`.
2. Use `relationship_type = institution_disposition` for institution->institution matrix edges.
3. Seed neutral default rows for:
   - `ancestry -> profession`
   - `profession -> ancestry`
4. Apply seeding only for **new campaigns / new writes / explicit manual enablement**, not historical backfill.
5. Add contract tests for:
   - new service existence
   - relationship type separation
   - neutral default edge shape
   - idempotent seeding behavior

### Explicitly out of scope

1. Do not wire `DispositionResolverService` consumers yet.
2. Do not materialize/store assembled institutional contribution.
3. Do not add automatic institution-matrix mutation from encounter combat turns.
4. Do not expand beyond `ancestry` and `profession`.
5. Do not redesign formula constants in this phase.

### Required constraints

1. Preserve resolver-first behavioral authority.
2. Keep actor-held institution sentiment and institution-matrix edges separate.
3. Keep no-record defaults neutral.
4. Keep trigger integration future-ready but do not solve the full shared runtime consumer in this phase.

### Required deliverables

1. New `InstitutionDispositionMatrixService`
2. DI wiring
3. Neutral default seeding implementation
4. Contract tests
5. Minimal architecture comments where needed

That keeps the first implementation chunk small, testable, and authority-safe.
