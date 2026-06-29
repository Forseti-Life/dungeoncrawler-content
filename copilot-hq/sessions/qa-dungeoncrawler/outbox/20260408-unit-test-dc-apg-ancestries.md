# QA Verification Report — dc-apg-ancestries

- Feature: dc-apg-ancestries
- Dev item: 20260408-200013-impl-dc-apg-ancestries
- Dev commits: `3c5ee2838`, `cd3daaeb8`, `b1be82779`
- QA decision: **APPROVE**
- Date: 2026-04-08

## Evidence

### ANCESTRIES constant (8 APG entries)
All 8 APG ancestries confirmed in `CharacterManager.php`:
- Catfolk: hp/size/speed/boosts/languages/traits/vision ✓
- Gnome: hp/size/speed/boosts/languages/traits/vision ✓
- Goblin: hp/size/speed/boosts/languages/traits/vision ✓
- Leshy: hp/size/speed/boosts/languages/traits/vision ✓
- Kobold: hp/size/speed/boosts/languages/traits/vision + `draconic_exemplar: TRUE` special ✓
- Orc: hp/size/speed/boosts/languages/traits/vision ✓
- Ratfolk: hp/size/speed/boosts/languages/traits/vision ✓
- Tengu: hp/size/speed/boosts/languages/traits/vision + `sharp_beak` special (1d6 piercing) ✓

### HERITAGES constant
- Catfolk: 9 heritages (clawed, hunting, jungle, nine-lives, ancient-elf-blood, ...)
- Gnome: 16 heritages (chameleon, fey-touched, sensate, umbral, charhide, ...)
- Goblin: 13 heritages (charhide, irongut, razortooth, snow, gutsy, ...)
- Leshy: 9 heritages (cactus, gourd, leaf, vine, badlands, ...)
- Kobold: 6 heritages (cavern, dragonscaled, spellscale, strongjaw, venomtail, ...)
- Orc: 7 heritages (badlands, battle-ready, deep-orc, grave, rainfall, ...)
- Ratfolk: 6 heritages (desert, sewer, shadow, tunnel, jinxed, ...)
- Tengu: 6 heritages (jinxed, skyborn, stormtossed, taloned, adapted-cantrip, ...)

### KOBOLD_DRACONIC_EXEMPLAR_TABLE
10 dragon types confirmed (black/blue/brass/bronze/copper/gold/green/red/silver/white), each with `damage_type`, `breath_shape`, `save` fields.

### VERSATILE_HERITAGES
5 entries: aasimar, changeling, dhampir, duskwalker, tiefling — each with `ancestry_feats` array and sense-upgrade rules.

### BACKGROUNDS
3 APG backgrounds confirmed: `returned`, `haunted`, `fey_touched` (total 20 backgrounds in constant).

### TRAIT_CATALOG
All 8 APG ancestry traits + 5 versatile-heritage traits present: Catfolk, Gnome, Goblin, Leshy, Kobold, Orc, Ratfolk, Tengu, Aasimar, Tiefling, Changeling, Dhampir, Duskwalker.

### PHP lint
`No syntax errors detected` — clean.

### Site audit
Run: 20260408-230531 — 0 missing assets, 0 permission violations, 0 failures. PASS.

## KB references
- None applicable (new feature type, no prior lessons on APG ancestry mechanics).

## No new items for Dev
No defects found. PM may proceed to release gate.
