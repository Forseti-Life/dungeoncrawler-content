# Test Plan — dc-cr-clan-dagger

- Feature: Clan Dagger (Dwarven Starting Equipment)
- Work item id: dc-cr-clan-dagger
- Release target: 20260308-dungeoncrawler-release-b (NEXT RELEASE — grooming only)
- QA owner: qa-dungeoncrawler
- AC source: features/dc-cr-clan-dagger/01-acceptance-criteria.md
- Date: 2026-03-09
- Status: groomed (NOT activated — do not add to suite.json until feature enters Stage 0)

## Knowledgebase check
- No prior lesson found for ancestry-granted items or sell-taboo mechanics specifically.
- This feature is the **prototype** for the "ancestry starting equipment" pattern — the implementation establishes the data model for future ancestry-granted items (per feature.md). First-of-kind patterns carry higher ambiguity risk; PM clarification items flagged below.
- Related prior test plan: `features/dc-cr-ancestry-traits/03-test-plan.md` (Dwarf trait `["Dwarf", "Humanoid"]` confirmed).
- Equipment system implementation notes: `features/dc-cr-equipment-system/02-implementation-notes.md` — catalog uses JSON files under `dungeoncrawler_content/content/items/`; no sell/trade endpoint exists yet in the current implementation scope.

---

## Suite mapping

| Suite ID | Type | Purpose |
|---|---|---|
| `dc-cr-clan-dagger-e2e` | playwright | End-to-end: auto-grant at creation, item properties, sell taboo, combat stats |
| `role-url-audit` | audit | ACL check: any new sell/trade route is blocked to non-owner roles |

> `dc-cr-clan-dagger-e2e` is a NEW suite to be added to `qa-suites/products/dungeoncrawler/suite.json` at Stage 0.

---

## Test cases

### Happy Path

#### TC-001: Clan Dagger exists in item catalog with correct stats
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Verify the `clan-dagger` item entity is defined in the item catalog with all required fields matching AC spec.
- Steps:
  1. `GET /equipment?type=weapon` (or catalog read equivalent)
  2. Find item with `id: clan-dagger`
  3. Assert each field
- Expected:
  - `damage: "1d4"`
  - `damage_type: ["piercing", "slashing"]` (or equivalent Versatile S representation)
  - `bulk: "L"`
  - `traits: ["Agile", "Dwarf", "Versatile S"]` (order-insensitive)
  - `level: 0`
  - `price: 0`
- Roles covered: administrator, authenticated
- AC: Happy Path — clan-dagger item entity definition

#### TC-002: Dwarf character receives Clan Dagger at creation
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Create a Dwarf character and verify a Clan Dagger appears in inventory automatically.
- Steps:
  1. POST to character creation endpoint with ancestry = Dwarf
  2. GET inventory for the newly created character
- Expected: Inventory contains exactly one item with `id: clan-dagger` (or `item_type: clan-dagger`); no player selection step was required.
- Roles covered: authenticated (player creating own character), administrator
- AC: Happy Path — auto-grant at character creation

#### TC-003: Clan Dagger is tagged with ancestry_granted and sell_taboo
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Inspect the inventory item record to confirm required flags are set.
- Steps:
  1. Create Dwarf character (as in TC-002)
  2. GET inventory item record for the Clan Dagger
  3. Assert flags
- Expected:
  - `ancestry_granted: true`
  - `sell_taboo: true`
- Roles covered: authenticated
- AC: Happy Path — inventory item tags

#### TC-004: Attempting to sell Clan Dagger triggers consequence
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Initiate a sell or trade action targeting the Clan Dagger; verify a consequence response is returned.
- Steps:
  1. Create Dwarf character with Clan Dagger in inventory
  2. Attempt sell/trade action on the Clan Dagger (via sell endpoint or trade flow)
  3. Assert response
- Expected: Response includes a taboo warning/consequence (e.g., `sell_blocked: true` or HTTP 422 with cultural consequence message, or GM confirmation required flag). The Clan Dagger is NOT removed from inventory unless GM override is applied.
- **PM clarification needed (see below — CQ-001)**: exact sell endpoint and response contract are not defined in the AC.
- Roles covered: authenticated
- AC: Happy Path — sell taboo consequence

#### TC-005: Clan Dagger functions as a standard weapon in combat
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Wield the Clan Dagger and make an attack; verify the attack is resolved using the item's stats.
- Steps:
  1. Create Dwarf character with Clan Dagger
  2. Start encounter and wield Clan Dagger
  3. Make an attack
  4. Assert attack result uses Clan Dagger stats (1d4 damage, piercing/slashing, traits applied)
