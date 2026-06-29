## Currently Working On

Character wizard hardening and actor-action-availability architecture.

### Active Tasks

1. **Character Wizard Hardening** (in-progress, June 22 start)
   - Rebuilt the wizard mirror from the canonical top-level character payload in both `CharacterCreationStepForm` and `CharacterCreationStepController`.
   - Added regression coverage for the mirror rebuild and for campaign finalization replacing a self-link with a dedicated library source row.
   - Updated the character wizard contract check to lock the rebuilt-mirror behavior in source.
   - Validation currently passes on PHP syntax checks and the focused JS contract test.
   - **Next action:** Commit and push the Dungeoncrawler content repo changes once org is re-enabled.

2. **Actor-Action-Availability Architecture** (in-progress, June 16 start)
   - Formal architecture request in inbox: `sessions/architect-copilot/inbox/20260616-actor-action-availability-architecture/`
   - This is a foundational subsystem design task that depends on character-wizard hardening completing first.
   - Will be picked up after character wizard ships.

3. **Follower Subsystem Architecture** (in-progress, June 22 start)
   - Formal architecture request in inbox: `sessions/architect-copilot/inbox/20260622-follower-subsystem-architecture/`
   - Covers unification of familiar, animal companion, and future follower subsystems via NPC generation pipeline.
   - Will start after character wizard review is complete.

## Key Decisions

1. Treat the top-level character payload as canonical and regenerate the nested wizard mirror from it before persistence.
2. Finalized campaign-created characters must always point at a separate library source row, never themselves.
3. Actor-action availability must unify all action family resolvers (spells, feats, consumables, item activations) under one server-authoritative subsystem.
4. Follower subsystem should leverage existing NPC generation and sheet pipeline rather than inventing parallel actor model.

## Blockers
None — all three active streams have clear acceptance criteria and are ready to progress. Work is sequenced by execution-order dependency (character wizard → action availability → followers).

## Org Status
Org is disabled (Board-controlled). All three tasks are prepared and will resume when org is re-enabled.
