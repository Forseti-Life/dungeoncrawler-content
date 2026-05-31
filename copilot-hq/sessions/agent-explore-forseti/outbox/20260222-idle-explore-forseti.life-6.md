# Outbox update: 20260222-idle-explore-forseti.life-6

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-explore-forseti.life-6
- Generated: 2026-02-22T13:57:46-05:00

---

I have enough findings for a complete cycle 6 outbox. Let me produce it:

---

- Status: done
- Summary: Idle exploration cycle 6 complete. Static code review of two previously unexplored forseti.life modules: `forseti_safety_content` (home, pages, safety map, agent-power-framework, legal pages) and `institutional_management` (landing, dashboard, groups, group map). Key findings: (1) `GroupMapController::viewGroupMap()` passes all group member real-time GPS coordinates (latitude, longitude from `user_location_tracking` table) to the Twig template and JavaScript as JSON — any authenticated group member at `/group/{group}/map` can see the live locations of all other group members with no opt-in consent check; (2) `GroupMapController` hardcodes API paths `/api/location/update` and `/api/location/latest?group_id=X` in its rendered JS config, but the actual location routes in `amisafe.routing.yml` are `/api/amisafe/location/update` and `/api/amisafe/location/history` — path mismatch means live map refresh is silently broken; (3) `InstitutionalController::dashboard()` is a stub (`"Dashboard content coming soon..."`) despite being behind the `access institutional dashboard` permission gate; (4) `forseti_safety_content` has a duplicate `safetyMap()` in `ForsetiPagesController` that calls `$response->send()` directly inside a private helper (bypasses Drupal's render pipeline, outputs raw redirect response, returns empty string to render array); (5) `forseti.talk_with_forseti` at `/talk-with-forseti` requires only `access content` (anonymous-accessible per routing) but controller redirects anonymous to registration — the UX message says "register for a free account" but no price/plan is actually defined anywhere in the codebase; (6) `institutional_management.landing` at `/institutional` is fully anonymous-accessible and prominently links to `/group/add/family` and `/group/add/institution` — if Drupal's Group module requires authentication for group creation, anonymous visitors clicking these CTAs will hit an access-denied error with no friendly redirect.

## Product context
- Website: forseti.life
- Module: forseti_safety_content, institutional_management
- Role: agent-explore-forseti (Security Explorer)
- Feature/work item: Idle exploration cycle 6 — forseti_safety_content + institutional_management user flow review

## Findings

### F1 — HIGH: GroupMapController exposes all member GPS locations with no per-member consent check
- File: `sites/forseti/web/modules/custom/institutional_management/src/Controller/GroupMapController.php` lines 44–90
- Route: `GET /group/{group}/map` — `_user_is_logged_in: TRUE`
- `$members = $group_entity->getMembers()` → for each member, queries `user_location_tracking` table for `latitude`, `longitude`, `recorded_at`, `accuracy`
- All member locations passed to Twig as `#members` array AND injected into JS as `drupalSettings.groupMap.members`
- Any authenticated group member can see real-time GPS coordinates of all other group members
- No per-member opt-in / location-sharing consent check before including them in the map data
- Privacy risk: family or institution member who joins a group has their location silently broadcast to all other members
- Fix: add a `location_sharing_consent` flag (or similar) to group membership records; exclude members who have not explicitly opted in to sharing location with group

### F2 — MEDIUM: GroupMapController JS API paths don't match actual amisafe route paths (broken live refresh)
- File: `sites/forseti/web/modules/custom/institutional_management/src/Controller/GroupMapController.php` lines 140–141
- Controller injects into JS config:
  - `'latestLocations' => '/api/location/latest?group_id=' . $group`
  - `'updateLocation' => '/api/location/update'`
- Actual routes in `amisafe.routing.yml`:
  - `/api/amisafe/location/update`
  - `/api/amisafe/location/history` (no `group_id` param)
- Path mismatch → JS polling for live location updates silently 404s; map shows stale location snapshot from initial page load only
- Fix: update GroupMapController to reference `/api/amisafe/location/history` and `/api/amisafe/location/update`; also confirm `group_id` filter is supported by `ApiController::locationHistory()`

### F3 — MEDIUM: InstitutionalController::dashboard() is a stub behind a real permission gate
- File: `sites/forseti/web/modules/custom/institutional_management/src/Controller/InstitutionalController.php` line 96
- Route: `GET /institutional/dashboard` — `_permission: 'access institutional dashboard'`
- Body: `'Dashboard content coming soon...'` — no real implementation
- No authenticated users currently have `access institutional dashboard` (confirmed: not in `user.role.authenticated.yml`)
- Risk: if the permission is granted to any role, users will land on a blank stub page with no explanation
- Fix: either implement the dashboard or redirect to `/my-groups` and remove the stub route until ready

### F4 — MEDIUM: ForsetiPagesController::safetyMap() calls $response->send() inside render pipeline (broken pattern)
- File: `sites/forseti/web/modules/custom/forseti_safety_content/src/Controller/ForsetiPagesController.php` (private `getSafetyMapContent()`)
- `ForsetiPagesController::safetyMap()` returns `['#markup' => $this->getSafetyMapContent()]`
- `getSafetyMapContent()` creates `new \Symfony\Component\HttpFoundation\RedirectResponse('/amisafe/crime-map')`, calls `$response->send()` (flushes headers/body directly to PHP output), then returns `''`
- This is architecturally broken: `$response->send()` inside a render method can corrupt Drupal's response stack, cause double-output, or silently fail depending on output buffering
- However: `forseti.safety_map` at `/safety-map` is wired to `ForsetiHomeController::safetyMap()` (a proper implementation), NOT `ForsetiPagesController::safetyMap()` — so this broken method may be unreachable dead code
- Fix: confirm `ForsetiPagesController::safetyMap()` has no route; if confirmed dead, delete it. If reachable via any path, replace `$response->send()` with `return new RedirectResponse(...)` from the controller method

### F5 — LOW: /institutional landing CTA links to /group/add/family for anonymous users
- File: `sites/forseti/web/modules/custom/institutional_management/src/Controller/InstitutionalController.php` lines 37, 143, 200
- Route: `GET /institutional` — `_permission: 'access content'` (anonymous-accessible)
- Landing page hero prominently links to `/group/add/family` with a large "Create Your Family Group" CTA button
- Anonymous visitors clicking this CTA will hit Drupal's Group module create-entity form, which requires authentication
- Expected: anonymous user is redirected to login/register with a clear message
- Actual: likely a generic Drupal access-denied or login redirect with no context
- Fix: wrap CTA link in conditional rendering — show "Register to Create a Group" (→ `/user/register`) for anonymous; show "Create Your Family Group" (→ `/group/add/family`) for authenticated

### F6 — LOW: /talk-with-forseti redirects anonymous to register with "free account" messaging — no plan/pricing page exists
- File: `sites/forseti/web/modules/custom/forseti_safety_content/src/Controller/ForsetiPagesController.php`
- Route: `GET /talk-with-forseti` — `_permission: 'access content'` (anonymous hits controller, gets redirected)
- Message: `"Conversations with Forseti are reserved for community members. Please register for a free account to get started."`
- No pricing or plan page found anywhere in `forseti_safety_content.routing.yml` — no `/pricing`, `/plans`, `/register-for-free` route
- Drupal's `/user/register` is a bare registration form with no context about what the user will get after registering
- UX gap: anonymous user visiting `/talk-with-forseti` gets sent to a generic registration form with no explanation of features or value proposition
- Fix: create a dedicated `/join` or `/register` landing page that explains what authenticated users get; use it as the redirect destination instead of bare `/user/register`

### F7 — OBSERVATION: Agent Power Framework has 35+ public dimension pages (all _permission: 'access content')
- All `/agent-power-framework/**` routes: `_permission: 'access content'` (anonymous-readable)
- No mutations — read-only informational pages about AI agent evaluation dimensions
- No security risk; this is a public educational feature
- Minor UX note: there is no breadcrumb or "back to overview" link visible in routing — users navigating deep into dimension sub-pages may have no path back to `/agent-power-framework`

## Next actions
- F1 (GPS consent): P1 — queue privacy fix to PM; needs product decision on consent model before implementation
- F2 (broken API paths): P2 — easy fix, queue to Dev with exact path correction
- F3 (dashboard stub): P3 — queue to PM to decide: implement or remove route
- F4 (broken safetyMap redirect): P2 — confirm dead code status; delete if unreachable
- F5 (anonymous CTA): P3 — queue UX fix to Dev
- F6 (register CTA): P3 — queue to PM/Dev for `/join` landing page

## Blockers
- None

## Needs from Supervisor
- None

## ROI estimate
- ROI: 8
- Rationale: F1 is a significant privacy issue — broadcasting real-time GPS of all group members (including family members) without per-member consent violates reasonable privacy expectations and likely GDPR/CCPA obligations for a community safety app. F2 means the live map refresh feature is silently broken for all users. These two findings together are high-impact on a product whose core value proposition is community safety.
