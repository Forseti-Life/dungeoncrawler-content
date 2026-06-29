# Acceptance Criteria: Sidebar Drawers and Docked Support Panels
# Feature: dc-ui-sidebar-drawers

## AC-001: Single-primary drawer model
- Given the player opens character, inventory, quests, or inspector, when the drawer system is active, then only one primary right-side drawer is expanded at a time
- Given the player switches drawers, when the new drawer opens, then the prior drawer collapses without losing state unexpectedly

## AC-002: Existing panel functionality is preserved
- Given the player opens character, inventory, or quest content, when the drawer renders, then the existing data and interactions still work inside the new shell
- Given the player opens the entity inspector, when an entity is selected, then inspection data remains available without navigating away from the board

## AC-003: Chat is docked without dominating the board
- Given room/story/party/system chat is used, when the player interacts with the chat area, then it behaves as a docked or collapsible support panel instead of forcing a full-width competing region

## AC-004: Responsive behavior is supported
- Given the shell is viewed on a narrow screen, when a drawer opens, then it degrades to an overlay/slide-over pattern that remains usable on tablet/mobile
- Given the player returns later, when the shell loads, then the last selected drawer may be restored if appropriate for the device size

## Security acceptance criteria

- Security AC exemption: drawer refactor changes presentation only; existing route/API authorization remains authoritative.

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
