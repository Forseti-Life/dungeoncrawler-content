# Outbox update: 20260222-idle-security-explore-forseti.life

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-security-explore-forseti.life
- Generated: 2026-02-22T10:03:12-05:00

---

Now I have enough to write the security analysis. Let me compose the outbox update.

The outbox directory is on an overlayfs read-only lower layer (Device: 0,51 indicates overlay mount). Per org instructions, the executor handles outbox persistence. I'll produce the full outbox content here for executor routing.

---

## OUTBOX CONTENT — `sec-analyst-forseti-agent-tracker`
### File: `outbox/20260222-idle-security-explore-forseti.life.md`

---

- Status: done
- Summary: Completed adversarial-user code review of the `copilot_agent_tracker` Drupal module (forseti.life) by reading all PHP controllers, forms, services, schema, routing, and permissions from the GitHub repo. Four prioritized findings emerged: (1) no payload size limits on `summary`, `details`, and `metadata` fields in the public-facing POST API, creating a storage-DoS vector for any token holder; (2) the telemetry token is rendered in cleartext HTML on the admin dashboard page, increasing exposure under shared-screen/screenshot/admin-compromise scenarios; (3) state-changing approval and dismiss actions use GET requests with CSRF tokens in URLs, a weaker pattern than form-POST CSRF that can leak via Referer headers; (4) the `metadata` JSON blob drives WaitingOnKeith business logic with no schema or size constraints, enabling a valid token holder to degrade the UI with crafted payloads. No exploitable SQL injection, XSS, or authentication bypass was found — Drupal's render system, prepared statements, and `hash_equals` token check are all used correctly.

## Findings (prioritized)

### [HIGH] No payload size limits — storage DoS risk
- **Surface:** `ApiController::event()` → `AgentTrackerStorage::recordEvent()`
- **Fields:** `summary` (text), `details` (text/big), `metadata` (text/big)
- **What I tried:** Read the full payload ingestion path. `details` and `metadata` columns are MySQL `text / big` (up to 4 GB). No `Content-Length` cap, no PHP `strlen()` check before insert.
- **Reproduction:** POST to `/api/copilot-agent-tracker/event` with valid `X-Copilot-Agent-Tracker-Token` header. Set `summary` to a 10 MB string. Request completes; row is inserted at full size.
- **Expected:** 413 / 400 with a max-size rejection.
- **Actual:** Accepted and stored (based on code; no guard present).
- **Impact:** Disk exhaustion DoS. Elevated if token leaks.
- **Likelihood:** Low normally; Medium if token compromised.
- **Mitigation:** Add server-side length caps before DB write: `summary` ≤ 4 KB, `details` ≤ 64 KB, `metadata` ≤ 64 KB. Add overall request body size cap (e.g., reject if `Content-Length` > 128 KB).
- **Verification:** POST oversized payloads with a valid token — confirm 400 response and no DB insert.

### [HIGH] Telemetry token displayed in cleartext on admin dashboard HTML
- **Surface:** `DashboardController::dashboard()` — the line `'<p><strong>Telemetry token</strong> ... <code>' . $token . '</code></p>'`
- **What I tried:** Read the dashboard render array. Token is unconditionally rendered into page HTML for anyone with `administer copilot agent tracker`.
- **Expected:** Token hidden by default; reveal only on deliberate user action.
- **Actual:** Token visible in initial page HTML, browser history, devtools, screenshots.
- **Impact:** If admin account is compromised (phishing, session fixation, shared screen), token is immediately readable. Token compromise enables arbitrary telemetry injection into all agent records.
- **Likelihood:** Medium — this is an internal admin page, but wide display attack surface.
- **Mitigation:** Replace with a masked display + "Reveal" JS toggle button, or move to a dedicated sub-page requiring re-auth.
- **Verification:** Load `/admin/reports/copilot-agent-tracker` — confirm token is not in initial HTML source (view-source).

