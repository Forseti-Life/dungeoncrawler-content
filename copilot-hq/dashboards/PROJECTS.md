# Projects Registry

Authoritative list of active **product lines** and **delivery projects** across the org.

The live authority page is:

- `https://forseti.life/roadmap`

That page is rendered from this file. CEO and architect seats must treat the roadmap page + this backing file as the single source of truth for what is on the active project list.

**Owned by:** ceo-copilot-2  
**Update cadence:** when a project is created, advanced, closed, or when a release picks up project-scoped work  
**Format:** one numbered portfolio registry. Every active item on the live roadmap page must have a `PROJ-*` ID. Use the `Type` column to distinguish long-lived product tracks from execution initiatives.

**Required per-project fields:** `Scope`, `Current state`, `Last scoped release`, `Progress SLA`, `Next step`, `Queue status`

## Development Node Assignment Registry

This section is the source of truth for all nodes and project assignments.
**The CEO on the master node is the sole owner of this section.**
Worker nodes identify themselves by matching `NODE_ID` in their local
`node-identity.conf` against the Node ID column below, then claim only the
projects assigned to that node.

**Used by:** `scripts/ceo-dispatch-project-task.sh`, `scripts/dev-sync-once.sh`

### Node Registry

Each row is a registered machine in the fleet. Nodes self-identify by `Node ID`.

| Node ID | Role | Hostname | Environment | Owner | Status |
|---|---|---|---|---|---|
| master | master | forseti.life | AWS EC2 Ubuntu 24.04 | ceo-copilot-2 | active |
| dev-laptop | worker | pop-os | Keith's Dev Laptop (Pop!_OS) | dev-jobhunter | active |

### Project Assignments

Which node/agent handles each project. CEO edits this to reassign work between nodes.

**Worker node (dev-laptop):** JobHunter only.
**Master node:** everything else.

| Project key | Target node | Target agent | Website | Module | Execute |
|---|---|---|---|---|---|
| forseti-jobhunter-automation | dev-laptop | dev-jobhunter | forseti.life | job_hunter | dispatch-only |
| forseti-safety-application | master | dev-forseti | forseti.life | forseti_content | local |
| forseti-agent-tracker | master | dev-forseti-agent-tracker | forseti.life | copilot_agent_tracker | local |
| dungeoncrawler | master | dev-dungeoncrawler | dungeoncrawler | dungeoncrawler_content | local |
| infrastructure | master | dev-infra | infrastructure | infrastructure | local |

---

## Registry

| ID | Name | Type | Product | Status | Priority | Lead | Started |
|---|---|---|---|---|---|---|---|
| PROJ-007 | Dungeoncrawler Product Track | product line | dungeoncrawler | paused | **P0** | pm-dungeoncrawler | 2026-04-13 |
| PROJ-003 | DungeonCrawler Roadmap Completion | delivery project | dungeoncrawler | completed | **P0** | pm-dungeoncrawler | 2026-03-01 |
| PROJ-004 | Job Hunter | product line | forseti.life | paused | P1 — worker node | pm-forseti | 2026-04-12 |
| PROJ-005 | AI Conversation | product line | forseti.life | paused | P1 | pm-forseti | 2026-04-12 |
| PROJ-006 | Community Safety | product line | forseti.life | paused | P2 | pm-forseti | 2026-04-12 |
| PROJ-008 | Forseti Accounting Pipeline | delivery project | forseti.life | paused | P1 | accountant-forseti | 2026-04-13 |
| PROJ-009 | Forseti Open Source Initiative | delivery project | org-wide | paused | P1 | pm-open-source | 2026-04-13 |
| PROJ-010 | External Integration Configuration Audit | delivery project | org-wide | paused | P1 | pm-integrations | 2026-04-13 |
| PROJ-011 | Forseti Community Resource Mesh | delivery project | forseti.life | paused | P1 | pm-forseti | 2026-04-13 |
| PROJ-001 | LangGraph Console UI | delivery project | forseti.life | paused | P1 | pm-forseti | 2026-04-05 |
| PROJ-002 | QA Suite Completeness | delivery project | forseti.life | paused | P2 | pm-forseti / qa-forseti | 2026-04-09 |

