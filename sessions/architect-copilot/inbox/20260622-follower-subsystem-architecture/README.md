# Architecture Request — Follower Subsystem

- Agent: architect-copilot
- Created: 2026-06-22
- Topic: dungeoncrawler-follower-subsystem
- Priority: P1

## Summary

Dungeoncrawler now has partial familiar and animal companion support, but it does **not** yet have one coherent follower subsystem. The codebase already contains important reusable pieces:

- `AnimalCompanionService` resolves animal companions into **NPC-style runtime payloads**
- `NpcService` / `NpcSheetGenerationService` already provide an existing NPC generation and sheet pipeline
- `FamiliarService` provides backend familiar rules, storage, and APIs

The next architecture step should be to **leverage the existing NPC-style / generated-sheet framework** rather than inventing a separate parallel actor model for familiars, animal companions, and similar subordinate followers.

## What to do

1. Review all current follower-adjacent implementation paths:
   - `FamiliarService`
   - `AnimalCompanionService`
   - `NpcService`
   - `NpcSheetGenerationService`
   - campaign runtime sync / hexmap actor projection
2. Define a unified **Follower Subsystem** that covers:
   - familiars
   - animal companions
   - future companions / allied subordinate actors
3. Specify when a follower should:
   - remain a lightweight summary follower record
   - resolve into an NPC-style runtime actor
   - receive a dedicated sheet/reference UI
4. Reuse the existing NPC/generateNPC-style framework where possible instead of building a second actor stack.
5. Define the storage contract between:
   - owner character build-time selections
   - follower canonical record
   - campaign runtime follower actor payload
6. Define the render path for:
   - follower summary section on character sheets
   - dedicated follower sheet/reference page
   - combat/runtime actor presentation

## Findings gathered so far

### Existing reusable pieces
- `src/Service/AnimalCompanionService.php`
  - already resolves companions into an **NPC-style runtime payload**
  - already computes species/stage/specialization/stats/attacks/support benefit
- `src/Service/NpcService.php`
  - existing canonical NPC framework
- `src/Service/NpcSheetGenerationService.php`
  - existing NPC sheet generation pipeline
- `src/Service/FamiliarService.php`
  - familiar record schema
  - familiar ability catalog
  - APIs for create/reset, daily abilities, damage/death/replacement, touch-spell delivery, witch spell storage

### Current gaps
- Familiar is **not** a full NPC-style actor yet
- Familiar has no dedicated sheet/reference page
- Follower display on the character sheet was only summary-level and had to be added separately
- Familiar runtime is still primarily backend/service-level, not a first-class campaign actor
- No single follower subsystem boundary unifies familiar + companion + future follower handling

### Current live/product observations
- Character `1026` had a familiar-granting feat but no saved familiar choices because the older wizard flow never surfaced the familiar workflow
- The wizard now surfaces familiar selection and the character sheet now shows a pending follower state
- This confirms we need both:
  - creation-time follower configuration
  - a true post-creation follower model/sheet/runtime

## Required outcomes

- A proposed `FollowerSubsystem` architecture and service graph
- Reuse plan for `NpcService` / `NpcSheetGenerationService` / `AnimalCompanionService`
- A decision on whether familiars become:
  - full NPC-style actors
  - partial actors with specialized sheets
  - or a hybrid model
- Canonical storage contract for follower creation, progression, and campaign runtime
- UI plan for follower sheets/reference pages and owner-character integration
- Migration plan from current partial familiar/animal-companion paths

## Acceptance criteria

- The design does **not** create a redundant second actor framework when the existing NPC-style system can be extended
- Familiar and animal companion handling share a coherent subsystem boundary
- The design covers both build-time configuration and runtime actor behavior
- The resulting follower model is explicit enough to support a dedicated follower sheet/reference page
