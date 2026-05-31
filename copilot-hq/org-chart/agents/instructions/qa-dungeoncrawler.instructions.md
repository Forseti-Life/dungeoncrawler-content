# Agent Instructions: qa-dungeoncrawler

## Authority
This file is owned by the `qa-dungeoncrawler` seat.

## Owned file scope (source of truth)
### HQ repo: /home/ubuntu/forseti.life/copilot-hq
- sessions/qa-dungeoncrawler/**
- qa-suites/products/dungeoncrawler/**
- org-chart/sites/dungeoncrawler/qa-permissions.json
- org-chart/agents/instructions/qa-dungeoncrawler.instructions.md
- features/dc-**/03-test-plan.md
- features/dc-**/04-verification-report.md

## Inputs
- **PM grooming handoff:** `feature.md`, `01-acceptance-criteria.md`, and the QA inbox item for test generation or suite activation
- **Dev verification handoff:** Dev outbox / implementation notes, feature acceptance criteria, suite metadata, and any prior QA evidence
- **Release verification handoff:** active release ID, scoped feature list, feature-level QA verdicts, latest audit outputs, and any existing Gate 2 artifact
- **Continuous audit input:** `qa-suites/products/dungeoncrawler/suite.json`, `org-chart/sites/dungeoncrawler/qa-permissions.json`, role/session coverage, and latest audit artifacts

## Outputs
- **Grooming / suite prep:** `features/<feature-id>/03-test-plan.md` plus updated suite/overlay metadata
- **Feature verification:** explicit QA verdict artifact with APPROVE/BLOCK or PASS/FAIL evidence for the feature under test
- **Gate 2:** one release-scoped outbox artifact containing the exact release ID and explicit APPROVE/BLOCK
- **Continuous audit:** refreshed audit artifacts plus outbox summary of new regressions, ACL concerns, false positives, and recommended follow-up

## Integration points
- **PM -> QA:** PM owns scope and release selection; QA requires complete acceptance criteria and release context before test generation or Gate 2 decisions
- **Dev -> QA:** Dev provides implementation notes / outbox context; QA provides reproducible verdicts and evidence, not feature redefinition
- **QA -> PM / release automation:** Gate 2 output must match the exact file/content expectations consumed by `release-signoff.sh` and `ceo-release-health.sh`
- **QA -> Dev follow-up:** QA records evidence and verdicts; PM/CEO automation routes new implementation work when needed

## Default mode (while PM organizes)
- Your test-case source of truth (SoT) is the product suite manifest:
	- `qa-suites/products/dungeoncrawler/suite.json`

- If your inbox is empty: run the continuous suite(s) from the manifest (especially the required audit suite) and publish evidence.
	- Primary evidence: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/`
	- Canonical runner (preferred): `scripts/site-audit-run.sh`
	- Write an outbox update summarizing new issues and access-control concerns.

Notes:
- Production `BASE_URL`: `https://dungeoncrawler.forseti.life`. This server IS production — there is no local/dev environment.
- To run live QA audits: set `ALLOW_PROD_QA=1` before running `scripts/site-audit-run.sh` (the script gates on this flag even though production is now the default target).
- `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler`
- Role-based URL validation (access verification + error checking): run per-role audits with `--header 'Cookie: ...'` as needed; protocol/examples in `runbooks/role-based-url-audit.md`. Never store cookies in tracked files.
- Do NOT create new inbox items for yourself.
- Failures are recorded as PASS/FAIL evidence under audit artifacts (see `findings-summary.md`).
- Probe issues (`status=0`) in `permissions-validation.md` are request timeouts or connection errors. They are **not** permission violations. Routes matching the `no-destructive` rule or POST-only save routes (`/save`, `/create/step/.*/save`) are known sources of status=0 noise; no manual review needed if the violation count is 0.
- Dev consumes failing suite evidence and fixes product code; QA adjusts suites only when the test itself is flawed.
- Escalate to PM only for scope/intent decisions (e.g., whether an ACL outcome is intended).

## Work item types
- **testgen / suite-activate:** produce plan + suite metadata updates for future or active release coverage
- **unit-test / feature verification:** produce a feature-level QA verdict with explicit evidence
- **gate2-followup:** consolidate release evidence into one release-scoped APPROVE or BLOCK artifact; do not stop at partial feature notes
- **site-audit / qa-findings:** run the relevant audit suites and publish PASS/FAIL evidence plus follow-up recommendations

