# CEO Work Item — Storyline Generator Validator Gate

- Agent: ceo-copilot-2
- Created: 2026-06-29
- Topic: storyline-generator-validator-gate
- Priority: P1
- Org status: disabled — execute implementation only after Board re-enables org operations

## Summary

Leverage the storyline validation subsystem as a mandatory gate for the storyline generator subsystem so generation only emits contract-valid canonical objects.

## Required contract

1. Storyline generation must run validator checks before output is accepted/persisted.
2. Validation must cover the full object chain:
   - storyline
   - quests
   - objectives
   - tasks
   - locations
   - actors
   - items
3. Any validation failure must hard-fail generation with explicit diagnostics (no fallback/no silent recovery).
4. Generated objects must preserve canonical cross-link integrity across the full chain.

## Acceptance criteria

- Generator cannot emit/persist outputs unless validation returns PASS for all required object levels.
- Invalid generated data is rejected with explicit, actionable contract-failure diagnostics.
- Tests cover:
  - valid generation path (PASS)
  - each invalid object-level failure path (FAIL)
  - cross-link integrity enforcement across storyline->quest->objective->task and entity references.
- Storyline generator + validator integration path is documented in the work notes.

## Current state analysis (2026-06-29)

### What is already in place

1. **Storyline validator subsystem exists and is multi-stage.**
   - `StorylineManagerService::validateStorylineEndToEndContract(...)` runs:
     - schema,
     - cross-references,
     - questline progression,
     - navigation progression,
     - objective control chain.
2. **Generator already routes storyline definitions through validator-backed normalization.**
   - `StorylineGenerationService::normalizeGeneratedPackage(...)` and
     `normalizeGeneratedBootstrapPackage(...)` both call
     `StorylineManagerService::normalizeStorylineDefinition(...)`.
3. **Objective contracts are enforced for quest templates.**
   - `StorylineGenerationService::assertQuestTemplateConformsToObjectiveContract(...)`
     enforces objective phase contract via `ObjectiveTypeService`.
4. **Cross-reference checks already cover core actor/location/item anchors.**
   - `StorylineManagerService::validateStorylineCrossReferences(...)` +
     objective-control validation enforce:
     - actor/contact anchors,
     - room/location anchors,
     - item anchors (`item` refs in objectives).

### Gaps against Board directive

1. **No single pre-persist gate for the full generated bundle.**
   - In bootstrap/expansion flow, quest templates are persisted before storyline create/replace, so partial persistence is possible if storyline validation fails later.
2. **Task-level contract is not a first-class validated object.**
   - Current validator is strong on storyline/quest/objective chains, but task objects are not explicitly modeled/validated as their own contract layer.
3. **Validator coverage depends on service availability for schema stage.**
   - Some schema-stage checks are conditional on `StateValidationService`; cross-reference/control-chain layers still run, but full gate behavior is not currently centralized as one explicit generation contract decision.

## Future state analysis (target)

### Contract target (generator must satisfy before any persistence)

1. Validate and approve one **generation bundle contract** containing:
   - storyline,
   - quests,
   - objectives,
   - tasks,
   - locations,
   - actors,
   - items.
2. Reject bundle with explicit per-layer diagnostics on any failure.
3. Persist only after bundle validation PASS — all-or-nothing, inside a DB transaction.

---

## Target architecture

### Object hierarchy the validator must own end-to-end

```
Storyline
 └─ Chapter[]
     └─ Scene[]  (must have quest_ids progression gate)
         └─ Quest template[]  (objectives_schema)
             └─ Objective[]  (type, completion_criteria, HOW trigger / next_step)
                 └─ Task[] = objective.children  (task_id, description, completion_criteria, entity refs)
                     └─ entity refs: target(actor) | location | item
```

Entity pools cross-linked throughout the hierarchy:
- **Actors** — declared in `contacts[]` + `asset_references[asset_type=npc]` + `metadata.generated_outline` boss/NPC ids
- **Locations** — declared in `asset_references[asset_type=room|location]` + scene_ids + canonical dungeon/room registry
- **Items** — declared in `asset_references[asset_type=item]`

---

### How tasks map to the existing code

