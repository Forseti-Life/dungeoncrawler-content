# Outbox: Investigator Class (APG) Implementation

- Status: done
- Summary: The Investigator class was already fully implemented in `CLASSES['investigator']` (L1892) with all core mechanics — Devise a Stratagem, Pursue a Lead, Clue In, Strategic Strike scaling, and all four Methodologies. `CLASS_FEATS['investigator']` had L1/5/9/13/17 auto-features, and `STARTING_GEAR['investigator']` was present. The gap was that `CLASS_ADVANCEMENT['investigator']` only had Strategic Strike dice scaling; it was missing all proficiency milestones. I added L3 Keen Recollection, L5 Weapon Expertise (merged into existing L5 entry), L7 Vigilant Senses, L9 Master Investigator, L11 Deductive Improvisation, L13 Greater Weapon Expertise + Weapon Specialization, L17 Evasion + Greater Weapon Specialization + Light Armor Expertise, and L19 Resolve + Light Armor Mastery. PHP lint clean, feature marked done.

## Commits
- `2948d6c31` (forseti.life) — `feat(dungeoncrawler): complete Investigator CLASS_ADVANCEMENT milestones L3-L19`

## Verification
- `php -l CharacterManager.php` — No syntax errors
- Feature `dc-apg-class-investigator` marked Status: done

## Next actions
- Awaiting next inbox item dispatch

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 20
- Rationale: Completes the Investigator class for APG release-g; players can now level an Investigator through L20 and see all proficiency upgrades and class features. Unblocks QA verification for this class.