- Expected: Attack is resolved with `damage_die: 1d4`, damage type is piercing (default) or slashing (if player chose Versatile S). Attack resolution follows dc-cr-encounter-rules.
- **Dependency note**: this test depends on dc-cr-encounter-rules being complete.
- Roles covered: authenticated
- AC: Happy Path — combat functionality

#### TC-006: Agile trait applies MAP −4/−8
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Make a second and third attack with the Clan Dagger in the same turn; verify MAP values are −4 and −8 respectively (not standard −5/−10).
- Steps:
  1. Create Dwarf character with Clan Dagger; start encounter
  2. First attack: MAP = 0 (baseline)
  3. Second attack: assert MAP = −4
  4. Third attack: assert MAP = −8
- Expected: MAP progression = 0 / −4 / −8 when Clan Dagger is the attacking weapon.
- **AC tag**: `[VERIFY]` — existing combat system must correctly apply Agile trait.
- Roles covered: authenticated
- AC: Happy Path — Agile trait MAP

#### TC-007: Versatile S trait allows slashing damage
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Attacker explicitly requests slashing damage type; verify the attack rolls use slashing.
- Steps:
  1. Wield Clan Dagger in encounter
  2. Make attack with slashing type selected
  3. Assert damage type on result = `slashing`
- Expected: Attack result shows `damage_type: slashing` when slashing is selected; default (no selection) = `piercing`.
- **AC tag**: `[VERIFY]` — existing combat system must respect Versatile S choice.
- Roles covered: authenticated
- AC: Happy Path — Versatile S trait

---

### Edge Cases

#### TC-008: Non-dwarf character does NOT receive Clan Dagger at creation
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Create a character with a non-Dwarf ancestry (e.g., Elf) and verify no Clan Dagger appears in inventory.
- Steps:
  1. POST character creation with ancestry = Elf (or Human, or any non-Dwarf)
  2. GET inventory
- Expected: No item with `id: clan-dagger` in inventory.
- Roles covered: authenticated
- AC: Edge Case — non-dwarf exclusion

#### TC-009: Each new Dwarf character receives its own Clan Dagger instance
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Create two Dwarf characters under the same account; verify each has its own Clan Dagger and they are distinct item instances (not the same ID).
- Steps:
  1. Create Dwarf character A → get Clan Dagger instance ID
  2. Create Dwarf character B → get Clan Dagger instance ID
  3. Assert IDs are different
- Expected: Two distinct item instances, each with `ancestry_granted: true` and `sell_taboo: true`. Character B's Clan Dagger is NOT the same entity as character A's.
- Roles covered: authenticated
- AC: Edge Case — separate instances per character

#### TC-010: Lost (not sold) Clan Dagger does not trigger sell taboo
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Drop/lose the Clan Dagger without initiating a sell or trade action; verify no taboo consequence fires.
- Steps:
  1. Create Dwarf character with Clan Dagger
  2. Drop/discard the Clan Dagger via drop action (not sell/trade)
  3. Assert: no sell_taboo consequence in response; no cultural penalty applied
- Expected: Item removed from inventory (or flagged as lost) without any sell_taboo consequence. Only sell/trade actions trigger the taboo.
- **PM clarification needed (see CQ-002)**: is "drop/lose" a distinct action from sell/trade in the API, or does QA need a separate endpoint?
- Roles covered: authenticated
- AC: Edge Case — lost vs. sold distinction

#### TC-011: Sell taboo fires only on explicit sell/trade — not on other item interactions
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Verify that non-sell/trade interactions (wield, stow, inspect, equip) do not trigger the sell taboo.
- Steps:
  1. Create Dwarf character with Clan Dagger
  2. Perform: equip → unequip → inspect → stow
  3. Assert: no sell_taboo consequence in any response
- Expected: No taboo consequence for any non-sell/trade action.
- Roles covered: authenticated
- AC: Edge Case — taboo scope

---

### Failure Modes

#### TC-012: Admin API bypass does not grant more than one Clan Dagger
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Attempt to add a second Clan Dagger via admin API or direct inventory manipulation; verify deduplication logic prevents a second instance.
- Steps:
  1. Create Dwarf character (already has one Clan Dagger)
  2. Use admin API to POST a second Clan Dagger grant to the same character
  3. Assert: inventory still contains exactly one Clan Dagger
- Expected: Idempotent grant — second grant is a no-op or returns an error; inventory count = 1.
- **PM clarification needed (see CQ-003)**: AC says "should not grant two" — is this a hard constraint (enforced at API layer) or a soft recommendation? What is the expected HTTP status for the duplicate grant?
- Roles covered: administrator
- AC: Failure Mode — duplication prevention

