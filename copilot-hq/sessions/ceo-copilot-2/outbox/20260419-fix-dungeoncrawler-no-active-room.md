# CEO Outbox: DungeonCrawler "No active room" Bug Fix

- Date: 2026-04-19
- CEO: ceo-copilot-2
- Status: done

## Summary

Fixed a bug blocking chat messages in all non-room session views (narrative, NPC conversations, party, GM-private).

## Root Cause

In `hexmap.js` `setupChatLog()`, the guard `if (!campaignId || !roomId)` fired **before** the `activeSessionView` check. When a player was in a narrative/NPC view (e.g. talking to Gribbles the innkeeper), `resolveActiveRoomId()` returns null — but `postSessionViewMessage()` doesn't need a roomId. The guard incorrectly blocked all chat with "Unable to send message: No active room".

## Fix

- Removed `!roomId` from the early guard (now only guards `!campaignId`)
- Added a `!roomId` guard inside the `room`-view-only branch, before `postChatMessage()`
- Non-room session views are fully unblocked

## Files Changed

- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/hexmap.js`

## Deployment

- Commit: `b5cc5ab80`
- Pushed to `keithaumiller/forseti.life` main
- `deploy.yml` workflow dispatched ✅
