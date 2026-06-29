# Implementation Notes: dc-cr-campaign-multiplayer-v1

## Delivery posture

This feature is a multiplayer **epic** and should be implemented in slices. The recommended first code slice is:

1. add campaign membership storage
2. widen campaign access checks to active members
3. show joined campaigns in the list view

That slice creates the minimum safe foundation for follow-on assignment and turn-taking work.

## Drupal leverage opportunities

Use Drupal core and existing module patterns where they reduce custom work, but avoid a full entity-system rewrite for v1.

### Recommended simplifications

| Need | Lowest-complexity approach | Avoid |
|---|---|---|
| host adds a member | Drupal `FormBase` + `entity_autocomplete` for `user` on a dedicated owner campaign management page | custom user-search endpoint or invite-token flow |
| host removes a member | Drupal confirm form route, matching existing archive/delete patterns | bespoke DELETE API as the first implementation |
| player assigns their character | reuse `/campaigns/{campaign_id}/characters` and `selectCharacter()` after access widening | a brand-new self-assignment subsystem before the existing path is adapted |
| route authorization | keep tagged access-check services in `dungeoncrawler_content.services.yml` and add `_campaign_host_access` beside `_campaign_access` | repeating owner/member checks inline across controllers and forms |
| mutation CSRF protection | reuse route-level `_csrf_request_header_mode: TRUE` for JSON writes | custom token plumbing in controllers |
| schema rollout | Drupal Schema API + numbered `hook_update_N()` backfills | raw SQL migration scripts outside the existing install/update path |
| member labels | load user labels from Drupal user storage when rendering roster data | copying usernames/emails into `dc_campaign_members` |
| regression coverage | extend existing `BrowserTestBase` functional suites and traits | separate one-off harnesses for route/controller behavior |

### Recommended non-goals for simplification

Do **not** try to save work by converting campaigns/memberships into Drupal content entities, fieldable config entities, or Views-backed admin UIs as part of multiplayer v1.

Why not:

- the module is already built around custom tables and services
- access and runtime logic already expect table-driven hot columns
- entity migration would expand scope far beyond the multiplayer need

The best leverage point is Drupal's routing, forms, access checks, cacheability, and test framework — not a storage-model rewrite.

## Critical audit findings from the current code

The current implementation has several exact seams that the multiplayer plan must address:

1. `CampaignAccessCheck` is still **owner-only** and reads only `dc_campaigns.uid`.
2. `CampaignController` repeatedly enforces `campaign->uid === current user` in owner-facing flows like:
   - `tavernEntrance()`
   - `listCampaignDungeons()`
   - `listVisitedLocations()`
   - `selectCharacter()`
3. `HexMapController::demo()` bypasses route access and manually checks `dc_campaigns.uid`, so joined players would still be blocked even if `_campaign_access` is widened.
4. Combat routes such as `/api/combat/action`, `/api/combat/attack`, `/api/combat/end-turn`, and `/api/combat/set` currently rely on generic character access permission, not campaign-member authorization.
5. `CampaignStateController::setState()` exists and functional tests expect `POST /api/campaign/{campaign_id}/state`, but the current routing file only exposes the GET route. The write route must be restored or explicitly reintroduced as part of multiplayer hardening.

These are not optional cleanup items; they are part of the minimum safe multiplayer cutover.

## Single-player compatibility contract

Multiplayer v1 must preserve the current single-player experience as the default, stable baseline.

### Definition of a single-player campaign

A campaign is considered single-player-compatible when it has either:

- only the owner membership row, or
- only one active membership row total and that row belongs to the owner

That path must continue to behave like today's product even after membership is introduced.

### Required preserved behavior

For existing owner-only campaigns:

1. owner can still view the campaign list
2. owner can still assign/select a character
3. owner can still launch tavern -> dungeon -> hexmap
4. owner can still archive, unarchive, and delete the campaign
5. campaign state GET/POST still works
6. combat still works for the owner-controlled character

