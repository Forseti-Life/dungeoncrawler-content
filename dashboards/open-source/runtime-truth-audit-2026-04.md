# Runtime Truth Audit — 2026-04

- Project: `PROJ-009`
- Owner: `architect-copilot`
- Status: `done`
- Last updated: `2026-04-15`

## Summary

The current open-source readiness state is **still blocked** and the live monorepo documentation had drifted away from that reality.

This audit records the verified current state so the project can keep moving from accurate evidence instead of stale public-facing copy.

## Verified current state

### 1. Public release is still blocked

The authoritative open-source planning and gate artifacts still show a **no-go** state:

1. `dashboards/open-source/public-readiness-status-2026-04.md`
   - project status = `blocked`
   - blockers = security cleanup, history hygiene, publication-candidate packaging, validation evidence
2. `sessions/pm-open-source/artifacts/20260414-proj-009-publication-candidate-gate-drupal-ai-conversation.md`
   - PM gate decision = **NO-GO** for public freeze / public push
3. `sessions/dev-open-source/outbox/20260414-phase1-security-audit-forseti-open-source.md`
   - current-tree key files still exist
   - secret-bearing history still exists
   - raw-history publication is unsafe
4. `org-chart/sites/open-source/qa-regression-checklist.md`
   - Phase 1 open-source security audit = `FAIL / NO-GO`
   - first-candidate sanitization refresh = `BLOCK / NO-GO`

### 2. The monorepo root README had inaccurate open-source status

Verified drift in `/home/ubuntu/forseti.life/README.md` before correction:

1. It stated:
   - `## Open Source Status`
   - `This repository is fully open source.`
2. That statement conflicts with the authoritative readiness artifacts above.
3. It also used `./scripts/...` quickstart commands that do not exist at the monorepo root.

### 3. Quickstart and helper-script paths were inaccurate

Verified current tree:

1. Root `QUICKSTART.md` is **missing**
2. `copilot-hq/QUICKSTART.md` is **present**
3. The monorepo helper scripts are under `script/`, not `scripts/`
   - `script/quick-start.sh`
   - `script/complete-setup.sh`
   - `script/verify-setup.sh`

### 4. The publication model remains curated extracts, not public monorepo exposure

This remains consistent across the current project docs:

1. Use curated mirrors / extracted repos
2. Do **not** flip the live operational monorepo public
3. Keep `sessions/**`, `inbox/responses/**`, `tmp/**`, `prod-config/**`, `database-exports/**`, and key material private by default

## Practical interpretation

The repo currently has three different truths that need to be kept separate:

1. **Operational monorepo reality** — private, live, not publication-clean
2. **Readiness/project reality** — in progress, blocked on security/history/freeze evidence
3. **Future public-repo reality** — curated extracted repos under `github.com/Forseti-Life/`

The project is healthy only if those three states are described accurately and never collapsed into "the monorepo is already public."

## Corrections made in this slice

1. Reconciled `/home/ubuntu/forseti.life/README.md` to reflect the blocked publication state
2. Corrected the monorepo helper-script paths from `./scripts/...` to `./script/...`
3. Pointed readers to the authoritative open-source readiness docs in `copilot-hq/`

## Remaining blockers after this doc correction

This audit does **not** clear any publication gate by itself. The active blockers remain:

1. external rotation confirmation for previously exposed AWS credentials
2. full git-history scrub / sensitive-data cleanup
3. first-candidate freeze as a curated sanitized extract
4. fresh validation evidence against that frozen candidate

## Recommended next action

Treat the next execution slice as a **candidate-freeze preparation pass** against the already-selected first candidate:

1. keep the current no-go state explicit in repo-facing docs
2. finish the remaining `drupal-ai-conversation` sanitization work
3. confirm AWS rotation externally
4. freeze the curated candidate packet and rerun the gate
