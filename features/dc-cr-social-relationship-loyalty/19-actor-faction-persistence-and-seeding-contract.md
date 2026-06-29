# Actor Faction Persistence and Seeding Contract

## Objective

Define how every newly created PC and NPC stores:

1. Default sentiment toward the seven canonical base institutions
2. Explicit institution/faction memberships at creation time
3. Mutability rules that distinguish abandonable allegiances from immutable identity memberships

## Design Lock

- **Theme-authored institutions remain first-class subjects**
- **PCs and NPCs are the scene-presented actors**
- **Every PC/NPC needs stored faction sentiment state from creation onward**
- **Factions/institutions the actor has not heard of start neutral by default**
- **Sentiment matrices are domain-scoped**, not universal:
  - Base political/social factions track sentiment toward other political/social factions
  - Class factions track sentiment toward other class factions only
  - Race/ancestry factions track sentiment toward other race/ancestry factions only
- **Membership must distinguish**:
  - **Mutable allegiance** (crown/commonweal/faith/guild/criminal/order) — can be renounced or changed
  - **Immutable identity** (ancestry like elf) — cannot be abandoned through normal runtime mutations

## Storage Model Extension

### Sentiment Tracks

Every actor maintains domain-scoped sentiment/reputation tracks:

| Domain | Institutions | Mutability | Seeding Rule |
|---|---|---|---|
| **Political/Social** | Crown, Commonweal, Compact, Wildwood, Syndicate, Forge, Church | Mutable | Seeded at creation from peer matrix; can change via gameplay |
| **Class Faction** | Class-specific organizations (Wizard's Council, Rogue's Guild, etc.) | Mutable | Seeded only for actor's class; others start neutral-unknown |
| **Ancestry Faction** | Ancestry-specific communities (Elf enclave, Dwarf clan, etc.) | Immutable | Seeded for actor's ancestry; others start neutral-unknown |
| **Custom Campaign** | Campaign-specific factions | Mutable | Empty at creation; populated by campaign setup or GM |

### Knowledge State (Unknown vs. Known Neutral)

Each sentiment track has a **knowledge state** flag:

- **Unknown-neutral** (default): Actor has not encountered this institution; neutral posture is baseline ignorance
- **Known-neutral** (marked): Actor is aware of institution but has no formed opinion; neutral posture is active assessment
- **Known-favorable/hostile/etc.**: Actor has formed explicit opinion from experience

This prevents the system from conflating "never heard of" with "met and came away unimpressed."

### Membership Edges

Actors maintain explicit membership edges to institutions with mutability classification:

```json
{
  "memberships": [
    {
      "subject_id": "institution_allegiance_crown",
      "domain": "political_social",
      "mutability": "mutable",
      "joined_at": "creation_time",
      "reason": "birthright | recruitment | apprenticeship | other",
      "status": "active | inactive | abandoned"
    },
    {
      "subject_id": "institution_identity_elf",
      "domain": "ancestry",
      "mutability": "immutable",
      "joined_at": "creation_time",
      "reason": "ancestry",
      "status": "active"
    }
  ]
}
```

### Sentiment Record Structure

Each sentiment track for an institution:

```json
{
  "subject_id": "institution_allegiance_crown",
  "domain": "political_social",
  "current_score": 0,
  "knowledge_state": "unknown_neutral | known_neutral | known",
  "knowledge_depth": 0,
  "source_type": "default_seed | membership_inference | experience | override",
  "history": [
    {
      "timestamp": "...",
      "delta": 0,
      "reason": "...",
      "source": "..."
    }
  ]
}
```

## Creation-Time Seeding Rules

### Rule 1: Political/Social Domain (All Actors)

Every new actor seeds default sentiment toward all seven canonical institutions using the peer-sentiment matrix:

1. Roll/author the actor's **institutional memberships** (typically 2-3 at creation):
   - Ancestry faction (immutable, if applicable)
   - Class faction (immutable for class, may shift with class changes)
   - Allegiances (mutable; typically 1-2 chosen during creation)

2. For each membership:
   - Mark sentiment toward that institution as **known** with positive tilt
   - Inherit that institution's sentiment toward other institutions per the matrix
   - Mark other institutions as **unknown-neutral** (not yet encountered)

