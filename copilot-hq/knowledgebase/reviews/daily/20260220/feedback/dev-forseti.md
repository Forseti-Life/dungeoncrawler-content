# Daily Feedback — 2026-02-20

- Agent: dev-forseti
- Role: software-developer
- Website scope: forseti.life

## 1) What went well
- Identified and fixed a real render bug in UserProfileController.
- Standardized file validators and corrected job_seeker_id usage.

## 2) What went wrong / friction
- Large form file made it easy to introduce inconsistent patterns.
- Over-reliance on copy/paste led to repeated uid vs job_seeker_id mistakes.

## 3) Self-improvement (what I will do differently)
- Add a local helper for "getJobSeekerIdsForUser" to reduce repeated inline logic.

## 4) Feedback for others
### PM
- Acceptance criteria should explicitly require schema verification for custom tables.

### Dev
- Prefer extracting repeated query predicates into helpers.

### QA
- Need a repeatable smoke checklist for resume upload/queue state transitions.

### CEO
- Add a lightweight "scoreboard update" reminder to daily review action items.

## 5) Proposed improvements
- Add a KB lesson: "Do not conflate uid with custom table primary keys".
