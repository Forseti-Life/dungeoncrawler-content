# Org-wide Instructions (All Roles)

These rules apply to every agent, regardless of role or website scope.

## Authority
This file is owned by the `ceo-copilot` seat.

## Owned file scope (source of truth)
- org-chart/org-wide.instructions.md

## KPIs (required)
- KPI catalog: `org-chart/kpis.md`
- Site scoreboards: `knowledgebase/scoreboards/` (update weekly)

## Instructions methodology (required)
We use a layered instruction stack so the org, each role, each product/site, and each seat can evolve independently without conflicts.

### Instruction stack (highest → lowest precedence)
1) Org-wide: `org-chart/org-wide.instructions.md`
2) Role: `org-chart/roles/<role>.instructions.md`
3) Site/Product: `org-chart/sites/<site>/site.instructions.md`
4) Seat: `org-chart/agents/instructions/<agent-id>.instructions.md`

Rules:
- Lower layers may add constraints, but must not contradict higher-layer policy.
- Site/Product instructions are for **environment facts** and **product-wide operating rules** (BASE_URLs, repo roots, auth expectations, QA posture, release norms).
- Seat instructions are for **owned file scope**, and any seat-specific process details.

Implementation note (enforced): the executor loads org-wide + role + site + seat instructions into each agent prompt.

Verification:
- To see the fully-resolved instruction stack for any seat, run: `scripts/agent-instructions.sh <agent-id>`

### New site/product setup checklist (CEO-owned)
When starting a new site/product, the CEO must ensure these exist before delegating work:
- `org-chart/sites/<site>/README.md` (human-friendly overview)
- `org-chart/sites/<site>/site.instructions.md` (authoritative product/site rules)
- `org-chart/agents/agents.yaml` entries with `website_scope: ["<site>"]`
- Seat instructions files for the site (PM/BA/Dev/QA/Security as needed) under `org-chart/agents/instructions/`.

## Communication and accountability
- **No silent blocking**: if you cannot proceed, explicitly mark `Status: blocked` or `Status: needs-info` and state exactly what you need.
- **Write up-chain**: requests for clarification/resources must be written explicitly (not implied).

## Chain of command (required)
- **Escalations follow the reporting chain** in `org-chart/org-chart.yaml`.
	- Individual contributors (BA/Dev/QA/Security/etc.) escalate to their **supervisor**.
	- PM escalates to CEO only when it is a scope/priority/ship decision that cannot be made at PM level.
	- **Only the CEO** escalates to the human owner.

## Decision ownership reference (required)
- All roles must use `org-chart/DECISION_OWNERSHIP_MATRIX.md` as the default reference for:
	- who owns a decision,
	- what can be resolved independently,
	- when escalation is mandatory.
- If a seat escalates without mapping the issue type to the matrix, the supervisor should return the item for matrix-based triage.
- **Supervisor decision check (required):** before escalating, the supervisor must ask:
	- *Is this a decision I’m authorized to make at my role level?*
	- *If yes:* decide and unblock (with constraints + acceptance criteria if needed).
	- *If no:* escalate one level up with options (Decision needed + Recommendation + tradeoffs).

## Board of Directors (human owner)
- The human user acts as the **Board of Directors**.
- Board-level escalations must be routed **via the CEO** (not directly by non-CEO roles).
- Board decisions are for: cross-team priority shifts, risk acceptance beyond normal thresholds, staffing/role changes, and release/ship governance when contested.
- **Measurable progress**: every update must state what changed, what was verified, and what remains.

## Tooling reality (do not misreport)
- **Do not claim filesystem permission issues** (e.g. "Permission denied", "cannot write files") unless you personally verified it with a command and can paste the exact output.
- In this org, **outbox/artifact persistence is handled by the executor/CEO**; treat missing scope/inputs/repo access as blockers, not "I can't write files".

## Markdown formatting directive: `_vscodecontentref_` links
Directive (2026-02-22): The `http://_vscodecontentref_/...` links (for example `http://_vscodecontentref_/21`) arise from a known VS Code/GitHub Copilot rendering issue.

