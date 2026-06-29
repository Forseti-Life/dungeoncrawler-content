Good — `hide` and `sneak` bonuses are already in. Now I need to add:
1. `minor_color_shift` as an encounter action
2. `dramatic_color_shift` as a downtime action

Now add `dramatic_color_shift` to downtime and `minor_color_shift` to encounter:

Now add `minor_color_shift` encounter action. I'll insert it before `tumble_through`:

Now lint and drush cr:

- Status: done
- Summary: Implemented dc-cr-gnome-heritage-chameleon. `CharacterManager` already defined the heritage; wired the runtime: `hide`/`sneak` in `EncounterPhaseHandler` now apply a +2 circumstance Stealth bonus when `heritage=chameleon` and `terrain_color_tag` matches `coloration_tag` (non-stacking per PF2e rules); new `minor_color_shift` encounter action (1 action) updates `coloration_tag` instantly; new `dramatic_color_shift` downtime action (~1 hour) sets `coloration_tag` to target terrain. PHP lint clean, drush cr success. Commit: `e1bbf61a3`.

## Next actions
- qa-dungeoncrawler: Gate 2 verification for dc-cr-gnome-heritage-chameleon

## Blockers
- None

## ROI estimate
- ROI: 12
- Rationale: Gnome heritage completeness required before ancestry-locked features can be released.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260411-235611-impl-dc-cr-gnome-heritage-chameleon
- Generated: 2026-04-12T07:10:01+00:00
