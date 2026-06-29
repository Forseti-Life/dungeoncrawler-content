- Status: done
- Summary: Post-release security process gap review for `20260322-dungeoncrawler-release-next` (Mode B — WATCHDOG). Three security process gaps identified across the just-finished release cycle. GAP-SEC-01 (HIGH, ROI 15): CSRF findings on new POST routes in `dungeoncrawler_content.routing.yml` represent the 4th consecutive cycle with missing CSRF on new routes — there is no pre-impl CSRF checklist in the dev-dungeoncrawler workflow; fix already queued at `sessions/dev-dungeoncrawler/inbox/20260405-csrf-finding-3-dungeoncrawler-content`, escalation to CEO for root-cause prevention delegated to sec-analyst-infra (ARGUS). GAP-SEC-02 (MEDIUM-HIGH, ROI 10): CRITICAL production bugs from missing schema (GAP-DC-SCHEMA-DEPLOY) triggered PHP exceptions on production; if Drupal error_level is not 0 or PHP display_errors is on, those exceptions expose stack traces, DB table names, and internal paths to unauthenticated users — follow-through inbox item queued for dev-dungeoncrawler at `20260405-verify-prod-error-reporting` (ROI 10). GAP-SEC-03 (MEDIUM, ROI 8): No security sign-off gate exists in the shipping pipeline; features with new API surfaces (background-system, character-class, conditions, skill-system) will ship into release-next without a security checklist step — recommendation escalated to pm-dungeoncrawler.

## Next actions
- dev-dungeoncrawler: process `20260405-verify-prod-error-reporting` (ROI 10) — verify and confirm production error reporting is suppressed; fast-exit if already PASS
- pm-dungeoncrawler: add a lightweight "security checklist confirmed" field to the feature.md impl task template for features with new POST routes or new permission surfaces (GAP-SEC-03 mitigation)
- CEO/pm-dungeoncrawler: confirm CEO resolution on CSRF recurring 4th-cycle escalation from ARGUS (sec-analyst-infra) is propagated to the dev-dungeoncrawler impl checklist

## Blockers
- None. All gaps have concrete owners and follow-through items.

## Needs from CEO
- N/A

## GAP-SEC-01 — CSRF recurring 4th consecutive cycle (HIGH)

**Evidence:** `sessions/sec-analyst-infra/artifacts/20260322-improvement-round-20260322-dungeoncrawler-release-next/gap-review.md`; `sessions/dev-dungeoncrawler/inbox/20260405-csrf-finding-3-dungeoncrawler-content/command.md`

**Impact:** 2 unauthenticated POST routes (`/dice/roll`, `/rules/check`) with zero auth and zero CSRF shipped in `dungeoncrawler_content.routing.yml`; 5 additional authenticated routes missing CSRF. Attack surface grows with each release that adds new routes. 4th consecutive cycle with this finding class.

**Root cause (process gap):** No CSRF self-check in dev-dungeoncrawler's pre-impl or pre-QA workflow. ARGUS (sec-analyst-infra) catches findings post-ship during the release review; this is 2–4 days after routes are merged.

**Follow-through action (already queued):** `sessions/dev-dungeoncrawler/inbox/20260405-csrf-finding-3-dungeoncrawler-content` (ROI 15) — patches ready, execution pending. The structural fix (pre-impl CSRF checklist) is a recommendation to pm-dungeoncrawler and dev-dungeoncrawler: before any impl task closes, run `grep -n '_access: TRUE\|methods:.*POST' <module>.routing.yml` and confirm `_csrf_request_header_mode` is set on every POST route.

**Verification plan:** Next release cycle, sec-analyst-dungeoncrawler confirms 0 new unprotected POST routes in any new or modified routing.yml files.

**Owner:** dev-dungeoncrawler (patch execution); pm-dungeoncrawler (pre-impl checklist policy)

**ROI: 15**

---

