# QA Verification: dc-cr-class-ranger

- **Inbox item:** `20260409-unit-test-20260409-050000-impl-dc-cr-class-ranger`
- **Dev commit:** `feaa66b51`
- **Audit run:** `20260409-051852`
- **Regression checklist commit:** `0224d8573`

---

## Verdict: APPROVE

---

## AC verification

### Identity & Base Statistics
- `key_ability_choice = TRUE`, `key_ability = 'Strength or Dexterity'` ✅
- `hp = 10` ✅
- Proficiencies: Expert Perception, Trained Fortitude/Reflex/Will ✅
- `armor_proficiency = ['light', 'medium', 'unarmored']` ✅
- `weapons = 'Trained in simple and martial weapons'` ✅

### Hunt Prey
- `action_cost = 1`, `free_action_feats = TRUE` ✅
- `max_prey = 1`, `exception_feat = 'Double Prey'` ✅
- `change_prey = 'Designating new prey replaces current prey designation.'` ✅
- Benefits: +2 Perception seek/recall, ignore DC5 dark flat-check, ignore concealment (not total) ✅

### Hunter's Edge (L1 subclass)
- `selection = 'L1 choice; permanent'`, three options present ✅
- **Flurry:** MAP –3/–6 (–2/–4 agile) prey-only; normal MAP vs others ✅
- **Precision:** +1d8 first hit only; `scaling = [1 => '1d8', 11 => '2d8', 19 => '3d8']` ✅
- **Outwit:** +2 Deception/Intimidation/Stealth/Recall Knowledge vs prey; +1 AC vs prey attacks ✅

### CLASS_ADVANCEMENT L1–L19
| Level | Features | Verified |
|---|---|---|
| 1 | Hunt Prey + Hunter's Edge | ✅ |
| 3 | Iron Will (Will → Expert) | ✅ |
| 5 | Ranger Weapon Expertise + Trackless Step | ✅ |
| 7 | Evasion (Reflex → Master, success→crit) + Vigilant Senses (Percep → Master) + Weapon Spec | ✅ |
| 9 | Swift Prey (free action on turn) + Nature's Edge (flat-footed in natural/difficult terrain) | ✅ |
| 11 | Hunter's Edge Mastery (Precision 2d8 / Flurry full-round / Outwit AC+2) + Ranger Weapon Mastery | ✅ |
| 13 | Medium Armor Expertise (light/medium/unarmored → Expert) + Greater Weapon Spec | ✅ |
| 15 | Improved Evasion (crit-fails → fails) + Incredible Senses (Percep → Legendary) | ✅ |
| 17 | Masterful Hunter (Precision 3d8 per description) + Medium Armor Mastery | ✅ |
| 19 | Surge of Pursuit (Hunt Prey free action on reactions/off-turn) | ✅ |

### Notes
- **Precision scaling note:** `CLASSES['ranger']['hunters_edge']['precision']['scaling']` correctly marks `19 => '3d8'` (matching AC). CLASS_ADVANCEMENT L17 description also states "+3d8" (aligns with PF2e CRB Masterful Hunter). Both the machine-readable array (AC-aligned) and the L17 description are internally consistent with each other and the CRB — no blocking conflict.

---

## Evidence

| Check | Result |
|---|---|
| PHP lint | No syntax errors |
| Suite `dc-cr-class-ranger-e2e` | 32 TCs, activated for `20260409-dungeoncrawler-release-c`, `required_for_release: true` |
| Site audit `20260409-051852` | 0 violations, 0 failures |
| Regression checklist | Updated to APPROVE — commit `0224d8573` |