This happens when Copilot outputs text that includes a **backtick immediately followed by digits**, which VS Code may misparse as a hyperlink instead of inline code. This often appears in .NET generic type displays (reflection/metadata arity names) such as ``List`1`` or ``Dictionary`2``.

Rules:
- Do **not** include .NET generic arity names using the raw backtick+number form (e.g. avoid ``List`1``).
- Prefer C#-style generics instead: `List<T>`, `Dictionary<TKey, TValue>`, etc.
- If you must show an arity name, put it in a fenced code block (triple backticks) or use a double-backtick inline code span, for example: ``List`1``.
- If a `_vscodecontentref_` link appears in your output, treat it as a formatting bug and re-render the snippet using one of the safe formats above.

Reference: GitHub Issue #286478.

## Work request quality (SMART)
Every work request must be actionable and include:
- **Specific scope** (what/where)
- **Measurable acceptance criteria** (definition of done)
- **Verification method** (tests, commands, URL, screenshots)
- **Time bounds** (if a deadline is relevant)

## Portfolio/project list authority (required)
- When summarizing, triaging, or reporting the active project list for Forseti portfolio work, the authority is the live roadmap at `https://forseti.life/roadmap`.
- The backing source file for that roadmap is `dashboards/PROJECTS.md`.
- Treat the roadmap page + `dashboards/PROJECTS.md` as the single source of truth for:
	- active portfolio items,
	- project numbering (`PROJ-*`),
	- whether an item is a product line or delivery project.
- Do **not** rely on stale prompt text, ad hoc “products under management” lists, or session memory when they conflict with the roadmap registry.
- If you find a mismatch between operating instructions/context and the roadmap registry, reconcile `dashboards/PROJECTS.md` or escalate to the owning seat instead of repeating the stale list.

## Content autonomy (default: empowered)
All roles are empowered to create and edit **content** as they identify a need.

Definition: “content”
- Markdown/text documentation, specs, runbooks, lessons learned, checklists, release notes, and other non-code artifacts.

Rules:
- No PM approval is required for content creation/edits.
- Bias toward small, incremental edits that remove ambiguity and prevent repeat blockers.
- If you change content that affects another role’s workflow, incorporate the change into their **instructions** via the correct layer (seat/role/org/runbook) or escalate with a concrete patch proposal.

Collision prevention (still required):
- Do not have multiple seats editing the same file concurrently; supervisors still sequence changes.

Code boundary:
- This autonomy applies to content. For code/scripts, follow ownership/scope boundaries unless explicitly delegated.

## SDLC shipping gates (required)
- Releases follow `runbooks/shipping-gates.md`.
- Tester (QA) owns **Gate 2 — Verification** and is responsible for the QA validation step (test plan + verification report with APPROVE/BLOCK).

## Release scope cap (required)
- **Maximum 20 features per site per release.** 20 is a maximum cap, NOT a target. PM agents must ship as soon as auto-close conditions are met — do not hold a release open to fill remaining scope slots.
- Enforcement: `scripts/pm-scope-activate.sh` refuses activation when the active release for that site already has 20 or more features in scope (counted by `- Website: <site>` + `- Status: in_progress` in `features/*/feature.md`).
- **Auto-close triggers (either condition fires `release-close-now` to PM at ROI 999):**
  - ≥ **10 features** in_progress for that site, OR
  - ≥ **24 hours** elapsed since `tmp/release-cycle-active/<team>.started_at`
- During an active release, agents with inbox items tagged for the current release ID are **always given execution slots first**. Agents without release-tagged inbox items yield their slot to release-working agents. This is enforced by the orchestrator's `pick_agents` step.

## Release-cycle instruction refresh (required)
At the start of every release cycle (for any site/repo), each agent’s **first action** is to validate and refactor their own seat instructions file:
- File: `org-chart/agents/instructions/<your-seat>.instructions.md`

Definition of “refactor” here:
- Remove stale paths, assumptions, commands, URLs, and ownership statements.
- Add newly discovered constraints and the correct “how to verify” commands.
- Keep it short and operational: what you own, how you work, and how you escalate.

## Continuous instructions improvement during a release cycle (required)
During an active release cycle, any time you receive:
- feedback,
- a clarification request,
- a process improvement suggestion,

…you must incorporate it **during the same release cycle** when it is merited.

Merit test (simple): if the change would prevent a repeated blocker/confusion, reduce rework, or improve verification reliability, update your seat instructions.

Boundary rule:
- You may directly edit only your own seat instructions file.
- For org-wide/role/runbook/script changes outside your scope: write an instructions-change proposal (or a concrete patch suggestion) and escalate to the owning seat.

## Troubleshooting protocol (required)
When diagnosing a bug or unexpected behavior, follow this order:

1. **Trace the live logic first** — read the actual code path that produces the output. Do not theorize; follow the execution.
2. **Identify current state** — determine what the system is doing right now (what values are set, what is being output, what the UI/DB/log actually shows). Use direct observation: run the code, query the DB, read the file.
3. **Fix it** — once the current state is understood and the broken step is identified, fix it. Surgical and direct.

**What NOT to do:**
- Do not dig into git history, commit messages, or blame to understand "why." That is archaeology, not diagnosis.
- Do not theorize about how a bug was introduced. Trace current behavior → find the broken step → fix it.
- Do not over-explain the root cause. Document what was broken and what was changed.

## Blocker research protocol (required)
If you are blocked (or about to mark `Status: blocked` / `Status: needs-info`), you MUST do this first:

1) **Read the docs in the expected place**
- If you expected a runbook: check `runbooks/`
- If you expected product/feature scope: check `features/<feature>/feature.md`, `org-chart/sites/<site>/README.md`, `org-chart/sites/<site>/site.instructions.md`, and `org-chart/ownership/module-ownership.yaml`
- If you expected agent scope/process: check `org-chart/org-wide.instructions.md`, your role file in `org-chart/roles/`, and your seat file in `org-chart/agents/instructions/`
- If you expected prior decisions: check `sessions/<seat>/artifacts/` and `sessions/<seat>/outbox/`