#### TC-013: Clan Dagger granted in follow-up step if catalog unavailable at creation
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Simulate equipment catalog unavailability during Dwarf character creation; verify creation succeeds and an error is logged, and the Clan Dagger can be granted retroactively.
- Steps:
  1. Mock/stub equipment catalog to return an error for the duration of character creation
  2. POST Dwarf character creation
  3. Assert: character created successfully (HTTP 200/201)
  4. Assert: error logged (e.g., in Drupal watchdog or API response metadata)
  5. Trigger follow-up Clan Dagger grant
  6. Assert: Clan Dagger now in inventory
- Expected: Creation does not fail due to item catalog unavailability. Error is observable. Follow-up grant mechanism exists.
- **PM clarification needed (see CQ-004)**: how is the follow-up grant triggered? Is there an explicit retry endpoint, or is it a background queue worker?
- Roles covered: authenticated, administrator
- AC: Failure Mode — catalog unavailable graceful fail

---

### Permissions / Access Control

#### TC-014: Item grant at creation is server-side only — client cannot bypass
- Suite: `dc-cr-clan-dagger-e2e` (playwright) + `role-url-audit`
- Description: Verify the Clan Dagger grant fires server-side regardless of what the client submits in the creation payload. A client that omits or skips the grant step still receives the Clan Dagger.
- Steps:
  1. POST Dwarf character creation with client payload that intentionally excludes any item grant field
  2. GET inventory
- Expected: Clan Dagger is present in inventory despite client omission. Grant is server-initiated, not client-triggered.
- Roles covered: authenticated
- AC: Permissions — server-side grant

#### TC-015: Sell taboo check is server-side — client cannot bypass
- Suite: `dc-cr-clan-dagger-e2e` (playwright)
- Description: Send a raw sell request that bypasses client-side UI warnings; verify the server still enforces the taboo block/flag.
- Steps:
  1. Craft a direct HTTP request to the sell endpoint, skipping UI
  2. Assert server returns taboo consequence (not a clean sell confirmation)
- Expected: Server enforces sell_taboo regardless of client behavior.
- Roles covered: authenticated
- AC: Permissions — server-side sell taboo enforcement

#### TC-016: GM can override sell taboo with explicit admin flag
- Suite: `dc-cr-clan-dagger-e2e` (playwright) + `role-url-audit`
- Description: A GM (admin-level user) initiates a sell override for the Clan Dagger with an explicit override flag; verify the sale is permitted.
- Steps:
  1. As an admin/GM user, POST sell request for Clan Dagger with `gm_override: true` (or equivalent flag per implementation)
  2. Assert: sell is permitted; Clan Dagger removed from inventory; no taboo consequence error returned
- Expected: Sale completes when GM override is present and the requesting user has admin/GM role.
- **PM clarification needed (see CQ-005)**: what role(s) constitute "GM" in the Drupal permission model? Is it `administrator` or a dedicated `dc_playwright_admin` equivalent for GMs?
- Roles covered: administrator (GM)
- AC: Permissions — GM override

---

## PM clarification items (required before Stage 0 activation)

| ID | AC item | Question |
|---|---|---|
| CQ-001 | Happy Path — sell taboo trigger | What is the sell/trade endpoint (route + HTTP method)? What is the exact response schema for the taboo consequence (HTTP status, response body fields)? |
| CQ-002 | Edge Case — lost vs. sold | Is there a distinct "drop/lose" action endpoint separate from sell/trade? How does the system differentiate a lost item from a sold item for taboo purposes? |
| CQ-003 | Failure Mode — duplication | Is duplicate-grant prevention a hard constraint (enforced server-side with error response) or a soft recommendation? What HTTP status is returned for the duplicate attempt? |
| CQ-004 | Failure Mode — catalog unavailable | What triggers the follow-up Clan Dagger grant when catalog was unavailable at creation time? Is this a manual admin action, a retry endpoint, or an async queue worker? |
| CQ-005 | Permissions — GM override | Which Drupal role(s) have GM override authority? Is this a new role, or does `administrator` suffice? |

---

## Notes to Dev

- This feature depends on a sell/trade endpoint that is not yet implemented in `dc-cr-equipment-system` (per `features/dc-cr-equipment-system/02-implementation-notes.md`). TC-004, TC-010, TC-011, TC-015, TC-016 cannot be activated until that endpoint exists. Dev should document the sell endpoint route + response schema in their implementation notes.
- TC-012 deduplication logic should be clarified in implementation notes: is this enforced at service layer, DB constraint, or both?
- The follow-up grant mechanism (TC-013) needs an explicit route or queue trigger documented in implementation notes.