If multiplayer support breaks any of the above, the rollout is not safe.

### Behavior-safe defaults

- Backfilled owner membership must make an old campaign behave the same as before migration.
- A campaign with no non-owner active members must not require a new multiplayer-only UI path to remain playable.
- If legacy code still depends on `dc_campaigns.active_character_id`, keep that field populated for the owner path until all legacy readers are removed.
- Do not require users to "re-join" or reinitialize existing campaigns after schema migration.

## Proposed data model

### New table: `dc_campaign_members`

Recommended columns:

- `id` bigint or serial primary key
- `campaign_id` bigint not null
- `uid` bigint not null
- `role` varchar(32) not null (`owner`, `player`)
- `status` varchar(32) not null (`active`, `removed`) for shipped v1; `invited` may be reserved but is not required in the initial flow
- `character_id` bigint null (canonical library character ID selected by this member)
- `added_by_uid` bigint not null
- `created` int not null
- `changed` int not null
- unique key on (`campaign_id`, `uid`)
- unique key or validation rule preventing duplicate active use of the same (`campaign_id`, `character_id`) pair

Why this shape:

- keeps host ownership distinct from participation
- supports one-character-per-player on the membership row for v1 without introducing a second mapping table yet
- avoids overloading `dc_campaigns.active_character_id`
- keeps canonical character assignment stable even when runtime campaign instance rows are recreated or normalized

## Concrete decisions

### Member onboarding

- **Shipped v1 behavior:** the owner directly adds existing authenticated users as active members.
- No email invite, token, or acceptance workflow is required in v1.
- Membership writes are therefore owner-only management actions, not a two-sided negotiation flow.

### Character assignment

- Each active member self-selects one of their own completed library characters.
- The membership row stores the **canonical** character ID, not the campaign-runtime row ID.
- Existing runtime instances in `dc_campaign_characters` remain the play-time representation and continue using deterministic campaign-scoped rows such as `instance_id = pc-<campaign>-<character>`.
- `dc_campaigns.active_character_id` becomes a compatibility field only. Do not use it as the multiplayer source of truth for route access or launch resolution.

### Launch resolution

- When a member launches play, the controller resolves:
  1. current active membership row
  2. membership `character_id`
  3. the campaign-scoped runtime row in `dc_campaign_characters`
- If the runtime row does not yet exist, reuse the existing clone/materialization path from `CampaignController::selectCharacter()` to create or refresh it.
- Launch should fail clearly when the current member has no assigned character.

### Single-player launch fallback

For owner-only campaigns during the transition:

- if the owner membership row has `character_id`, use it as the primary source
- if not, but `dc_campaigns.active_character_id` is present, treat that as the compatibility fallback
- if neither is present, show the same "choose a character" behavior the product uses today

This keeps old campaigns playable while eliminating dependence on `active_character_id` for true multiplayer logic.

## Existing code surfaces to change

### Schema and install/update path

- `dungeoncrawler_content.install`
  - add `dc_campaign_members`
  - backfill one owner membership row for existing campaigns
  - backfill owner `character_id` from `dc_campaigns.active_character_id` where possible
  - preserve `dc_campaigns.uid` as canonical host owner
  - do **not** migrate from `dc_sessions.player_uids`; session rows are adjacent precedent, not the source of truth

### Access and listing

- **Add a new access layer**
  - keep `_campaign_access` for active-member readable/playable surfaces
  - add a dedicated `_campaign_host_access` check for host/admin-only campaign management surfaces
  - do not rely on widened `_campaign_access` alone for delete/archive/member-management routes
- `src/Access/CampaignAccessCheck.php`
  - allow active members on play routes
  - keep owner/admin-only checks for destructive/admin surfaces
- `src/Access/CampaignHostAccessCheck.php` (new)
  - allow owner/admin only
  - used by archive/delete/unarchive/member-management routes and forms