2) **Broaden your search before escalating**
- Search `knowledgebase/` (lessons + proposals + reviews)
- Search `sessions/shared-context/` for copied docs/scripts
- Search the workspace for the exact keyword(s) (URL, env var name, route, feature name)

3) **If you still can’t find it: write it**
- If the missing documentation belongs in your owned scope, add it there.
- If it belongs to someone else’s owned scope, write a draft in your outbox (or a KB proposal) and escalate with:
	- the draft content location
	- where it should ultimately live
	- the minimal change needed

4) **Only then escalate**
- Your escalation must include what you already checked, where you searched, and what you drafted (if any).
- If the blocker is credentials/URLs, document the best-known defaults (e.g., production URL) and propose a safe fallback (staging/local) rather than stopping immediately.

## ROI discipline (required)
- Any inbox item you create for a subordinate (delegation) MUST include an ROI estimate and a 1-3 sentence rationale.
- Any escalation (blocked/needs-info) MUST include an ROI estimate for resolving the blocker and why.
- ROI scale: 1–infinity (higher = higher org value / urgency / leverage). Highest ROI takes precedence.
- Be reasonable: choose ROI values that are meaningful relative to your current queue (avoid inflating everything to extreme values).

## Inbox ROI ordering (required)
- Any inbox item folder you create MUST include a `roi.txt` file at the root of the item folder containing a single integer ROI (1–infinity).
- Executors and dashboards use `roi.txt` to prioritize which inbox item is processed next.
- If you create a follow-on item but cannot estimate ROI yet, set `roi.txt` to `1` and explicitly request triage/ROI clarification.

## Idle behavior (required)
If your inbox is empty:
- Default: do NOT create new inbox items “just to stay busy” (including for yourself).
- Default: write a brief outbox status (“inbox empty, awaiting dispatch”) and stop.

Directive (2026-02-22): **Idle request generation is restricted**
- Do NOT create new inbox items “just to stay busy” (including for yourself).
- Do NOT queue follow-up work items during idle cycles.
- If you have concrete recommendations, write them in your outbox and (if action is needed) escalate to your supervisor with `Status: needs-info` and an ROI estimate.
- Automation note: `scripts/idle-work-generator.sh` is disabled for all seats.

