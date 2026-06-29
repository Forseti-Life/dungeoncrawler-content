- Status: done
- Summary: Improvement round processed for forseti-release-next. Three process gaps identified from the CSRF patch release cycle: (1) AC spec did not distinguish GET-only vs GET+POST routes for `_csrf_token` applicability — `job_hunter.addposting` was incorrectly included in the 28-route count, causing a 403 regression for authenticated users on a plain GET hyperlink; (2) no pre-implementation HTTP-method audit checklist item existed before applying CSRF route requirements; (3) the pre-Gate-2 checklist was only added retroactively after the pattern recurred across multiple cycles. CSRF regression was already resolved (27 routes, addposting is clean — no `_csrf_token` on GET+POST routes). Seat instructions updated with CSRF+GET lesson. KB lesson queued.

## Next actions
- Add KB lesson: Drupal `_csrf_token: 'TRUE'` on GET+POST routes causes 403 on plain GET — only apply to POST-only routes.
- Update AC template for CSRF tasks to require HTTP method column per route row.
- Verify `job_hunter.addposting` returns HTTP 200 for authenticated GET in next QA cycle.

## Blockers
- None.

## Process gaps identified

### GAP-A: AC spec did not distinguish route HTTP method for CSRF applicability
- **What happened**: The CSRF patch AC listed 28 routes with `_csrf_token: 'TRUE'`. `job_hunter.addposting` is a `[GET, POST]` route used as a plain hyperlink. Adding `_csrf_token` caused Drupal to require a `token` query param on GET, returning 403 for authenticated users with no token.
- **Owner**: `pm-forseti` (AC template update), `dev-forseti` (implementation check)
- **SMART fix**: Update the CSRF task AC template to include a column "HTTP methods" per route row, with a required note that `_csrf_token: 'TRUE'` must NOT be added to routes with GET in their methods list unless the GET is always token-parameterized.
- **Acceptance criteria**: Next CSRF-related task AC includes HTTP method for each route; dev checklist includes a step to verify no `[GET, ...]` routes receive `_csrf_token`.
- **ROI**: 8 — prevents regression class that costs a full QA cycle + fix commit.

### GAP-B: No pre-implementation HTTP-method audit step
- **What happened**: Implementation proceeded route-by-route without a step to audit the HTTP methods of all target routes before applying changes. A 30-second grep (`grep -A3 "job_hunter" routing.yml | grep methods`) would have caught the GET+POST issue before commit.
- **Owner**: `dev-forseti`
- **SMART fix**: Add checklist item to `dev-forseti.instructions.md` pre-implementation section: "Before adding `_csrf_token` to any route, run `grep -A5 '<route-name>' routing.yml | grep methods` and verify methods is `[POST]` only."
- **Acceptance criteria**: Seat instructions contain the HTTP-method audit step; checklist is exercised in the next CSRF-related task.
- **ROI**: 6 — 5-second check that prevents a regression category.

### GAP-C: Pre-Gate-2 permission/route checklist added retroactively
- **What happened**: The pre-Gate-2 checklist was not present for multiple release cycles before being added. The checklist is now in seat instructions, but was only added after patterns recurred. Earlier addition would have caught the permission mismatch and CSRF issues sooner.
- **Owner**: `pm-forseti` (process cadence), `dev-forseti` (instructions currency)
- **SMART fix**: At the start of each release cycle, `dev-forseti` validates seat instructions as the first action (already org-wide policy — ensure it is being followed, not skipped under time pressure).
- **Acceptance criteria**: Seat instructions refresh is the first git commit of every new release cycle; commit message includes "instructions refresh".
- **ROI**: 4 — process hygiene; prevents slow drift.

## ROI estimate
- ROI: 7
- Rationale: CSRF regression class is high-frequency and high-impact; fixing the AC template and pre-implementation checklist eliminates a full class of GET+POST regressions at near-zero implementation cost.
