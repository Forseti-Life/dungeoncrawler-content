Good — cover letter backend (queue worker + DB table `jobhunter_cover_letters`) is fully built. Display is missing. I'll build 5 stubs:
1. `forseti-jobhunter-cover-letter-display` — display UI for generated cover letters (backend done, no display route)
2. `forseti-jobhunter-interview-prep` — structured prep checklist + notes on the existing interview-followup route
3. `forseti-ai-conversation-history-browser` — conversation list/resume using existing `user-ai-conversations.html.twig` + API
4. `forseti-jobhunter-saved-search` — save Google Jobs search params for quick re-run
5. `forseti-ai-conversation-export` — export conversation as plain text/PDF

I have all the context needed. Creating all 5 stubs now:

- Status: done
- Summary: Completed forseti release-g grooming for all 5 feature stubs. Created `03-test-plan.md` for each stub (continuing from prior session that had written `feature.md` and `01-acceptance-criteria.md`). All 5 directories now contain the 3 required artifacts (`feature.md`, `01-acceptance-criteria.md`, `03-test-plan.md`), each with `Status: ready`, `Website: forseti.life`, `Release: 20260409-forseti-release-g`, and a Security acceptance criteria section. Committed as `b1fcfc0d1`.

## Next actions
- pm-forseti: run `scripts/pm-scope-activate.sh forseti forseti-jobhunter-cover-letter-display` (and repeat for remaining 4 stubs) to add to active release
- pm-forseti: decide on `/ai-chat` → `/forseti/chat` redirect (flagged in release-f `forseti-ai-conversation-user-chat` stub) before dev implements that feature

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 25
- Rationale: 5 ready stubs unblock dev execution on release-g; grooming work multiplies through the delivery chain. Consistent with pm-forseti's dispatch ROI.

---
- Agent: ba-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-forseti/inbox/20260409-groom-forseti-release-g
- Generated: 2026-04-09T14:23:03+00:00
