# Acceptance Criteria: dc-cr-campaign-multiplayer-v1

- Feature ID: dc-cr-campaign-multiplayer-v1
- Website: dungeoncrawler
- PM owner: pm-dungeoncrawler
- Status: ready for QA test plan

## Grooming decision

Decision: ship as a **phased epic**, not as a single giant release item. Multiplayer v1 is only considered complete when the membership, joined-campaign access, one-character-per-player assignment, and serialized turn-taking layers are all in place.

Rationale: the current product already persists campaign state and sessions, but it still assumes one owning user in multiple controller and schema surfaces. Splitting delivery into slices reduces rollback risk while keeping one PM-tracked feature for the whole suggestion.

## Release posture

- Slice 1: campaign membership schema + migration path
- Slice 2: access checks + joined-campaign discovery
- Slice 3: player-character assignment
- Slice 4: turn-taking + version-conflict UX
- Slice 5: hardening, QA closure, and suggestion close-out

## Acceptance criteria (full epic)

### AC-1: Campaign membership exists independently of host ownership
- A dedicated campaign membership layer exists for active players instead of overloading `dc_campaigns.uid`.
- The original campaign owner remains identifiable as host/owner for admin/destructive actions.
- Membership records support at least role and status states required for v1 participation.
- Existing campaigns are backfilled with an owner membership row, and if `dc_campaigns.active_character_id` is populated for the owner, that assignment is preserved or recoverable during migration.
- Verification: a campaign can persist at least one owner row and one non-owner active player row without changing the owning `uid`.

### AC-2: Joined players can discover and access their campaigns
- A joined authenticated player can see campaigns they actively belong to in the campaign listing surface.
- A non-member authenticated user cannot access another campaign's play routes by guessing IDs.
- Existing owner access continues to work.
- The owner can add an existing authenticated user directly as an active member without requiring a second acceptance step.
- The owner has a dedicated campaign management page for roster management and other host-only campaign actions.
- Host-only campaign management routes remain host/admin-only after `_campaign_access` is widened for joined members.
- Verification: functional tests cover owner, joined player, outsider, and admin outcomes.

### AC-3: Each human player controls one character in the shared campaign
- The multiplayer model supports one active player-to-one campaign character assignment.
- Assignment is explicit and not inferred solely from `dc_campaigns.active_character_id`.
- Each member can only assign from characters they own.
- A player cannot take over another member's assigned character without host/admin action.
- The same canonical character cannot be assigned to multiple active members in the same campaign.
- Verification: assignment persistence survives page reload and campaign reopen.

### AC-4: Shared state writes are serialized for v1 multiplayer
- State-changing multiplayer actions continue to require expected campaign-state versions.
- If two players act on stale state, the stale writer receives an explicit conflict response and is required to reload/retry.
- Exploration-mode writes remain version-gated shared writes; no separate websocket lock service is introduced.
- Verification: tests prove one stale write is rejected with the existing conflict behavior instead of silently winning.

### AC-5: Turn ownership is enforced for combat-safe multiplayer
- In turn-based play, only the player whose assigned character currently owns the turn can submit combat actions for that turn.
- Other members can view shared state but cannot mutate turn-owned combat actions out of turn.
- Combat turn authority is derived from the active encounter participant/runtime character mapping, not from a client-supplied actor ID alone.
- Verification: out-of-turn action attempts fail explicitly and do not mutate campaign state.

### AC-6: Exploration and campaign UX communicate multiplayer state clearly
- The UI exposes who is in the campaign and whose turn it is where relevant.
- Conflict or "another player acted" states are surfaced clearly enough for reload-and-retry behavior.
- The campaign list and campaign detail surfaces clearly distinguish hosted campaigns vs. joined campaigns and whether the current member still needs to select a character before launch.
- The owner campaign management page clearly exposes roster membership and current assignment status without overloading the tavern/launch flow.
- The campaign hexmap launch path works for active members and no longer assumes `dc_campaigns.uid === current user`.
- Verification: rendered responses or payloads contain the member/turn metadata needed by the client shell.

### AC-7: Security and backward compatibility are preserved
- Existing single-player campaigns continue to load after the multiplayer schema changes.
- New multiplayer routes preserve existing authentication and CSRF protections.
- Logging and persistence do not expose sensitive invite/member data beyond operational IDs.
- The campaign state write route is explicitly exposed and protected; controller/tests and routing must agree on the POST write surface.
- Verification: regression coverage proves pre-existing owner-only campaigns remain usable after migration.

## Security acceptance criteria

- No anonymous campaign membership mutation routes are introduced.
- Membership and assignment endpoints validate campaign membership/ownership on every write, not just on page load.
- Version-conflict and permission errors fail explicitly; no silent fallback to stale state.
- Audit logging is limited to campaign IDs, user IDs, membership state transitions, and conflict outcomes.

## KB reference

- None found in `knowledgebase/` for Dungeoncrawler campaign multiplayer rollout. Capture lessons after the first delivery slice ships.
