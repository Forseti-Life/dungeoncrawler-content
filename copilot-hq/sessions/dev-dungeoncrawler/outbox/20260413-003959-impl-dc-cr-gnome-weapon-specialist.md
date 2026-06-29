Now I have the full picture. I'll add `gnome-weapon-specialist` with a `gnome_weapon_specialist_crit_spec` flag in `derived_adjustments['flags']`. Let me now implement all three steps:

Good. The prerequisite check already exists. Now implement all three parts:

**1. Add feat definition to CharacterManager**

**2. Add FeatEffectManager case**

**3. Verify prerequisite validation already covers the new feat (the check is on the flag, not the feat ID — so level check still needed)**

The prerequisite validation already handles `prerequisite_gnome_weapon_familiarity` generically — it will fire for `gnome-weapon-specialist` too. Now lint and drush cr:

Done. Here's the outbox:

---

- Status: done
- Summary: Implemented `gnome-weapon-specialist` (level 5 Gnome feat, requires Gnome Weapon Familiarity). Added feat definition to `CharacterManager::ANCESTRY_FEATS['Gnome']` between levels 1 and 13, added a `gnome-weapon-specialist` case to `FeatEffectManager` that sets `derived_adjustments.flags.gnome_weapon_specialist_crit_spec = TRUE`, and confirmed the existing `prerequisite_gnome_weapon_familiarity` validation in `CharacterLevelingService` covers the new feat without changes. PHP lint clean, drush cr successful. Commit `f500494c0`.

## Next actions
- Route to `qa-dungeoncrawler` for Gate 2 verification of the gnome weapon feat batch (familiarity, specialist, expertise)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 12
- Rationale: Completes the gnome weapon feat trilogy; unblocks QA Gate 2 for the entire gnome weapon batch. The flag-based approach aligns with existing crit spec patterns and requires no combat-engine changes.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260413-003959-impl-dc-cr-gnome-weapon-specialist
- Generated: 2026-04-13T04:56:59+00:00
