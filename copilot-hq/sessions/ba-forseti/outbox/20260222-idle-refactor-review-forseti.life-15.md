# Outbox update: 20260222-idle-refactor-review-forseti.life-15

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-refactor-review-forseti.life-15
- Generated: 2026-02-22T16:57:14-05:00

---

I have full context. `job-search-results.js` is confirmed not in the JS index (9 files reviewed, this is file #10+). The file is 130 lines with two behaviors. Let me produce the outbox.

Here is the outbox content for `sessions/ba-forseti/outbox/20260222-idle-refactor-review-forseti.life-15.md`:

---

- Status: done
- Summary: Reviewed `job-search-results.js` (130 lines) — a file absent from `CODE_REVIEW_INDEX.md` (which lists 9 files reviewed; the module directory has 12 JS files). Found 6 BA-level requirements clarity gaps: the "save job" POST sends `csrf_token` in the request body but the server-side handler's expected token validation method is undefined — the JS index's CSRF security checklist does not cover this file, so its CSRF posture is unaudited; on a `403 Security token expired` response the button restores to original text via the `catch` block and shows `window.alert()`, but there is no token refresh — the user cannot save the job without a page reload, and no UI guidance tells them to reload; the fallback path `if (!jobId || !csrfToken) { window.location.href = saveUrl; }` silently performs a GET request to the save URL when CSRF token is missing — if the save route accepts GET, this is a CSRF bypass; the `already_saved` vs new save distinction (`'✅ Already Saved'` vs `'✅ Saved'`) is implemented in JS but no AC defines whether a button in `already_saved` state should ever have been clickable (it was enabled, allowing a user to attempt a duplicate save); the pagination behavior performs a full page navigation (`window.location.href = url.toString()`) with a visual opacity fade that has no timeout or cleanup — if the navigation is blocked by a browser (e.g., unload confirmation dialog), the results list stays at 50% opacity and `pointerEvents: none` permanently; and the scroll-to-top on page load fires for any URL with `page > 1`, including deep-linked URLs bookmarked by the user, which scrolls them away from their intended anchor position on every load. All downstream seats at queue cap; follow-ups documented for deferred routing.

## File reviewed
- `sites/forseti/web/modules/custom/job_hunter/js/job-search-results.js` (130 lines)
- Not listed in `CODE_REVIEW_INDEX.md` (index covers 9 of 12 JS files; `job-search-results.js`, `utils.js`, and `opportunity-management.js` are absent — the latter two were flagged in item -9).

## Requirements clarity improvements (6 found)

### 1. "Save job" POST sends CSRF token in request body — server-side validation method unaudited; file absent from JS security checklist (HIGH — unaudited security posture)
```js
body: new URLSearchParams({
  job_id: jobId,
  csrf_token: csrfToken     // ← body parameter, not header
}).toString()
```
Most Drupal AJAX handlers validate CSRF via `X-CSRF-Token` header (the Drupal standard). This file sends `csrf_token` in the POST body. Other JS files in this module that were fixed during code review (`target-companies.js`, `job-discovery.js`) were corrected to use `X-CSRF-Token` header. This file was never reviewed and may use the wrong validation path.

The JS index CSRF security checklist reads "All POST/PUT/DELETE have CSRF ✅ COMPLETED" — but this file is not in the index. Whether `csrf_token` in body is validated server-side (in `JobApplicationController` or whichever controller handles save-job) is not documented.
- AC: (a) Identify the server-side route handler for the save-job POST. Confirm whether it validates `$_POST['csrf_token']` or `$_SERVER['HTTP_X_CSRF_TOKEN']`. (b) Align the JS to use whichever the server expects — prefer `X-CSRF-Token` header (Drupal standard). (c) Add `job-search-results.js` to `CODE_REVIEW_INDEX.md` with CSRF status. Verification: submitting save-job with a tampered CSRF token returns `403`.

### 2. On `403 Security token expired` the button resets to original text but there is no token refresh or user guidance — user is silently stuck (MEDIUM — missing error recovery flow)
```js
.then((response) => {
  if (response.status === 403) {
    throw new Error('Security token expired');
  }
  // ...
})
.catch((error) => {
  console.error('Save job failed:', error);
  button.classList.remove('is-saving');
  button.setAttribute('aria-busy', 'false');
  button.textContent = originalText;
  window.alert('Could not save this job right now. Please try again.');
});
```
When the CSRF token expires (403), the `catch` block shows a generic `window.alert()` with "Please try again." But clicking "Save" again will fail again with the same expired token — the button's `data-csrf-token` attribute is unchanged. The user must reload the page to get a fresh token, but no guidance tells them this.

No AC defines: "What should the user experience be when the CSRF token expires during a save-job attempt?"
- AC options (PM must decide):
  - (a) On 403: fetch a fresh CSRF token via `Drupal.ajax.defaultSettings.url` or `/session/token`, update `button.dataset.csrfToken`, and automatically retry the save. (b) On 403: update button text to "⚠️ Session expired — click to reload" and set `button.onclick = () => window.location.reload()`.
- Recommended: Option (b) — simpler, no automatic retry complexity, clear user action. Diff: replace the generic `window.alert()` in the `403` case with a targeted button state change.

### 3. Fallback path silently performs GET to save URL when CSRF token or jobId is missing — potential CSRF bypass if route accepts GET (HIGH — undefined security contract)
```js
if (!jobId || !csrfToken) {
  window.location.href = saveUrl;   // ← full page GET navigation to save URL
  return;
}
```
When `jobId` or `csrfToken` is missing from the button's `data-` attributes (a rendering error, a bot scraping the page, or a user with JS partially loaded), the code falls back to a full-page GET navigation to the `saveUrl`. If the save route's controller does not explicitly reject GET requests and performs the save action on GET, this is a CSRF bypass — no token required for the GET path.

Even if the route rejects GET, the fallback is a full page navigation away from the search results — the user loses their result set and scroll position. No AC defines the intended behavior when token data is missing from the button.
- AC: (a) Define the server-side route as POST-only (reject GET with 405 Method Not Allowed). (b) Change the JS fallback: if `!csrfToken`, show an inline error on the button ("Login required to save") rather than navigating away. Diff: replace `window.location.href = saveUrl` with `button.textContent = '⚠️ Login to save'; button.disabled = true`.

### 4. `already_saved` button was enabled and clickable — no AC defines whether a previously-saved job should have a saveable button (MEDIUM — unclear initial state)
```js
button.textContent = payload.already_saved ? '✅ Already Saved' : '✅ Saved';
button.style.pointerEvents = 'none';
```
The JS disables the button after a successful save (or already-saved response). But the button was enabled when the page loaded — meaning a user can click "Save" on a job they already saved, trigger a server round-trip, and get `already_saved: true` back. The server apparently handles the duplicate gracefully, but:
- The initial button state on page load is undefined — no AC says "if the job is already saved, render the button as disabled/grayed on page load."
- The server is doing extra work (duplicate-save detection) that could be eliminated by correct initial rendering.
- The Twig template or PHP controller that renders `.btn-save-job` has no documented responsibility to check saved-state and render accordingly.

- AC: (a) If a job is already saved by the current user, the server renders the save button as disabled with text "✅ Already Saved" and no `data-csrf-token`. (b) The JS `jobSearchResultsSave` behavior detects `aria-disabled="true"` buttons and skips attaching the click handler. Verification: load job search results while logged in with ≥1 saved job; confirm that job's button renders as disabled without JavaScript interaction.

### 5. Pagination opacity fade has no timeout/cleanup — navigation block leaves results list permanently disabled (LOW — UI state leak)
```js
if (resultsContainer) {
  resultsContainer.style.opacity = '0.5';
  resultsContainer.style.pointerEvents = 'none';
}
window.location.href = url.toString();
```
The opacity fade and `pointerEvents: none` are set immediately before `window.location.href` navigation. In normal flow (navigation succeeds), the page unloads and the state doesn't matter. But if navigation is blocked — by a browser "leave page?" dialog, a `beforeunload` event from another behavior, or the URL being same-origin and navigation being intercepted — the results container stays at 50% opacity and non-interactive with no recovery path. The user cannot interact with search results and there is no spinner timeout.

- AC: Add a safety timeout: if `window.location.href` assignment does not cause an unload within N ms, restore the container opacity and pointer events. Diff:
  ```js
  resultsContainer.style.opacity = '0.5';
  resultsContainer.style.pointerEvents = 'none';
  const safetyTimer = setTimeout(() => {
    resultsContainer.style.opacity = '';
    resultsContainer.style.pointerEvents = '';
  }, 5000);
  window.addEventListener('beforeunload', () => clearTimeout(safetyTimer));
  window.location.href = url.toString();
  ```

### 6. Auto-scroll-to-top fires on all `page > 1` URLs including user-bookmarked deep links — always scrolls user away from their anchor (LOW — incorrect scroll trigger condition)
```js
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('page') && parseInt(urlParams.get('page')) > 1) {
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
```
This fires on every page load where `page > 1` — including when a user directly bookmarks page 3 of results, shares a link to page 5, or navigates back/forward in browser history. The intent is presumably "scroll to top when the user clicks a pagination button" — but the implementation fires on any page-2+ URL, regardless of how the user arrived there.

There is no AC defining: "When exactly should the scroll-to-top fire?" The pagination click handler already sets `window.location.href` — by the time the page loads, it cannot distinguish "arrived via pagination click" from "arrived via bookmark."
- AC: Define the intended trigger: "Scroll to top only when the user navigated here by clicking a pagination button (not on direct link, bookmark, or back/forward navigation)." Implementation: set a `sessionStorage` flag in the pagination click handler (`sessionStorage.setItem('jh_paginated', '1')`); read and clear it on page load (`sessionStorage.removeItem('jh_paginated')`); only scroll if the flag was set. Diff:
  ```js
  // In pagination click handler (before window.location.href):
  sessionStorage.setItem('jh_paginated', '1');

  // In attach (replace current scroll block):
  if (sessionStorage.getItem('jh_paginated') === '1') {
    sessionStorage.removeItem('jh_paginated');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  ```

## Follow-up items (deferred — all downstream seats at queue cap)

> Queue cap: dev-forseti (3), qa-forseti (3), pm-forseti (28+). Route when capacity opens.

### FU-1 → dev-forseti: Audit save-job CSRF token path + fix fallback GET + add to JS index (ROI 35)
- Files: `js/job-search-results.js`, `js/CODE_REVIEW_INDEX.md`, and the PHP controller handling the save-job route (identify via `grep -rn "btn-save-job\|save.*job" src/Controller/`)
- Diff:
  1. Identify server-side handler for save-job POST. If it uses `X-CSRF-Token` header validation: change JS to send `headers: { 'X-CSRF-Token': csrfToken }` and remove `csrf_token` from body.
  2. Replace `window.location.href = saveUrl` fallback with: `button.textContent = '⚠️ Login to save'; button.disabled = true;`
  3. Add 403-specific handling: update button to "⚠️ Session expired — click to reload" with `onclick = () => window.location.reload()` instead of generic alert.
  4. Add `sessionStorage`-gated scroll-to-top (see GAP-6 diff above).
  5. Add `job-search-results.js` row to `CODE_REVIEW_INDEX.md` table.
- Verification:
  - Submit save-job with tampered CSRF token → 403 response → button shows "Session expired — click to reload", not generic alert.
  - Navigate to page 3 via pagination button → page scrolls to top.
  - Navigate directly to `?page=3` via address bar → page does NOT scroll to top.
  - Remove `data-csrf-token` attribute from button in DevTools → click save → button shows "Login to save" (not page navigation).
- `roi.txt`: 35

### FU-2 → pm-forseti: Define initial button state for already-saved jobs (ROI 20)
- Context: `job-search-results.js` disables the "Save" button client-side after save, but the initial server-rendered state for already-saved jobs is undefined — the button is enabled on page load regardless.
- Decision needed: Should the server render save buttons as disabled (with "✅ Already Saved") when the job is already saved by the current user? Recommend: yes — eliminates a server round-trip and provides clearer UX.
- Files: Twig template(s) rendering `.btn-save-job` (identify via `grep -rn "btn-save-job" templates/`), `js/job-search-results.js`
- AC: If `$is_saved` is true, render `<a class="btn-save-job" aria-disabled="true" aria-label="Already saved">✅ Already Saved</a>` with no `data-csrf-token`. JS behavior skips attaching click handler to `aria-disabled="true"` buttons.
- Verification: Load job search results with a saved job → saved job button is disabled on initial render, no JS interaction needed.
- `roi.txt`: 20

## Blockers
- None.

## Next actions
- Executor: route FU-1 (ROI 35) to dev-forseti when capacity opens — CSRF audit + fallback fix are security-relevant.
- Executor: route FU-2 (ROI 20) to pm-forseti when capacity opens — needs product decision on initial render state.
- Executor: flag to js-index maintainer (or dev-forseti) that `job-search-results.js` should be added to `CODE_REVIEW_INDEX.md` — the "All POST/PUT/DELETE have CSRF ✅ COMPLETED" checklist is incomplete while this file is absent.
- BA: inbox clear. Ready for next assignment.

## ROI estimate
- ROI: 30
- Rationale: The CSRF token path (body vs header) is an unaudited security posture on an authenticated POST endpoint; verifying and aligning it prevents a class of session-fixation issues. The GET fallback (GAP-3) is a concrete CSRF bypass risk if the route accepts GET. Both are targeted single-file fixes.
