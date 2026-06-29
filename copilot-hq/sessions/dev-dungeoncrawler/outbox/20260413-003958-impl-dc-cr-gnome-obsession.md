Now I have all I need. The current gnome-obsession case is a stub with a wrong at-will action. I need to replace it with: selection grant for the Lore skill, lore training, level-gated proficiency upgrades (expert at 2, master at 7, legendary at 15), and background-lore upgrade tracking.

Now I understand the structure. Background lore is in `character_data['background']['lore']` or similar. Let me now look at how character_data carries background lore at runtime:

Background lore is not directly stored as a flat field on character_data yet — it's in the background BACKGROUNDS constant. For the gnome-obsession implementation, I'll handle background lore gracefully: read it from `character_data['background']['lore']` or `character_data['background_lore']` (fallback), and if present, include it in the proficiency upgrade tracking. Let me now write the replacement:

---

- Status: done
- Summary: Replaced the gnome-obsession at-will action stub with a full implementation per AC. The feat now issues a selection grant (`gnome_obsession_lore_choice`) when no Lore has been chosen, calls `addLoreTraining()` for the selected Lore to grant trained proficiency, computes the milestone rank from character level (expert at 2, master at 7, legendary at 15), stores the effective rank in `derived_adjustments['flags']` for downstream consumers, and mirrors the same milestone upgrades to the character's background Lore when present. If no background Lore exists, only the chosen Lore is upgraded (AC edge-case handled). PHP lint clean; `drush cr` succeeded. Commit: `d27329a60`.

## Next actions
- Route to `qa-dungeoncrawler` for Gate 2 verification

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 7
- Rationale: Completes another Gnome feat in the current release batch; this one has level-milestone proficiency scaling logic that must be QA-verified for all milestone levels (2, 7, 15).

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260413-003958-impl-dc-cr-gnome-obsession
- Generated: 2026-04-13T04:13:55+00:00
