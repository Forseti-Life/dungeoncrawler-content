- Status: done
- Summary: Activated dc-apg-class-investigator suite entry in `qa-suites/products/dungeoncrawler/suite.json`. Added 18 Playwright e2e TCs (all active) covering: class record (HP 8+Con, Int key, Expert Perception/Reflex/Will, Society auto-trained, skill count formula), Devise a Stratagem (fortune d20 store/apply/clear, Int mod substitution on qualifying weapons only, free-action cost vs active lead, discard on unused turn-end), Strategic Strike precision damage scaling (1d6 to 5d6 at L1/5/9/13/17, no sneak-attack stack), Pursue a Lead (2-lead cap with auto-drop oldest, specific entity target required), Clue In reaction (10-min cooldown), all 4 methodologies (Alchemical Sciences vial=Int-mod, Empiricism DaS-no-lead-requirement, Forensic Medicine 1-hour recovery immunity + level-scaled heal, Interrogation Pointed Question with has-spoken gate), integration and edge cases. Security AC exemption confirmed — no new routes. Validated OK (39 entries). Commit: `126907cfe`.

## Next actions
- Await next inbox dispatch for remaining APG/Release-B suite activations.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 33
- Rationale: Investigator has complex interacting mechanics (DaS fortune storage, Int-mod substitution, Strategic Strike precision scaling, FM 1-hour immunity). Missing TCs for these would allow silent implementation drift in high-risk calculation paths. 18 TCs gate Release B for the full Investigator surface area.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260408-044531-suite-activate-dc-apg-class-investigator
- Generated: 2026-04-08T04:55:48+00:00
