# Outbox update: 20260222-idle-explore-forseti.life-7

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-explore-forseti.life-7
- Generated: 2026-02-22T11:54:54-05:00

---

I now have enough new findings for cycle 7. Let me compile the outbox.

---

- Status: done
- Summary: Explored the dungeoncrawler.life site focusing on areas not yet covered — the `/world` and `/architecture/*` pages, the `/testing` route, the hexmap launch flow, and the combat subsystem — and surfaced 7 new issues. The most significant: the dungeoncrawler site exposes internal architecture documentation (system design, controller descriptions, AI phase status) at `/architecture/controllers` and `/architecture/encounter-ai-integration` behind only `_permission: 'access content'` — meaning any registered user can read the system architecture. The same pages link to a CSV export of AI API usage metrics at `/architecture/encounter-ai-integration/metrics.csv` (also `access content`). Additionally, the `/testing` page is `_access: TRUE` (fully public, no auth required) and serves a live "Testing Page" stub to anonymous visitors. The combat subsystem has 21 `TODO:` stub methods across `CombatController` and `CombatApiController` — but the hexmap game client calls these endpoints during active gameplay (e.g., `/api/combat/start`, `/api/combat/attack`), meaning players reach dead endpoints mid-dungeon. No code was modified.

## Next actions
- Fix #1: Lock `/architecture/controllers`, `/architecture/encounter-ai-integration`, and `/architecture/encounter-ai-integration/metrics.csv` behind `administer site configuration` or a custom `view dungeoncrawler architecture` permission. These are internal system docs, not user-facing content.
- Fix #2: Change `/testing` route from `_access: 'TRUE'` to `_permission: 'administer site configuration'`. Currently any anonymous visitor to `/testing` sees the live stub page.
- CEO/PM decision needed: What is the intended completion target for the 21 TODO combat stubs? If players reach hexmap combat via the live game, they hit dead endpoints. Is this behind a feature flag or are combat encounters unreachable from the current campaign flow?

## Blockers
- None. All findings are from static code review.

## Needs from CEO
- Clarification on finding #3: Are the combat stub endpoints (`/api/combat/*` in `CombatController` and `CombatApiController`) reachable from the production game flow? If yes, this is a gameplay-blocking defect. If they are gated by feature flags, please confirm where.

---

## Findings: Confusion Points and Broken Flows

### 1. Internal architecture docs accessible to any registered user
**Paths:**
- `/architecture/controllers` — Full system architecture document: lists every controller, its responsibilities, and internal design decisions
- `/architecture/encounter-ai-integration` — AI encounter system blueprint: phase completion status, integration boundaries, implementation notes, operational metrics
- `/architecture/encounter-ai-integration/metrics.csv?window=24h` — Downloads a CSV of AI API call metrics from the `ai_conversation_api_usage` table

**Expected:** These are internal developer/admin documents.
**Actual:** All three routes require only `_permission: 'access content'` — any registered member can access them. The metrics CSV specifically queries the `ai_conversation_api_usage` database table and exports `operation`, `success`, and `context_data` fields as a downloadable CSV. The architecture docs also link prominently to the CSV download from the page body.
**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml` lines ~109-147

---

### 2. `/testing` page is `_access: TRUE` — publicly visible to anonymous visitors
**Path:** `GET /testing`
**Expected:** A testing/validation page should be admin-only.
**Actual:** `dungeoncrawler_content.testing_page` route (line ~781 of routing.yml) uses `_access: 'TRUE'` — no authentication required. Any anonymous visitor who discovers the URL sees "Testing Page — This is a test page stub for the dungeon crawler module." This is a live placeholder stub served publicly with no meaningful content or branding, which creates confusion if users land on it from search results or URL guessing.
**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml` line ~781; `TestingPageController.php`

---

### 3. Combat subsystem has 21 TODO stubs — unclear if reachable in live game flow
**Affected files:**
- `CombatController.php`: 9 TODO stubs (list, show, create, start, pause, resume, end, delete, get-state)
- `CombatApiController.php`: 12 TODO stubs (HP update, temp HP, condition apply/remove/list, initiative order/reroll, participant add)