Directive (2026-04-06): **Improvement rounds are supervisor-dispatched only (IC roles)**
- IC roles (Dev, QA, BA, Sec-Analyst) must NOT self-initiate improvement rounds when idle.
- Improvement rounds for IC roles must arrive as an explicit inbox item dispatched by the role’s PM supervisor or the CEO.
- **PM and CEO seats** may still self-initiate triage/review passes as part of their supervisory function.
- If an IC seat’s inbox is empty, write “inbox empty, awaiting dispatch” in outbox and stop. Do not begin a review/refactor pass without an inbox item authorizing it.

## File scope + ownership (required)
- Each configured agent seat MUST have a clearly defined scope of owned files/paths.
- Per-seat scope is defined in: `org-chart/agents/instructions/<agent-id>.instructions.md`.
- If scope is unclear/missing:
	- First, take a best-guess based on `website_scope`, `module_ownership`, and role (bias toward the smallest safe scope).
	- Immediately escalate for clarification to your supervisor/CEO (include your guess and what files you would touch).
- If you identify a needed update outside your owned scope:
	- Do NOT directly modify the file.
	- Send a request to the owning agent (or the owning PM for module-level work) using `runbooks/passthrough-request.md` as the payload format.

## Recommend-anything rule (allowed)
- Any role may recommend improvements to any file/system when opportunities, issues, or escalation patterns are identified.
- Recommendations must be sent to the owning agent (include: file path(s), why, ROI, and a suggested minimal diff or concrete change).

## Instructions self-improvement (empowered)
- Every agent is empowered to update their own per-seat instructions file (`org-chart/agents/instructions/<agent-id>.instructions.md`) when they discover a needed improvement to their process flow.
- Supervisors do **not** own subordinate scope files; they ensure scope is clear by requesting updates from the owning agent and by sequencing work to prevent collisions.
- You may not unilaterally change org-wide policies or another agent’s scope by editing their files; request changes up-chain.

## Self-improvement dispatch policy (required)
- Improvement/self-improvement queue dispatch is a **post-release process review** function for PM + CEO seats.
- Do not dispatch generic improvement-round items across all agents as a daily standing action.
- PM + CEO post-release reviews must identify concrete process gaps and convert them into delegated follow-through work items for owning seats.

## Supervisor responsibility (collision prevention)
- The supervisor agent is responsible for ensuring subordinate scopes are clear and that multiple agents are not modifying the same file at the same time.
- Supervisors should coordinate by:
	- assigning disjoint file scopes per work item, or
	- sequencing changes (one agent at a time) when a shared file must change.

## Status template (required in outbox)
- `- Status: done | in_progress | blocked | needs-info`
- `- Summary: <one paragraph>`

## Process flow authority
- Each role’s file in `org-chart/roles/*.instructions.md` contains the authoritative process flow for how that role selects the next work item, how it escalates, and what it does when idle.

Then sections:
- `## Next actions`
- `## Blockers`

Then one of these sections (required when blocked/needs-info):
- `## Needs from Supervisor`
- `## Needs from CEO`
- `## Needs from Board`

Notes:
- Use **Needs from Supervisor** for normal up-chain escalation.
- Use **Needs from CEO** only when your supervisor is the CEO (or when policy requires CEO review).
- Use **Needs from Board** only for CEO-to-human escalations.

## Report blockers up-chain (required)
If `Status: blocked` or `Status: needs-info`, you must report it to your supervisor agent.
Automation will route an escalation inbox item to the supervisor based on org chart rules.

## Escalation quality (required)
Any `blocked` / `needs-info` escalation MUST include:
- Matrix issue type: exact row label from `org-chart/DECISION_OWNERSHIP_MATRIX.md`
- Product context: `website`, `module`, `role`, and the specific `feature/work item`.
- A clear **Decision needed**: what the supervisor/CEO must decide.
- A **Recommendation**: what you think we should do, and why (including tradeoffs).
- Enough context to avoid ambiguity (links, file paths, commands, acceptance criteria).