- `src/Controller/CampaignController.php`
  - include joined campaigns in list/query logic
  - stop relying on `active_character_id` alone for launch eligibility
  - distinguish hosted vs. joined cards in list/detail payloads
  - add an owner-facing campaign management page as the main host control surface
- `src/Controller/CharacterListController.php`
  - stop requiring `dc_campaigns.uid === current user` for campaign-scoped roster selection
  - scope selectable characters to the current member's own eligible characters
- `src/Controller/CampaignController::selectCharacter()`
  - split current owner-only "select campaign character" behavior into multiplayer-safe assignment logic
  - persist selected canonical character on the current member row
  - continue materializing the campaign runtime character row using existing clone/update rules
- `src/Controller/HexMapController.php`
  - replace the manual owner check with membership-aware launch authorization
  - verify the requested runtime character row belongs to the current member unless admin override applies
  - reject launch when the query points at another member's runtime character instance

### Shared state and turn-taking

- `src/Service/CampaignStateService.php`
  - continue using optimistic versioning as the write gate
  - carry turn/acting metadata in shared state as needed
- `src/Controller/CampaignStateController.php`
  - fail explicitly on stale writes or out-of-turn mutations
  - enrich GET responses with multiplayer metadata needed by the client shell, preferably by composing membership data outside the canonical state blob
- `src/Controller/CombatEncounterApiController.php`
  - add campaign-member authorization for combat routes that currently accept any authenticated gameplay user
  - verify the logged-in user is entitled to act for the submitted `actorId`
  - treat client-supplied `actorId` as a request, never as authority

## Route contract plan

### Existing routes to keep but reinterpret

| Route | Current behavior | Multiplayer v1 posture |
|---|---|---|
| `dungeoncrawler_content.campaigns` | owner-only list query in controller | show hosted + joined campaigns |
| `dungeoncrawler_content.campaign_tavernentrance` | owner-only character picker | membership-aware campaign lobby / assignment gate |
| `dungeoncrawler_content.campaign_select_character` | owner selects campaign-wide active character | current member assigns their own canonical character |
| `dungeoncrawler_content.campaign_dungeons` | owner-only dungeon launch view | active member view; launch resolved from membership assignment |
| `dungeoncrawler_content.characters` | owner-only campaign roster selection in controller | active-member campaign character selection page limited to owned characters |

### Existing routes that should move to host-only access

| Route | Why |
|---|---|
| `dungeoncrawler_content.campaign_delete` | destructive host action |
| `dungeoncrawler_content.campaign_archive` | lifecycle management action |
| `dungeoncrawler_content.campaign_unarchive` | lifecycle management action |

Recommendation: swap these from `_campaign_access` to a new `_campaign_host_access`.

### New multiplayer management routes

Recommended lowest-complexity v1 posture:

- add a dedicated owner-only campaign management page as the server-rendered host control surface
- implement host add-member there as a Drupal form using `entity_autocomplete` against users
- implement remove-member as a confirm form or standard host-only POST surface
- keep self-assignment flowing through the existing campaign character selection pages while the underlying persistence changes from `active_character_id` to membership `character_id`
- keep tavern/lobby focused on launch/readiness, not all host administration concerns

This lets v1 reuse Drupal form validation, redirects, messenger feedback, and CSRF handling instead of introducing a full new lobby API surface immediately.

### Optional JSON contract for later shell/API consolidation

If the client shell later needs a stable programmatic contract, these are the right follow-on endpoints:

| Route | Method | Access | Purpose |
|---|---|---|---|
| `/api/campaign/{campaign_id}/members` | GET | active member | read roster for lobby/client hydration |
| `/api/campaign/{campaign_id}/members` | POST | host/admin | add existing authenticated user as active member |
| `/api/campaign/{campaign_id}/members/{uid}` | DELETE | host/admin | remove member from campaign |
| `/api/campaign/{campaign_id}/members/self/character` | POST | active member | assign/change current member's canonical character |

Notes:

