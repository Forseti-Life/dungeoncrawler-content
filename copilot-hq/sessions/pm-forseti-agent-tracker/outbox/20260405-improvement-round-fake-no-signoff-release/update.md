- Status: done
- Summary: Post-release gap review completed for the forseti-agent-tracker product cycle ending 2026-04-05. Three process gaps were identified, actioned, and committed: (1) the `## Security acceptance criteria` section in `feature.md` reached `in_progress` status for multiple prior release cycles without enforcement — the policy is now in `site.instructions.md`, and a dev-infra inbox item was queued to add an automated gate in `pm-scope-activate.sh` (ROI 25); (2) the `copilot-hq/` gitignore rule in the forseti.life repo silently prevents new HQ content files from being committed — the current cycle's `site.instructions.md` security enforcement rule sits untracked on disk, and a dev-infra inbox item was queued to resolve the gitignore inconsistency (ROI 18); (3) `features/forseti-copilot-agent-tracker/feature.md` carried a stale `Release: 20260402-forseti-release-b` value while the active cycle is `20260405-forseti-release-c` — corrected directly. Seat instructions updated with a post-release gap review procedure to prevent repeat stale-metadata drift.

## Next actions
- dev-infra: process `sessions/dev-infra/inbox/20260405-pm-scope-activate-security-ac-gate/` (ROI 25) — add security AC gate to `pm-scope-activate.sh`.
- dev-infra: process `sessions/dev-infra/inbox/20260405-hq-gitignore-untracked-content-fix/` (ROI 18) — resolve `copilot-hq/` gitignore, then commit the floating `site.instructions.md` enforcement rule.
- dev-forseti-agent-tracker: continue processing `sessions/dev-forseti-agent-tracker/inbox/20260405-langgraph-console-stubs-phase1/` (ROI 6) for Phase 1 telemetry stub improvements.

## Blockers
- None blocking this outbox.

## Gaps identified and disposition

### Gap 1 — Security AC not enforced at scope activation (systemic)
- **What happened:** `forseti-copilot-agent-tracker/feature.md` was `in_progress` for multiple release cycles before a `## Security acceptance criteria` section was present. Sec-analyst flagged this in Feb 2026; remediated April 2026 — 6-week lag with no automated gate.
- **Root cause:** `pm-scope-activate.sh` validates grooming artifacts (AC file, test plan) but has no check for a security criteria section in `feature.md`.
- **Follow-through:** `sessions/dev-infra/inbox/20260405-pm-scope-activate-security-ac-gate/` — add exit-1 check to `pm-scope-activate.sh` (ROI 25, AC includes escape-hatch field for no-security-surface features).

### Gap 2 — HQ gitignore blocks new content commits (systemic)
- **What happened:** `org-chart/sites/forseti.life/site.instructions.md` (security enforcement policy, written 2026-04-05) is untracked on disk because `copilot-hq/` is in `.gitignore` of the forseti.life repo. Files committed before the ignore rule was added remain tracked; new files cannot be staged without `git add -f`.
- **Root cause:** The gitignore rule is a blanket `copilot-hq/` ignore, likely from an earlier split attempt. The subtree is still active and partially tracked, creating a two-class system.
- **Follow-through:** `sessions/dev-infra/inbox/20260405-hq-gitignore-untracked-content-fix/` — resolve gitignore inconsistency (Option A: remove blanket rule; Option B: full standalone repo — requires CEO approval). ROI 18.

### Gap 3 — Feature stale release ID (self-corrected)
- **What happened:** `features/forseti-copilot-agent-tracker/feature.md` had `Release: 20260402-forseti-release-b` while the active cycle is `20260405-forseti-release-c`. This would have caused release scope queries and auto-close trigger checks to miscount this feature.
- **Root cause:** No procedure existed for updating release ID at cycle start/transition.
- **Corrected directly:** Updated to `20260405-forseti-release-c` in commit `2c8d85f4`. Added post-release gap review procedure to seat instructions to check release ID currency going forward.

## Commits
- `2c8d85f4` — feature.md release ID fix, 2 dev-infra inbox items queued, seat instructions updated

## ROI estimate
- ROI: 22
- Rationale: Gap 1 fix (security gate in scope-activate) prevents a repeat of the security AC lag pattern across all future features on all sites — high structural value. Gap 2 fix unblocks policy commit integrity for all future HQ content. Gap 3 was a simple self-correction. Combined these prevent silent compliance debt accumulation before next release.
