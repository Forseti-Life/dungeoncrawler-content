# Feature Brief: Campaign Multiplayer v1

- Work item id: dc-cr-campaign-multiplayer-v1
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release:
- Feature type: enhancement
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Priority: P1
- Project: PROJ-007
- Depends on: dc-cr-session-structure, dc-cr-encounter-rules, dc-cr-exploration-mode
- Source: community_suggestion NID 55 (Talk to Forseti intake)
- Category: campaign-system
- Created: 2026-05-16

## Summary

Deliver the first Dungeoncrawler multiplayer slice: multiple authenticated humans can participate in the same campaign, each player controls one character, campaign access is shared through explicit membership, and state-changing play is serialized through turn-taking rather than live real-time sync.

## Goal

- Add a campaign membership model that separates host ownership from active players.
- Let joined players discover and open campaigns they belong to.
- Support one player-to-one character assignment inside a shared campaign.
- Enforce serialized multiplayer state changes using the existing optimistic-version campaign state model.

## V1 operating decisions

- **Member onboarding:** the campaign owner directly adds **existing authenticated users** as active members. There is **no invite-acceptance flow** in v1.
- **Character ownership:** each active member selects **their own** completed character from their existing roster; v1 does not let the host assign another user's library character on their behalf.
- **Membership source of truth:** membership lives in a dedicated campaign-membership table, not in `dc_campaigns.uid`, `active_character_id`, or `dc_sessions.player_uids`.
- **Character assignment source of truth:** the membership row stores the assigned **canonical library character ID**; campaign runtime instances continue living in `dc_campaign_characters`.
- **Turn authority:** optimistic versioning remains the global write gate, and combat writes add a second gate: the acting member must match the current turn owner.

## Non-goals

- No websockets, push presence, or live cursor sync in v1.
- No simultaneous freeform action submission.
- No spectator mode or open public campaign browsing.
- No redesign of core combat rules beyond the minimum turn-ownership enforcement needed for multiplayer safety.

## Gap Analysis

### Implementation status

| Requirement | Existing code path | Coverage status |
|---|---|---|
| Campaign host vs. player membership model | `dungeoncrawler_content.install` (`dc_campaigns.uid`, `dc_sessions.player_uids`) | Partial |
| Joined-player campaign access checks | `src/Access/CampaignAccessCheck.php` | Partial |
| Joined-player campaign discovery/listing | `src/Controller/CampaignController.php` (`listCampaigns()`) | None |
| Owner campaign management surface | `src/Controller/CampaignController.php`, existing campaign lifecycle forms | None |
| Player character assignment inside a shared campaign | `dc_campaigns.active_character_id`, `dc_campaign_characters`, `CampaignController::selectCharacter()` | Partial |
| Serialized shared-state writes and version conflict handling | `src/Service/CampaignStateService.php`, `src/Controller/CampaignStateController.php` | Partial |
| Combat/exploration turn ownership enforcement for multiple humans | campaign state payload + encounter flow handlers | None |

### Coverage determination

- **Feature type: enhancement** — Dungeoncrawler already has campaign/session persistence and optimistic shared-state versioning, but the multiplayer-specific membership, access, assignment, and turn-ownership layers are still missing.

### Test path guidance for QA

| Requirement | Test file | Test type |
|---|---|---|
| Membership schema + membership resolution rules | `tests/src/Unit/Service/CampaignMembershipServiceTest.php` | Unit |
| Campaign route access for host vs. joined player vs. outsider | `tests/src/Functional/Controller/CampaignAccessControllerTest.php` | Functional |
| Joined-player campaign listing | `tests/src/Functional/Controller/CampaignControllerTest.php` | Functional |
| Owner campaign management page | `tests/src/Functional/Controller/CampaignControllerTest.php` | Functional |
| One-player/one-character assignment rules | `tests/src/Functional/Controller/CharacterListControllerTest.php` | Functional |
| Shared-state version conflicts + turn ownership | `tests/src/Unit/Service/CampaignStateServiceTest.php` | Unit |
| Multiplayer happy-path play flow | `tests/src/Functional/Controller/CampaignStateControllerTest.php` | Functional |

