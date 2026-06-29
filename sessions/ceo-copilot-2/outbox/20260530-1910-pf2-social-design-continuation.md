# CEO Outbox: PF2e Social Relationship System - Design Phases 11-12 Complete

**Date**: 2026-05-30T19:10:00+00:00
**Session**: CEO continuation - Paused/Resumed work
**Status**: Delivered

## Executive Summary

Advanced the PF2e-inspired social relationship system from Phases 10 through 12. Completed institution sentiment mapping design and actor faction persistence contract. Ready for implementation dispatch to dev teams.

## Work Completed

### Phase 11: Institution Sentiment Mapping and Character Sheets

**Artifact**: `features/dc-cr-social-relationship-loyalty/18-institution-sentiment-mapping-and-character-sheets.md`

**Locked decisions**:
- Seven canonical institutions: Crown, Commonweal, Compact, Wildwood Covenant, Shadow Syndicate, Forge Assembly, Twilight Church
- Theme-authored peer-sentiment matrix replaces circle-ring visualization as source of truth
- Formal alliances: Crown ↔ Church (coronation authority); Compact ↔ Forge (knowledge craft); Compact ↔ Church (moral philosophy)
- Active enmities: Crown ↔ Syndicate; Commonweal ↔ Syndicate
- Rivalries: Crown ↔ Wildwood; Wildwood ↔ Syndicate

**Implementation path**:
- Publish matrix and relationships in team feature documentation
- Proceed to actor faction persistence design
- Schedule admin UI implementation for institutional relationship authoring during campaign setup

### Phase 12: Actor Faction Persistence and Seeding Contract

**Artifact**: `features/dc-cr-social-relationship-loyalty/19-actor-faction-persistence-and-seeding-contract.md`

**Locked design**:

1. **Domain-Scoped Sentiment Tracks** (4 domains per actor)
   - Political/Social: Crown, Commonweal, Compact, Wildwood, Syndicate, Forge, Church (mutable)
   - Class Faction: Class-specific organizations (mutable)
   - Ancestry Faction: Ancestry communities (immutable)
   - Custom Campaign: Campaign-specific factions (mutable)

2. **Knowledge State Distinction** (prevents conflating "never heard of" with "met and unimpressed")
   - Unknown-neutral: Actor has not encountered this institution
   - Known-neutral: Actor is aware but formed no opinion
   - Known: Actor has explicit opinion from experience

3. **Membership Posture** (two categories with different mutation rules)
   - **Mutable allegiances** (crown, commonweal, faith, guild, criminal, order): Can renounce, replace, degrade
   - **Immutable identity** (ancestry, cultural): Cannot abandon through normal gameplay; sentiment can change

4. **Creation-Time Seeding Rules** (deterministic for all actor types)
   - Lock actor's institutional memberships (typically 2-3 at creation)
   - Seed sentiment toward all seven canonical institutions from peer-sentiment matrix
   - Mark non-membership institutions as unknown-neutral (baseline ignorance)
   - Inheritance: Known institutions inherit source's sentiment toward peers

5. **Schema Extensions**
   - New table: `dc_actor_sentiments` (actor_id, subject_id, domain, current_score, knowledge_state, source_type)
   - Extended table: `dc_actor_memberships` (actor_id, subject_id, mutability, status, reason)

## Current System State

- **Merge health**: ✅ Clean (no conflicts, no blocking tracked changes)
- **Orchestrator**: Stopped (expected; Board has not re-enabled org automation)
- **Active inbox**: 5 items (PF2e social, dev-infra remediation, map-tab, 2x QA audits)
- **Processed items**: 1463 total

## Next Implementation Phase

**Recommended immediate next slice**: Actor sentiment seeding into character/NPC creation flows

1. Wire artifact 19 into character creation step 6 (structured affiliation entry)
2. Extend NPC creation payload to include sentiment seeding
3. Add admin UI for institution sentiment override at campaign setup
4. Create verification tests for creation-time seeding rules

**Follow-on slices** (after implementation verification):
- Richer NPC authoring UI surfaces with sentiment/membership controls
- GM/admin review queue for generated faction drafts
- Deeper inspector and approval surfaces for manifest-backed provenance

## Decisions Locked for Downstream Implementation

1. ✅ Theme-authored sentiment is the source of truth (not visualization rings)
2. ✅ Seeding docs define starting posture only; live campaign relationships are authoritative
3. ✅ Actor sentiment is first-class with explicit storage and seeding
4. ✅ Membership mutability is explicit (identity vs allegiance)
5. ✅ Unknown-neutral state prevents sentiment ambiguity

## Artifacts Delivered

- `features/dc-cr-social-relationship-loyalty/18-institution-sentiment-mapping-and-character-sheets.md`
- `features/dc-cr-social-relationship-loyalty/19-actor-faction-persistence-and-seeding-contract.md`
- Updated inbox item README with execution history

## Commit Hash

- `3228c700cb` - Phase 11-12 complete: institution sentiment mapping and actor faction persistence contract

## Recommendations

1. Use artifacts 18 and 19 as the canonical requirements for the next implementation slice
2. Dispatch dev-dungeoncrawler work item for actor sentiment seeding into character/NPC creation
3. Wire PM review of schema extensions before dev implementation begins
4. Maintain one shared `dc_actor_sentiments` query surface for all sentiment-reading operations
5. Lock migration path for existing campaign actors (backfill vs creation-only)

## Board Notes

- This continuation session completes the highest-value design prerequisites for actor sentiment and membership persistence
- The implementation is now well-scoped and has explicit non-goals documented
- Merge health remains clean; HQ repo is ready for continued development
- Ready to dispatch work or continue CEO-owned design iterations as Board directs
