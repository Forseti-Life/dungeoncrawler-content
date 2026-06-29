# Test Plan: dc-cr-campaign-multiplayer-v1

## Coverage summary

- AC items: 7
- Planned test cases: 25
- Suites: PHPUnit unit + functional, targeted route/access audit, manual multiplayer smoke check
- Security: no exemption; new membership/assignment writes must be covered

---

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` (unit) | PHPUnit unit | membership resolution, assignment rules, version-conflict handling |
| `module-test-suite` (functional) | PHPUnit functional | campaign access, joined-player listing, controller responses |
| `role-url-audit` | `scripts/site-audit-run.sh` or equivalent auth audit | verify play surfaces stay denied for outsiders and available to valid roles |
| manual multi-user smoke | browser/manual | confirm two authenticated users can participate in the same campaign without websocket support |

---

## Test cases

### TC-MP-01 — Existing owner campaign backfills membership safely
- Suite: unit/install update coverage
- Expected: a pre-multiplayer campaign gains an owner membership row without losing existing owner access
- AC: AC-1, AC-7

### TC-MP-01A — Existing owner-only campaign list still renders after migration
- Suite: functional
- Expected: migrated owner-only campaign still appears in the owner's campaign list without requiring any non-owner member data
- AC: AC-1, AC-7

### TC-MP-01B — Existing owner-only tavern launch path still works
- Suite: functional
- Expected: owner-only campaign can still reach tavern / dungeon / launch flow after migration
- AC: AC-6, AC-7

### TC-MP-01C — Legacy active_character_id fallback remains playable during transition
- Suite: functional
- Expected: if owner membership assignment is temporarily absent but legacy `active_character_id` is still valid, owner launch remains possible on the compatibility path
- AC: AC-7

### TC-MP-02 — Joined player can see shared campaign in campaign list
- Suite: functional
- Expected: an active player membership causes the campaign to appear in the joined player's campaign list
- AC: AC-2

### TC-MP-03 — Host can directly add existing user as active member
- Suite: functional
- Expected: host adds an existing authenticated user and the membership becomes active immediately; no second acceptance step is required
- AC: AC-2

### TC-MP-04 — Outsider cannot access shared campaign routes
- Suite: functional
- Expected: non-member authenticated user receives denied access for campaign play routes
- AC: AC-2, AC-7

### TC-MP-05 — Joined member cannot access host-only lifecycle routes
- Suite: functional
- Expected: joined member is denied archive/delete/unarchive surfaces that remain host/admin-only
- AC: AC-2, AC-7

### TC-MP-06 — Host retains privileged owner actions
- Suite: functional
- Expected: host/owner can still reach owner-only actions that joined players cannot
- AC: AC-1, AC-2

### TC-MP-06A — Host campaign management page renders roster controls
- Suite: functional
- Expected: host can open the campaign management page, see current members, and reach add/remove/lifecycle controls from that surface
- AC: AC-2, AC-6

### TC-MP-06B — Joined member cannot access host campaign management page
- Suite: functional
- Expected: joined active member is denied the dedicated owner management page even though they can still access normal play surfaces
- AC: AC-2, AC-7

### TC-MP-07 — Member assigns only their own character
- Suite: functional
- Expected: active member can assign a completed character they own and cannot assign a library character owned by another user
- AC: AC-3

### TC-MP-08 — One player maps to one character
- Suite: functional
- Expected: a member can be assigned one campaign character and cannot simultaneously control another active member's character
- AC: AC-3

### TC-MP-09 — Duplicate character assignment is rejected
- Suite: unit
- Expected: attempting to assign the same character to two active members fails explicitly
- AC: AC-3

### TC-MP-09A — Reassignment does not wipe existing runtime progress
- Suite: unit or functional
- Expected: when an existing campaign runtime row is reused, persisted position/state fields are preserved instead of being silently reset from canonical data
- AC: AC-7

### TC-MP-10 — Campaign state write route exists and is protected
- Suite: functional
- Expected: `POST /api/campaign/{campaign_id}/state` is present, requires CSRF, and is denied to outsiders
- AC: AC-7

### TC-MP-11 — Stale shared-state write returns conflict
- Suite: unit
- Expected: second writer using an outdated version is rejected with conflict behavior
- AC: AC-4

### TC-MP-12 — Hexmap launch accepts joined member with valid assignment
- Suite: functional
- Expected: joined active member can reach the campaign launch path when using their assigned runtime character and is denied when pointing at another member's runtime row
- AC: AC-6, AC-7

### TC-MP-12A — Owner-only hexmap launch still works after membership cutover
- Suite: functional
- Expected: owner-controlled single-player launch still succeeds after `HexMapController` moves off direct owner-only checks
- AC: AC-7

### TC-MP-13 — Out-of-turn combat action is rejected
- Suite: functional
- Expected: a member whose character does not own the current turn cannot mutate combat state
- AC: AC-5

### TC-MP-14 — Combat actor spoofing is rejected
- Suite: functional
- Expected: logged-in member cannot submit a combat action using another participant's `actorId` even if the participant exists in the encounter payload
- AC: AC-5, AC-7

### TC-MP-15 — Member/turn metadata is exposed to the client shell
- Suite: functional
- Expected: response payload or rendered state includes enough multiplayer metadata to show roster and current actor
- AC: AC-6

### TC-MP-15B — Management page shows assignment readiness clearly
- Suite: functional
- Expected: the owner management page exposes which members are missing character assignment versus ready to launch without requiring the tavern flow to act as the admin surface
- AC: AC-6

### TC-MP-15A — Single-player campaign receives compatible metadata shape
- Suite: functional
- Expected: owner-only campaigns still receive a valid roster/current-actor metadata shape without breaking the existing shell
- AC: AC-6, AC-7

### TC-MP-16 — Owner can still archive/unarchive/delete after migration
- Suite: functional
- Expected: owner-only lifecycle actions continue to work after host/member access split is introduced
- AC: AC-7

### TC-MP-17 — Archived campaign migration remains safe
- Suite: functional
- Expected: archived campaigns gain owner membership without becoming inaccessible or accidentally reactivated
- AC: AC-7

### TC-MP-18 — Missing or stale legacy character assignment degrades safely
- Suite: functional
- Expected: campaign remains viewable/manageable and prompts for reselection instead of hard-failing when old `active_character_id` is invalid
- AC: AC-7

### TC-MP-18A — Slice 3 requires explicit POST state-route parity
- Suite: functional route contract
- Expected: the exact route used by controller/tests for campaign state writes is registered, protected, and returns conflict semantics consistently
- AC: AC-4, AC-7

### TC-MP-18B — Joined member launch resolves to own runtime row before combat actions
- Suite: functional
- Expected: the same assignment/runtime mapping used for joined-member hexmap launch is the row later used for combat authority, with no fallback to another member's runtime entity
- AC: AC-5, AC-6, AC-7

### TC-MP-19 — Two-user manual smoke path works without live sync
- Suite: manual multi-user smoke
- Expected: user A acts, user B refreshes and sees updated state, stale action from user B is rejected until reload
- AC: AC-4, AC-6

---

## Definition of done

- [ ] Membership schema/update coverage passes
- [ ] Owner-only migrated campaigns still function end-to-end
- [ ] Host direct-add membership flow is covered
- [ ] Joined-player access/listing functional coverage passes
- [ ] Host-only routes remain host-only after membership widening
- [ ] Owner management page is covered for host access, joined-player denial, and roster readiness visibility
- [ ] Character assignment rules are covered
- [ ] Runtime-row reuse preserves existing campaign progress
- [ ] Campaign state POST route + protection are covered
- [ ] Joined-member hexmap launch is covered
- [ ] Owner-only launch and lifecycle flows still work after access cutover
- [ ] Conflict handling and out-of-turn enforcement are covered
- [ ] Slice 3 prerequisite contracts are covered before turn-authority work ships
- [ ] Manual two-user smoke check passes or explicit risk acceptance is recorded