### [MEDIUM] State-changing actions (Approve, Dismiss) use GET + CSRF token in URL
- **Surface:** `copilot_agent_tracker.waiting_on_keith_approve` (`/admin/reports/waitingonkeith/{item_id}/approve?token=...`) and `copilot_agent_tracker.dismiss_sent_message` (`/admin/reports/waitingonkeith/sent/{reply_id}/dismiss?token=...`)
- **What I tried:** Read routing.yml and controller. Both actions are GET routes that trigger DB writes (insert reply, mark resolved, update dismissed). CSRF token is in the query string.
- **Expected:** POST-form submission with CSRF token in body (standard Drupal pattern).
- **Actual:** GET request; token in URL. URL can leak via Referer header to any externally-loaded resource (analytics, CDN, fonts) on the same page load, or via browser/proxy logs.
- **Impact:** An attacker who captures the URL (via Referer leak or log access) can replay the approve/dismiss action within the token's validity window. Also: any automation/bot that crawls admin pages may accidentally trigger approvals.
- **Likelihood:** Low-Medium. Modern browsers suppress Referer for cross-origin in many cases, but `unsafe-url` referrer policies would still leak.
- **Mitigation:** Convert to POST-form actions. Use a `<form method="post">` with a hidden CSRF token field instead of a link with `?token=`.
- **Verification:** Observe Network tab on approve/dismiss — confirm POST, no token in URL.

### [MEDIUM] `metadata` JSON drives business logic with no schema or size constraint
- **Surface:** `ApiController::event()` → `copilot_agent_tracker_agents.metadata` → `DashboardController::waitingOnKeith()` (`$meta['inbox_messages']`, `inbox_count`, `inbox_items`)
- **What I tried:** Traced metadata from API input through to the WaitingOnKeith page render. The page iterates all `inbox_messages` on every request.
- **Reproduction:** POST metadata with 5,000 `inbox_messages` entries; load WaitingOnKeith page — page will iterate all 5,000 per load.
- **Expected:** Schema validation or array size cap at write time.
- **Actual:** Arbitrary structure stored and fully iterated on read.
- **Impact:** PHP memory exhaustion / slow page loads for the CEO inbox view. Denial-of-service against the operator dashboard.
- **Likelihood:** Low normally; elevated if a misconfigured agent floods events or token leaks.
- **Mitigation:** Cap metadata blob at 64 KB (covered by HIGH fix). Also cap `inbox_messages` reads at 200 entries in `waitingOnKeith()`.
- **Verification:** POST metadata with 1,000 `inbox_messages` — confirm page load time is unaffected and/or cap is enforced.

### [LOW] No message length cap on ComposeAgentMessageForm
- **Surface:** `ComposeAgentMessageForm::buildForm()` — `message` textarea has no `#maxlength`; `copilot_agent_tracker_replies.message` is `text/big`.
- **Impact:** Admin can submit very large messages; low practical risk but consistent with above DoS pattern.
- **Mitigation:** Add `'#maxlength' => 32768` on the textarea and server-side `strlen()` check in `submitForm()`.

## What I verified is NOT a risk
- SQL injection: All DB queries use Drupal's query builder with prepared statements. `->condition()` calls are safe.
- XSS: Dashboard uses Drupal's render system (`#rows`, `#items`, `$this->t('@var', ...)` with `@`-prefix auto-escaping). Explicit `htmlspecialchars()` used for pre-formatted message content. No raw `Markup::create()` from user-controlled data.
- Authentication bypass: All admin routes correctly require `administer copilot agent tracker`. The API endpoint is `_access: TRUE` but correctly enforces token via `hash_equals()` with non-empty token check.
- Token brute force: Token is 32 random bytes (256-bit entropy via `Crypt::randomBytesBase64`). Brute force is computationally infeasible.

## Next actions
- Send passthrough recommendation to `dev-forseti-agent-tracker` and `pm-forseti-agent-tracker`: add payload size caps to `ApiController`, mask token in `dashboard()`, convert approve/dismiss to POST.
- Track findings in knowledgebase for reuse in future security cycles.

## Blockers
- None. Review completed from source code via GitHub API. Live site not tested (no credentials needed for code-level review; no credentials available regardless).

## Needs from CEO
- None required to complete this cycle.

## ROI estimate
- ROI: 35
- Rationale: The token cleartext exposure and unlimited payload size are both small code changes (under 30 lines combined) that close credible risks on the agent management surface. The GET→POST conversion for approve/dismiss is low-effort / high-hygiene and eliminates a pattern that will repeat if not corrected. Together these prevent a bad day if the admin account or telemetry token is ever compromised.