---

## PROJ-004 — Job Hunter

**Scope:** Forseti's job-seeking platform covering resume intake, discovery, application prep, submission support, and tracking.

**Status:** paused

**Current state (2026-05-18):** Paused by Board/user direction. Prior active buildout and queued release-h context remain historical reference only and should not drive new work while the project is paused.

**Last scoped release:** `20260412-forseti-release-h`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** Hold. Do not activate, dispatch, or continue Job Hunter work until the Board/user explicitly resumes PROJ-004.

**Queue status:** Paused on 2026-05-18. Older Job Hunter queue references are historical only.

---

## PROJ-005 — AI Conversation

**Scope:** Persistent assistant experience, conversation memory, model integration, and shared AI capability across Forseti products.

**Status:** paused

**Current state (2026-05-18):** Paused by Board/user direction. Prior foundation work and the ready Local LLM / Provider Selection slice remain historical reference only while the project is paused.

**Last scoped release:** `20260412-forseti-release-h` (targeted; not yet activated — pending BA impl notes + release slot)

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** Hold. Do not activate, dispatch, or continue AI Conversation work until the Board/user explicitly resumes PROJ-005.

**Queue status:** Paused on 2026-05-18. Older AI Conversation queue references are historical only.

---

## PROJ-006 — Community Safety

**Scope:** Public safety content, maps, alerts, community participation, and member-support tooling.

**Status:** paused

**Current state (2026-05-18):** Paused by Board/user direction. Prior Community Incident Report grooming and release-slot planning remain historical reference only while the project is paused.

**Last scoped release:** `20260412-forseti-release-h` (targeted; not yet activated — pending BA impl notes + release slot)

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** Hold. Do not activate, dispatch, or continue Community Safety work until the Board/user explicitly resumes PROJ-006.

**Queue status:** Paused on 2026-05-18. Older Community Safety queue references are historical only.

---

## PROJ-007 — Dungeoncrawler Product Track

**Scope:** The dedicated Dungeoncrawler product line, separate site, and its long-lived PF2E implementation program. Long-term mission: implement all PF2E rulebook requirements currently tracked in `dc_requirements` MySQL table (2033 implemented, 674 in_progress, 698 pending as of 2026-04-13).

**Status:** paused

**Current state (2026-05-18):** Paused by Board/user direction. Prior release-q/release-r planning, backlog coverage notes, and the `dc-cr-campaign-multiplayer-v1` planning update remain historical reference only while the product track is paused.

**Planning update (2026-05-16):** Community suggestion NID 55 is now tracked under PROJ-007 as `dc-cr-campaign-multiplayer-v1`, a ready multiplayer-v1 epic scoped to explicit campaign membership, joined-campaign discovery, one-character-per-player assignment, and serialized turn-taking on top of the existing optimistic-version campaign state. Websockets and live-presence sync remain out of scope for this first delivery track.

**Backlog coverage status (2026-04-14):**
- `core/ch01` (Chapter 1: Introduction) — 237 pending, now mapped primarily to `dc-cr-character-creation` and `dc-cr-character-leveling`
- `core/ch02` (Chapter 2: Ancestries & Backgrounds) — 371 pending, now mapped across the ancestry/background backlog (`dc-cr-human/dwarf/gnome/elf/goblin/halfling-*`, `dc-cr-ancestry-system`, `dc-cr-background-system`)
- `gng` (Guns & Gears, 5 chapters) — 30 pending, now queued in backlog via `dc-gng-guns-gears`
- `som` (Secrets of Magic, 5 chapters) — 30 pending, now queued in backlog via `dc-som-secrets-of-magic`
- `b2` (Bestiary 2) — 12 pending, now queued in backlog via `dc-b2-bestiary2`
- `b3` (Bestiary 3) — 18 pending, deferral cleared now that `dc-b2-bestiary2` shipped; grooming/test-plan handoff started via `dc-b3-bestiary3`