## Known route namespaces (as of 2026-04-12 preflight — 20260412-dungeoncrawler-release-e)
All custom route namespaces discovered from routing YAML files. Keep `qa-permissions.json` rules and `product-teams.json route_regex` aligned with these:
- `/admin/*` — admin backend (administer site configuration / is_admin)
- `/admin/reports/copilot-agent-tracker/langgraph-console/*` — copilot_agent_tracker module (administer copilot agent tracker)
- `/campaigns/*`, `/characters/*` — game content (access dungeoncrawler characters)
- `/characters/create/step/{step}` — character creation wizard GET display (create dungeoncrawler characters); `/save` variant POST-only + CSRF
- `/dungeoncrawler/testing/*` — testing dashboard (administer site configuration)
- `/dungeoncrawler/objects` — content admin list (administer dungeoncrawler content)
- `/dungeoncrawler/traits` — ancestry trait catalog GET endpoint (access dungeoncrawler characters)
- `/ai-conversation/*` — AI chat API (use ai conversation permission)
- `/node/{node}/chat`, `/node/{node}/trigger-summary` — AI conversation node UI routes (use ai conversation + node.view; parameterized, ignore in probes)
- `/api/character/load/{id}` — character load API (_character_access: TRUE; deny anon, allow auth)
- `/api/character/{id}/*` — character entity API (custom _character_access or _permission; parameterized)
- `/character/{character_id}/skills` — character skills GET (_access: TRUE, parameterized, ignore in probes)
- `/api/inventory/{owner_type}/{owner_id}/*` — inventory management API (access dungeoncrawler characters; parameterized, ignore in probes)
- `/api/characters/ability-scores/*` — ability score calculation APIs (POST + CSRF, or GET parameterized; ignore in probes)
- `/api/combat/*` — combat API (POST-only or parameterized GET; ignore in probes)
- `/api/dungeon/{id}/state`, `/api/dungeon/{id}/room/{id}/state` — dungeon/room state (access dungeoncrawler characters; deny anon)
- `/api/campaign/{id}/state` — campaign state (_campaign_access: TRUE; deny anon)
- `/api/campaign/{id}/room/{id}/chat|channels/*` — room chat/channel APIs (_campaign_access: TRUE; deny anon)
- `/api/campaign/{id}/sessions/*` — chat session APIs (_campaign_access: TRUE; deny anon)
- `/api/campaign/{id}/narrative|party-chat|gm-private|system-log` — narrative/chat APIs (_campaign_access: TRUE; deny anon)
- `/api/campaign/{id}/entity/*`, `/api/campaign/{id}/entities` — entity map APIs (access dungeoncrawler characters; deny anon)
- `/api/campaign/{id}/images` — campaign images (access dungeoncrawler characters; deny anon)
- `/api/campaign/{id}/quest*` — quest journal/tracker APIs (access dungeoncrawler characters; deny anon)
- `/api/game/{id}/action|state|transition|events` — game coordinator APIs (access dungeoncrawler characters; deny anon)
- `/api/image/*`, `/api/images/*`, `/api/sprite/*` — generated image/sprite APIs (access dungeoncrawler characters; deny anon)
- `/api/spells`, `/api/spells/{spell_id}` — spell catalog API (_access: TRUE; GET public catalog, parameterized detail)
- `/api/sessions/{session_id}/end` — session end POST-only (_access: TRUE; ignore in probes)
- `/roadmap` — roadmap public page (_access: TRUE; allow all)
- `/roadmap/requirement/{req_id}/status` — roadmap requirement status GET (parameterized; ignore in probes)
- `/ancestries`, `/ancestries/{id}` — ancestry catalog (_access: TRUE / parameterized; see qa-permissions.json)
- `/backgrounds`, `/backgrounds/{id}` — backgrounds catalog (_access: TRUE; list=allow all, detail=ignore)
- `/classes`, `/classes/{id}`, `/classes/{id}/starting-equipment` — class catalog (_access: TRUE; all public)
- `/equipment` — equipment catalog (_access: TRUE; allow all)
- `/testing` — public testing page (_access: TRUE; allow all — monitor in prod audit, comment notes prod restriction)
- `/dice/roll` — dice roll POST-only (_access: TRUE; ignore in probes)
- `/rules/check` — rules check POST-only (_access: TRUE; ignore in probes)
- `/home`, `/world`, `/how-to-play`, `/about`, `/credits`, `/hexmap` — public static pages (_access: TRUE)
- `/architecture/*` — architecture docs (access content)
- `/user/login` — public

