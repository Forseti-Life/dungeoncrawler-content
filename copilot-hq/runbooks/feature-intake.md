# Feature Intake Runbook

**Owner:** `pm-forseti` (and `pm-dungeoncrawler` for their site)  
**Trigger:** Start of every release cycle — Stage 0 (before scope freeze)  
**Scripts:** `scripts/suggestion-intake.sh`, `scripts/suggestion-triage.sh`

---

## Overview

Users interact with Forseti (the AI assistant) via the "Talk to Forseti" channel. When the AI
detects a feature suggestion in the conversation, it automatically creates a `community_suggestion`
Drupal node (status: `new`). These nodes are the raw upstream of the product backlog.

**Pipeline:**

```
User message
    ↓
Talk to Forseti (ai_conversation node)
    ↓  [AI detects [CREATE_SUGGESTION] tag]
community_suggestion node (status: new)
    ↓  [suggestion-intake.sh at cycle start]
PM inbox batch item  →  triage/NID-<n>-triage.md
    ↓  [PM reviews each one]
suggestion-triage.sh accept → features/<feature-id>/feature.md (status: planned)
suggestion-triage.sh defer  → Drupal node: deferred  (queued next cycle)
suggestion-triage.sh decline→ Drupal node: declined  (archived)
suggestion-triage.sh escalate → board security review queue (human review required)
    ↓  [accepted features]
Gap analysis  →  feature.md ## Gap Analysis + Feature type set
    ↓
Acceptance Criteria  →  01-acceptance-criteria.md (criteria tagged [NEW]/[EXTEND]/[TEST-ONLY])
    ↓
QA handoff  →  pm-qa-handoff.sh  →  03-test-plan.md
    ↓  [feature is groomed]
Release scope selection  →  01-change-list.md
    ↓
Normal dev/QA/ship cycle
```

---

## Step 1 — Run intake at cycle start

At the **start of every release cycle** (Stage 0, before scope freeze), the PM runs:

```bash
./scripts/suggestion-intake.sh forseti
```

This will:
- Query Drupal for all `community_suggestion` nodes with `field_suggestion_status = new`
- Write a batch inbox item to `sessions/pm-forseti/inbox/<date>-suggestion-intake/`
- Mark queried nodes as `under_review` in Drupal
- Print a summary of how many suggestions were found

If there are no new suggestions, it exits cleanly — nothing to do.

---

## Step 2 — Triage each suggestion

Open the batch inbox item README:

```
sessions/pm-forseti/inbox/<date>-suggestion-intake/README.md
```

For each suggestion in `triage/NID-<n>-triage.md`, make one of four decisions:

| Decision | Meaning | Drupal status set |
|----------|---------|-------------------|
| `accept` | Include in backlog, create feature brief | `in_progress` |
| `defer`  | Good idea, wrong timing — next cycle | `deferred` |
| `decline`| Not aligned with mission or product direction | `declined` |
| `escalate` | Security/integrity/stability risk needs human board review | `under_review` |

**Security gate is mandatory.** If a suggestion could introduce security abuse, release-integrity bypass,
or crash/data-destruction risk, PM must choose `escalate` (not `accept`).

Use the triage template at `templates/suggestion-triage.md` to document your rationale.

**Mission alignment is required for every accept decision.** If you cannot articulate how a feature
advances "Democratize and decentralize internet services by building community-managed versions of
core systems for scientific, technology-focused, and tolerant people" — defer or decline it.

---

## Step 3 — Record the decision

```bash
# Accept: creates features/<feature-id>/feature.md automatically
./scripts/suggestion-triage.sh forseti <nid> accept <feature-id>

# Defer: queued for next cycle
./scripts/suggestion-triage.sh forseti <nid> defer

# Decline: archived, won't resurface
./scripts/suggestion-triage.sh forseti <nid> decline

# Escalate: board-security review required before any acceptance
./scripts/suggestion-triage.sh forseti <nid> escalate
```

