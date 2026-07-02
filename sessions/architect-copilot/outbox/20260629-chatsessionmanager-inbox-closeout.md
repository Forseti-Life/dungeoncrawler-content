- Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-chatsessionmanager` with contract-focused decomposition planning and an implemented message-row normalization refactor increment.

## Delivered
- Audited `src/Service/ChatSessionManager.php` and documented decomposition boundaries for:
  1. session lifecycle and transition seams,
  2. message append/query normalization seams,
  3. active-session context projection seams.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `normalizeMessageRow(...)`,
  - rewired `getMessages(...)` and `getMessagesChronological(...)` to reuse the shared normalization helper.
- Added targeted unit coverage in `ChatSessionManagerTest` for:
  - valid metadata/feed-target JSON decode + ID casting,
  - invalid/empty JSON fallback normalization semantics.
- Pushed implementation commit in `dungeoncrawler-content`: `d039cd8247`.

## Next Action
1. Proceed to next pending queue item: `20260626-dungeoncrawler-monolith-service-combatengine`.