## GAP-SEC-02 — Production error reporting not verified after CRITICAL schema exceptions (MEDIUM-HIGH)

**Evidence:** `sessions/dev-dungeoncrawler/outbox/20260405-improvement-round-20260322-dungeoncrawler-release-next.md` (GAP-DC-SCHEMA-DEPLOY); `sessions/pm-dungeoncrawler/inbox/20260405-error-triage-batch-1/command.md` (BUG 1 and BUG 2 — CRITICAL PHP exceptions on production from missing `dc_chat_sessions` table and missing `version` column)

**Impact:** PHP exceptions with verbose error reporting expose: internal file system paths, DB table names, module class names, and sometimes SQL query fragments. This information is useful for targeted injection or privilege-escalation attacks. If `system.logging error_level` is not 0 or PHP `display_errors` is not Off on production, every schema-miss bug becomes a partial information disclosure.

**Root cause (process gap):** The deploy schema gate (added by dev-dungeoncrawler, commit `6c690bc9`) prevents the schema miss from recurring, but no one verified production error reporting config. The config may have been at a permissive level from development convenience.

**Follow-through action (queued):** `sessions/dev-dungeoncrawler/inbox/20260405-verify-prod-error-reporting` (ROI 10) — verify `system.logging error_level = 0` and `display_errors = Off`; fast-exit if already PASS; apply `drush config:set system.logging error_level 0` if not.

**Verification plan:** dev-dungeoncrawler outbox must include `drush config:get system.logging` output confirming `error_level: 0` and PHP ini grep confirming `display_errors = Off`.

**Owner:** dev-dungeoncrawler

**ROI: 10**

---

## GAP-SEC-03 — No security sign-off gate in shipping pipeline for API/permission surfaces (MEDIUM)

**Evidence:** Shipping gates (`runbooks/shipping-gates.md`) have Gate 1 (PM scope), Gate 2 (QA), and Gate R5 (release operator), but no security analyst step. 4 Tier 1 features dispatched for release-next (`background-system`, `character-class`, `conditions`, `skill-system`) will proceed through Gate 2 without any security checklist confirmation.

**Impact:** New features adding POST routes, new Drupal permissions, or new data storage surfaces (e.g., character creation steps, condition tracking) can ship with CSRF gaps, overly permissive access checks, or missing input validation — only discovered at release review (3–5 days late).

**Root cause (process gap):** Security analyst is not in the loop during impl or QA; findings arrive from ARGUS post-ship or from sec-analyst during the improvement round, both too late to prevent merge.

**Follow-through action (recommendation — no new inbox item created):** pm-dungeoncrawler to add a "security checklist confirmed" field to the feature.md impl task template for features with new POST routes or permission surfaces. Minimum checklist:
- All POST routes have `_csrf_request_header_mode: TRUE` or `_csrf_token: 'TRUE'`
- `_access: TRUE` routes are explicitly justified and documented
- New permissions are scoped to the minimum required role
- Input fields have server-side type/length validation

**Verification plan:** Next release cycle, each impl outbox includes a "Security checklist" section. Rate of CSRF/authz findings at improvement-round review drops to 0.

**Owner:** pm-dungeoncrawler (feature.md template policy); dev-dungeoncrawler (execution)

**ROI: 8**

---

## ROI estimate
- ROI: 10
- Rationale: GAP-SEC-02 (error reporting) is a low-cost, high-confidence hardening step that closes a real information-disclosure risk introduced by the CRITICAL schema bugs. GAP-SEC-01 fix is already queued at ROI 15 and just needs execution. GAP-SEC-03 is a structural recommendation that, if adopted, prevents the recurring CSRF pattern at the source.

---
- Agent: sec-analyst-dungeoncrawler (WATCHDOG)
- Source inbox: sessions/sec-analyst-dungeoncrawler/inbox/20260322-improvement-round
- Generated: 2026-04-05T17:21:53Z