- `campaign_select_character` should remain the first implementation path unless the client shell proves it truly needs direct assignment JSON.
- Member add/remove and self-assignment writes must require `_csrf_request_header_mode: TRUE`.

### Campaign state routes

Required stable contract:

| Route | Method | Access | Notes |
|---|---|---|---|
| `/api/campaign/{campaign_id}/state` | GET | active member | existing read route |
| `/api/campaign/{campaign_id}/state` | POST | active member | must exist explicitly; controller/tests already assume it |

The POST route should return:

- `200` on accepted write
- `400` on invalid JSON/payload
- `403` on membership/authorization failure
- `409` on version conflict or out-of-turn state conflict

## Suggested route-to-access mapping

### `_campaign_access`

Use for:

- campaign lobby/tavern
- campaign dungeon selection
- joined campaign state reads
- joined room/chat/image reads
- joined safe gameplay writes that are member-authorized

### `_campaign_host_access` (new)

Use for:

- archive / unarchive / delete campaign
- add/remove members
- any future campaign-wide settings mutation

### Combat route authorization

Combat routes cannot rely on generic `access dungeoncrawler characters` alone. They must authorize against encounter/campaign membership at request time.

Recommended posture:

- keep route-level generic auth
- add controller/service-level membership + turn-owner verification
- optionally introduce a future `_combat_member_access` layer if repeated patterns warrant it

## Authorization matrix

### Host/admin-only actions

- add member
- remove member
- archive/delete campaign
- any future campaign settings that materially affect all members

### Active-member actions

- view joined campaign
- assign or change **their own** character
- launch gameplay with their assigned character
- read shared campaign state
- submit versioned exploration writes

### Active-member with current turn ownership

- submit combat writes that mutate turn-owned state
- end turn for their currently acting character

### Forbidden in v1

- outsider access to campaign play routes
- member assignment of another user's library character
- combat mutation by an active member who does not own the current acting character
- anonymous campaign access
- joined member access to host-only lifecycle routes

## Runtime row reconciliation contract

The multiplayer rollout must not accidentally wipe or fork single-player runtime progress.

### Canonical -> runtime reconciliation rules

When a member assigns a canonical character to a campaign:

1. look for an existing campaign runtime row matching:
   - `campaign_id`
   - canonical `character_id`
   - deterministic `instance_id = pc-<campaign>-<character>`
2. if found, reuse that row
3. if not found, create it from the canonical character using the existing clone/materialization flow

### Preserve vs refresh rules

Preserve from existing runtime row when reusing it:

- position / room / location fields
- campaign-scoped runtime state
- encounter-adjacent mutable values already earned in the campaign

Refresh from canonical character only when creating a new runtime row or when an explicit rebuild path is chosen later.

Do **not** silently overwrite an existing campaign runtime row from the canonical library character during ordinary reassignment or launch.

## Field migration guidance

### `dc_campaigns.active_character_id`

Do not treat this as the multiplayer source of truth.

Recommended posture:

- keep it temporarily for backward compatibility only
- introduce explicit membership-level `character_id`
- where untouched legacy code still reads it, prefer syncing it only for the host's selected character rather than trying to make it represent the whole party
- narrow or retire campaign-wide active-character assumptions as follow-on cleanup

## Suggested implementation slices

### Slice 1: Membership foundation
- schema update + owner backfill
- membership lookup helper/service
- access-check widening for active members
- joined-campaign list visibility
- owner campaign management page shell + add/remove member actions

### Slice 2: Character assignment
- self-service assign one owned completed character per member
- prevent duplicate assignment of the same character to multiple active members
- update character-selection flows and validation
- resolve membership assignment into existing campaign runtime character rows

### Slice 3: Turn-taking enforcement
- encode current acting character in campaign state and derive the acting member from membership
- reject out-of-turn combat writes
- preserve reload-and-retry behavior on version conflicts
- do not add a lock daemon, websocket channel, or server-side lease system in v1

### Slice 4: UX and hardening
- surface member roster and current turn owner to the client
- improve conflict messaging
- close regression gaps on pre-multiplayer campaigns

