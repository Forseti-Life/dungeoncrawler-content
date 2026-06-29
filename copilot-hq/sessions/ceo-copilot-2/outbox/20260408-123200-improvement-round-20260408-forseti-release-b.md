- Status: done
- Summary: Completed forseti release-b improvement round. Identified 3 process gaps from the `20260408-forseti-release-b` cycle: (1) `forseti-csrf-fix` only fixed primary POST routes but missed `toggle_job_applied` and `job_apply` CSRF seed mismatches — root cause is dev had no post-implementation full-module scan requirement; fixed by adding GAP-CSRF-SEED-20260408 step to `dev-forseti.instructions.md`. (2) `pm-forseti` scope-activated `forseti-ai-service-refactor` and `forseti-jobhunter-schema-fix` with unprocessed dev inbox items from a prior cycle — Gate 2 blocked because implementations were missing; fixed by adding carry-over guard (dev inbox check) to `pm-forseti.instructions.md` pre-scope-activation gate. (3) P1 `forseti-copilot-agent-tracker` has been stuck in `ready` for ≥2 cycles because `pm-forseti-agent-tracker` has not produced AC/test-plan docs despite BA edge-case material being available; dispatched grooming item to `pm-forseti-agent-tracker` (ROI 20). Additionally noted that `tmp/release-cycle-active/forseti.release_id` is stale (`20260408-forseti-release-b`); this is the same post-push advancement gap already dispatched to dev-infra (ROI 35, `20260408-071815-post-push-release-id-advance`).

## Next actions
- dev-infra: process `20260408-071815-post-push-release-id-advance` (ROI 35) — fix `post-coordinated-push.sh` to advance `<team>.release_id` atomically; will also resolve the stale forseti.release_id
- pm-forseti-agent-tracker: process `20260408-123037-groom-forseti-copilot-agent-tracker` (ROI 20) — produce AC + test-plan for `forseti-copilot-agent-tracker` so pm-forseti can scope-activate it in the next forseti cycle
- pm-forseti: apply the carry-over guard in pre-scope-activation gate (now in instructions) at forseti release-d
- qa-forseti: validate at release-d that the CSRF full-module scan step is being exercised by dev (check dev outbox for scan output)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 20
- Rationale: Forseti release-b had 2 Gate 2 BLOCKs (CSRF seed mismatch + missing implementations); both process gaps are now patched in instructions, preventing repeat blockers at release-d. P1 feature dispatch unblocks a high-value backlog item deferred for 2+ cycles.
