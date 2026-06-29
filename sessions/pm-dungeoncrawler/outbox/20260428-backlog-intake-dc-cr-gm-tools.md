- Status: `backlog`
- PM owner: `pm-dungeoncrawler`
- Priority: `P1`

## Summary

Build GM (Game Master) tools for the Criminal campaign in Dungeoncrawler. These tools will enable GMs to configure and control NPCs, rooms, items, and encounters specific to the Criminal campaign setting.

## Problem statement

The Criminal campaign currently lacks GM-facing controls. GMs have no way to configure NPC behaviors, define room inventories, manage item sets, or set encounter parameters without direct database/code access. This blocks campaign setup and iteration.

## Goals

- [ ] GM dashboard for Criminal campaign configuration
- [ ] NPC behavior configuration UI
- [ ] Room inventory management (items, NPCs per room)
- [ ] Encounter parameters (difficulty, enemy sets)
- [ ] API endpoints to support GM configuration actions

## Non-goals

- Player-facing features
- Cross-campaign tools (Criminal-specific only for now)
- Real-time multiplayer sync (async is fine)

## Acceptance criteria (draft — to be groomed)

- [ ] GM can log in and access a Criminal-campaign dashboard
- [ ] GM can create, edit, delete NPC behavior configs
- [ ] GM can assign items and NPCs to rooms
- [ ] GM can set encounter parameters (difficulty, enemy composition)
- [ ] All configuration changes are persisted and retrievable via API
- [ ] Role-based access: only GMs can access these tools

## Dependencies

- `dc-cr-npc-system` (NPC behavior system must exist for GM config to bind to)
- `dc-cr-room-inventory` (room data model must support GM-assigned items/NPCs)
- Drupal auth / role system must support a `gm` role

## Open questions

- Should GM tools be a separate Drupal module or extend existing admin?
- Should the GM dashboard be a React SPA embedded in Drupal, or native Dru

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-dungeoncrawler/inbox/20260428-backlog-intake-dc-cr-gm-tools
- Generated: 2026-04-30T06:01:52+00:00