## Slice dependency gates

Slice sequencing is not just a roadmap preference; later slices depend on concrete contracts from earlier ones.

### Slice 3 entry criteria

Do not begin turn-enforcement work until all of the following are true:

1. `dc_campaign_members` exists and owner backfill is complete
2. active-member access is working for joined campaign play routes
3. host-only lifecycle/member-management routes are split behind `_campaign_host_access`
4. each active member can resolve exactly one canonical `character_id`
5. launch resolution can deterministically map current member -> canonical character -> runtime row
6. `POST /api/campaign/{campaign_id}/state` is explicitly present in routing and protected
7. joined-member hexmap launch no longer depends on direct `dc_campaigns.uid` checks

If any item above is missing, Slice 3 will produce fragile or misleading authorization behavior.

### Slice 3 implementation gate by surface

| Surface | Required before Slice 3 | Why |
|---|---|---|
| membership schema | owner/member row exists and is queryable by campaign + uid | turn ownership must resolve to a real member |
| assignment path | membership `character_id` is canonical and validated | turn ownership cannot depend on legacy `active_character_id` |
| runtime launch | deterministic runtime row resolution/reuse is working | combat participant lookup must land on the same runtime entity the player launched |
| campaign state route | GET and POST contracts agree | conflict handling cannot be validated if writes use an inconsistent route surface |
| hexmap/tavern access | joined member can reach play shell with their own runtime row | turn-gated UX is impossible if joined members still fail the launch path |
| host/member split | lifecycle routes remain host-only | widening access for play must not widen destructive actions |

### Slice 4 dependency checklist

Slice 4 UX/hardening depends on Slice 3 producing trustworthy authority signals.

Required outputs from Slice 3:

- a stable `multiplayer.members[]` response shape
- a stable `multiplayer.currentActor` response shape
- a stable `multiplayer.permissions.canActNow` signal
- explicit `403` vs `409` semantics for spoofed vs stale/out-of-turn writes
- deterministic behavior when no member assignment exists

Without those contracts, Slice 4 would be forced to guess at server meaning and would likely drift from the real authorization model.

## Suggested response shape additions

The canonical state blob should stay focused on game state. Multiplayer roster data should be derived from membership rows.

Recommended GET state response additions:

- `multiplayer.members[]`
  - `uid`
  - `displayName`
  - `role`
  - `status`
  - `characterId`
  - `characterName`
- `multiplayer.currentActor`
  - `characterId`
  - `uid`
  - `context` (`combat`, `exploration`, or `none`)
- `multiplayer.permissions`
  - `canManageMembers`
  - `canAssignSelfCharacter`
  - `canActNow`

This gives the client what it needs without duplicating the entire member roster into `dc_campaigns.campaign_data`.

### Roster rendering leverage

When building `multiplayer.members[]`:

- resolve user display labels from Drupal's user storage at read time
- keep only operational IDs (`uid`, `role`, `status`, `character_id`) in `dc_campaign_members`
- avoid denormalizing user-facing identity fields into the membership table unless a measured performance problem appears later

That keeps the membership schema smaller and avoids stale copied profile data.

### UI-state contract that Slice 4 can rely on

The client should be able to render the following states without inferring hidden business logic:

| State | Minimum server signals |
|---|---|
| hosted campaign, owner ready | member row for current user, assigned `characterId`, `canManageMembers = true` |
| joined campaign, assignment missing | active membership with null `characterId`, `canAssignSelfCharacter = true` |
| joined campaign, ready but not acting | assigned `characterId`, `canActNow = false`, `currentActor.uid != current user` |
| current member may act now | assigned `characterId`, `canActNow = true` |
| stale/reload required | write response `409` with current version/state payload |
| forbidden/spoofed action | write response `403` with explicit authorization failure |

This is the practical handoff between Slice 3 authority rules and Slice 4 UX hardening.

## Source-of-truth split

