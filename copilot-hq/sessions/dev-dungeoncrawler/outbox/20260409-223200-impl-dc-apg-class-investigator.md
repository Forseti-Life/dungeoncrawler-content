Good - the Investigator class is already quite detailed in CLASSES. Let me check CLASS_FEATS and CLASS_ADVANCEMENT for investigator:

The investigator is in CLASS_ADVANCEMENT already. Now let me check if there's a full level-by-level advancement for investigator (all levels) or if it's a stub with only L1, L5, L9, L13, L17:

The CLASSES entry and CLASS_ADVANCEMENT L1/strategic-strike levels are already there. The CLASS_ADVANCEMENT is missing the non-strategic-strike level entries (proficiency upgrades L3, L5, L7, L9, L11, L13, L17, L19). I need to expand it. Let me check CLASS_FEATS for investigator too:

Now I have a complete picture. The CLASS_ADVANCEMENT['investigator'] only has L1, L5, L9, L13, L17 for Strategic Strike scaling, but is missing proficiency upgrade milestones (L3 Keen Recollection, L5 Weapon Expertise, L7 Vigilant Senses, L9 Master Investigator, L11 Deductive Improvisation, L13 Greater Weapon Expertise, L17 Evasion/Light Armor Expertise, L19 Resolve). I'll add those now:

Now mark feature.md done and write the outbox:

---

- Status: done
- Summary: Investigator class was already implemented in `CLASSES`, `CLASS_FEATS`, and `STARTING_GEAR`. The gap was `CLASS_ADVANCEMENT['investigator']` only had Strategic Strike dice scaling (L1/5/9/13/17) and was missing all proficiency milestones. Added L3 Keen Recollection, L5 Weapon Expertise, L7 Vigilant Senses, L9 Master Investigator, L11 Deductive Improvisation, L13 Greater Weapon Expertise + Weapon Specialization, L17 Evasion + Greater Weapon Specialization + Light Armor Expertise, L19 Resolve + Light Armor Mastery. PHP lint clean.

## Commits
- `2948d6c31` (forseti.life) — CLASS_ADVANCEMENT milestones
- `a79e988c1` (copilot-hq) — feature.md done + outbox

## ROI estimate
- ROI: 20
- Rationale: Completes Investigator for release-g; unblocks QA gate verification.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260409-223200-impl-dc-apg-class-investigator
- Generated: 2026-04-09T22:35:44+00:00
