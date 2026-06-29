Let me check UIManager's showEntityInfo and hexmap.js layout-related methods:

Now I have enough to implement. Let me start with the Twig and CSS changes:

**Edit 1**: Add sidebar drawer toggle button to header actions:

**Edit 2**: Add chat dock toggle button in chat header:

**Edit 3**: Add Inspector tab:

**Edit 4**: Add sidebar-panel-inspector after quests panel:

**Edit 5**: Remove entity-info-panel from hexmap-info (replaced by reference comment):

**Edit 6**: Add mobile backdrop div and new JS IIFEs before closing `</script>`:

Now add the CSS. First check the end of hexmap.css:

Now I need to check that the `hexmap-chat__header` doesn't already have a conflicting CSS display rule, and that `.hexmap-chat` CSS exists:

There's already an existing `.hexmap-chat__header` rule with `display: flex; justify-content: space-between`. My new rule conflicts — I need to remove the duplicate from my addition. The existing rule already has `display: flex` and `align-items: center`, so I just need to not add conflicting styles. Let me fix the CSS:

Now verify the twig template is well-formed (check for balanced divs in the game-layout section):

Good — game-layout is balanced. Now verify the twig has the correct closing structure:

Good. Now commit:

Write artifact and outbox:

- Status: done
- Summary: Implemented the dc-ui-sidebar-drawers feature. Added a sidebar drawer toggle button (always visible in header) that collapses the sidebar column on desktop or slides it in as a fixed overlay on mobile, with localStorage persistence. Added a chat dock toggle button that collapses the chat to header-only. Added an Inspector tab (4th sidebar tab) containing the entity-info-panel, which auto-activates via MutationObserver when an entity is selected on the map. All existing panel functionality (character/inventory/quests) is preserved unchanged; UIManager.showEntityInfo continues to target the same DOM IDs. Responsive mobile behavior uses `position: fixed; transform: translateX(100%)` slide-over with backdrop tap-to-close. Commit: `4ce401ed4`.

## Next actions
- QA: verify drawer toggle opens/closes sidebar on desktop and mobile, chat collapses to header, Inspector tab auto-switches on entity selection, last tab persists on reload

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 22
- Rationale: Drawer UX directly unlocks board-first gameplay — sidebar no longer competes with the map as an equal peer. Unblocks QA Gate 2 for this feature and unblocks subsequent UI modernization features.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260418-133600-impl-dc-ui-sidebar-drawers
- Generated: 2026-04-18T13:57:34+00:00