Naming convention for feature IDs: `forseti-<short-kebab-description>`  
Examples: `forseti-safety-content-search`, `forseti-job-hunter-alerts`, `forseti-community-voting`

---

## Step 4 — Gap analysis (required before writing AC)

**This step is mandatory.** Do not write AC or hand off to QA until it is complete.

For every requirement in the feature, audit the existing codebase and fill in the `## Gap Analysis` table in `feature.md`. Determine for each requirement whether coverage is **Full**, **Partial**, or **None**, then set the `Feature type:` header field accordingly:

| Finding | Feature type |
|---|---|
| Majority Full coverage | `needs-testing` |
| Majority Partial coverage | `enhancement` |
| Majority None | `new-feature` |

Also record the exact test file path QA should create or extend for each requirement. QA must not guess at locations.

---

## Step 5 — Fill in accepted feature briefs

For each accepted feature, open `features/<feature-id>/feature.md` and complete:

1. **Module assignment** — which Drupal module owns this?
   Check `org-chart/ownership/module-ownership.yaml`
2. **Feature type** — set from gap analysis (`new-feature` / `enhancement` / `needs-testing`)
3. **Acceptance Criteria** — use `templates/01-acceptance-criteria.md`; tag every criterion `[NEW]`, `[EXTEND]`, or `[TEST-ONLY]` per gap analysis
4. **Risk assessment** — use `templates/06-risk-assessment.md`
5. **Priority** — P0 (release blocker), P1 (core value), P2 (nice to have)

---

## Step 5 — Feed into release scope

Once features are triaged and briefs are complete, select which ones enter this release cycle:

1. Add to the release candidate change list: `sessions/pm-forseti/artifacts/release-candidates/<release-id>/01-change-list.md`
2. Scope freeze: no new features added after this point
3. Proceed with the normal release cycle per `runbooks/release-cycle-process-flow.md`

---

## Recurring cadence

| When | Action |
|------|--------|
| **Stage 3 of current release (start)** | Run `suggestion-intake.sh`, triage all `new` items, **run gap analysis**, write AC (tagged), run `pm-qa-handoff.sh` — grooming for NEXT release |
| **Stage 0 of next release** | Pick from groomed pool only — all grooming already done, scope selection is instant |
| **During current release execution** | New suggestions accumulate as `new` in Drupal — do NOT pull until Stage 3 starts |
| **Deferred items** | Remain `deferred` in Drupal; PM resets to `new` manually when ready to re-evaluate |

> **The rule:** grooming for the next release runs during Stage 3 of the current release, in parallel
> with Dev execution. It never holds up the current release. Stage 0 only touches groomed items.

---

## Viewing suggestions in Drupal (manual)

Admins can view all community suggestions at:
- `/admin/content?type=community_suggestion&status=1` (all)
- Filter by `field_suggestion_status` = `new` for unprocessed items

---

## Automation hook (CEO/release monitor)

The release monitor (`scripts/release-kpi-monitor.py`) can be extended to call
`suggestion-intake.sh` automatically when a new release cycle starts (Stage 0 trigger).

To enable, add to the monitor's Stage 0 handler:
```python
subprocess.run(["bash", "scripts/suggestion-intake.sh", site], check=False)
```

---

## Related files

| File | Purpose |
|------|---------|
| `scripts/suggestion-intake.sh` | Pull new suggestions → PM inbox |
| `scripts/suggestion-triage.sh` | Record accept/defer/decline decision |
| `templates/suggestion-triage.md` | Triage decision template |
| `templates/feature-brief.md` | Feature brief template |
| `templates/01-acceptance-criteria.md` | AC template |
| `runbooks/release-cycle-process-flow.md` | Full release cycle (this feeds into Stage 0) |
| `org-chart/sites/forseti.life/site.instructions.md` | Mission statement (use for alignment check) |
