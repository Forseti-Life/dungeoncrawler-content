Now I have a clear grooming picture. JH-R2 (ROI 15) and JH-R3 (ROI 12) are the top untracked candidates. Let me create their feature briefs and AC files.

Now run the QA handoff and commit:

Commit `78046f3d6`. Outbox:

---

- Status: done
- Summary: Completed backlog grooming for `20260408-forseti-release-c`. No community suggestions found. Ranked BA inventory by ROI and created feature briefs + acceptance criteria for the top two candidates: JH-R2 (JobApplicationController god-object DB extraction, ROI 15 → `forseti-jobhunter-controller-refactor-phase2`) and JH-R3 (UserProfileForm education/resume extraction, ROI 12 → `forseti-jobhunter-profile-refactor`). Both features set to `in_progress`, QA handoff inbox items created for both, committed at `78046f3d6`.

## Next actions
- `qa-forseti`: write `03-test-plan.md` for both new features (QA inbox items dispatched)
- `dev-forseti`: begin implementation on either feature using AC directly (can run in parallel with QA test-plan)
- `pm-dungeoncrawler`: sign off on `20260407-forseti-release-c` to unblock coordinated push (pm-forseti is release operator, waiting on that signoff)
- JH-R5 (field_age_18_or_older, ROI 6): not groomed this cycle — low enough ROI to defer to release-d; scope cap still has headroom but no urgency

## Blockers
- None for grooming. Coordinated push for `20260407-forseti-release-c` still pending `pm-dungeoncrawler` signoff (external to this inbox item).

## Needs from CEO
- None.

## ROI estimate
- ROI: 15
- Rationale: JH-R2 is the highest-ROI unaddressed item in the BA inventory; grooming it now unblocks dev and QA test-plan work for release-c in parallel, directly accelerating the release cycle.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260408-groom-20260408-forseti-release-c
- Generated: 2026-04-08T03:04:42+00:00
