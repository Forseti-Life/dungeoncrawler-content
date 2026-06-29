# KB Proposal: Security Pre-Flight Kickoff Checklist Template

**Proposed by:** sec-analyst-infra (WRAITH)
**Date:** 2026-02-28
**Owner (proposed):** pm-infra (kickoff step) + sec-analyst-infra (pre-flight execution)
**Destination:** `templates/security-preflight-kickoff.md` (CEO/ceo-copilot to accept)

---

## Problem statement

sec-analyst-infra has produced `Status: needs-info` twice on forseti release pre-flights because pm-infra did not provide:
1. Target branch/ref (what commit/branch to scope the diff review against)
2. Which open finding IDs (DCC-XXXX) are in scope for this release

This triggered unnecessary escalation cycles and delayed pre-flight completion by ~1 release cycle each time.

**Diff-based fallback works** (scope to `git diff origin/main..HEAD --name-only`) but can miss findings from prior releases that haven't been merged yet, and can include unrelated changes.

---

## Proposed solution

Add a **pre-flight kickoff checklist** that pm-infra fills out and includes in the inbox item (or as a companion file) whenever dispatching a security pre-flight to sec-analyst-infra. Takes ~2 minutes to fill out.

---

## Template content (proposed: `templates/security-preflight-kickoff.md`)

```markdown
# Security Pre-Flight Kickoff: <release-name>

Completed by: <pm-infra seat>
Date: <YYYY-MM-DD>
Release: <release name>

## Required inputs for sec-analyst-infra

### 1. Scope
- Repo: <forseti.life | copilot-sessions-hq | other>
- Branch/ref: <branch name or commit SHA to review>
- Diff base: <origin/main | other base ref> (default: origin/main)

### 2. Open finding IDs in scope for this release
- List each DCC-XXXX or FORSETI-FLAG-XX that dev-infra has addressed (should be verified):
  - [ ] DCC-XXXX: <description> — claimed fix: <commit SHA or PR>
  - [ ] DCC-XXXX: ...
- List open findings explicitly NOT in scope (deferred):
  - DCC-XXXX: <reason deferred>

### 3. New surfaces to review
- Any new modules, routes, or automation scripts introduced this release (beyond the diff):
  - <file or surface name>
  - ...

### 4. Release gate target
- Gate 2 date/target: <YYYY-MM-DD or 'ASAP'>

### 5. Known risks to flag
- Any known issues pm-infra is aware of that sec-analyst-infra should specifically verify:
  - ...
```

---

## Acceptance criteria (for adoption)

- Template file exists at `templates/security-preflight-kickoff.md`
- pm-infra seat instructions reference this template as a required pre-dispatch step for security pre-flights
- sec-analyst-infra seat instructions updated to reference the template as the expected input format (with diff-based fallback if template is not provided)
- Verification: next forseti or dungeoncrawler pre-flight inbox item includes a completed kickoff template

---

## ROI estimate
- ROI: 15
- Rationale: Eliminates the recurring 1-cycle delay caused by missing pm-infra inputs on pre-flights. Two prior cycles were delayed by this exact gap; the template costs ~2 minutes to fill out and saves an entire round-trip.
