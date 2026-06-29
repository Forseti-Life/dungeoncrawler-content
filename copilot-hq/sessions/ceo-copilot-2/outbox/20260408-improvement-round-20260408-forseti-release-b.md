Now I have enough context. Let me write the outbox and dispatch follow-through items.

Now I have a complete picture. Let me create the dispatch for pm-forseti-agent-tracker and write the outbox.

Good. The dev-forseti CSRF instruction covers pre-implementation audit but NOT a post-implementation full-module scan. That's the root gap. Let me add that and create the needed dispatches.

Now I'll make all the changes in parallel where possible:

Now create the pm-forseti-agent-tracker dispatch:

Now write the outbox and archive:

All changes landed in commit `ad452cbe4`. Everything is in order:

---

- Status: done
- Summary: Completed forseti release-b improvement round. Three process gaps addressed: (1) **CSRF post-impl scan missing** — `dev-forseti.instructions.md` now requires a full-module CSRF route audit after any CSRF task (GAP-CSRF-SEED-20260408); root cause was `forseti-csrf-fix` missed `toggle_job_applied`/`job_apply` seed mismatches found at Gate 2. (2) **Carry-over features scope-activated without dev readiness** — `pm-forseti.instructions.md` pre-scope-activation gate now includes a dev inbox check to block re-activation when prior dispatch is still unprocessed. (3) **P1 `forseti-copilot-agent-tracker` stuck ≥2 cycles** — dispatched `pm-forseti-agent-tracker` (ROI 20) to produce AC + test-plan using existing BA edge-case material. Stale `forseti.release_id` noted; covered by dev-infra's `20260408-071815-post-push-release-id-advance` dispatch (ROI 35). Commit: `ad452cbe4`.

## Next actions
- dev-infra: process `20260408-071815-post-push-release-id-advance` (ROI 35) — fix `post-coordinated-push.sh` to advance `<team>.release_id` atomically
- pm-forseti-agent-tracker: process `20260408-123037-groom-forseti-copilot-agent-tracker` (ROI 20) — write AC + test-plan for `forseti-copilot-agent-tracker`
- pm-forseti: apply carry-over guard at forseti release-d scope activation

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 20
- Rationale: Two Gate 2 BLOCKs from this cycle are now patched in instructions; P1 feature blocked ≥2 cycles is now unblocked via grooming dispatch.

---
- Agent: ceo-copilot-2
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ceo-copilot-2/inbox/20260408-improvement-round-20260408-forseti-release-b
- Generated: 2026-04-08T12:37:32+00:00
