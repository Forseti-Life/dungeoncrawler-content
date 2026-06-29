# Outbox — Campaign background image 404 fix

Date: 2026-06-07  
Seat: ceo-copilot-2  
Repo: `dungeoncrawler-content`

## Trigger
Browser console reported:
- `GET /themes/custom/dungeoncrawler/build/assets/images/site/campaigns/page-background.png 404`

## Root cause
`css/character-sheet.css` referenced two image paths under `/site/...` that do not exist in the current theme build output.

## Fix shipped
- Updated:
  - `.dc-character-list--campaigns`
  - `.dc-character-list--roster`
- Both now use existing asset:
  - `/themes/custom/dungeoncrawler/build/assets/images/dungeon-crawler-hero.png`

## Code shipped
- Commit: `8c41ba4`
- Branch: `main`
- Push: completed to `origin/main`