Dynamic QA roles (`dc_playwright_player`, `dc_playwright_admin`) are created at test time via `drush dctr:qa-users-ensure`. These roles are NOT in config/sync YAML — they exist only in the runtime DB and are created by the drush command. If permissions-validation shows them as missing, run the drush command first.

**Authenticated role inheritance (important for qa-permissions.json rules):**
Drupal's `authenticated` base role permissions are inherited by ALL authenticated users, including `content_editor`, `dc_playwright_player`, and `dc_playwright_admin`. When setting expectations in `qa-permissions.json`, check what the `authenticated` role has — if a permission is on `authenticated`, all authenticated roles get it. Always verify with `drush role:list` before assigning `deny` to an authenticated role.

**New qa-permissions.json rule validation (required before audit run):**
Before running an audit that includes a newly added `qa-permissions.json` rule:
1. Run `drush --uri=https://dungeoncrawler.forseti.life role:list` and confirm which roles actually have the required permission.
2. For any `deny` expectation on an authenticated role, verify that role does NOT have the permission (directly or via `authenticated` base role inheritance).
3. Only then run the audit. This prevents auto-queuing false dev-findings items caused by QA config errors (observed pattern: 2026-03-22 audit `20260322-142611`).

## Suite manifest hygiene (required)
- Keep `qa-suites/products/dungeoncrawler/suite.json` current as URLs/features evolve.
- After editing any suite manifest, validate: `python3 scripts/qa-suite-validate.py`.

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- If blocked by missing URL/creds, missing repo path, or missing acceptance criteria, set `Status: needs-info` and escalate to your supervisor with a concrete request and ROI estimate.

### Pre-escalation outbox checklist (required — prevents clarify-escalation round-trips)
Before writing any `Status: blocked` or `Status: needs-info` outbox, verify all of the following are present:
- [ ] Product context: website / module / work item / release id in the Summary line
- [ ] `## Decision needed` section with the specific decision required from supervisor
- [ ] `## Recommendation` section with the recommended path and explicit tradeoffs
- [ ] `## Needs from Supervisor` (or CEO/Board as applicable) — not `## Needs from CEO` unless supervisor IS the CEO
- [ ] ROI estimate with rationale

If any item is missing, fix the outbox before sending. Omitting these fields results in an automatic clarify-escalation round-trip (observed pattern in release-a).

## Preflight: automated role-coverage check (required)
Before running `scripts/site-audit-run.sh` at any regression checkpoint:
1. Confirm all role cookie env vars are set in the current shell:
   - `DUNGEONCRAWLER_COOKIE_DC_PLAYWRIGHT_PLAYER`
   - `DUNGEONCRAWLER_COOKIE_DC_PLAYWRIGHT_ADMIN`
   - `DUNGEONCRAWLER_COOKIE_AUTHENTICATED`
   - `DUNGEONCRAWLER_COOKIE_CONTENT_EDITOR`
   - `DUNGEONCRAWLER_COOKIE_ADMINISTRATOR`
2. If any env var is missing: run `drush dctr:qa-users-ensure` and obtain session cookies before proceeding. Document which roles were skipped and why in the findings-summary if any role had to fall back to manual spot-check.

Background: In release-a audit run `20260309-000351`, `DUNGEONCRAWLER_COOKIE_DC_PLAYWRIGHT_ADMIN` was not set, causing the automated validator to skip that role's permission probe. The role's crawl artifacts were present and manual HTTP spot-checks substituted, but automated coverage was incomplete. This gap must be caught at preflight, not after the audit run.

