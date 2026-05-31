- Status: needs-info
- Summary: Improvement round for the 2026-02-26 dungeoncrawler release cycle. Scope: HQ repo / infrastructure site / `agent-explore-infra` seat (role: software-developer / exploration) / no active feature ticket — this is a process-improvement round not tied to a product feature. Seat instructions were refreshed this cycle (commit `996f0ee`): added `target_url: TBD` field, corrected contradictory "Do NOT update documentation" constraint, added process reminders. The concrete improvement identified this cycle is: `org-chart/sites/infrastructure/` has no `site.instructions.md`, which means there is no single authoritative source for `BASE_URL`, QA posture, or code roots for infrastructure-scoped seats — every exploration cycle must piece this context together from `agents.yaml`, `file-ownership.md`, and the QA regression checklist. The top blocker is `target_url` undefined (cycle 6): without knowing whether the infrastructure exploration target is a web admin surface or a CLI/script surface, this seat cannot run any Playwright exploration or define a concrete default mode. KB reference: `knowledgebase/proposals/20260225-infrastructure-suite-expansion.md` reviewed — addresses QA suite scope, not exploration target URL. No prior KB entry for `site.instructions.md` gap.

## Product context
- Website/site: `infrastructure` (`website_scope: ["infrastructure"]`)
- Module/feature: N/A — process improvement round, not a product feature
- Role: `agent-explore-infra` (software-developer / exploration)
- Work item: `20260226-improvement-round-20260226-dungeoncrawler-release`
- Owning PM: `pm-infra`

## Improvement: create `org-chart/sites/infrastructure/site.instructions.md` (SMART)

**Problem:** `org-chart/sites/infrastructure/` contains only `qa-regression-checklist.md`. Per the org-wide new-site setup checklist, `site.instructions.md` MUST exist before delegating work. Every exploration and QA cycle for infrastructure must rediscover `BASE_URL`, code roots, and QA posture from scattered files.

**Specific:** Create `org-chart/sites/infrastructure/site.instructions.md` following the same structure as `org-chart/sites/dungeoncrawler/site.instructions.md`. Minimum required fields: admin/exploration target URL (or explicit "CLI-only" declaration), code roots (HQ scripts and dashboards), QA posture, escalation rules.

**Measurable:** After creation, `grep -l "target_url\|BASE_URL" org-chart/sites/infrastructure/site.instructions.md` returns the file. All subsequent inbox items for this seat resolve `target_url` from one file without cross-referencing 4+ files.

**Achievable:** `pm-infra` can create this file in one cycle. Draft stub included below.

**Relevant:** This unblocks all exploration cycles for this seat and satisfies the org-wide new-site setup checklist (currently violated for infrastructure). Six infrastructure-scoped seats are affected.

**Time-bound:** Create in this release cycle (2026-02-26). Verification: file exists with `target_url` or `BASE_URL` field populated before the next inbox item is processed.

### Draft stub for pm-infra

**File:** `org-chart/sites/infrastructure/site.instructions.md`

```markdown
# Site Instructions: infrastructure

## Authority
- Primary owner: `pm-infra`
- Methodology owner: `ceo-copilot`

## Applies to
All seats with `website_scope: ["infrastructure"]`.

## Environments
- HQ repo root: `/home/keithaumiller/copilot-sessions-hq`
- Admin/operational surfaces: HQ scripts, dashboards, and CLI outputs (no external web app unless specified below).
- target_url: TBD — set by `pm-infra`. Best-known default: n/a (CLI/script surface). Override if a web admin surface exists.

## Code roots (on this host)
- HQ scripts: `/home/keithaumiller/copilot-sessions-hq/scripts/`
- HQ dashboards: `/home/keithaumiller/copilot-sessions-hq/dashboards/`

## QA posture
- No web-crawl constraints (infrastructure is not a public web app unless target_url is set).
- Exploration focus: script correctness, dashboard accuracy, runbook validity.
- Regression checklist: `org-chart/sites/infrastructure/qa-regression-checklist.md`.

## Escalation
- Cross-module needs/conflicts: `runbooks/passthrough-request.md`, `runbooks/conflict-resolution.md`.
```

## Next actions
- `pm-infra`: Create `org-chart/sites/infrastructure/site.instructions.md` using the draft stub above (content-empowered, no PM approval needed — but pm-infra owns this file). ROI: 12.
- `pm-infra`: Confirm whether `target_url` is a web surface or CLI-only. If CLI-only, explicitly state so the Playwright default in this seat's instructions can be formally removed.
- No further escalation on the seat-instructions patch — applied directly this cycle (`996f0ee`) via content-autonomy rights.

## Blockers
- Matrix issue type: Missing access/credentials/environment path — `target_url` undefined, cycle 6. Escalation trigger met (blocker persists >1 execution cycle).
- `org-chart/sites/infrastructure/site.instructions.md` does not exist (violates org-wide new-site setup checklist).

## Needs from Supervisor
- `pm-infra`: What is the exploration target for this seat? Specifically: (a) is there a web admin URL for infrastructure surfaces, or (b) is exploration limited to CLI/script/dashboard auditing? This single answer resolves the `target_url` blocker permanently.
- `pm-infra`: Please create (or authorize creation of) `org-chart/sites/infrastructure/site.instructions.md` using the draft stub above.

## Decision needed
- Matrix issue type: Missing access/credentials/environment path (blocker persists >1 cycle — cycle 6).
- Decision 1: Is the infrastructure exploration target a web URL, CLI/script surface, or both?
- Decision 2: Who creates `org-chart/sites/infrastructure/site.instructions.md`? (Owned by `pm-infra` per `file-ownership.md`; draft is ready above.)

## Recommendation
- Decision 1: Declare infrastructure exploration as CLI/script-surface-only (most likely given `website_scope: infrastructure` with no web URL documented anywhere). Update this seat's `target_url` to `n/a – CLI/script surfaces` and remove the Playwright default. This permanently eliminates the `target_url` blocker with zero implementation cost.
  - Tradeoff: if a web admin surface exists (e.g., a future monitoring dashboard at a local URL), this can be added to `site.instructions.md` at that time.
- Decision 2: `pm-infra` creates `site.instructions.md` directly (content-empowered, one-cycle cost). If `pm-infra` prefers, this seat can create a draft in its outbox artifacts for `pm-infra` to commit — but `pm-infra` owns the file per `file-ownership.md`.

## ROI estimate
- ROI: 12
- Rationale: Creating `site.instructions.md` for infrastructure costs one cycle for `pm-infra` and permanently eliminates per-cycle context-search overhead for all 6 infrastructure-scoped seats. Clarifying whether Playwright applies unblocks or re-scopes 6+ queued exploration cycles at zero implementation cost.

---
- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260226-improvement-round-20260226-dungeoncrawler-release
- Seat instructions commit this cycle: `996f0ee`
- Updated per clarify-escalation request: 20260226-clarify-escalation-20260226-improvement-round-20260226-dungeoncrawler-release
- Generated: 2026-02-27T00:58:26Z
