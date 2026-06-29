I can see a bug: there are **two `11 =>` entries** in the swashbuckler CLASS_ADVANCEMENT (lines 7395 and 7401). In PHP, the second key overwrites the first, so Perception Master and Weapon Mastery are silently lost. I need to merge them.

---

- Status: done
- Summary: Swashbuckler was already implemented with full Panache mechanics, 5 Styles, Precise Strike scaling, Confident Finisher, Opportune Riposte (L3), and Exemplary Finisher (L9). Found and fixed a **silent data loss bug**: duplicate PHP integer key `11 =>` in `CLASS_ADVANCEMENT['swashbuckler']` caused Perception Master and Weapon Mastery to be silently discarded (PHP takes the last value for duplicate keys). Merged all three L11 features into one entry. PHP lint clean; 37 `dc_requirements` swashbuckler rows; site 200.

## Commits
- `2a8d950ea` (forseti.life) — duplicate L11 key fix
- `6f41a931a` (copilot-hq) — feature.md done + outbox

## ROI estimate
- ROI: 20
- Rationale: Fixes silent data loss bug in addition to completing the class; players leveling a Swashbuckler to L11 were not receiving Perception Master or Weapon Mastery.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260409-223200-impl-dc-apg-class-swashbuckler
- Generated: 2026-04-09T22:42:30+00:00