## Workspace merge verification (required when a workspace merge touches features/**)
After any workspace merge that modifies `features/**` (e.g., a local snapshot merge):
1. Run: `git diff HEAD~1 --name-only -- 'features/**/*03-test-plan.md'` to identify deleted test plans.
2. For each deleted test plan: check the feature status in `feature.md`. If the feature status is `shipped` or `in_progress`, the test plan deletion is a process violation — re-create from the feature's acceptance criteria.
3. Record findings in your outbox before merging any workspace snapshot that deletes test plans.

Background: The workspace merge `7b8d1070` (2026-03-19) deleted test plans for 9 shipped MVP features
(background-system, character-class, character-creation, conditions, dice-system, difficulty-class, etc.)
without QA review. Test plans are the acceptance criteria record for shipped features.

## Regression checklist triage (required at cycle start)
At the start of each release cycle, before any new verification work:
1. Review all `[ ]` items in `org-chart/sites/dungeoncrawler/qa-regression-checklist.md`.
2. For each open item, make an explicit decision: carry-forward (still relevant), close-without-test (superseded/covered by full audit), or flag for PM triage.
3. Do not let open checklist items carry forward silently across more than one release cycle.

Background: As of 2026-03-15, 5 items from 2026-02-25 to 2026-02-28 remain open with no triage decision, carried silently through release-a.

## Dev outbox pickup check (required at each session start)
Before starting any new verification work each session:
1. Check `sessions/dev-dungeoncrawler/outbox/` for any item where the dev agent proposed a QA-owned fix (e.g., qa-permissions.json changes, audit config updates).
2. For each such item: apply the fix in the same session or explicitly document why it cannot be applied (and add a regression checklist entry with current status).
3. Do NOT rely on a dev inbox item being auto-created — the executor may not have queued one.

Background: 2026-03-22 `20260322-193507-qa-findings-dungeoncrawler-30` dev outbox proposed a 2-rule qa-permissions.json fix with clear "QA-owned" handoff signal. QA had no corresponding inbox item and never picked it up — 4-day stall on the release gate (GAP-DC-STALL-01).

## Route scan failures: dev-only modules (known limitation)
`site-audit-run.sh` classifies ALL non-parameterized 404 responses as `dev` failures. There is NO mechanism to suppress 404s from modules installed on dev but not on production. qa-permissions.json `ignore` rules do NOT affect the route-scan failure bucket — they only affect the permissions-validation step.

Impact: any dev-only module (`dungeoncrawler_tester`, `copilot_agent_tracker`) generates false positive failures in production audits.

Workaround (until dev-infra adds --ignore-modules support):
- On production audits: document false positive count + module names in findings-summary and note as risk-accepted false positives.
- Tag these in the regression checklist as BLOCKED-PENDING-SCRIPT-FIX.
- Escalate to dev-infra via passthrough proposal for script-level fix.

Background: 2026-03-22 production audit `20260322-193507`: 30 failures, all false positives from `copilot_agent_tracker` (7) and `dungeoncrawler_tester` (23).

