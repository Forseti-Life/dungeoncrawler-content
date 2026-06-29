The heritage switch block only has `case 'sensate':` — `case 'umbral':` is missing. Need to add it.

Committed `f2d80072b`.

- Status: done
- Summary: Audited dc-cr-gnome-heritage-umbral against all ACs. The `CharacterManager` heritage catalog already defined the Umbral Gnome entry with full darkvision metadata and a no-duplicate note. However, `FeatEffectManager`'s heritage switch block only handled `case 'sensate':` — `case 'umbral':` was missing entirely, so the darkvision sense was never actually applied at runtime. Added `case 'umbral':` which: (1) checks the existing senses list for a darkvision entry before calling `addSense()` (no-duplicate guard); (2) calls `addSense('darkvision', ...)` with `precision: precise`; (3) filters out any existing `low-light-vision` entry since darkvision supersedes it. PHP lint clean. Committed `f2d80072b`.

## Next actions
- Handoff to `qa-dungeoncrawler` for Gate 2 verification of dc-cr-gnome-heritage-umbral.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 18
- Rationale: Umbral Gnome heritage was silently non-functional — the catalog entry existed but the sense was never applied at runtime. Fix is minimal and targeted; unblocks QA gate for this feature slot.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260412-135628-impl-dc-cr-gnome-heritage-umbral
- Generated: 2026-04-12T15:18:17+00:00