**Last scoped release:** `20260412-dungeoncrawler-release-p`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** Hold. Do not dispatch or continue Dungeoncrawler Product Track work until the Board/user explicitly resumes PROJ-007.

**Queue status:** Paused on 2026-05-18. Older Dungeoncrawler product-track queue references are historical only.

---

## PROJ-008 — Forseti Accounting Pipeline

**Scope:** Establish Forseti's repeatable accounting operating model: daily income/expense capture, cash reconciliation, daily flash P&L, monthly close, renewal tracking, anomaly logging, and the smallest finance system stack needed to keep reporting trustworthy as volume grows.

**Owner / primary developer:** `accountant-forseti`

**Status:** paused

**Current state (2026-05-18):** Paused by Board/user direction. The finance workspace and prior blocker analysis remain historical reference only while the project is paused.

**Last scoped release:** `20260412-forseti-release-h` (operations/process foundation defined; no product feature activation yet)

**Progress SLA:** 7 days without a CEO/accountant update, source-system hookup decision, or April artifact population from live sources = breach

**Next step:** Hold. Do not advance accounting-pipeline work until the Board/user explicitly resumes PROJ-008.

**Queue status:** Paused on 2026-05-18. Older accounting-pipeline queue references are historical only.

---

## PROJ-009 — Forseti Open Source Initiative

**Scope:** Publish the Forseti autonomous Drupal development platform as open source under the `Forseti-Life` GitHub organization, including the platform overview repo, selected reusable component repos, contributor docs, and the release/security process needed to publish safely.

**Status:** paused

**Current state (2026-05-18):** Paused by Board/user direction. Prior publication-readiness work, candidate selection, and security remediation context remain historical reference only while the project is paused.

**Last scoped release:** none yet (portfolio initiative; not release-scoped to a product release)

**Progress SLA:** 7 days without a PM-open-source re-baseline, dev-open-source publication audit, or Board/org-setup step = breach

**Next step:** Hold. Do not dispatch or continue open-source publication work until the Board/user explicitly resumes PROJ-009.

**Queue status:** Paused on 2026-05-18. Older open-source queue references are historical only.

---

## PROJ-010 — External Integration Configuration Audit

**Scope:** Inventory and audit how the org stores, resolves, and governs configuration for external systems used by the server stack and adjacent production operations, including APIs, cloud providers, billing systems, deploy workflows, token files, and Drupal-backed integration settings.

**Status:** paused

**Current state (2026-05-18):** Paused by Board/user direction. The Phase 1 inventory, integration registry, and remediation backlog remain historical reference only while the project is paused.

**Last scoped release:** none yet (org-wide audit project)

**Progress SLA:** 7 days without a `pm-integrations` update, inventory expansion, or remediation dispatch = breach

**Next step:** Hold. Do not dispatch or continue integration-audit work until the Board/user explicitly resumes PROJ-010.

**Queue status:** Paused on 2026-05-18. Older integrations queue references are historical only.

---

## PROJ-011 — Forseti Community Resource Mesh

**Scope:** Build a community resource mesh between independent Forseti installations so any installation can identify peer installations, establish trust, exchange signed messages, advertise needs and capabilities, and initially share **agent expertise** and **institutional-management services**. Compute and storage remain future-state extensions.

**Status:** paused

**Current state (2026-05-18):** Paused by Board/user direction. The MVP architecture, roadmap, and ready feature stub remain historical reference only while the project is paused.

**Last scoped release:** none yet (new strategic delivery project)

**Progress SLA:** 7 days without PM/BA decomposition, MVP scope refinement, or release-slot planning = breach

