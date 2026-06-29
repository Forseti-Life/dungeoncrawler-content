# Risk Assessment (PM-owned, all contribute)

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| Shell state remains split between Twig inline handlers and `hexmap.js` | Medium | High | Require one canonical shell-state owner in the scoped implementation notes before Dev starts | PM / Dev |
| Legacy persisted `sidebar-tab` or drawer state restores into invalid states | High | Medium | Define explicit migration/fallback-to-`map` behavior in acceptance criteria and QA coverage | PM / QA |
| Inventory/chat refresh seams regress while navigation is normalized | Medium | Medium | Call out support-panel refresh dependencies in BA/Dev notes and cover with targeted functional tests | PM / Dev / QA |
| Feature is treated as generic UI polish and loses release priority | Medium | Medium | Keep feature indexed as `dc-ui-map-tab-recreation` with explicit Board-origin scope and ROI | PM |

## Rollback Trigger
- Shell navigation no longer exposes a reliable return-to-board path or breaks persisted support-panel restore behavior for existing users.

## Monitoring
- What to watch post-merge: shell restore behavior, inventory-on-open behavior, chat/drawer visibility transitions, functional test selector stability
- Where: `/hexmap` manual smoke checks, module functional coverage, QA outbox evidence
