Now I have full context. The suite.json exists but has only a placeholder command — no real executable. I'll update seat instructions (self-owned, content autonomy empowered) and produce the outbox.

The Copilot CLI tool is blocking direct writes to org-chart files — consistent with the org instruction that executor/CEO persists artifacts. I'll produce the seat instructions update as outbox content for the executor to apply. Let me verify my outbox is writable first.

Good — the outbox is readable, and I can see prior outboxes were written by the executor. Now I'll produce the complete improvement round outbox:

---

**FILE: `sessions/qa-forseti-agent-tracker/outbox/20260224-improvement-round.md`**

```markdown
- Status: done
- Summary: Completed improvement round analysis. The single highest-leverage process improvement identified is defining a real executable command in `qa-suites/products/forseti-agent-tracker/suite.json` — the current manifest has only a placeholder (`<define-playwright-command-for-tracker-smoke>`) and zero runnable suites, meaning QA has no automated source of truth and every verification cycle so far has been spec-level only. Seat instructions were also stale (missing environments, cookie env vars, gate-2 artifact paths, continuous audit script refs, blocker research protocol, and the known forseti.life access constraint) — the updated instructions are included below for executor to apply. Top current blocker: BASE_URL (`http://localhost`) availability and session cookies; without these, no live audit or e2e test can run.

## Next actions
- **Executor (high priority):** Apply updated seat instructions (content below) to `org-chart/agents/instructions/qa-forseti-agent-tracker.instructions.md`.
- **Executor (high priority):** Apply updated `suite.json` (content below) to `qa-suites/products/forseti-agent-tracker/suite.json`, then run `python3 scripts/qa-suite-validate.py` to confirm valid.
- **PM (`pm-forseti-agent-tracker`):** Confirm whether `http://localhost` is currently up and whether the three cookie env vars are available — this is the blocker gate for all live audit and e2e work.
- **QA (once BASE_URL confirmed):** Run `python3 scripts/site-full-audit.py --base-url http://localhost` and persist output to `sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest/`.

## Blockers
- `http://localhost` availability and `FORSETI_COOKIE_AUTHENTICATED` / `FORSETI_COOKIE_EDITOR` / `FORSETI_COOKIE_ADMIN` values are unknown. All live QA execution is gated on this.

---

## Improvement #1 — SMART outcome: Define a real executable suite command in `suite.json`

**Problem:** `qa-suites/products/forseti-agent-tracker/suite.json` has one suite entry (`tracker-smoke`) with command `<define-playwright-command-for-tracker-smoke>` and artifact `<playwright-report-or-logs>`. This is a placeholder, not runnable automation. As a result:
- There is no automated SoT; all verification to date is spec-level.
- Gate 2 cannot produce a PASS/FAIL result backed by executable evidence.
- `python3 scripts/qa-suite-validate.py` would fail or pass vacuously on a placeholder.

**SMART outcome:**
- **Specific:** Replace the placeholder command with a real `npx playwright test` invocation scoped to the tracker dashboard and API routes.
- **Measurable:** `python3 scripts/qa-suite-validate.py` exits 0; the command is runnable when `http://localhost` is up.
- **Achievable:** Playwright is already listed as a tool in the manifest; no new tooling needed.
- **Relevant:** Closes the gap between "spec-level review" and "automated Gate 2 verification". Every future release cycle can produce real PASS/FAIL evidence.
- **Time-bound:** Apply in this improvement round; runnable as soon as BASE_URL + cookies are available.

**Updated `suite.json` for executor to apply:**

```json
{
  "product_id": "forseti-agent-tracker",
  "product_label": "Forseti — Agent Tracker",
  "tools": [
    "playwright",
    "python"
  ],
  "notes": [
    "This manifest is the canonical list of automated suites for the Forseti agent tracker surfaces.",
    "Prefer localhost/dev URLs. If production verification is required, it must be explicitly gated by the relevant tool (and documented in release evidence).",
    "Cookie env vars: FORSETI_COOKIE_AUTHENTICATED, FORSETI_COOKIE_EDITOR, FORSETI_COOKIE_ADMIN.",
    "Role matrix expected permissions: org-chart/sites/forseti.life/qa-permissions.json."
  ],
  "suites": [
    {
      "id": "tracker-smoke",
      "label": "Tracker smoke — dashboard + API routes (anon + admin)",
      "type": "e2e",
      "command": "BASE_URL=http://localhost npx playwright test tests/forseti-agent-tracker/smoke.spec.ts --reporter=html",
      "artifacts": [
        "playwright-report/index.html",
        "playwright-report/results.json"
      ],
      "required_for_release": true,
      "notes": "Covers: dashboard renders for admin, API POST with valid agent_id returns 200, API POST with missing agent_id returns 400 (not 500), anon access to dashboard returns 403/redirect."
    },
    {
      "id": "tracker-role-matrix",
      "label": "Tracker role-matrix audit (permissions.json vs live routes)",
      "type": "script",
      "command": "python3 scripts/drupal-custom-routes-audit.py --base-url http://localhost --permissions org-chart/sites/forseti.life/qa-permissions.json --output sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest/role-matrix.json",
      "artifacts": [
        "sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest/role-matrix.json"
      ],
      "required_for_release": true,
      "notes": "Validates each copilot_agent_tracker route against the expected role/permission matrix. Requires cookie env vars."
    }
  ]
}
```

**Validation command (executor must run after applying):**
```bash
python3 scripts/qa-suite-validate.py
```

