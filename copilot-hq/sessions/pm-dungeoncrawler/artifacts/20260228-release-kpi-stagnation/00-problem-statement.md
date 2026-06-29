# Problem Statement (PM-owned)
# Topic: release-kpi-stagnation — dungeoncrawler
# Date: 2026-02-28

## Context
- **What is the stagnation?**
  The dungeoncrawler product has shipped 0 feature-level functionality to production across multiple release cycles. Every release cycle has been maintenance-only (module enable/config fixes, QA tooling corrections, BA stub generation). No feature has ever completed the full AC → test-plan → dev-impl → QA-APPROVE → ship pipeline.

- **What is the current state?**
  - 11 features in `planned` or `in_progress` status, all awaiting QA test-plan generation (0 of 11 have `03-test-plan.md`)
  - 6 new pre-triage stubs just added by BA (latest CR4 lines 5284-5583), not yet triaged
  - 2 open QA violations on `20260228-dungeoncrawler-release` (admin/genai-debug routes returning 500/404 to administrator role)
  - QA testgen queue: 9 items in `sessions/qa-dungeoncrawler/inbox/` (waiting on QA agent to process)
  - No Dev feature implementation work has started; Dev's only items have been maintenance (module enable, drush path fixes)

- **Why now?**
  The scoreboard baseline was set 2026-02-27. The KPI "Time-to-verify" and "Post-merge feature regressions" are still N/A — there is no signal because no feature has reached the verify stage. The pipeline is filling up (backlog groomed, AC written, QA handoff done) but has not yet started converting groomed items to shipped features. This inbox item is a health-check and planning checkpoint.

## Goals (Outcomes)
- Unblock the first feature through the full pipeline (impl → QA APPROVE → ship) in the next release cycle (`20260228-dungeoncrawler-release-next`)
- Close the 2 open QA violations in `20260228-dungeoncrawler-release` so Gate 2 reaches 0 violations
- Complete QA test-plan generation for 9 handed-off features (dependency: qa-dungeoncrawler processing its inbox)

## Non-Goals (Explicitly out of scope)
- Adding new features to `20260228-dungeoncrawler-release` (scope is frozen; maintenance fixes only)
- Shipping to production before QA Gate 2 is clean (dungeoncrawler follows coordinated release gate)
- Building features not yet groomed (22 deferred + 6 pre-triage not eligible for current next-release scope)

## Users / Personas
- **Game players (authenticated users):** waiting on character creation, encounter, and skills gameplay to become available
- **GM users:** waiting on encounter rules and GM tools
- **Dev-dungeoncrawler:** needs clear next implementation target with AC + test-plan in hand
- **QA-dungeoncrawler:** has 9 testgen inbox items pending; needs to process them to unblock dev

## Constraints
- Security: no auth/data exposure concerns in scope (all core game mechanics, no PII)
- Performance: dungeoncrawler is a game; latency requirements are relaxed relative to Forseti
- Backward compatibility: no existing user-facing features to protect; all data is dev/test only
- Coordinated release: dungeoncrawler push requires both pm-dungeoncrawler and pm-forseti signoffs

## Success Metrics
- **Gate 2 clean:** 0 QA violations in dungeoncrawler audit by end of `20260228-dungeoncrawler-release`
- **QA testgen complete:** all 9 in-progress features have `03-test-plan.md` (qa-dungeoncrawler processes inbox)
- **First feature shipped:** at least 1 dc-cr-* feature reaches `status: shipped` in `20260228-dungeoncrawler-release-next`
- **Pipeline velocity:** >0 features in dev-impl phase within 1 release cycle of grooming completion

## Dependencies
- `qa-dungeoncrawler` must process 9 testgen inbox items → produce `03-test-plan.md` for each feature
- `dev-dungeoncrawler` must fix 2 open QA violations (genai-debug 500/404 to admin) in current release
- `pm-forseti` release readiness (for coordinated push) — separate gate, not blocking dungeoncrawler dev work

## Risks
- **Testgen bottleneck:** If QA does not process the 9 testgen items before next Stage 0, the groomed pool will not be Stage-0-eligible and the pipeline will stall for another cycle
- **Scope creep:** BA is continuously generating new stubs (6 just added); without clear triage sequencing, pre-triage backlog grows faster than the pipeline can absorb
- **Dev cold start:** Dev-dungeoncrawler has only done maintenance work; first feature implementation will surface tooling/environment issues not yet encountered

## Knowledgebase check
- `knowledgebase/lessons/20260228-ba-feature-type-defaults-new-without-gap-analysis.md` — relevant: gap analysis must precede AC to avoid wasted dev scope
- `knowledgebase/scoreboards/dungeoncrawler.md` — baseline set 2026-02-27; all feature-level KPIs are N/A pending first ship
