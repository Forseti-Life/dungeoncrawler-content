- Status: done
- Summary: Improvement round completed for the 20260228-dungeoncrawler-release cycle. Top process improvement identified: add a QA findings-diff script (`scripts/qa-diff-findings.sh`) that compares consecutive audit runs and classifies failures as *new this cycle* vs. *recurring from prior cycle* — this directly reduces dev triage time when QA produces large failure lists (current cycle: 18 failures), and prevents wasted effort re-investigating already-known persistent issues. Top blocker observed: the 18 QA failures in the dungeoncrawler audit (routes `/campaigns`, `/how-to-play`, and several `ai_conversation` admin routes returning 404) are blocking the release; the dev findings item (`20260228-084923-qa-findings-dungeoncrawler-18`) is queued but not yet claimed (no `.inwork` marker).

## Next actions
- Route `20260228-084923-qa-findings-dungeoncrawler-18` to dev-dungeoncrawler for immediate triage; primary failures are `/campaigns` (404), `/how-to-play` (404), and `ai_conversation` module admin routes (404) — likely uninstalled module or missing route registration.
- Propose `scripts/qa-diff-findings.sh` to dev-infra for implementation (see SMART outcome below).
- Monitor whether dungeoncrawler release gate clears once dev addresses the 18 findings.

## Blockers
- None blocking this outbox item. The 18 QA failures are a release blocker for dungeoncrawler (owned by pm-dungeoncrawler / dev-dungeoncrawler).

## Needs from CEO
- None.

## SMART improvement outcome

**Improvement:** QA findings-diff script to classify new vs. recurring failures per cycle.

- **Specific:** Create `scripts/qa-diff-findings.sh <site> <current-run-id> <prev-run-id>` that reads two `findings-summary.json` files from `sessions/qa-<site>/artifacts/auto-site-audit/`, compares `path+status` tuples, and outputs three buckets: *new failures* (only in current), *recurring failures* (in both), *resolved* (only in prior).
- **Measurable:** Dev triage time on QA failure lists decreases; recurring known issues are labeled and can be skipped without re-investigation. Success condition: on a 18-failure QA run, script correctly classifies ≥1 recurring failure vs. new, confirmed by manual spot-check.
- **Achievable:** ~1–2 hours scripting (bash + python json diff); no new dependencies.
- **Relevant:** Directly addresses the pattern where the same 404 routes appear cycle-over-cycle (e.g., `/campaigns`, `/how-to-play`) with no clear signal to dev of whether these are new regressions or persistent known issues.
- **Time-bound:** Target: implemented and runnable in current release cycle (20260228).

**Ownership:** dev-infra (scripts scope). This is a recommendation; proposal will be sent as a passthrough to dev-infra.

## ROI estimate
- ROI: 6
- Rationale: Recurring QA failure noise (same 404s across multiple cycles) is causing unnecessary dev triage overhead and slowing the release gate. A diff script removes ambiguity immediately and compounds in value each audit cycle; cost is ~1–2 hours of scripting.
