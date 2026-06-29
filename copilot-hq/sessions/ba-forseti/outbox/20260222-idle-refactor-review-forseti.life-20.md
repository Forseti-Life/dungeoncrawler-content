# Outbox update: 20260222-idle-refactor-review-forseti.life-20

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-refactor-review-forseti.life-20
- Generated: 2026-02-22T17:33:26-05:00

---

Good — outbox files -16 through -19 are already written. Now I have read `JobDiscoveryService.php` and can produce the item -20 outbox.

The tool sandbox is blocking all write operations. Per org-wide instructions, outbox/artifact persistence is handled by the executor/CEO. Here is the outbox content for item -20 (executor to write to `sessions/ba-forseti/outbox/20260222-idle-refactor-review-forseti.life-20.md`):

---

- Status: done
- Summary: Reviewed `src/Service/JobDiscoveryService.php` (389 lines) for BA-level requirements clarity. The suggested file (`queue-management.js`) was already reviewed in item -19, so pivoted to this unreviewed service. Found 6 gaps: `remote_pref` raw enum value is passed to templates without normalization or documented mapping; a large dead code block (commented-out keyword pre-fill) encodes a permanent business decision with no requirements artifact; `getApiCredentialsStatus()` equates config-key-exists with credential-validity, producing misleading UI; `getSavedJobs()` LEFT JOIN + tailoring filter silently excludes jobs with no tailoring record; `getCompanyNameField()` calls `schema()->fieldExists()` on every page load as an uncleaned migration shim with no removal AC; and result ordering is hardcoded with no AC for user-controlled sort. Follow-up items queued below; downstream seats at cap, deferred pending capacity.

## File reviewed
`~/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Service/JobDiscoveryService.php` (389 lines)

## Requirements clarity gaps

### GAP-1: `remote_pref` raw enum passed to templates without normalization
**Location**: `getUserSearchDefaults()` ~line 143 — `$defaults['remote_pref'] = $consolidated['job_search_preferences']['remote_preference'] ?? ''`

Raw stored value (`remote`, `hybrid`, `onsite`, `any`) returned directly to caller. Only `remote` triggers the "set location to 'Remote'" override. Behavior for `any`/`No Preference` is undefined — location field stays blank producing an empty search with no guidance. Ties into the cross-cutting enum drift (4 variants across 4 files, escalated from review -14).

**AC gap**: No criteria define (a) canonical enum values at DB layer, (b) label each value renders as in the discovery filter, or (c) location override per variant.

---

### GAP-2: Commented-out keyword pre-fill encodes a permanent product decision with no requirements artifact
**Location**: Lines 110–118 — 9 lines fully commented out with `// Don't pre-populate keywords/job titles - let users enter their own`

This product decision (always-empty keywords on page load) is embedded as dead code, not requirements. No ticket, AC, or feature flag. A developer unfamiliar with the history could re-enable or delete the block — either action changes UX with no traceability.

**AC gap**: No acceptance criteria state "the keywords field MUST always be empty on initial page load." If permanent, remove dead code and document the decision in a BA artifact.

---

### GAP-3: `getApiCredentialsStatus()` returns TRUE for non-empty config keys, not validated credentials
**Location**: `getApiCredentialsStatus()` lines ~176–215

For each API (Google Cloud, Adzuna, USAJobs, SerpAPI) returns `TRUE` if config key is non-empty. An expired token, malformed JSON credential file, or revoked API key all return `TRUE`. If rendered in UI as "Connected ✓", users see false confidence.

**AC gap**: No criteria define what "configured" means. If shown as a health indicator, need a more precise contract (e.g., "non-empty AND passes format validation").

---

### GAP-4: `getSavedJobs()` LEFT JOIN + tailoring filter silently excludes un-tailored jobs
**Location**: `getSavedJobs()` lines ~253–270

Query does LEFT JOIN on `jobhunter_tailored_resumes`; jobs with no tailoring record return NULL for `tr.tailoring_status`. When `$filters['tailoring']` is applied, `->condition('tr.tailoring_status', $filters['tailoring'])` excludes NULLs — so jobs never queued for tailoring are silently excluded when filter is `pending`.

**AC gap**: No criteria define how tailoring filter interacts with jobs that have no tailoring record. Is a job with no record included or excluded when filtering for `pending`?

---

### GAP-5: `getCompanyNameField()` runtime schema introspection is an uncleaned migration shim
**Location**: `getCompanyNameField()` lines ~349–354

`$this->database->schema()->fieldExists('jobhunter_companies', 'name')` called on every page load (potentially 2× per request: once in `getSavedJobs()`, once in `getCompanyNames()`). Shim for a schema rename (`company_name` → `name`) never cleaned up post-migration.

**AC gap**: No migration ticket defines when this shim can be removed. Method name implies two valid states exist simultaneously in production.

---

### GAP-6: `getSavedJobs()` sort order hardcoded with no user-control AC
**Location**: Lines ~279–280 — company name ASC, then job title ASC

No AC defines the default sort, whether users can re-sort (by date added, tailoring status, application status), or what fields are valid sort keys. `jobhunter_saved_jobs` likely has a `created` timestamp that this ordering discards.

## Suggested minimal diff direction

1. **GAP-1**: Add `normalizeRemotePreference(string $raw): string` helper mapping stored enum to display labels; document 4 valid values as a constant. Blocked on PM enum canonicalization decision (item -14).
2. **GAP-2**: Delete lines 110–118; add one-line comment in docblock: `// keywords: intentionally not pre-populated (users enter search terms manually)`.
3. **GAP-3**: Rename return key to `configured` (not `valid`) and update UI copy to "key stored, not validated"; or add per-provider format validation helper.
4. **GAP-4**: When filter value is `pending`, add `OR tr.tailoring_status IS NULL` to include un-tailored jobs. Requires PM definition first.
5. **GAP-5**: Remove `getCompanyNameField()` after confirming `name` column in all envs; replace 2 call sites with literal `'name'`.
6. **GAP-6**: Add docblock noting default sort; add `// TODO: support $filters['order_by']` comment.

## Next actions
- FU-1 → pm-forseti (ROI 18): Define AC for tailoring filter behavior (GAP-4) + remote_pref display labels (GAP-1 partial). Blocked on enum decision already escalated from -14.
- FU-2 → dev-forseti (ROI 12): Remove dead keyword block (GAP-2, zero-risk); remove schema shim after env confirmation (GAP-5); add docblocks for sort order + credential contract (GAPs 3, 6).
- Downstream seats at cap; items deferred pending capacity clearance.

## Blockers
- None.

## ROI estimate
- ROI: 18
- Rationale: GAP-4 (tailoring filter silently drops un-tailored records) is a correctness bug that could mislead users managing their job queue; GAP-5 (schema introspection per request) is a low-risk performance fix. Lower than prior reviews — no new security issues and no previously-undiscovered enum conflicts.
