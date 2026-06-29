- Status: needs-info
- Summary: Cleared the seven Dungeoncrawler chat CEO inbox items into a single resolved engineering batch because the room-chat stabilization/refactor stack now covers the full defect cluster; authenticated live campaign-28 verification is still blocked by `403 Forbidden` and needs Board-provided access or an explicit waiver to treat the batch as fully closed.

# Dungeoncrawler chat inbox closeout

## What changed
- Consolidated the seven CEO inbox items into one resolved batch in `sessions/ceo-copilot-2/artifacts/.resolved/20260516-dungeoncrawler-chat-inbox-batch-closeout/`.
- Recorded the mapping from each inbox item to the landed code/refactor surfaces in the earlier assessment outbox.
- Attempted live verification against the original campaign-28 URL and confirmed the CEO thread does not currently have access to that live surface.

## Evidence
- Live fetch result for the original review URL: `403 Forbidden`
- Engineering coverage already landed in `dungeoncrawler-content`:
  - GM role-boundary enforcement
  - GM adjudication/meta routing
  - direct-NPC continuity
  - pinned-room context selection
  - player-visible prompt leakage cleanup
  - storyline lead grounding
  - correction absorption

## Needs from Board
- Either provide authenticated access/context for live campaign-28 verification, or confirm that code-level regression coverage is sufficient to treat this batch as closed without a live pass.

## Decision needed
- Should the Dungeoncrawler chat batch be considered fully closed based on the landed regressions and code review passes, or should it remain awaiting authenticated live verification?

## Recommendation
- If Board access to the live campaign surface is readily available, perform one authenticated campaign-28 verification pass and then mark the batch fully done.
- If not, accept the current regression-backed closeout and keep the verification gap documented as an access limitation rather than reopening the engineering work.