Supervisors must ensure escalations do not conflict with other agent objectives before elevating further.

## Escalation aging (required)
If you have **3 escalations in a row** (blocked/needs-info) without being unblocked, the system will escalate to your supervisor's supervisor (your superior).

## Multi-Repository Architecture (Broken-Out Repos)

**Effective Date:** April 21, 2026

### Architecture Overview

Forseti.life operates a **hybrid repository model**:

**Private Monorepo** (Operational Deployment)
- Location: `/home/ubuntu/forseti.life/` on production server
- Role: Deployment source, production orchestrator, operational tooling
- Audience: Internal team only
- Remotes:
  - `origin`: https://github.com/keithaumiller/forseti.life (personal account)
  - `community`: https://github.com/Forseti-Life/forseti.life (organization mirror)

**Public Repositories** (11 repos in Forseti-Life organization)
- Organization: https://github.com/Forseti-Life
- Role: Open-source community, reusable libraries, external contributions
- Audience: Public (contributors, integrators, users)
- Structure (4 tiers):
  - **Tier 1:** Core Products (forseti-job-hunter, dungeoncrawler-pf2e)
  - **Tier 2:** Developer Libraries (forseti-shared-modules, forseti-mobile, forseti-meshd, h3-geolocation)
  - **Tier 3:** Operations & Tooling (copilot-hq, forseti-devops, forseti-docs)
  - **Tier 4:** Content & Reference (dungeoncrawler-content, forseti-platform-specs)

### Developer Responsibilities

**Private Monorepo (All Roles)**
- Primary development location for internal team
- Release cycles execute via orchestrator
- Push to `origin` (primary) or `community` (organization mirror)
- Both remotes require valid GitHub PAT: `/home/ubuntu/github.token`

**Public Repos (PM/Dev/QA/BA/Security)**
- Contribute to community PRs, accept external contributions
- Backport hotfixes from monorepo when necessary
- Each repo has independent CI/CD, testing, and release process
- Each repo has its own CONTRIBUTING.md, CODE_OF_CONDUCT.md, SECURITY.md

### Git Remotes & Authentication

**Token Management:**
- Centralized at: `/home/ubuntu/github.token`
- Exported by crontab: `GH_TOKEN` environment variable
- Scopes required: `repo`, `workflow`, `public_repo`, `read:org`, `write:org`
- Valid for: Both keithaumiller personal account + Forseti-Life organization

**Git Configuration (No Embedded Tokens):**
```bash
origin    https://github.com/keithaumiller/forseti.life.git
community https://github.com/Forseti-Life/forseti.life.git
```
Both use credential helper (`store`) + centralized `GH_TOKEN`.

### Release & Deployment (Operational Notes)

**Current State (Coordinated Push Logic):**
- Orchestrator runs coordinated push when all PMs signed off
- Push logic: triggers GitHub Actions `deploy.yml` in personal account (`keithaumiller/forseti.life`)
- Local signoff files (`tmp/auto-push-dispatched/`) track deployment state

**Future Enhancement (Multi-Repo Sync):**
- TBD: Sync public repos with changes from monorepo after release
- TBD: Coordinate CI/CD workflows across Tier 1-3 public repos
- See: `MULTIREPOSITORY_DEVELOPER_GUIDE.md` for integration model

### Validation & Health Checks

**Validate Multi-Repo Access:**
```bash
bash copilot-hq/scripts/multirepository-validator.sh
bash copilot-hq/scripts/multirepository-validator.sh --full
```

**CEO Release Health (includes multi-repo checks):**
```bash
bash copilot-hq/scripts/ceo-release-health.sh
```

### Documentation Authority

For multi-repo guidance, refer to (in order):
1. `MULTIREPOSITORY_DEVELOPER_GUIDE.md` (developer workflows)
2. `README.md` "Multi-Repository Architecture" section
3. `GITHUB_ORGANIZATION_REFERENCE.md` (repo inventory)

For release cycle policy, refer to:
1. `runbooks/shipping-gates.md` (gates R1-R5)
2. `orchestrator/release_cycle.py` (state machine logic)
3. `scripts/post-coordinated-push.sh` (post-push automation)
