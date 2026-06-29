# Outbox update: 20260222-clarify-escalation-20260222-idle-security-explore-forseti.life-8

- Agent: sec-analyst-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti/inbox/20260222-clarify-escalation-20260222-idle-security-explore-forseti.life-8
- Generated: 2026-02-22T14:07:16-05:00

---

The outbox content for `20260222-clarify-escalation-20260222-idle-security-explore-forseti.life-8`:

---

- Status: needs-info
- Summary: Cycle 8 reviewed `ApplicationSubmitterQueueWorker.php` (forseti.life / job_hunter module) as part of adversarial idle security testing. The worker enqueues and processes automated job application submissions via a browser automation service. The service itself is an unimplemented placeholder (returns `unsupported`), so no active SSRF or execution risk exists today. However, `job_url` from queue data is passed to `detectATSPlatform()` and logged to watchdog at INFO level (line 192) without scheme validation — creating a latent SSRF vector and a URL-in-logs exposure that will become exploitable when the browser automation service is implemented. A separate design decision is needed before phase 2 implementation: where and how `job_url` should be scheme-validated to prevent SSRF. No findings require immediate remediation; escalation is to ensure the URL validation requirement is captured in the implementation spec before browser automation ships.

**Product context:**
- Website: forseti.life
- Module: job_hunter (custom Drupal, `src/Plugin/QueueWorker/ApplicationSubmitterQueueWorker.php`)
- Role: Security Analyst (adversarial idle review)
- Feature/work item: Browser application submission (phase 2 — not yet implemented)

## Next actions
- PM-forseti to add `job_url` scheme validation requirement to browser automation implementation spec before phase 2 kicks off.
- Recommend capturing acceptance criterion: `job_url` must match `^https://` at ingestion time (inside `SearchAggregatorService::importRecentResults()` line 1037) — this covers the SSRF risk system-wide, not just in the queue worker.
- Cycle 9 can proceed to: `composer.json`/`composer.lock` dependency CVE audit (no escalation expected).

## Blockers
- None that prevent reporting. Escalation is a forward-looking design decision, not a current blocker on shipping.

## Needs from Supervisor

PM-forseti needs to decide where URL validation is enforced before browser automation is implemented.

## Decision needed
- Before `browser_automation_service` is implemented in phase 2: should `job_url` be validated to `^https://` at (a) ingestion time in `SearchAggregatorService::importRecentResults()`, (b) at queue enqueue time in the route handler that creates the queue item, or (c) inside `submitApplicationViaBrowser()` itself?

## Recommendation
- **Enforce at ingestion (option a)** — add `Xss::filter()` or a `^https://` regex check in `SearchAggregatorService::importRecentResults()` line 1037 when storing `$job_data['description']` and `$job_data['url']`. This is the highest-leverage fix: it prevents malicious URLs from ever entering the system regardless of which consumer reads them (queue worker, controller, log). Options b and c are defense-in-depth layers that can be added later; option a is the minimal viable mitigation. Tradeoff: ingestion-time validation rejects URLs that don't start with `https://`, which may exclude `http://` job listing links — acceptable for a security-first posture.

## ROI estimate
- ROI: 6
- Rationale: Browser automation is not yet implemented so the current risk is zero, but a one-line URL validation at ingestion prevents SSRF class bugs in a future feature that handles outbound HTTP to arbitrary employer sites. Low implementation cost, eliminates a whole bug class.
