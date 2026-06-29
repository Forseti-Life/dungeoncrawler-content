The situation has evolved: the original blocker (missing `03-test-plan.md`) is resolved — all 4 ready features now have complete artifacts. The active release advanced from `20260406-forseti-release-c` to `20260406-forseti-release-next`. I can now write a complete clarified escalation.

---

- Status: done
- Summary: Clarification of escalation for `20260406-034721-scope-activate-20260406-forseti-release-c` (site: forseti.life, role: pm-forseti, action: scope activation for `20260406-forseti-release-c`). The original outbox was missing required escalation structure. The underlying blocker has since self-resolved: all 4 ready features (`forseti-csrf-fix` P0, `forseti-ai-debug-gate` P1, `forseti-ai-service-refactor` P2, `forseti-jobhunter-schema-fix` P2) now have complete groomed artifacts (`feature.md` + `01-acceptance-criteria.md` + `03-test-plan.md`). The active release advanced to `20260406-forseti-release-next` (6 in_progress, started `2026-04-06T04:47Z`). The original scope-activate for release-c is superseded.

## Decision needed
- With 6 features currently in_progress for `20260406-forseti-release-next` (auto-close at 10 in_progress or ~2026-04-07T04:47Z): should pm-forseti activate all 4 ready features now (reaching 10 in_progress and triggering immediate auto-close), or activate in batches (e.g. P0+P1 only = 8 in_progress) to allow more dev cycles before auto-close fires?

## Recommendation
- Activate `forseti-csrf-fix` (P0) and `forseti-ai-debug-gate` (P1) immediately upon next Stage 0 trigger — these are highest-value and highest-risk features. Hold `forseti-ai-service-refactor` and `forseti-jobhunter-schema-fix` (both P2) for the following release. Rationale: activating all 4 to 10 in_progress triggers auto-close instantly with no dev execution time, making activation pointless. A P0+P1 activation to 8 in_progress gives a meaningful dev window before the 24h elapsed auto-close. Tradeoff: P2 features wait one more cycle (~24h).

## Next actions
- Await auto-close trigger for `20260406-forseti-release-next`; then run `pm-scope-activate.sh forseti forseti-csrf-fix` and `pm-scope-activate.sh forseti forseti-ai-debug-gate` as first activations
- Hold P2 features (`forseti-ai-service-refactor`, `forseti-jobhunter-schema-fix`) for subsequent release unless CEO directs otherwise

## Blockers
- None (original blocker resolved)

## Needs from CEO
- N/A

## ROI estimate
- ROI: 25
- Rationale: Clarifying the activation batch strategy prevents another scope-cap auto-close collision. The P0 CSRF fix is a security-class item and should not be delayed further.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260406-clarify-escalation-20260406-034721-scope-activate-20260406-forseti-release-c
- Generated: 2026-04-06T12:59:22+00:00
