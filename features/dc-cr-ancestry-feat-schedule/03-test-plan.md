# Test Plan: dc-cr-ancestry-feat-schedule

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-AFS-01-05)
- Suites: playwright (character progression, rebuild/import, access control)
- Security: All ancestry-feat selection writes require authenticated character-owner or GM access.

---

## TC-AFS-01 — Milestone availability and slot gating
- Description: Characters receive ancestry feat selection opportunities at levels 1, 5, 9, 13, and 17 and not at unrelated levels.
- Suite: playwright/feat-progression
- Expected: Characters receive ancestry feat selection opportunities at levels 1, 5, 9, 13, and 17 and not at unrelated levels.
- AC: Happy Path-1

## TC-AFS-02 — Primary progression rule application
- Description: At each ancestry-feat milestone, the picker allows any ancestry feat whose level is less than or equal to the character level and whose prerequisites are satisfied.
- Suite: playwright/feat-progression
- Expected: At each ancestry-feat milestone, the picker allows any ancestry feat whose level is less than or equal to the character level and whose prerequisites are satisfied.; Previously selected ancestry feats remain attached to the character after later level-ups and do not get replaced when a new slot opens.
- AC: Happy Path-2, Happy Path-3

## TC-AFS-03 — Persistence and recalculation across level changes
- Description: Previously selected ancestry feats remain attached to the character after later level-ups and do not get replaced when a new slot opens.
- Suite: playwright/feat-progression
- Expected: Previously selected ancestry feats remain attached to the character after later level-ups and do not get replaced when a new slot opens.; Level-up output clearly indicates when an ancestry feat is pending so QA can verify the milestone is visible in the character progression flow.
- AC: Happy Path-3, Happy Path-4

## TC-AFS-04 — Edge-case rebuild and empty-option handling
- Description: A character leveling through multiple milestones in one rebuild or import can fill each missing ancestry-feat slot in order.
- Suite: playwright/feat-progression
- Expected: A character leveling through multiple milestones in one rebuild or import can fill each missing ancestry-feat slot in order.; An ancestry with no currently legal feat options reports a blocked selection state instead of offering invalid choices.; Retraining or rebuild flows recalculate ancestry feat eligibility from the current level and ancestry rather than leaving stale choices in place.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-AFS-05 — Validation, ownership, and invalid input handling
- Description: Submitting an ancestry feat above the character level or without prerequisites returns a validation error instead of being silently accepted.
- Suite: playwright/feat-progression
- Expected: Submitting an ancestry feat above the character level or without prerequisites returns a validation error instead of being silently accepted.; A character cannot mutate ancestry-feat slots belonging to another character or campaign context.
- AC: Failure Modes-1, Failure Modes-2