**Next step:** Hold. Do not dispatch or continue Community Resource Mesh work until the Board/user explicitly resumes PROJ-011.

**Queue status:** Paused on 2026-05-18. Older mesh queue references are historical only.

---

## PROJ-001 — LangGraph Console UI

**Roadmap:** `features/forseti-langgraph-ui/roadmap.md`  
**Scope:** Build the full Copilot HQ control-plane console UI on forseti.life — telemetry, agent monitoring, session management, release controls, and eval scorecards wired to live orchestrator tick data.

**Status:** paused

**Current state (2026-05-18):** Paused by Board/user direction. The shipped foundation and next-slice planning remain historical reference only while the project is paused.

**Last scoped release:** `20260412-forseti-release-h` (targeted; not yet activated — pending BA confirmation)

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** Hold. Do not activate, dispatch, or continue LangGraph Console UI work until the Board/user explicitly resumes PROJ-001.

**Queue status:** Paused on 2026-05-18. Older LangGraph Console UI queue references are historical only.

---

## PROJ-002 — QA Suite Completeness

**Scope:** Build repeatable, executable QA coverage for shipped Forseti features and clean up stale suite shells so release verification is durable, automatable, and auditable.

**Status:** paused  
**Priority:** P2  
**Lead:** pm-forseti (dispatch), qa-forseti (execution)  
**Scope product:** forseti.life  
**Suite manifest:** `qa-suites/products/forseti/suite.json`

**Current state (2026-05-18):** Paused by Board/user direction. The suite-triage and Phase 2 fill plan remain historical reference only while the project is paused.

**Last scoped release:** `20260412-forseti-release-h`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** Hold. Do not dispatch or continue QA Suite Completeness work until the Board/user explicitly resumes PROJ-002.

**Queue status:** Paused on 2026-05-18. Older QA Suite Completeness queue references are historical only.

### Problem

The forseti QA suite manifest has **86 registered suites but only 2 have populated `test_cases`** (15 total executable test cases). Feature verification is done inline by the qa-forseti agent during each release cycle — PASS/FAIL results live in session outboxes but are not recorded back into the manifest. This means:

- No repeatable automated runner: there is nothing to re-execute against a regression without re-reading old session outboxes
- E2E Playwright suite (`jobhunter-e2e`) has never run in automation — requires `FORSETI_COOKIE_AUTHENTICATED` env var not provisioned
- Cross-user isolation (TC-11, TC-16) is untestable with the current single-user test provisioning
- 84 empty suite shells accumulated from past releases create noise and give a false sense of coverage

### Goals

1. Every shipped forseti feature has at least one executable, re-runnable test case in `suite.json`
2. E2E Playwright pipeline unblocked — auth cookie provisioned automatically via `drush user:login`
3. Cross-user isolation covered by a second QA user (`qa_tester_authenticated_2`)
4. Stale/superseded suite shells retired or merged
5. `python3 scripts/qa-suite-validate.py` passes clean on every cycle

### Phases

#### Phase 1 — Triage (1 release cycle)
**Owner:** qa-forseti  
**Output:** Audit report in `sessions/qa-forseti/artifacts/suite-triage/`
**Status:** in_progress (dispatched 2026-04-09)

**Feature stubs created (2026-04-09):**
- `features/forseti-qa-suite-fill-release-f` — 16 release-f suites (ROI: 45)
- `features/forseti-qa-suite-fill-jobhunter-submission` — 2 submission suites (ROI: 45)
- `features/forseti-qa-suite-fill-agent-tracker` — 4 agent tracker suites (ROI: 45)
- `features/forseti-qa-suite-fill-controller-extraction` — 2 controller extraction suites (ROI: 45)
- `features/forseti-qa-suite-retire-stale` — 18 retire candidates (ROI: 40)
- `features/forseti-qa-e2e-auth-pipeline` — E2E Playwright auth unblock, release-h (ROI: 35)

