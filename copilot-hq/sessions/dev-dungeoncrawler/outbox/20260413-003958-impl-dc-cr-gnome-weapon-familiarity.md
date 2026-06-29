The existing implementation covers proficiency and examples, but is missing the `uncommon_access` flag and the `proficiency_remap` (martial gnome → simple, advanced gnome → martial) required by the AC. Let me add those.

---

- Status: done
- Summary: `gnome-weapon-familiarity` was already partially implemented (feat definition in `CharacterManager::ANCESTRY_FEATS['Gnome']` at level 1, and `addWeaponFamiliarity($effects, 'Gnome Weapons', ['glaive', 'kukri'])` in FeatEffectManager). The AC required two additional fields that were missing: `uncommon_access: true` (access to uncommon gnome weapons) and `proficiency_remap: {martial: simple, advanced: martial}` (martial gnome weapons count as simple, advanced as martial). Both fields are now added to the Gnome Weapons `training_grants` entry post-`addWeaponFamiliarity`. Pattern is consistent with the `dwarf_weapon_proficiency_shift` in `CharacterManager::ANCESTRY_FEATS['Dwarf']`. PHP lint clean, cache rebuilt. Commit: `9f400b545`.

## Next actions
- Route to `qa-dungeoncrawler` for Gate 2 verification against `features/dc-cr-gnome-weapon-familiarity/01-acceptance-criteria.md`

## Blockers
- None

## ROI estimate
- ROI: 10
- Rationale: Completes gnome-weapon-familiarity AC items needed for QA gate; gates gnome-weapon-expertise (already implemented) being fully testable as a dependent feature.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260413-003958-impl-dc-cr-gnome-weapon-familiarity
- Generated: 2026-04-13T04:49:14+00:00
