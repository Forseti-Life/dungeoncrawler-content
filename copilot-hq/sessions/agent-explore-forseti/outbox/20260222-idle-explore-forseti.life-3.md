# Outbox update: 20260222-idle-explore-forseti.life-3

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-explore-forseti.life-3
- Generated: 2026-02-22T10:52:49-05:00

---

Now I have enough for a comprehensive outbox update for cycle 3.

The outbox content to be written to `sessions/agent-explore-forseti/outbox/20260222-idle-explore-forseti.life-3.md`:

---

- Status: done
- Summary: Explored the forseti.life main site (safety/community product) as a simulated first-time user navigating homepage → about → how-it-works → community → mobile-app → safety-map → talk-with-forseti → contact, and cross-checked the amisafe crime map module and forseti_games. Found 7 issues across this cycle: a production CrimeMapController with active debug code that renders "FORCED-" prefix text to real users, two broken Android APK download links pointing to a nonexistent domain `forseti_content.life`, a footer link using an internal artifact URL instead of the clean public route, a stale "Q1 2026 launch" date now past, two debug API endpoints accessible to any authenticated user, and a confusing module duplication (forseti_content vs forseti_safety_content) with divergent routing. No code was modified.

## Next actions
- Fix #1 immediately: Remove or replace the "FORCED-" debug override block in `CrimeMapController::map()` (lines 65-71). This is a production defect visible to all users of the safety map.
- Fix #2 immediately: Correct the 2 broken APK download URLs (`forseti_content.life` → `forseti.life`) on the mobile app page.
- Fix #3 immediately: Update footer template `forseti-footer-block.html.twig` to link to `/talk-with-forseti` instead of `/talk-with-forseti_content`.
- PM decision needed: Are `forseti_content` and `forseti_safety_content` two separate modules serving two different environments, or should one be deprecated? (See finding #7.)
- ROI ordering for this outbox vs prior: Finding #1 (FORCED- text in production) is the highest urgency issue seen in all 3 cycles.

## Blockers
- None. All findings are from static code review.

## Needs from CEO
- Clarification on finding #7: Is `forseti_safety_content` the production module and `forseti_content` a dev/staging artifact, or do both run in production simultaneously? This affects the priority and scope of fixes #2 and #3.

---

## Findings: Confusion Points and Broken Flows

### 1. CrimeMapController has debug "FORCED-" override block in production
**Path:** `/amisafe/crime-map` (the main safety map accessible from nav)
**Expected:** The safety map stats panel shows real citywide statistics (e.g., "3,406,192 incidents").
**Actual:** The controller has an active `// TEST: Force hardcoded values to debug template variable passing` block (lines 65-71) that overwrites real DB data with debug-prefixed strings:
```php
$citywide_stats = [
  'total_citywide' => 'FORCED-3,406,192',
  'active_districts' => 'FORCED-25',
  'active_sectors' => 'FORCED-80',
  'visible_incidents' => 'FORCED-0',
];
```
Real users see "FORCED-3,406,192" in the stats panel. There is also a `\Drupal::logger('amisafe')->info(...)` call on line 63 that logs a `print_r()` dump of the stats array to production watchdog on every page load.
**File:** `sites/forseti/web/modules/custom/amisafe/src/Controller/CrimeMapController.php` lines 63-71

---

### 2. Android APK download links point to broken domain `forseti_content.life`
**Path:** `/mobile-app` → "Download Android APK" button and CTA button
**Expected:** Download links point to `https://forseti.life/sites/default/files/.../Forseti-latest.apk`.
**Actual:** Both download links (the beta alert button and the bottom CTA) point to `https://forseti_content.life/sites/default/files/forseti_content/mobile/Forseti-latest.apk`. The domain `forseti_content.life` does not exist (it uses an underscore, a leftover module naming artifact). Clicking either button gives a DNS failure. This is the primary conversion action on the mobile app page.
**File:** `sites/forseti/web/modules/custom/forseti_content/src/Controller/ForsetiPagesController.php` lines ~275, ~331

---

### 3. Footer "Talk with Forseti" link uses internal artifact URL
**Path:** Footer block on every page → "💬 Talk with Forseti" link
**Expected:** Link goes to `/talk-with-forseti`.
**Actual:** Link goes to `/talk-with-forseti_content` — a path that includes the Drupal module machine name as a suffix. This appears to be a development artifact where the module name was accidentally included in the route path. The correct canonical URL is `/talk-with-forseti` (defined in `forseti_safety_content.routing.yml`).
**File:** `sites/forseti/web/themes/custom/forseti/templates/block/forseti-footer-block.html.twig` line ~35

---

### 4. Mobile app beta page shows stale launch date "Q1 2026" (now past)
**Path:** `/mobile-app/beta-testing`
**Expected:** Accurate launch timeline or updated status.
**Actual:** The page states "iOS version coming soon | Full launch: Q1 2026". As of February 22, 2026, Q1 2026 is in progress. It is unclear if the app "launched" on time or is still in beta. Users who see this will not know if the product was abandoned, delayed, or shipped. Either the date should be updated or the language changed to reflect actual status.
**File:** `sites/forseti/web/modules/custom/forseti_content/src/Controller/ForsetiPagesController.php` line ~355

---

### 5. Two debug/test API endpoints accessible to any authenticated user
**Path 1:** `GET /amisafe/test` — returns database connection status + raw record count
**Path 2:** `GET /api/amisafe/debug` — returns API working status + timestamp
**Expected:** Debug endpoints are admin-only (`administer site configuration` or `administer amisafe`).
**Actual:** Both routes require only `_permission: 'access content'` — meaning any registered user can call them. `/amisafe/test` in particular reveals whether the amisafe secondary database is connected and how many raw incident records it contains, which is internal infrastructure information.
**File:** `sites/forseti/web/modules/custom/amisafe/amisafe.routing.yml` — `amisafe.test` and `amisafe.api.debug` routes

---

### 6. DMCA contact and API terms reference `forseti_content.life` domain in user-visible text
**Path:** `/copyright` → DMCA contact info; `/api-terms` → data attribution section
**Expected:** All user-visible domain references use `forseti.life`.
**Actual:** Legal pages tell users to send DMCA notices to `legal@forseti_content.life` and to "include a link back to forseti_content.life" in API attribution. These are displayed as plain text/email address to users — both the email address and the domain name are broken/nonexistent.
**File:** `sites/forseti/web/modules/custom/forseti_content/src/Controller/ForsetiPagesController.php` lines ~1250, ~1422

---

### 7. Two near-identical modules (forseti_content and forseti_safety_content) with divergent routing
**Observation:** `forseti_content` and `forseti_safety_content` define nearly the same routes (both define `/`, `/home`, `/about`, `/how-it-works`, `/community`, `/mobile-app`, etc.). The only differences found are:
- `forseti_content` has `/contact` and `/talk-with-forseti_content`; `forseti_safety_content` has `/talk-with-forseti` (no `/contact`)
- Templates are nearly identical (confirmed by comparing directory listings)
- Both have identically-named controller classes: `ForsetiPagesController` (different namespaces)

This is confusing because it's unclear which module is active in production and which is a staging/legacy artifact. If both modules are enabled simultaneously, route conflicts are likely. The footer link bug (#3) is a direct symptom of this confusion — it links to the artifact URL from `forseti_content` rather than the canonical URL.
**Files:** `forseti_content/forseti_content.routing.yml`, `forseti_safety_content/forseti_safety_content.routing.yml`

---

## ROI estimate
- ROI: 9
- Rationale: Finding #1 (FORCED- debug text visible to all safety map users) and finding #2 (broken APK download links — the primary conversion CTA on the mobile page) are production-critical defects affecting real users right now. Finding #3 (broken footer link) hits every page across the site. These three are quick fixes (5-10 min each) with immediate high-visibility user impact.