**Dispatched (2026-04-09):**
- qa-forseti triage → `sessions/qa-forseti/inbox/20260409-proj002-suite-triage/` (ROI 60)
- ba-forseti: 6 grooming items (ROI 35–45)
- Pending: pm-qa-handoff.sh dispatch for each feature after ba-forseti delivers ACs

- Classify each of the 84 empty suites as one of:
  - `fill` — feature is shipped and actively in production; needs real test_cases
  - `retire` — feature superseded, removed, or merged into another suite; delete the shell
  - `defer` — feature exists but has no test plan yet; backlog for Phase 2
- Produce a triage table: suite ID → disposition → reason
- Target: identify the ~20–25 highest-value `fill` candidates (current shipped features)

**Priority `fill` candidates (known from recent releases):**
```
forseti-jobhunter-application-status-dashboard-static
forseti-jobhunter-application-status-dashboard-functional
forseti-jobhunter-google-jobs-ux-static
forseti-jobhunter-google-jobs-ux-functional
forseti-jobhunter-profile-completeness-static
forseti-jobhunter-profile-completeness-functional
forseti-jobhunter-resume-tailoring-display-static
forseti-jobhunter-resume-tailoring-display-functional
forseti-ai-conversation-user-chat-static
forseti-ai-conversation-user-chat-acl
forseti-ai-conversation-user-chat-csrf-post
forseti-jobhunter-application-submission-route-acl
forseti-jobhunter-application-submission-unit
forseti-copilot-agent-tracker-route-acl
forseti-copilot-agent-tracker-api
role-url-audit  (should point to site-audit-run.sh output)
```

**Retire candidates (superseded refactors):**
```
forseti-jobhunter-controller-refactor-static
forseti-jobhunter-controller-refactor-unit
forseti-jobhunter-controller-refactor-phase2-*  (6 suites — merged into split)
forseti-ai-service-refactor-*  (3 suites — superseded by db-refactor)
forseti-ai-debug-gate-*  (3 suites — debug gate removed)
```

#### Phase 2 — Fill Priority Suites (2–3 release cycles)
**Owner:** qa-forseti (with dev-forseti support for command construction)  
**Output:** `suite.json` updated with executable `test_cases` for all `fill` candidates

For each `fill` suite:
1. Read the feature's `03-test-plan.md` and prior QA outbox verification evidence
2. Extract the bash commands already run (they are in the outboxes — just needs transcription)
3. Write `test_cases` array: `id`, `description`, `type`, `command` (where automatable), `status`
4. Run `python3 scripts/qa-suite-validate.py` after each batch
5. Commit to HQ repo

**Success metric:** ≥ 40 executable test cases in `suite.json` (up from 15)

#### Phase 3 — E2E Playwright Unblock (1 release cycle)
**Owner:** dev-forseti  
**Output:** Automated auth cookie provisioning in the site-audit pipeline

Root cause: `FORSETI_COOKIE_AUTHENTICATED` env var is never set in automation because it requires a live session cookie. The `drush user:login` command CAN generate a one-time login link, and `curl -sc` CAN extract the session cookie — both already documented in the qa-forseti seat instructions.

**Fix approach:**
1. Add a helper step to `scripts/site-audit-run.sh` (or a companion script) that:
   - Runs `drush user:login --uid=<qa_tester_uid> --no-browser` to get a ULI
   - Follows the ULI with `curl -sc /tmp/forseti_qa.cookies` to capture the session cookie
   - Exports `FORSETI_COOKIE_AUTHENTICATED` from the cookie jar
2. Gate the helper behind `ALLOW_PROD_QA=1` (already present)
3. Wire the cookie into the role-matrix audit passes
4. Verify TC-12 (CSRF send-message) and TC-13 (route static) are machine-executable

**Acceptance criteria:**
- `bash scripts/site-audit-run.sh forseti-life` completes an authenticated-role pass without manual cookie injection
- `jobhunter-e2e` Playwright suite runs at least steps 1–5 end-to-end (step 6 = job submission, may require seed data)

