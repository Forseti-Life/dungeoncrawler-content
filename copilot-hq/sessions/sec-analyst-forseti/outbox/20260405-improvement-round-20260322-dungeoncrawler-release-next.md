- Status: done
- Summary: Post-release security process gap review for `20260322-dungeoncrawler-release-next` (forseti.life-seat, cross-site synthesis). This item was dispatched to sec-analyst-forseti but the authoritative dungeoncrawler gap review is owned by sec-analyst-dungeoncrawler (completed: `sessions/sec-analyst-dungeoncrawler/outbox/20260405-improvement-round-20260322-dungeoncrawler-release-next.md`). Per seat scope constraints, this outbox is a forseti.life-side synthesis only. Three dungeoncrawler process gaps identified by the dedicated analyst (GAP-SEC-01: CSRF recurring 4th cycle; GAP-SEC-02: production error reporting not verified after CRITICAL schema exceptions; GAP-SEC-03: no security sign-off gate in shipping pipeline) have direct forseti.life parallels. GAP-FORSETI-01 (CSRF recurrence) is already queued at `sessions/dev-forseti/inbox/20260405-csrf-finding-4-job-hunter` (ROI 12). GAP-FORSETI-02 (error reporting) and GAP-FORSETI-03 (no security gate) are recommendations to pm-forseti requiring no new inbox items at this time. Seat instructions refreshed: stale `/home/keithaumiller/` HQ repo path corrected to `/home/ubuntu/`.

- website: forseti.life
- module: release-process / job_hunter
- role: sec-analyst-forseti
- feature/work item: 20260402-improvement-round-20260322-dungeoncrawler-release-next

## Scope note

This inbox item is a dungeoncrawler post-release review. sec-analyst-forseti scope is forseti.life only. The dungeoncrawler-specific security gap review is owned by sec-analyst-dungeoncrawler (see their outbox linked above). This update covers only the forseti.life implications of the same three process gaps. No dungeoncrawler inbox items were created (consistent with seat constraints: cross-site items without explicit delegation are recommendation-only).

## GAP-FORSETI-01 — CSRF recurring pattern (HIGH, already queued)

**Dungeoncrawler parallel:** GAP-SEC-01 — 4 consecutive cycles with unprotected POST routes merging before security review.

**Forseti.life evidence:** `sessions/dev-forseti/inbox/20260405-csrf-finding-4-job-hunter` — CSRF FINDING-4a/4b/4c/4d affecting `job_hunter.routing.yml` step3/4/5 and addposting routes; step5 (submit-application) is highest-risk (could force application submission for a logged-in user).

**Process gap:** Same root cause as dungeoncrawler — no pre-impl CSRF self-check in dev-forseti workflow. Findings arrive from ARGUS (sec-analyst-infra) post-ship.

**Follow-through action (already exists, no new inbox item needed):**
- Owner: dev-forseti (patch execution — already queued, ROI 12)
- Acceptance criteria: `sessions/dev-forseti/artifacts/csrf-finding-4-applied.txt` exists with commit hash + PASS verification; 0 unprotected POST routes in `job_hunter.routing.yml`
- Verification: `grep -n '_access: TRUE\|methods:.*POST' sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml` then confirm every POST route has `_csrf_token: 'TRUE'` or equivalent
- **Structural fix (recommendation to pm-forseti):** Add the same pre-impl CSRF checklist to the forseti.life feature.md template: before any impl task closes, developer must confirm `_csrf_token` or `_csrf_request_header_mode` is set on all new/modified POST routes.

**ROI: 12** (same as dungeoncrawler GAP-SEC-01; step5 submit-application is the highest-risk user action in job_hunter)

---

## GAP-FORSETI-02 — Production error reporting not verified (MEDIUM)

**Dungeoncrawler parallel:** GAP-SEC-02 — CRITICAL schema exceptions exposed stack traces, DB table names, and internal paths via verbose PHP error output in production.

**Forseti.life status:** No CRITICAL schema exceptions were reported for forseti.life in this cycle. However, the underlying risk (Drupal `error_level` not confirmed at 0 in production, PHP `display_errors` not confirmed Off) exists independently of whether a schema exception occurs. Any PHP exception from any future bug would expose internal paths if error reporting is permissive.

**Follow-through action (recommendation — no new inbox item created per cross-site constraints):**
- Owner: dev-forseti (if pm-forseti approves)
- Acceptance criteria: `drush --uri=https://forseti.life config:get system.logging` returns `error_level: 0`; `php -i | grep display_errors` returns `display_errors => Off`
- Verification: output included in next dev-forseti daily-review or next impl outbox
- Suggested approach: pm-forseti may opt to include this as a one-line pre-deploy check in the release signoff gate rather than a standalone inbox item

**ROI: 6** (lower than dungeoncrawler equivalent because no CRITICAL exceptions this cycle; risk is latent not active)

---

## GAP-FORSETI-03 — No security sign-off gate in forseti.life shipping pipeline (MEDIUM)

**Dungeoncrawler parallel:** GAP-SEC-03 — Features with new API surfaces ship through Gate 2 without any security checklist confirmation; security findings arrive from ARGUS 3–5 days post-ship.

**Forseti.life status:** Same gap applies. The current forseti.life release (`20260322-forseti-release-next`) may include features with new routes or permissions that will proceed to QA without a security checklist step.

**Follow-through action (recommendation to pm-forseti — no inbox item created):**
- Owner: pm-forseti (policy); dev-forseti (execution)
- Acceptance criteria: feature.md template includes a `## Security checklist` section (4 items: CSRF on POST routes, `_access: TRUE` justification, permission scoping, server-side input validation) required for features with new routes or permission surfaces before impl task is marked `done`
- Verification: rate of CSRF/authz ARGUS findings drops to 0 in next release cycle improvement round

**ROI: 7**

---

## Next actions

- dev-forseti: process `20260405-csrf-finding-4-job-hunter` (ROI 12) — CSRF patches for job_hunter step3/4/5 and addposting; already in inbox
- pm-forseti: consider adding pre-impl CSRF checklist to forseti.life feature.md template (GAP-FORSETI-01 structural fix) — prevents recurring class
- pm-forseti: optionally queue a one-line production error-reporting verification for dev-forseti (GAP-FORSETI-02); low urgency, no active incident
- pm-forseti: consider adding `## Security checklist` block to feature.md template for features with new routes/permissions (GAP-FORSETI-03)

## Blockers
- None

## Needs from Supervisor
- N/A — all items are recommendations to pm-forseti, no decision required from sec-analyst-forseti perspective

## ROI estimate
- ROI: 8
- Rationale: GAP-FORSETI-01 patch is already queued at ROI 12 and just needs execution. Structural fixes (CSRF pre-impl checklist + security gate) prevent the same recurring CSRF class that has now appeared 4 consecutive cycles across both sites. Combined ROI on prevention justifies the synthesis outbox and the recommendations to pm-forseti.

---
- Agent: sec-analyst-forseti
- Source inbox: sessions/sec-analyst-forseti/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05T17:34:53Z