Multiplayer v1 should not pretend one table owns everything.

| Concern | Source of truth |
|---|---|
| Campaign host ownership | `dc_campaigns.uid` |
| Campaign membership + assigned canonical character | `dc_campaign_members` |
| Shared campaign exploration/story state | `dc_campaigns.campaign_data['state']` |
| Campaign runtime character instance | `dc_campaign_characters` |
| Combat turn order / active participant | `combat_encounters` + `combat_participants` |

This means:

- roster membership does **not** live in `campaign_data`
- combat turn authority does **not** need to be duplicated into `campaign_data` if an active encounter exists
- `active_character_id` is only compatibility residue until legacy call sites are cleaned up

## Turn authority algorithm

### Exploration-mode writes

For non-combat shared-state writes:

1. require active membership
2. require `expectedVersion`
3. accept write if version matches
4. reject with `409` and current state if stale

No extra lease/lock service is added in v1.

### Combat-mode writes

For combat actions:

1. resolve active encounter for the campaign
2. resolve `turn_index` from `combat_encounters`
3. resolve current participant from `combat_participants`
4. map current participant `entity_ref` / runtime row to canonical character
5. map canonical character to `dc_campaign_members.character_id`
6. compare resulting member `uid` to the logged-in user

If the logged-in user does not match the active turn owner:

- reject with `409` when the request is merely out-of-turn
- reject with `403` when the request attempts to spoof a character/member relationship it does not own

This keeps turn ownership server-authoritative and avoids trusting `actorId` from the client.

## Migration and compatibility

- Existing single-player campaigns remain valid after the schema update.
- Each existing campaign gets an owner membership row automatically.
- If an owner already has `active_character_id`, use that as the initial owner assignment where possible.
- Campaigns without any active member assignment remain viewable/manageable but cannot launch multiplayer gameplay until the current member assigns a character.
- No automatic multi-member seeding is performed from session history.

### Edge-case migration handling

The migration path must explicitly define behavior for:

- campaigns with `active_character_id = NULL`
- archived campaigns
- campaigns whose `active_character_id` points to a missing or stale canonical character
- campaigns with existing runtime rows in `dc_campaign_characters` but no clean canonical assignment signal

Recommended behavior:

- always create the owner membership row
- populate owner `character_id` only when the source value is valid
- leave invalid/missing assignments empty rather than guessing
- keep the campaign viewable/manageable and require the owner to reselect a character only when necessary

### Rollout posture

Recommended implementation posture:

- enable the new membership model for all campaigns via migration
- keep single-member campaigns on the old-feeling UX path wherever possible
- only expose obviously multiplayer-specific UX when a campaign has more than one active member

This is effectively a behavior-safe rollout without requiring a separate feature flag.

### Suggested update-hook sequence

Recommended numbering starting after current `10061`:

1. `dungeoncrawler_content_update_10062()`
   - create `dc_campaign_members`
   - add indexes/unique key for (`campaign_id`, `uid`)
2. `dungeoncrawler_content_update_10063()`
   - backfill one owner membership row per existing campaign
   - copy `active_character_id` into owner membership `character_id` where present
3. `dungeoncrawler_content_update_10064()`
   - optional schema follow-up if a new index is needed after real-world backfill review

### Index recommendations

- unique: (`campaign_id`, `uid`)
- index: (`campaign_id`, `status`)
- index: (`uid`, `status`)
- index: (`campaign_id`, `character_id`)

Important: because Drupal schema does not support a portable partial unique index like "unique active character per campaign excluding removed rows", duplicate active-character assignment must still be enforced in service/controller validation, not only at the DB layer.

## Open constraints

- v1 should not depend on websockets or long-lived background presence.
- Turn-taking must be enforced server-side; the client cannot be the source of truth.
- Member add/remove and assignment failures must be explicit 4xx responses, not silent no-ops or permissive fallback.
- Slice 3 is not ready to implement unless membership, assignment, launch, and POST state-route contracts are already stable.