Tasks are `objective.children` in `ObjectiveTypeService`.  
Currently only `composite` and `escort` objective types `supports_children = true`.  
The existing `validateObjectiveDefinition` recursively validates children using the same contract as the parent objective.

**Gap:** the current `children` contract does NOT enforce:
- `task_id` as a required first-class field (child uses `objective_id`, which is inconsistently populated)
- children are not checked against the bundle entity registry
- the AI prompt never instructs the model to generate task children
- the fallback `buildQuestTemplate` generates no children at all

**Design decision:** Tasks remain as `objective.children` — we do NOT introduce a new shape. We tighten the existing contract:
1. When an objective has `children`, each child must have `objective_id` (treated as task_id), `description`, `completion_criteria`, and valid entity refs.
2. `composite` type objectives MUST have children (currently optional in practice).
3. For `escort` type, children represent escort milestones.
4. Other types may have children only if `supports_children` is true.

---

### Validation pipeline (target — `StorylineManagerService::validateStorylineEndToEndContract`)

| Stage | Status | Scope | What it checks |
|---|---|---|---|
| 1. schema | ✅ Exists | storyline def | Required fields, types, shapes |
| 2. cross_references | ✅ Exists | storyline def | Chapter/scene/contact/asset anchor integrity, entry_point contract |
| 3. questline_progression | ✅ Exists | storyline def | primary_quest_id, reachability, no cycles, terminal nodes |
| 4. navigation_progression | ✅ Exists | storyline def | entry_dungeon, connectors, source/target ids |
| 5. objective_control_chain | ✅ Exists | quest templates | objective_id, HOW trigger, completion_criteria, dependency graph |
| 6. **task_contract** | ❌ NEW | quest template children | objective_id on children, description, completion_criteria, no duplicate ids, type validity |
| 7. **entity_linkage** | ❌ NEW | objectives + tasks | all actor/location/item refs resolve against storyline entity pool + canonical registry |

**Rule:** In generation context, all 7 stages are mandatory and unconditional. No stage can be conditional on optional service wiring.

---

### Stage 6 — task_contract detail

Triggered by: `validateObjectiveControlChainStage` already iterating all quests + objectives.  
Stage 6 adds a second pass over the same objective graph checking children.

For each objective with `children`:
1. Objective type must be in `supports_children` types (`composite`, `escort`).
2. For each child (task):
   - `objective_id` must be non-empty and unique within the quest.
   - `description` must be non-empty.
   - `completion_criteria` must be valid shape (kind, metric, description).
   - If child type is a player-interaction type: `next_step` required.
3. `completion_criteria.kind = all_children` on parent objective is required when children are present (enforces parent-tracks-children semantics).

