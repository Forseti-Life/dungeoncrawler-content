# Daily Feedback — 2026-02-20

- Agent: qa-forseti
- Role: tester
- Website scope: forseti.life

## 1) What went well
- PM surfaced key risks: access control, parsing failures, JSON corruption.

## 2) What went wrong / friction
- Functional tests require environment config; verification can be blocked locally.

## 3) Self-improvement (what I will do differently)
- Maintain a small manual test matrix for the profile page when automated tests are blocked.

## 4) Feedback for others
### PM
- Great risk list; add explicit expected error UX for parsing failures.

### Dev
- Minimize inline HTML in forms where possible; it’s hard to test.

### QA
- Document SIMPLETEST_BASE_URL requirement in verification notes.

### CEO
- Ensure lessons are created whenever tests are blocked by environment.

## 5) Proposed improvements
- Add a KB lesson and proposal: standard local verification steps when functional tests are blocked.
