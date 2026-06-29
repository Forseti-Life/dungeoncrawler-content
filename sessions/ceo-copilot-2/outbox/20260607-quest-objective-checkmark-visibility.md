# Outbox — Quest objective checkmark visibility fix

Date: 2026-06-07  
Seat: ceo-copilot-2  
Repo: `dungeoncrawler-content`

## Trigger
Live report (campaign 217): after talking to Eldric, the objective did not show as checked in the active quest list.

## Findings
- Server state was correct: `speak_to_eldric` was already marked `completed=true` in quest objective state.
- UI rendering path for active quests filtered completed objectives out before list rendering, which prevented completed talk objectives from being retained with ✅ in the visible objective list.

## Fix shipped
- Active quest objective rendering now includes completed objectives:
  - `js/v2/panels/QuestPanel.js`
  - `js/hexmap.js` (legacy parity)
- Rolled v2 cache-bust versions:
  - `js/v2/GameShell.js` QuestPanel import version
  - `js/hexmap-v2.js` GameShell import version
  - `dungeoncrawler_content.libraries.yml` `hexmap-v2` version

## Code shipped
- Commit: `056104a`
- Branch: `main`
- Push: completed to `origin/main`
