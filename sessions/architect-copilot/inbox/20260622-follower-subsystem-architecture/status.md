# Status

- status: pending
- created_at: 2026-06-22T18:34:57+00:00
- current_phase: awaiting architectural review

## Notes

Created after reviewing the live Dungeoncrawler familiar and animal companion paths.

The core architectural finding is that the codebase already contains partial subsystem pieces and an existing NPC-style runtime/sheet pipeline. The next step should be an architectural design that extends those existing systems into a unified follower subsystem instead of creating a fully separate parallel actor model.
