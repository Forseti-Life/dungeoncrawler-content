- Status: backlog
- PM owner: pm-dungeoncrawler
- Priority: P1

## Summary

Players who successfully complete a Criminal Record run should receive an XP reward. The XP amount should reflect the difficulty of the run (e.g., Criminal Record tier or length) and the outcome (full clear vs partial). Rewards should persist to the player's account and be visible post-run.

## Problem

Players currently complete Criminal Record runs with no measurable reward or progression. This creates a dead-end experience — there is no incentive to repeat, optimise, or compare performance. Without XP rewards, the Criminal Record mode is functionally disconnected from the broader player progression system.

## Goals

- Award XP to players on Criminal Record run completion.
- Scale XP by difficulty tier and run outcome (clear/partial).
- Persist rewards to player account and surface them post-run.
- Lay groundwork for future leaderboard / achievement integration.

## Non-goals

- Leaderboard UI (future).
- XP multipliers or bonuses beyond difficulty scaling.
- Real-money or loot-box mechanics.

## Acceptance criteria (draft, BA to refine)

- [ ] On full clear: player receives base XP * tier multiplier.
- [ ] On partial completion: player receives reduced XP proportional to progress.
- [ ] XP persists to player account after session ends.
- [ ] Post-run summary screen displays XP earned.
- [ ] No XP awarded for abandoned runs.
- [ ] XP formula is configurable without a code deploy.

## Open questions

1. What is the base XP value and tier multiplier table?
2. What defines "partial completion" — rooms cleared? Objectives met?
3. What is the player account data model / API endpoint for XP persistence?
4. Is there an existing XP system to integrate with, or does this greenfield one?
5. Does the post-run summary screen exist yet, or does it need to be built?

##

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-dungeoncrawler/inbox/20260428-backlog-intake-dc-cr-xp-rewards
- Generated: 2026-04-30T07:08:58+00:00
