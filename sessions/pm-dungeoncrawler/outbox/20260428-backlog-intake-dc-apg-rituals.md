- Status: backlog
- PM: pm-dungeoncrawler
- Priority: P2
- ROI: 6

## Summary
Implement an Augmented Player Guide (APG) ritual system for Dungeoncrawler. The APG provides context-aware guidance overlays and session annotations as players progress through dungeon rooms and encounters.

## Background
Players frequently lose context mid-session when switching devices or resuming after breaks. An APG ritual system would persist key session annotations (last room, active quest hooks, notable NPC interactions, inventory snapshots) and resurface them at re-entry points as lightweight "ritual" overlays. The goal is to reduce cognitive load and improve session continuity.

## Scope
- Session annotation persistence (key room/encounter/NPC events)
- APG ritual overlay at session re-entry
- Configurable ritual depth (brief / standard / full recap)
- Backend: Drupal custom module `dungeoncrawler_apg`
- Frontend: lightweight overlay injection at re-entry

## Non-goals
- Real-time co-op sync
- Full replay engine
- Social sharing of sessions

## Acceptance criteria (draft)
- [ ] Session annotations are persisted per-user, per-session across device switches
- [ ] APG ritual overlay renders at re-entry within 2s of session load
- [ ] Three recap depth options (brief/standard/full) are selectable by player
- [ ] Overlay does not block core gameplay UI
- [ ] Annotations correctly reflect last 5 room/encounter events (accuracy ≥ 95%)
- [ ] No regression on existing room progression or encounter flow

## Dependencies
- Drupal session/auth layer (existing)
- Room progression module (existing — must not regress)
- Frontend overlay framework (TBD — may require new lightweight library)

## Open questions
1. Does the frontend overlay use an existing JS framework in the Dungeoncrawler codebase, or does it require

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-dungeoncrawler/inbox/20260428-backlog-intake-dc-apg-rituals
- Generated: 2026-04-30T03:55:06+00:00