## Acceptance Criteria (link)

See `features/dc-cr-campaign-multiplayer-v1/01-acceptance-criteria.md`.

## Delivery shape

Treat this as a **phased multiplayer epic** under one tracked feature:

1. membership schema + migration
2. access checks + joined-campaign discovery + host management page
3. self-service player-to-character assignment
4. turn-taking + conflict handling
5. QA hardening and rollout

The first dev slice should stop short of any websocket or live-presence work.

## Concrete v1 behavior

### Membership lifecycle

- Every campaign gets one `owner` membership row for the existing host user.
- The owner can add or remove existing authenticated users as `player` members.
- v1 only needs `active` and `removed` behavior. An `invited` status may exist for forward compatibility, but it is not part of the shipped flow.
- The owner gets a dedicated campaign management page that acts as the canonical host surface for roster management and other host-only campaign actions.

### Campaign discovery and launch

- The campaign list shows both hosted campaigns and joined campaigns.
- A joined player can open shared campaign surfaces, but gameplay launch requires that member to have an assigned character.
- `dc_campaigns.active_character_id` is treated as a compatibility field only and must not remain the multiplayer launch source of truth.

### Character assignment

- Each active member can assign exactly one of **their own** completed characters to that campaign.
- The same canonical character cannot be assigned to two active members in the same campaign.
- The assignment is stored on the membership row and resolved into the existing deterministic `dc_campaign_characters` runtime instance when play launches.

### State writes

- Exploration writes remain shared, but they still require the current `expectedVersion`.
- Combat writes require both:
  - a current `expectedVersion`
  - an acting member whose assigned character matches the current combat actor
- Stale or out-of-turn writes fail explicitly; no silent merge or overwrite path is acceptable in v1.

## Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Single-owner assumptions remain in hidden controller paths | High | High | Audit owner-only checks before implementation and add explicit joined-player tests |
| `active_character_id` leaks single-player assumptions into multiplayer play | High | High | Introduce explicit player-character mapping rather than stretching the campaign-wide field |
| Concurrent writes corrupt shared state | Medium | High | Reuse versioned writes and fail loudly on mismatch with reload-and-retry UX |
| Invite/member UX ships without adequate auth coverage | Medium | Medium | Add route/access functional coverage before release |

## Security acceptance criteria

- Authentication/permission surface: only authenticated campaign members can access campaign play routes; host/admin-only actions stay restricted.
- CSRF expectations: all new POST/PATCH membership or assignment routes require existing secured request-header protections.
- Input validation: membership mutations must validate target user IDs, campaign IDs, allowed role/status values, and single-character assignment constraints.
- PII/logging constraints: no player email or invite token leakage in logs; log campaign IDs, user IDs, membership state transitions, and version-conflict events only.

## Roadmap section

- Roadmap: Dungeoncrawler multiplayer

## Latest updates

- 2026-05-16: Created the tracked multiplayer v1 feature from community suggestion NID 55 and groomed it into a phased PROJ-007 feature covering membership, shared campaign access, one-character-per-player assignment, and serialized turn-taking without websockets.
- 2026-05-16: Refined the v1 design against the live code paths. The concrete delivery posture is now: owner-direct-add of existing users, self-service per-member character assignment, membership-row canonical character ownership, and optimistic-version writes with combat-specific turn-owner enforcement.
- 2026-05-16: Added the single-player compatibility contract and Slice 3 dependency gates so turn-authority implementation is blocked on stable membership, launch, access-split, and POST state-route contracts instead of being layered onto ambiguous single-player assumptions.
- 2026-05-16: Reviewed the plan against existing Drupal/module patterns and identified the lowest-complexity path: keep storage in custom tables, but reuse Drupal access-check services, Form API, confirm forms, entity autocomplete for member add, existing character-selection pages for self-assignment, and BrowserTestBase for regression coverage.
- 2026-05-16: Made the owner-facing campaign management page explicit in the plan. It is now the intended host control surface for member add/remove, roster visibility, and host-only campaign actions, instead of burying those concerns inside tavern/launch flows.
