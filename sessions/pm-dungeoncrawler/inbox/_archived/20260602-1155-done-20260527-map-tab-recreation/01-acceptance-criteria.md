# Acceptance Criteria (PM-owned)

## Gap analysis reference

> Before writing criteria below, confirm the gap analysis in `features/dc-ui-map-tab-recreation/feature.md` and `02-implementation-notes.md` is complete.

## Happy Path
- [ ] `[EXTEND]` `/hexmap` exposes a visible `Map` tab or equivalent first-class shell navigation entry that coexists with the support-surface navigation model.
- [ ] `[EXTEND]` `Map` is the default active shell destination for a player with no prior persisted UI state.
- [ ] `[EXTEND]` Selecting `Map` from another support surface returns focus to the live board without forcing the user out of the current `/hexmap` route.

## Edge Cases
- [ ] `[EXTEND]` Persisted legacy sidebar/drawer keys are migrated or normalized so prior users do not land in an invalid hidden state after the map tab is added.
- [ ] `[EXTEND]` Narrow-screen behavior still presents a coherent return-to-board action when overlays/drawers are open.

## Failure Modes
- [ ] `[TEST-ONLY]` Invalid persisted shell-state values fall back to `map` cleanly.
- [ ] `[EXTEND]` If a support panel cannot restore its prior state, the shell still returns the user to a functional `Map` default rather than leaving the page in a broken intermediate state.

## Permissions / Access Control
- [ ] Anonymous user behavior: no new anonymous access is introduced.
- [ ] Authenticated user behavior: existing campaign/session permissions still govern what data the user can see once the shell surface changes.
- [ ] Admin behavior: debug/admin-only surfaces remain protected and are not exposed by the new navigation affordance.

## Data Integrity
- [ ] No data loss on update/migration.
- [ ] Rollback path identified for shell-state persistence changes.

## Knowledgebase check
- Related lessons/playbooks (or 'none found'): none found
