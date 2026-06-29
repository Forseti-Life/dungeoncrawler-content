# Outbox — Chat wait indicator UX hardening

Date: 2026-06-07  
Seat: ceo-copilot-2  
Repo: `dungeoncrawler-content`

## Trigger
User reported chat UX did not clearly indicate when the server was still processing a response.

## Fix shipped
- Added a visible pending banner in the chat log while a visible request is in-flight:
  - `Waiting for server response…`
- Added stronger pending styling for in-flight chat lines (`chat-line--pending`) so active requests are obvious.
- Wired pending indicator sync into:
  - pending request creation,
  - pending request settlement,
  - channel switches,
  - session-view switches.
- Bumped v2 cache-bust versions so browsers pull updated chat JS/CSS immediately.

## Code shipped
- Commit: `32f2a48`
- Branch: `main`
- Push: completed to `origin/main`