**New method:** `validateTaskContractStage(array $quest_templates): array`  
- Input: the `quest_templates` array from the generation bundle (not the storyline definition alone).
- Loops objectives → children in each quest template's `objectives_schema`.
- Returns aggregated errors.
- Called from `assertValidGenerationBundle`, NOT wired into the existing 5-stage `validateStorylineEndToEndContract` (because the manager's method takes a storyline definition, not quest templates).

---

### Stage 7 — entity_linkage detail

Builds a **bundle entity registry** from the storyline definition:
```
actors:    contacts[].entity_id
           + asset_references[asset_type=npc][].asset_id
           + metadata.generated_outline.big_boss.boss_id
           + metadata.generated_outline.sub_bosses[].boss_id
           + metadata.generated_outline.dungeons[].rooms[].npc_ids[]

locations: scene_ids (from chapters[].scenes[].scene_id)
           + asset_references[asset_type=room|location][].asset_id
           + canonical_location_index.room_ids (from loadCanonicalLocationTemplateIndex)

items:     asset_references[asset_type=item][].asset_id
```

For each objective and each task (child) across all quest templates:
- `target` field → must exist in `actors` (for kill/interact/investigate/escort)
- `location` / `location_id` field → must exist in `locations`
- `destination` / `destination_id` field → must exist in `locations`
- `item` field → must exist in `items`

**New method:** `buildBundleEntityRegistry(array $storyline_definition): array`  
Returns `['actors' => [...], 'locations' => [...], 'items' => [...]]`.

**New method:** `validateEntityLinkageStage(array $quest_templates, array $entity_registry): array`  
- Input: quest_templates array + entity_registry from above.
- For each objective + child: validates all entity refs.
- Returns errors.
- Called from `assertValidGenerationBundle`.

---

### AI prompt changes required (generation_source = ai)

Current prompt does NOT instruct AI to generate:
- `children` (tasks) on objectives
- `asset_references[asset_type=item]` for items referenced in objectives
- `entry_point` block in `metadata.generated_outline` (required by stage 2 cross-reference)

Additions to `generatePackageWithAi` prompt:
```
- Each quest template's objectives_schema must be an array of phases.
- Each phase must have a 'phase' integer and an 'objectives' array.
- Each objective must have: objective_id, type (one of: collect|kill|investigate|explore|escort|interact|composite),
  description, completion_criteria (kind, metric, description), and next_step for player-action types.
- Composite objectives must include a 'children' array of task objects, each with:
  objective_id (unique within quest), type, description, completion_criteria, and next_step if player-action.
- All NPC targets in objectives must be declared in storyline contacts[] or asset_references[asset_type=npc].
- All location/destination targets must be declared in storyline asset_references[asset_type=room|location].
- All item targets must be declared in storyline asset_references[asset_type=item].
- metadata.generated_outline must include an entry_point object with:
  primary_quest_giver_id, primary_quest_giver_name, primary_dungeon_id, primary_chapter_id,
  primary_scene_id, primary_location_id, introduction_path (direct|brokered), detail_summary.
```

---

### Fallback generator changes required (generation_source = fallback)

`buildQuestTemplate` generates 4 objective shapes (entrance/sanctum/boss+lieutenant/default).  
None currently have `children`.

Changes:
- `kill` type objectives (boss/lieutenant): add a `composite` parent wrapping two children:
  1. `investigate` child: locate and engage the boss.
  2. `kill` child: defeat the boss.  
  This ensures the structure validates under stage 6 (composite with children).
- All objectives: ensure `target` refs are populated from the boss/room ids already in the generator context.
- All `investigate`/`interact` objectives: add `next_step` (HOW trigger) — e.g. `"Enter the room and engage"`.

Also: `generateFallbackPackage` must build `entry_point` in `metadata.generated_outline` using the existing context (speaker_npc_id, entry_dungeon_id, entry_room_id from the request).

---

### Persistence model (current vs target)

**Current bootstrap flow (broken):**
```
normalizeGeneratedBootstrapPackage(...)    // normalization + partial validation
persistQuestTemplates(...)                 // WRITES quest templates — point of no return
createCampaignStoryline(...)               // WRITES storyline — can fail here leaving orphan quest templates
```

**Target bootstrap flow (correct):**
```
normalizeGeneratedBootstrapPackage(...)    // normalization only (no persistence)
assertValidGenerationBundle(bundle)        // GATE: all 7 stages + task + entity — throws on failure
                                           //       NOTHING HAS BEEN WRITTEN YET
$txn = $this->database->startTransaction()
  try {
    persistQuestTemplates(...)             // write quest templates
    createCampaignStoryline(...)           // write storyline
    $txn->commit()
  } catch (\Throwable $e) {
    unset($txn)                            // Drupal DB: unset triggers rollback
    throw new \RuntimeException(
      'Storyline bundle persist failed: ' . $e->getMessage()
    )
  }
```

Same pattern applies to `processPendingExpansionJobs` expansion write path.

---

### `assertValidGenerationBundle` method design

```php
protected function assertValidGenerationBundle(array $bundle): void {
    $errors = [];

    // 1. Storyline definition — all 5 existing stages
    $storyline = $bundle['storyline_definition'] ?? [];
    $result = $this->storylineManager->validateStorylineEndToEndContract($storyline, 'definition');
    foreach ($result['stages'] as $stage_name => $stage) {
        foreach ($stage['errors'] as $error) {
            $errors[] = "[storyline.{$stage_name}] {$error}";
        }
    }

    // 2. Quest templates — objective phase contract (existing assertObjectivePhases)
    foreach ($bundle['quest_templates'] ?? [] as $template) {
        $template_id = (string) ($template['template_id'] ?? 'unknown');
        try {
            $this->assertQuestTemplateConformsToObjectiveContract($template);
        } catch (\InvalidArgumentException $e) {
            $errors[] = "[quest.{$template_id}.objectives] " . $e->getMessage();
        }
    }

    // 3. Task contract — stage 6 (new)
    $task_errors = $this->validateTaskContractForBundle($bundle['quest_templates'] ?? []);
    foreach ($task_errors as $error) {
        $errors[] = "[task_contract] {$error}";
    }

    // 4. Entity linkage — stage 7 (new)
    $registry = $this->buildBundleEntityRegistry($storyline);
    $entity_errors = $this->validateEntityLinkageForBundle($bundle['quest_templates'] ?? [], $registry);
    foreach ($entity_errors as $error) {
        $errors[] = "[entity_linkage] {$error}";
    }

    if ($errors !== []) {
        throw new \InvalidArgumentException(
            'Storyline generation bundle failed contract validation: ' . implode('; ', $errors)
        );
    }
}
```

---

## Implementation plan

### Phase 1 — Pre-persist bundle gate (wire existing validation, add transaction)

**Goal:** Hard-fail on invalid bundles, prevent partial persistence. No new validator stages yet.

**Scope:**
- Add `assertValidGenerationBundle(array $bundle): void` to `StorylineGenerationService` (stages 1–5 + existing objective contract only).
- Add `buildBundleEntityRegistry(array $storyline_definition): array` stub (returns empty registry for now).
- Wrap `persistQuestTemplates` + `createCampaignStoryline` in DB transaction in `bootstrapCampaignStoryline`.
- Same in `processPendingExpansionJobs`.
- Call `assertValidGenerationBundle` before the transaction block in both paths.

**Gate to exit:**
- Unit test: valid bundle → no exception → both templates and storyline written.
- Unit test: storyline definition missing `entry_point` → `InvalidArgumentException` before any write.
- Unit test: bad objective phase → exception before any write.
- Integration test: forced write failure inside transaction → quest template writes rolled back.

---

### Phase 2 — Task contract (stage 6)

**Goal:** Tasks (objective children) become first-class validated objects.

**Scope:**
- Add `validateTaskContractForBundle(array $quest_templates): array` to `StorylineGenerationService`.
  - For each template → each phase → each objective with `children`:
    - Objective type must support children (`composite`, `escort`).
    - Objective `completion_criteria.kind` must be `all_children`.
    - Each child: `objective_id` required, unique in quest, `description` required, `completion_criteria` valid.
    - Each child: `next_step` required when type is player-interaction.
- Update `buildQuestTemplate` fallback to wrap `kill` objectives in `composite` with children.
- Add `next_step` to all player-interaction objectives in fallback generator.
- Add `entry_point` to fallback `generateFallbackBootstrapPackage` metadata.
- Update AI prompt for `generatePackageWithAi` and `generateBootstrapPackageWithAi` with task + entry_point requirements.
- Wire `validateTaskContractForBundle` into `assertValidGenerationBundle` as stage 6.

**Gate to exit:**
- Unit test: quest template with valid composite+children → PASS.
- Unit test: composite objective missing children → FAIL stage 6.
- Unit test: child missing `objective_id` → FAIL stage 6.
- Unit test: child missing `completion_criteria` → FAIL stage 6.
- Unit test: non-supporting type with children → FAIL stage 6.
- Fallback generator produces valid tasks for all 4 room roles.

---

### Phase 3 — Entity linkage (stage 7)

**Goal:** Every actor/location/item reference in objectives and tasks must resolve in the bundle.

**Scope:**
- Implement `buildBundleEntityRegistry(array $storyline_definition): array` fully.
  - Actors: contacts + npc asset_refs + outline boss/NPC ids.
  - Locations: scene_ids + room/location asset_refs + canonical location index.
  - Items: item asset_refs.
- Add `validateEntityLinkageForBundle(array $quest_templates, array $entity_registry): array`.
  - For each objective + child: validate `target`, `location`, `location_id`, `destination`, `destination_id`, `item` refs.
  - Hard-fail with `[entity_linkage] quest.{template_id}.objective.{objective_id}: target '{x}' not in entity registry`.
- Wire into `assertValidGenerationBundle` as stage 7.
- Update fallback generator to declare all referenced entities in `asset_references` and `contacts`.

**Gate to exit:**
- Unit test: objective target matches declared contact → PASS.
- Unit test: objective target not declared anywhere → FAIL stage 7.
- Unit test: location ref not in asset_references and not canonical → FAIL stage 7.
- Unit test: item ref not in asset_references → FAIL stage 7.
- Unit test: child task with undeclared location → FAIL stage 7.

---

### Phase 4 — Transaction-safe all-or-nothing persistence

**Goal:** The existing transaction wrap introduced in Phase 1 is hardened and verified.

**Scope:**
- Confirm `$this->database->startTransaction()` / `unset($txn)` (Drupal rollback pattern) is correct across both paths.
- Add RCA-oriented log on rollback including bundle context (template_ids, storyline template_id, campaign_id).
- Confirm expansion job status is set back to `failed` on rollback.
- Confirm no orphan quest templates can remain after a failed storyline write.

**Gate to exit:**
- Integration test: mock storyline write to throw, confirm no quest templates persist.
- Integration test: mock quest template write to throw, confirm no storyline persists.
- Log output on failure includes campaign_id, storyline template_id, and failing error message.

---

### Phase 5 — Explorer, library validation, and test coverage completion

**Goal:** All validation surfaces visible in Explorer; all canonical storyline templates pass 7-stage gate.

**Scope:**
- `StorylineExplorerPageController::collectValidationDiagnostics()`: surface task_contract + entity_linkage stage results.
  - Add stage 6 + 7 rows to the existing validation diagnostic table.
- Validate all three canonical library templates through the full 7-stage gate:
  - `torment-and-legacy`, `threshold-of-knowledge`, `little-trouble-in-big-absalom`.
  - Fix any data gaps surfaced (e.g. missing entry_point, missing task children, missing entity declarations).
- Full integration test: generate TAL bootstrap bundle end-to-end → assert PASS all 7 stages.
- Negative test: inject undeclared actor ref into TAL objective → assert FAIL at stage 7 with expected diagnostic.
- Negative test: inject child objective missing `objective_id` → assert FAIL at stage 6.

**Gate to complete item:**
- All 5 phase gates passed.
- Explorer shows all 7 validation stages with per-stage error/pass status.
- TAL, TOK, LTBA all pass the 7-stage bundle validator when loaded from canonical JSON files.
- CEO outbox entry written, committed, and pushed.

---

## File seams (primary)

| File | Phase | Changes |
|---|---|---|
| `src/Service/StorylineGenerationService.php` | 1–4 | `assertValidGenerationBundle()`, `buildBundleEntityRegistry()`, `validateTaskContractForBundle()`, `validateEntityLinkageForBundle()`, DB transaction wrap in bootstrap + expansion |
| `src/Service/StorylineGenerationService.php` | 2 | Update `buildQuestTemplate` fallback: composite wrapping for boss/lieutenant, `next_step` on player-action objectives, `entry_point` in fallback outline |
| `src/Service/StorylineGenerationService.php` | 2 | Update `generatePackageWithAi` + `generateBootstrapPackageWithAi` prompts: task children, entity declaration requirements, entry_point shape |
| `src/Service/StorylineManagerService.php` | 3 | `buildBundleEntityRegistry()` helper (or extract to generator) |
| `src/Controller/StorylineExplorerPageController.php` | 5 | Surface stage 6 + 7 in validation diagnostic output |
| `config/examples/templates/dungeoncrawler_content_storylines/*.json` | 5 | Fix any entry_point / task / entity declaration gaps in canonical library templates |
| `tests/src/Unit/Service/StorylineGenerationServiceTest.php` | 1,2,3,4 | Per-phase unit tests |
| `tests/src/Unit/Service/StorylineManagerServiceTest.php` | 2,3 | Task contract + entity linkage stage tests |