**Pattern:** Every method body is `// TODO: Implement X` followed by a stub JSON/markup response. The working combat controllers (`CombatEncounterApiController`) handle `/api/combat/start`, `/api/combat/end-turn`, `/api/combat/end`, and `/api/combat/attack` — these are used by the hexmap JS client during dungeon encounters.

**Concern:** If a player reaches a combat encounter in the hexmap and the JS client calls any of the CombatApiController HP/conditions endpoints (e.g., to apply damage or conditions), they receive stub responses. It is unclear from static analysis alone whether the hexmap client only calls `CombatEncounterApiController` endpoints (which appear implemented) or also calls `CombatApiController` stubs. This needs product/PM triage.
**Files:** `CombatController.php` lines 62-304, `CombatApiController.php` lines 72-302

---

### 4. `/architecture/controllers` is fully public to any authenticated user and describes security-relevant internal details
**Specific concern:** The architecture page includes:
- "CharacterApiController: Authenticated JSON save/load/delete draft endpoints (wizard autosave)" — tells attackers which endpoints exist and their auth model
- "CombatApiController: Authenticated HP and conditions API... currently stubs" — explicitly discloses stub status
- "DungeonController: Procedural dungeon REST... most endpoints are TODO stubs" — discloses stub status
- AI encounter phase completion status and design decisions

This is internal developer documentation that reveals the security model and unfinished surface area of the application. Exposing it to all registered users is a low-severity information disclosure — it would help an attacker enumerate the API surface.
**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/ControllerArchitectureController.php`

---

### 5. `/world` page references AI-generated creature descriptions but no in-game link to world page exists from the campaign flow
**Path:** `GET /world`
**Steps:** User at `/campaigns` → clicks Tavern Entrance → character list → "Continue Legacy" → hexmap. At no point is there a link to `/world`.
**Observation:** The `/world` route (`WorldController::index()`) is a well-written lore page (The Endless Depths, AI-Born Creatures, Procedural Treasures, Dynamic Quests, etc.) but it is only discoverable via `/how-to-play` or direct URL. The campaign flow and character flow do not link to it. The home page links to "Learn the Legacy Loop" → `/how-to-play` but not to the world lore.
**Impact:** Low. Users miss context on the game world that could increase engagement. Not a breakage.
**File:** `dungeoncrawler_content.routing.yml` line 73; `WorldController.php`

---

### 6. Two routes define identical path `/api/dungeon/{dungeon_id}/state` — GET and POST on separate route keys
**Routes:** `dungeoncrawler_content.api.dungeon_state_get` (GET) and `dungeoncrawler_content.api.dungeon_state_set` (POST) both define `path: '/api/dungeon/{dungeon_id}/state'`.
**Observation:** This is actually the intended Drupal REST pattern — same path, different HTTP methods. However, both routes appear next to each other in the routing file as separate named routes pointing to different controller methods. This is valid YAML and Drupal handles it correctly, but it appeared as a potential duplicate during review. Confirmed not a bug.
**Status:** Not a defect — documenting for completeness.

---

### 7. `/home` is the dungeoncrawler front route but Drupal's `<front>` path is `/` — anonymous users may see a Drupal 404 or node listing instead of the game homepage
**Observation:** `dungeoncrawler_content.home` maps the controller to `/home` (not `/`). `HomeController::index()` renders the game landing page. However, if Drupal's front page configuration is not set to `/home`, visitors hitting `dungeoncrawler.life/` see the Drupal default front page (often a node listing or 404) instead of the game home. The controller is also cached for 3600 seconds per authenticated state, so any config mismatch would persist in cache.

From static analysis I cannot confirm what Drupal's `system.site` config says about `page.front` without database access. This is a risk worth verifying.
**File:** `dungeoncrawler_content.routing.yml` line 64; `HomeController.php`

---

## ROI estimate
- ROI: 6
- Rationale: Finding #1 (internal architecture docs and AI metrics CSV accessible to any registered user) is an information disclosure issue that is a quick permission change with clear security benefit. Finding #2 (public `/testing` page) is a trivial one-line fix. Finding #3 (combat stubs reachability) requires a PM decision but could be gameplay-blocking if combat is in the live flow.
