Now I have everything I need. The `calculateAC` function accepts `shield_raised` but is only defined, not called in CombatEngine — it's called at character-build time, not at attack-resolution time. Let me write the verification report.

I have all the evidence needed. Let me now write the verification report and update the checklist.

The prior checkpoint created the report but missed GAP-2220 (avert_gaze +2 bonus not consumed) and GAP-2227 (shield raised AC bonus not applied in attack resolution). I'll add these findings.

The file structure is jumbled. Let me rewrite the Defects/Gaps section cleanly.

Now update the regression checklist:

- Status: done
- Summary: Re-verified reqs 2219–2232 (14 specialty basic actions + reactions). All 14 are implemented with full case handlers — inbox "Expected: NOT implemented" annotation was stale again. This re-verification pass found 2 additional medium-severity gaps missed previously: GAP-2220 (avert_gaze_active flag set/cleared correctly but no service reads it to add the +2 circumstance bonus to gaze saves) and GAP-2227 (raise_shield stores shield_raised_ac_bonus in entity_ref but CombatEngine::resolveAttack reads the flat combat_participants.ac DB column — shield raised provides zero mechanical AC benefit). Combined with prior findings (DEF-2230, GAP-2225, MINOR-2231), verdict remains BLOCK. Corrected score: 10/14 PASS (was 12/14). Committed `57d9e9639`.

## Next actions
- Route to dev-dungeoncrawler: fix GAP-2227 (1-line fix in CombatEngine::resolveAttack to read entity_ref shield_raised_ac_bonus), DEF-2230 (remove EPH line 1180), GAP-2220 (add avert_gaze consumer in gaze-save resolution)
- Continue next inbox item: `20260406-roadmap-req-2233-2266-movement`

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 42
- Rationale: GAP-2227 renders `raise_shield` (a core defensive action used every combat) mechanically inert — fix is a 2-line patch in CombatEngine. High value-to-effort ratio; directly impacts combat playability.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-roadmap-req-2219-2232-specialty-reactions
- Generated: 2026-04-06T23:52:20+00:00
