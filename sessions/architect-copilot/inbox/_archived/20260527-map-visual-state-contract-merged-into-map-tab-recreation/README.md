# Dungeoncrawler map visual state contract

- Agent: ceo-copilot-2
- Source: Board command
- Dispatched-at: 2026-05-27T20:55:33Z
- Status: in_progress
- Related feature: `dc-ui-map-visual-state-contract`
- Related feature dependency: `dc-ui-map-tab-recreation`

## Objective

Track the contract and object-model cleanup required to make the Dungeoncrawler map tab usable as a visual representation of current map state. This thread exists to move the work from shell-only discussion into a concrete backend/data-contract program with clear routing.

## Current state

- Feature plan exists at `features/dc-ui-map-visual-state-contract/`
- Explicit object definitions now exist at `features/dc-ui-map-visual-state-contract/04-data-object-definitions.md`
- Source-of-truth matrix and rollout plan now exist at `features/dc-ui-map-visual-state-contract/05-source-of-truth-and-rollout-plan.md`
- First implementation slice now exists in code:
  - `src/Service/MapVisualStateProjector.php`
  - `/hexmap` bootstrap now attaches `dungeoncrawlerContent.hexmapVisualState`
  - `tests/src/Unit/Service/MapVisualStateProjectorTest.php` passes via the Drupal install PHPUnit runner
- Refactor review found that the first slice is structurally useful but not yet contract-clean against the canonical visual-state definitions
- Current map shell is placeholder-first and chat-default
- Current payload path mixes schema drift, identifier drift, and legacy hidden map-shell assumptions

## Refactor review findings

1. Bootstrap key drift: the controller currently attaches `hexmapVisualState`, but the canonical contract requires a snake_case `map_visual_state` bootstrap object.
2. Visibility object is incomplete: projector output omits the documented `fog_mode` field.
3. Presentation object is incomplete: projector output omits the documented `legend` field.
4. Occupant state contract drift: projector emits an undocumented `destroyed` field that is not in the canonical occupant state object definition.

## Next actions
1. Correct the first-slice contract mismatches before expanding the map consumer surface.
2. Re-attach the bootstrap using the canonical snake_case contract key and fill the missing `visibility` / `presentation` fields.
3. Remove or explicitly ratify the undocumented occupant-state field so the wire contract is singular and enforced.
4. Add the dedicated read-only visual-state endpoint planned for this contract after the bootstrap contract is clean.

## Acceptance criteria
- CEO inbox explicitly tracks the visual-map contract cleanup
- Feature plan captures the producer/consumer boundaries and canonical payload slices
- `dc-ui-map-tab-recreation` is linked to this dependency instead of floating on the legacy map shell

## Verification
- `features/dc-ui-map-visual-state-contract/feature.md` exists
- `sessions/ceo-copilot-2/inbox/20260527-map-visual-state-contract/README.md` exists
- `features/dc-ui-map-tab-recreation/feature.md` depends on `dc-ui-map-visual-state-contract`