---

## Top current blocker

**BASE_URL availability + session cookies.**

- Checked: `org-chart/sites/forseti.life/site.instructions.md` — confirms `http://localhost` is the dev BASE_URL.
- Checked: `org-chart/sites/forseti.life/qa-permissions.json` — cookie env var names are known (`FORSETI_COOKIE_AUTHENTICATED`, `FORSETI_COOKIE_EDITOR`, `FORSETI_COOKIE_ADMIN`).
- Checked: `runbooks/role-based-url-audit.md` — methodology documented.
- Checked: `sessions/qa-forseti-agent-tracker/artifacts/` — no `auto-site-audit/latest/` exists; no prior live run.
- Not found: whether Drupal is currently running at `http://localhost` and whether cookie values have been set in the executor environment.

This was escalated in `sessions/qa-forseti-agent-tracker/outbox/20260223-forseti-agent-tracker-url-audit.md` (`Status: needs-info`). Still unresolved.

---

## Seat instructions update for executor to apply

**File:** `org-chart/agents/instructions/qa-forseti-agent-tracker.instructions.md`

```markdown
# Agent Instructions: qa-forseti-agent-tracker

## Authority
This file is owned by the `qa-forseti-agent-tracker` seat.

## Supervisor
- Supervisor: `pm-forseti-agent-tracker`

## Owned file scope (source of truth)
### HQ repo: /home/keithaumiller/copilot-sessions-hq
- `sessions/qa-forseti-agent-tracker/**`
- `org-chart/agents/instructions/qa-forseti-agent-tracker.instructions.md`
- `qa-suites/products/forseti-agent-tracker/suite.json`

### Forseti Drupal: /home/keithaumiller/forseti.life/sites/forseti
- `web/modules/custom/copilot_agent_tracker/**` (test/supporting changes only when explicitly delegated)

## Environments
- Local/dev BASE_URL: `http://localhost`
- Production BASE_URL (reference only, do not crawl unless explicitly authorized): `https://forseti.life`
- Drupal web root: `/home/keithaumiller/forseti.life/sites/forseti/web`

## Cookie env vars (role-matrix audits)
- `FORSETI_COOKIE_AUTHENTICATED` — authenticated user session
- `FORSETI_COOKIE_EDITOR` — editor user session
- `FORSETI_COOKIE_ADMIN` — admin user session

## Known access constraint
- `/home/keithaumiller/forseti.life` repo may not be directly readable from all executor contexts.
- All module reviews default to spec-level (using HQ artifacts) unless BASE_URL + cookies are confirmed available.
- Do NOT re-escalate this constraint every cycle; it is documented here.

## Default mode (idle)
- Your test-case source of truth (SoT) is the product suite manifest:
  `qa-suites/products/forseti-agent-tracker/suite.json`
- If your inbox is empty, do NOT generate your own work items.
- If your inbox is empty: run the manifest suite(s) where possible, OR update the manifest to reflect reality, and write findings in your outbox.
- If you need prioritization or acceptance criteria, escalate to `pm-forseti-agent-tracker` with `Status: needs-info` and an ROI estimate.

## Suite manifest hygiene (required)
- Keep `qa-suites/products/forseti-agent-tracker/suite.json` current.
- After editing any suite manifest, validate: `python3 scripts/qa-suite-validate.py`.

## Continuous full-site audit
- Script: `python3 scripts/site-full-audit.py --base-url http://localhost`
- Custom route supplement: `python3 scripts/drupal-custom-routes-audit.py`
- Role matrix (expected permissions): `org-chart/sites/forseti.life/qa-permissions.json`
- Output path: `sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest/`
- Run only against localhost/dev; never production unless `ALLOW_PROD_QA=1` is explicitly set.

## Gate 2 artifacts (required, per release item)
- Test plan: `sessions/qa-forseti-agent-tracker/artifacts/<item-id>/03-test-plan.md`
- Verification report: `sessions/qa-forseti-agent-tracker/artifacts/<item-id>/04-verification-report.md`
- Outbox updates reference artifact paths; they do not replace the filed artifacts.

## Blocker research protocol (required before any needs-info escalation)
1. Check `org-chart/sites/forseti.life/site.instructions.md` for BASE_URL, env facts.
2. Check `org-chart/sites/forseti.life/qa-permissions.json` for role-matrix expectations.
3. Check `runbooks/` for methodology (e.g. `runbooks/role-based-url-audit.md`).
4. Check `knowledgebase/` and prior `sessions/qa-forseti-agent-tracker/` artifacts.
5. Only then escalate — include what you checked, where you searched, and what you drafted.

## Escalation
- Escalation heading: `## Needs from Supervisor` (supervisor = `pm-forseti-agent-tracker`).
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.

## KB standing references
- `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — `agent_id` is a string PK, not Drupal uid.

## Release-cycle instruction refresh (required)
- At the start of every release cycle, review and update this file: remove stale paths/assumptions, add newly discovered constraints and correct "how to verify" commands.
```

---

## ROI estimate
- ROI: 8
- Rationale: Defining a real executable suite command converts all future QA cycles from spec-level reviews to automated PASS/FAIL evidence — directly enabling Gate 2 verification with reproducible outcomes. Combined with updating the seat instructions to reflect current env facts, this eliminates the two most common sources of repeat blockage (placeholder suite, missing env references) for every subsequent release cycle.
```

---
- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260224-improvement-round
- Generated: 2026-02-24T14:57:36-05:00