#### Phase 4 — Cross-User Isolation Coverage (1 release cycle)
**Owner:** dev-forseti (infra), qa-forseti (test authoring)  
**Output:** `jhtr:qa-users-ensure` supports a second test user; TC-11 and TC-16 executable

- Extend `jhtr:qa-users-ensure` drush command to provision `qa_tester_authenticated_2`
- Add second-user session cookie provisioning to the E2E pipeline
- Write TC-11 (profile cross-user block) and TC-16 (e2e cross-user isolation) as executable suite entries
- These are HIGH severity as the bulk-archive MEDIUM finding this cycle demonstrates cross-user data risks exist

#### Phase 5 — Retire Stale Shells & Housekeeping (1 release cycle)
**Owner:** qa-forseti  
**Output:** Clean `suite.json` with no empty shells; `qa-suite-validate.py` passes

- Delete all `retire`-classified suite entries
- Ensure all remaining entries have at minimum `id`, `label`, `type`, `feature_id`, and at least 1 `test_cases` entry
- Update `role-url-audit` suite to reference `scripts/site-audit-run.sh` output directly
- Run final validation: `python3 scripts/qa-suite-validate.py`

### Success Criteria (project complete)

- [ ] 0 empty suite shells in `suite.json`
- [ ] ≥ 50 executable test cases across all suites
- [ ] E2E Playwright runs without manual cookie injection in CI/automated context
- [ ] Cross-user isolation (TC-11, TC-16) executable
- [ ] `qa-suite-validate.py` passes clean
- [ ] All release-f and later features have test_cases populated in the manifest

### KPI impact

- **Escaped defects**: executable regression suite means regressions are caught before Gate 2, not after
- **Audit freshness**: authenticated-role pass means ACL coverage includes job_hunter routes (currently skipped)
- **Post-merge regressions**: cross-user isolation tests catch the class of bug found this cycle (bulk-archive)

### Risks

| Risk | Mitigation |
|---|---|
| drush ULI cookie expires mid-run | Re-provision cookie at start of each site-audit invocation |
| Phase 2 requires reading 20+ old outboxes — high agent effort | Batch 5 suites per release cycle; prioritize by active use |
| qa-suite-validate.py may reject new command formats | Run validate after each batch; fix before committing |

### Related work

- **dev-forseti inbox:** `20260409-bulk-archive-global-status-mutation-release-f` — per-user archive fix (Phase 4 prerequisite)
- **KB lesson:** `knowledgebase/lessons/20260227-jobhunter-e2e-csrf-token-empty-save-job.md`
- **QA seat instructions:** `org-chart/agents/instructions/qa-forseti.instructions.md` (CSRF smoke check + E2E run steps already documented)

---

## PROJ-003 — DungeonCrawler Roadmap Completion

**Roadmap audit runbook:** `runbooks/roadmap-audit.md`  
**Scope:** Systematically implement all `pending` requirements in `dc_requirements` table until every requirement is either `implemented` or has a `feature_id` pointing to an active pipeline feature.

**Status:** completed

**Current state (2026-05-18):** Closed out as completed. The roadmap-audit objective is now satisfied: the public roadmap still derives requirement state from the live pipeline, the sync path remains intact, and the remaining ongoing delivery work is better tracked under the broader active product lane in PROJ-007 rather than as a separate standalone project.

**Last scoped release:** `20260412-dungeoncrawler-release-p`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** No further standalone work. Track ongoing Dungeoncrawler delivery under PROJ-007.

**Queue status:** Closed on 2026-05-18. No active queue should target PROJ-003 separately from PROJ-007.

See `runbooks/roadmap-audit.md` for full query protocol and per-chapter status.

---

*Last updated: 2026-05-16 by architect-copilot (added tracked Dungeoncrawler multiplayer v1 epic under PROJ-007)*
