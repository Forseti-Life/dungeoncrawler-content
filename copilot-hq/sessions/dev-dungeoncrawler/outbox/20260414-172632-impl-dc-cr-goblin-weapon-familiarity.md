The `goblin-weapon-familiarity` case is missing `uncommon_access` and `proficiency_remap` flags — the same pattern used by `gnome-weapon-familiarity`. The AC requires both. Fix:

Committed `880f3e20e`.

---

- Status: done
- Summary: Goblin Weapon Familiarity (dc-cr-goblin-weapon-familiarity, release-l) was partially implemented — the `goblin-weapon-familiarity` FeatEffectManager case called `addWeaponFamiliarity` to grant trained proficiency with `dogslicer` and `horsechopper`, but was missing the `uncommon_access = TRUE` flag and `proficiency_remap = ['martial' => 'simple', 'advanced' => 'martial']` entry required by the AC. Added both, matching the established `gnome-weapon-familiarity` pattern. The feat definition in `CharacterManager` ANCESTRY_FEATS was already correct. PHP lint clean, cache rebuilt. Committed `880f3e20e`.

## Next actions
- Route to qa-dungeoncrawler for Gate 2 verification

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 11
- Rationale: Completes AC gap on an already-partially-implemented Goblin feat; surgical one-file fix that unblocks QA gate for release-l.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260414-172632-impl-dc-cr-goblin-weapon-familiarity
- Generated: 2026-04-14T17:46:05+00:00
