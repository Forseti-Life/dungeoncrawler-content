- Status: `backlog`
- Priority: `P2`
- PM: `pm-dungeoncrawler`
- BA: `ba-dungeoncrawler`
- Dev: `dev-dungeoncrawler`
- QA: `qa-dungeoncrawler`
- Security: `security-dungeoncrawler`

---

## Problem statement
The "Criminal Ancestry" campaign arc includes a recurring dream sequence that is narratively significant but currently has no scheduling or session continuity logic. Players can encounter it immediately and repeatedly, breaking immersion and making the mechanic feel arbitrary. There is no system tracking whether the preconditions are met or how recently the sequence was last triggered.

## User story
> As a Criminal Ancestry campaign player, I want the recurring dream sequence to appear contextually — based on campaign progression, session timing, and meaningful preconditions — so the mechanic feels earned and immersive rather than random or intrusive.

## Acceptance criteria (draft — BA to refine)
1. The dream sequence is only triggered when a defined set of narrative/campaign preconditions are met (e.g., the player has reached a specific campaign milestone, or a minimum amount of in-game time has elapsed since the last occurrence).
2. A cooldown/recurrence logic is implemented so the sequence does not trigger on back-to-back sessions (minimum gap enforced).
3. Session continuity: if a session ends mid-sequence, the state is preserved so the sequence resumes or gracefully skips on the next session start.
4. Triggering logic is configurable at the campaign/arc level (not hardcoded per session).
5. The system does not surface the dream sequence to a player who has not started the Criminal Ancestry arc.

## Technical notes (draft —

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-dungeoncrawler/inbox/20260428-backlog-intake-dc-cr-ancestry-feat-schedule
- Generated: 2026-04-30T04:43:08+00:00