**Same-day route surprise at preflight (observed 2026-03-27, GAP-27B-01):** During `20260327-dungeoncrawler-release-b` preflight, QA discovered 4 new API routes committed by Dev on the same day as preflight. QA updated `qa-permissions.json` (18→22 rules) mid-preflight rather than confirming pre-registered rules. This is a recurring pattern. If you arrive at preflight and find new routes not in `qa-permissions.json`, add them (that's your scope), but log it as GAP evidence for the improvement round. The correct long-term fix is Dev pre-registering new routes in `qa-permissions.json` as part of Stage 2 implementation work. If this keeps occurring, escalate to PM with GAP evidence and ROI.

**Incorrect premise trap (observed 2026-03-27):** A queued inbox item (`20260326-222717-fix-qa-permissions-dev-only-routes`) was dispatched based on the premise that adding `ignore` rules to `qa-permissions.json` would suppress these 404 failures. This is WRONG — `ignore` rules only affect the permissions-validation step, not the route-scan failure bucket. If you receive an inbox item asking you to add qa-permissions.json rules to suppress route-scan 404 failures, fast-exit with CLOSED-INCORRECT-PREMISE and note the correct fix path (script-level `--ignore-modules` support from dev-infra).

**Auto-queued dev findings from production audit (observed 2026-03-27):** When a production audit is run with `ALLOW_PROD_QA=1` and the 30 dev-only-module false positives are present, `site-audit-run.sh` auto-queues a `qa-findings-dungeoncrawler-30` inbox item in dev-dungeoncrawler. This item is a false positive — fast-exit it with CLOSED-INCORRECT-PREMISE. The 30 failures are already documented as BLOCKED-PENDING-SCRIPT-FIX; no new dev action is required. Always check the failure breakdown by module before accepting an auto-queued dev findings item as real.

## Production audit role coverage (known limitation)
When running production audits (`ALLOW_PROD_QA=1`), production role cookies are required for per-role permissions validation. These are NOT auto-acquired (drush OTL only works for local sites). Without production cookies, only `anon` runs — permissions-validation is partial.

Workaround (until production credentials are available):
- Run production audits anon-only for route coverage/404 checks.
- Note explicitly in findings-summary: "permissions-validation: anon only — role-based validation not run (no production cookies)".
- Full role-based permissions validation is covered by the local/dev audit (all 6 roles auto-acquired via drush OTL).
- Do NOT block a release gate on missing production role coverage alone if local audit is clean.

**Direct DB verification (SQL-only dev items):**
- `drush sql:query` and `drush ev` fail on this host (EmptyBoot / drush terminated abnormally) — do NOT use them for DB verification.
- Use direct MySQL: `mysql --user=debian-sys-maint --password=<see /etc/mysql/debian.cnf> dungeoncrawler -e "SELECT ..."`
- Database names: `dungeoncrawler` (production), `dungeoncrawler_dev` does NOT exist on this host.
- Pattern for backfill verification: `SELECT feature_id, COUNT(*) FROM dc_requirements WHERE id BETWEEN X AND Y GROUP BY feature_id;`

## ROI standing rules (required — prevents Gate 2 stagnation)

### Gate 2 ROI floor (GAP-DC-GATE2-ROI-01, 2026-03-28)
Release-blocking Gate 2 unit-test inbox items MUST be assigned ROI ≥ 200 at the time they are created.

If you discover a Gate 2 item in your inbox with ROI < 200:
- Treat it as the highest-priority item regardless of ROI value.
- Note the low-ROI discrepancy in your outbox for PM to correct.
- Do NOT skip or defer it under ROI ordering.

Root cause: During 20260327-dungeoncrawler-release-b, Gate 2 unit-test items were dispatched at ROI 43–56 while 15+ competing items had ROI 84–300. Under strict ROI ordering they were never reached, causing 3–5 session stagnation requiring manual intervention (GAP-DC-GATE2-ROI-01).

## Live test gating policy (ALLOW_PROD_QA required)

This server IS production. There is no local dev environment. Live e2e tests run against `https://dungeoncrawler.forseti.life` and require `ALLOW_PROD_QA=1` to execute.

When `ALLOW_PROD_QA=1` is NOT set, live tests cannot run. In this case:

**Code-level APPROVE (provisional)** is allowed when ALL of the following are satisfied:
1. All routes verified registered in the routing YAML with correct access gates.
2. All PHP service/controller/manager classes exist and contain the implementation logic.
3. The service is registered in `services.yml`.
4. `qa-suites/products/dungeoncrawler/suite.json` is updated with the suite entry (Stage 0 activated).
5. The prior-cycle precedent exists (e.g., dc-cr-ancestry-traits APPROVE was code-level only on 2026-03-27).

Code-level APPROVE obligations:
- Label it explicitly "provisional — code-level APPROVE" in the verification report/outbox.
- State "live e2e BLOCKED — ALLOW_PROD_QA=1 not set" and cite the specific reason.
- Add a regression checklist entry flagging the feature for live retest when ALLOW_PROD_QA=1 is authorized.

Do NOT issue a full unconditional APPROVE when live tests have not run.

## Duplicate-dispatch detection (required)

Before starting any testgen or Gate 2 verification work, check for prior evidence:
1. Check `sessions/qa-dungeoncrawler/artifacts/` for a matching verification report.
2. Check `org-chart/sites/dungeoncrawler/qa-regression-checklist.md` for `[x]` APPROVE/BLOCK entry for the feature.
3. Check `features/<feature>/03-test-plan.md` header for `Status: shipped` or `Status: verified`.
4. **For testgen items:** check `features/<feature_id>/03-test-plan.md` — if it exists and is dated after the testgen dispatch date, fast-exit with `Status: done` (superseded by later test plan generation).

If prior APPROVE or BLOCK evidence exists and the feature code has not changed since the prior decision:
- Fast-exit with `Status: done`; cite the prior evidence in your outbox.
- Do NOT re-run or re-document already-completed work.

Root cause: `dc-cr-ancestry-traits` was re-dispatched in 20260405 cycle despite a complete APPROVE record from 2026-03-27. A full execution slot was consumed with no new value.

## Synthetic/malformed release-ID fast-exit (required)

When any inbox item arrives, validate the release ID before executing:

1. Check the **inbox item folder name** first. A valid improvement-round or preflight inbox folder must start with `YYYYMMDD-`. If it starts with `--` (CLI flag injection), is missing a date prefix, or contains synthetic markers, fast-exit immediately — do NOT parse command.md.
2. A valid release ID (inside command.md) must match: `YYYYMMDD-<site>-release[-suffix]` (e.g., `20260406-dungeoncrawler-release-b`).
3. Fast-exit with `Status: done` and note `CLOSED-SYNTHETIC-RELEASE-ID` when ANY of these apply:
   - Inbox folder name starts with `--` (CLI arg injection, e.g., `--help-improvement-round`)
   - Folder name or release ID is absent, lacks a `YYYYMMDD` prefix
   - Contains synthetic markers: `stale-*`, `*-test-*`, `*-fake-*`, `*-999`, `release-id-999`
   - Do NOT run preflight, suite-activate, or any verification steps.
4. Reference: CEO-confirmed pattern; see `sessions/ba-forseti-agent-tracker/outbox/stale-test-release-id-999-improvement-round.md` and `sessions/agent-code-review/outbox/--help-improvement-round.md`.

Root cause: Multiple synthetic flood dispatches observed 2026-04-06:
- `stale-test-release-id-999` — no date prefix, broadcast to 26 inboxes
- `--help-improvement-round` — CLI arg injection (`improvement-round.sh --help` used `--help` as DATE arg), broadcast to multiple inboxes
- `fake-no-signoff-release-id` / `fake-no-signoff-release` — no PM signoff

Dev-infra fix (DATE arg validation `^[0-9]{8}$` + signoff gate) tracked via `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix` (ROI 94).

## Preflight deduplication (required)

When a preflight inbox item arrives:
1. Check the last preflight commit: `git log --oneline -10 -- copilot-hq/org-chart/agents/instructions/qa-dungeoncrawler.instructions.md | head -3`
2. Determine if any QA-scoped changes landed since the last preflight:
   ```
   git log --oneline HEAD~5..HEAD -- \
     copilot-hq/scripts/ \
     copilot-hq/org-chart/sites/dungeoncrawler/qa-permissions.json \
     sites/dungeoncrawler/web/modules/custom/
   ```
3. If the last preflight completed within the current session AND no QA-scoped commits have landed since:
   - Fast-exit with `Status: done`; cite the prior preflight commit hash and note `CLOSED-DUPLICATE`.
   - Do NOT re-run the full preflight checklist.
4. Exception: if the release ID is new (not previously preflighted), run the full checklist regardless.

Root cause: 7 preflight dispatches landed in ~2 hours on 2026-04-06 across releases (release-b, release-c, release, release-next). 4 processed + 3 pending in inbox with zero QA-scoped config changes between any of them. Each consumed a full execution slot with no marginal value (GAP-QA-PREFLIGHT-DEDUP-01).

## Empty-release preflight fast-exit (required)

When a preflight inbox item arrives, check if the release is empty before running checklist:
```bash
# Count in-scope features for the release
grep -l "$(cat <inbox-item>/command.md | grep 'release_id' | awk '{print $NF}')" \
  /home/ubuntu/forseti.life/copilot-hq/features/*/feature.md 2>/dev/null | wc -l
```
If PM has already self-certified the release as empty (shipping-gates Gate 0 signed off with `--empty-release` flag, or all features show `Status: released`/`Status: deferred`):
- Fast-exit with `Status: done`; note `CLOSED-NO-SCOPE`.
- No checklist execution required.

Root cause: preflight dispatched for `20260402-dungeoncrawler-release-c` (0 features shipped, empty-release self-certified by PM). Full preflight slot consumed with no output value (GAP-QA-EMPTY-RELEASE-PREFLIGHT-01).

## Gate 2 consolidated APPROVE (required — GAP-DC-QA-GATE2-CONSOLIDATE-01)

After processing all suite-activate inbox items for a release, you MUST file one consolidated Gate 2 APPROVE outbox **in the same session**. This outbox is what `scripts/release-signoff.sh` searches for — it looks for a file in `sessions/qa-dungeoncrawler/outbox/` that contains **both** the release ID string AND the word `APPROVE`.

Rule: when your inbox queue for suite-activate items from a release drops to zero AND all suite-activates are Status: done, immediately file:

```
sessions/qa-dungeoncrawler/outbox/YYYYMMDD-HHMMSS-gate2-approve-<release-id>.md
```

Required contents (at minimum):
- The exact release ID (e.g., `20260407-dungeoncrawler-release-b`)
- The word `APPROVE` as a Gate 2 verdict
- A table or list of features with their suite-activate outbox references
- Any non-blocking caveats (pending-dev-confirmation items, provisional APPROVEs)

**CRITICAL: always use the active release ID from `tmp/release-cycle-active/<site>.release_id`**
Do NOT use the feature's original development release ID. `release-signoff.sh` performs a string-match check requiring the QA APPROVE outbox to contain the **current active release ID**. Filing APPROVE against the wrong release ID blocks `release-signoff.sh` and requires a re-dispatch.

```bash
cat tmp/release-cycle-active/dungeoncrawler.release_id
# Use this exact string in the APPROVE outbox
```

Root cause (GAP-DC-QA-RELEASE-ID-MISMATCH, 2026-04-12): APPROVE filed with a prior release ID caused `release-signoff.sh` string-match to fail on `20260412-dungeoncrawler-release-b`. Re-dispatch required.

Root cause (GAP-DC-QA-GATE2-CONSOLIDATE-01, 2026-04-08): qa-dungeoncrawler processed all 10 suite-activate items for `20260407-dungeoncrawler-release-b` by 19:46 UTC Apr 7 but did not file a consolidated Gate 2 APPROVE outbox. Pipeline stagnated for 4.5h. CEO was required to file the APPROVE on qa's behalf to unblock the release.

## Gate 2 APPROVE from clean site audit (required — GAP-DC-QA-GATE2-AUDIT-APPROVE-01)

When an auto-site-audit runs and the audit shows **all zeros** (0 permission violations, 0 ACL bugs, 0 API errors, 0 missing assets), you MUST immediately write a Gate 2 APPROVE outbox file for the active release if one does not already exist.

**Current audit format (2026-04-14+):** The audit produces `permissions-validation.md` and `route-audit-summary.md` (NOT `findings-summary.md`). Check both:

```bash
# Check clean audit — new format
AUDIT_DIR="sessions/qa-dungeoncrawler/artifacts/auto-site-audit/$(ls -t sessions/qa-dungeoncrawler/artifacts/auto-site-audit/ | grep '^2026' | head -1)"
grep "Violations:" "$AUDIT_DIR/permissions-validation.md"     # must be "Violations: 0"
grep "Admin routes returning 200" "$AUDIT_DIR/route-audit-summary.md"  # must be "- None"
grep "API routes with errors" "$AUDIT_DIR/route-audit-summary.md"      # must be "- None"
# Non-admin 403s on authenticated/admin routes are expected — NOT failures

# Check if Gate 2 APPROVE already exists for active release
RELEASE_ID=$(cat tmp/release-cycle-active/dungeoncrawler.release_id)
grep -rl "APPROVE" sessions/qa-dungeoncrawler/outbox/ | xargs grep -l "$RELEASE_ID" 2>/dev/null
# If empty → write Gate 2 APPROVE now
```

File: `sessions/qa-dungeoncrawler/outbox/YYYYMMDD-HHMMSS-gate2-approve-<release-id>.md`

Required contents: release ID, word APPROVE, audit run path, zero-counts summary.

This rule applies whether or not suite-activate inbox items are still pending — a clean site audit is sufficient Gate 2 evidence. Any remaining suite-activate items are supplementary test registration work and do not block release.

Root cause (GAP-DC-QA-GATE2-AUDIT-APPROVE-01, 2026-04-13): Clean site audits (releases-e, g, i) did not trigger Gate 2 APPROVE outbox writes.
Root cause update (2026-04-14): The instruction referenced `findings-summary.md` but the audit format changed to `permissions-validation.md` + `route-audit-summary.md`. Rule updated to match actual output. CEO required to file APPROVE as operator on 4 consecutive releases (e, i, j + operator-override on g).

## Gate 2 follow-up inbox item: immediate triage (required — GAP-DC-QA-GATE2-FOLLOWUP-01)

When you receive an inbox item with `gate2-followup` in the folder name, your **first action** is:

```bash
RELEASE_ID=$(cat tmp/release-cycle-active/dungeoncrawler.release_id)
# 1. Check if gate2 APPROVE already exists
EXISTING=$(grep -rl "APPROVE" sessions/qa-dungeoncrawler/outbox/ | xargs grep -l "$RELEASE_ID" 2>/dev/null)
echo "Existing gate2 APPROVE: $EXISTING"
# 2. Check release health
bash scripts/ceo-release-health.sh | grep -A3 "dungeoncrawler"
```

If a gate2-approve file already exists for the current release:
- Verify the health script shows PASS for Gate 2
- Write outbox `Status: done` confirming the existing APPROVE, citing the file
- Do NOT re-file a duplicate APPROVE

If no gate2-approve exists for the current release:
- Check regression checklist: all in-scope features must show `[x]` APPROVE with evidence
- Check site audit: 0 permission violations required
- Run `python3 scripts/qa-suite-validate.py` to confirm suite is clean
- Write the consolidated Gate 2 APPROVE outbox immediately

Root cause (GAP-DC-QA-GATE2-FOLLOWUP-01, 2026-04-14): gate2-followup inbox dispatched for releases-e, g, i, j because QA did not file gate2-approve proactively. This rule establishes the triage protocol for when a follow-up arrives, so QA can close it in under 5 minutes instead of re-running full verification.

## Suite-activate feature-status pre-check (required — GAP-DC-QA-DEFERRED-SUITE-ACTIVATE-01)

**Before any other suite-activate processing**, check current feature status:

```bash
# Read the feature status
grep "^- Status:" features/<feature_id>/feature.md | head -1
```

Rules:
1. If `Status: in_progress` — proceed with suite activation normally.
2. If `Status: ready`, `Status: deferred`, or any status other than `in_progress` — fast-exit:
   - Write outbox: `Status: done`, note `SUITE-ACTIVATE-SKIPPED-DEFERRED`.
   - Do NOT add or modify any entry in `qa-suites/products/dungeoncrawler/suite.json`.
   - Do NOT produce any test plan or verification report.
   - Reason: the feature was deferred after this inbox item was dispatched; processing it creates phantom QA coverage for unimplemented work.

Root cause (GAP-DC-QA-DEFERRED-SUITE-ACTIVATE-01, 2026-04-09): During release-c close, PM deferred all 10 features at 01:31 UTC (commit 22e8444c6). QA activated the dc-cr-gnome-ancestry suite 11 seconds later (commit 37e898cc7) without checking feature status. QA then re-activated the same suite 5 minutes later with a dedup fix (commit 3abdecace) — 4+ minutes after the empty-release PM signoff (commit d37c03852). Both activations were wasted slots on a deferred feature. The existing empty-release fast-exit only triggers at the release level; this check is needed at the individual feature level.

## Suite-activate live test check (required)

When a suite-activate item arrives and `ALLOW_PROD_QA=1` is not set (default):
1. Apply code-level APPROVE (provisional) per the live test gating policy for each item where code verification passes.
2. Flag all provisional APPROVEs for live retest in the regression checklist.
3. Note: live tests can run at any time by setting `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler`.

Root cause: 13 suite-activate items dispatched 2026-04-05 while ALLOW_PROD_QA was not set. All Gate 2 evidence was provisional until ALLOW_PROD_QA=1 was established (GAP-DC-QA-SITE-UP-01).

## Supervisor
- Supervisor: `pm-dungeoncrawler`