3. Sentiment toward non-membership institutions:
   - Start at **neutral score 0**
   - Mark as **unknown-neutral** (baseline ignorance, not active assessment)

### Rule 2: Class Faction Domain (Class-Specific Actors)

If the actor has a class that grants access to a class-specific faction:

1. Seed membership edge to class faction (immutable for this character's lifetime, unless multiclass/archetype changes)
2. Seed positive sentiment within class-faction peer group
3. Mark other class factions as **unknown-neutral**

### Rule 3: Ancestry Faction Domain (Ancestry-Specific Actors)

If the actor's ancestry includes cultural/community institutions:

1. Seed membership edge to ancestry faction (immutable)
2. Seed positive sentiment within ancestry peer group
3. Mark other ancestry factions as **unknown-neutral**

## Membership Mutation Rules

### Mutable Allegiance Membership (Political/Social Domain)

- **Renounce**: Actor can voluntarily leave an allegiance (if criteria are met: no outstanding debts, no active hostility, etc.)
- **Replace**: Actor can switch allegiances if circumstances warrant (defeat in conflict, recruitment, ideological shift, etc.)
- **Degrade**: Allegiance can be marked inactive if conditions allow (absence, secret membership, etc.)
- **Sentiment impact**: Leaving an institution downgrades sentiment; joining upgrades it

### Immutable Identity Membership (Ancestry, Racial, Cultural)

- **Cannot abandon** through standard gameplay mechanics
- **Sentiment can still change** (actor can become estranged from their ancestry community)
- **Reintegration possible** after conflict resolution
- **Secret membership possible** (actor hides identity membership status)

## Integration Points

### Character Creation Flow

1. During ancestry/class selection, actor gains immutable memberships
2. During background/motivation step, actor chooses 1-2 mutable allegiances
3. System seeds sentiment tracks for all seven institutions based on choices
4. GM/authoring UI allows override of defaults

### NPC Creation Flow

1. GM provides actor stats and concept
2. System seeds appropriate memberships based on ancestry/class/concept
3. GM can override specific memberships or sentiments
4. NPC receives default sentiment toward institutions matching concept

### Campaign Initialization

1. GM can author institutional relationships at campaign start
2. System can backfill existing campaign actors with default seeding
3. GM can override any default seeding per campaign narrative needs

### Query/Filter Operations

Runtime queries support:

- "All actors with positive sentiment toward The Crown"
- "All actors with unknown-neutral (not yet encountered) sentiment toward The Wildwood Covenant"
- "All actors with membership in The Shadow Syndicate (active or inactive)"
- "All actors whose class faction differs from their ancestry faction"

## Schema Extensions

### New Tables/Fields

| Table | Field | Type | Purpose |
|---|---|---|---|
| `dc_actor_sentiments` | `actor_id` | FK | Reference to actor/character |
| | `subject_id` | string | Institution subject ID |
| | `domain` | enum | Domain classification |
| | `current_score` | int | -100 to +100 sentiment |
| | `knowledge_state` | enum | unknown_neutral, known, etc. |
| | `source_type` | enum | default_seed, experience, override |
| `dc_actor_memberships` | `actor_id` | FK | Reference to actor |
| | `subject_id` | string | Institution subject ID |
| | `mutability` | enum | mutable, immutable |
| | `status` | enum | active, inactive, abandoned |
| | `reason` | string | Why actor joined |

## Non-Goals

- No multiclass/respec workflow in v1 (identity memberships fixed at creation)
- No institution rivalry/alliance inheritance cascading (each actor's sentiment is independent)
- No automatic NPC recruitment to match PC institutional changes (GM decides NPC reactions)
- No sentiment-based automatic party rejection (sentiment affects NPC behavior but does not auto-resolve conflicts)

## Follow-On Work

1. **Admin UI**: Campaign setup screens for institutional seeding override
2. **Query/Filter tools**: GM panels to inspect actor sentiment state
3. **Backfill**: Existing campaign character seeding with defaults
4. **GM prompt injection**: Actor sentiment included in NPC context assembly
5. **Player-facing view**: Character sheet display of sentiment tracks and memberships

## Related Artifacts

- Prior: `features/dc-cr-social-relationship-loyalty/18-institution-sentiment-mapping-and-character-sheets.md`
- Next: `features/dc-cr-social-relationship-loyalty/20-faction-generation-workflow-and-tool-contract.md`
