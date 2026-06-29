# PF2E Reference Extraction Tracker

Tracks extraction status per book, per chapter/section.
Each chapter should produce a `references/` markdown file with paragraph-by-paragraph requirements analysis.

**Role in the tracking stack:** This file is the canonical **source-object tracker** for
chapter/section completeness. It answers “which source objects are done?” The canonical
source-document traceability ledger lives in `source-ledger.json`, and the detailed
subheading/paragraph working papers live under `audit/*.md`.

**Status key:**
- `[ ]` Not started
- `[~]` In progress
- `[x]` Complete

---

## 1. PF2E Core Rulebook — Fourth Printing
**Source:** `reference documentation/PF2E Core Rulebook - Fourth Printing.txt`
**Lines:** 103,265 | **Blank-line paragraphs:** ~22,302

| # | Chapter | File | Status |
|---|---------|------|--------|
| 1 | Chapter 1: Introduction (p.2) | `references/core-ch01-introduction.md` | `[x]` Done (as `chapter-01-introduction.md`) |
| 2 | Chapter 2: Ancestries & Backgrounds (p.32) | `references/core-ch02-ancestries-backgrounds.md` | `[x]` |
| 3 | Chapter 3: Classes (p.66) | `references/core-ch03-classes.md` | `[x]` |
| 4 | Chapter 4: Skills (p.232) | `references/core-ch04-skills.md` | `[x]` |
| 5 | Chapter 5: Feats (p.254) | `references/core-ch05-feats.md` | `[x]` |
| 6 | Chapter 6: Equipment (p.270) | `references/core-ch06-equipment.md` | `[x]` |
| 7 | Chapter 7: Spells (p.297) | `references/core-ch07-spells.md` | `[x]` |
| 8 | Chapter 8: The Age of Lost Omens (p.416) | — | `[SKIP]` world lore only | |
| 9 | Chapter 9: Playing the Game (p.442) | `references/core-ch09-playing-the-game.md` | `[x]` |
| 10 | Chapter 10: Game Mastering (p.482) | `references/core-ch10-game-mastering.md` | `[x]` |
| 11 | Chapter 11: Crafting & Treasure (p.530) | `references/core-ch11-crafting-treasure.md` | `[x]` |

**Progress: 11 / 11 chapters complete** (10 extracted + 1 lore skip)

---

## 2. PF2E Advanced Players Guide
**Source:** `reference documentation/PF2E Advanced Players Guide.txt`
**Lines:** 40,047 | **Blank-line paragraphs:** ~9,536

| # | Chapter | File | Status |
|---|---------|------|--------|
| 1 | Chapter 1: Ancestries & Backgrounds (p.6) | `references/apg-ch01-ancestries-backgrounds.md` | `[x]` |
| 2 | Chapter 2: Classes (p.52) | `references/apg-ch02-classes.md` | `[x]` |
| 3 | Chapter 3: Archetypes (p.148) | `references/apg-ch03-archetypes.md` | `[x]` |
| 4 | Chapter 4: Feats (p.201) | `references/apg-ch04-feats.md` | `[x]` |
| 5 | Chapter 5: Spells (p.210) | `references/apg-ch05-spells.md` | `[x]` |
| 6 | Chapter 6: Items (p.264) | `references/apg-ch06-items.md` | `[x]` |
| — | ~~Old merged file (incomplete)~~ | `references/apg-ch05-ch06-spells-items.md` | `[SUPERSEDED]` |

**Progress: 6 / 6 chapters complete**

---

## 3. PF2E Gamemastery Guide
**Source:** `reference documentation/PF2E Gamemastery Guide.txt`
**Lines:** 34,344 | **Blank-line paragraphs:** ~5,928

| # | Chapter | File | Status |
|---|---------|------|--------|
| 1 | Chapter 1: Gamemastery Basics (p.6) | `references/gmg-ch01-gamemastery-basics.md` | `[x]` |
| 2 | Chapter 2: Tools (p.54) | `references/gmg-ch02-tools.md` | `[x]` |
| 3 | Chapter 3: Subsystems (p.136) | `references/gmg-ch03-subsystems.md` | `[x]` |
| 4 | Chapter 4: Variant Rules (p.170) | `references/gmg-ch04-variant-rules.md` | `[x]` |

**Progress: 4 / 4 chapters complete**

---

## 4. PF2E Guns and Gears
**Source:** `reference documentation/PF2E Guns and Gears.txt`
**Lines:** 26,303 | **Blank-line paragraphs:** ~3,336

| # | Chapter | File | Status |
|---|---------|------|--------|
| 1 | Chapter 1: Gears Characters (p.10) | `references/gng-ch01-gears-characters.md` | `[x]` |
| 2 | Chapter 2: Gears Equipment (p.58) | `references/gng-ch02-gears-equipment.md` | `[x]` |
| 3 | Chapter 3: Guns Characters (p.100) | `references/gng-ch03-guns-characters.md` | `[x]` |
| 4 | Chapter 4: Guns Equipment (p.144) | `references/gng-ch04-guns-equipment.md` | `[x]` |
| 5 | Chapter 5: The Rotating Gear (p.186) | `references/gng-ch05-rotating-gear.md` | `[x]` |

**Progress: 5 / 5 chapters complete**

---

## 5. PF2E Secrets of Magic
**Source:** `reference documentation/PF2E Secrets of Magic.txt`
**Lines:** 30,715 | **Blank-line paragraphs:** ~4,352

| # | Chapter | File | Status |
|---|---------|------|--------|
| 1 | Chapter 1: Essentials of Magic (p.6) | `references/som-ch01-essentials-of-magic.md` | `[x]` |
| 2 | Chapter 2: Classes (p.32) | `references/som-ch02-classes.md` | `[x]` |
| 3 | Chapter 3: Spells (p.78) | `references/som-ch03-spells.md` | `[x]` |
| 4 | Chapter 4: Magic Items (p.154) | `references/som-ch04-magic-items.md` | `[x]` |
| 5 | Chapter 5: Book of Unlimited Magic (p.194) | `references/som-ch05-book-of-unlimited-magic.md` | `[x]` |

**Progress: 5 / 5 chapters complete**

---

## 6. PF2E Gods and Magic
**Source:** `reference documentation/PF2E Gods and Magic.txt`
**Lines:** 17,166 | **Blank-line paragraphs:** ~3,351
> Note: No formal chapters — organized as sections.

| # | Section | File | Status |
|---|---------|------|--------|
| 1 | Overview (p.6) | `references/gam-s01-overview.md` | `[x]` |
| 2 | Gods of the Inner Sea (p.10) | `references/gam-s02-gods-inner-sea.md` | `[x]` |
| 3 | Demigods and Other Divinities (p.72) | `references/gam-s03-demigods.md` | `[x]` |
| 4 | Philosophies and Spirituality (p.92) | `references/gam-s04-philosophies.md` | `[x]` |
| 5 | Character Options (p.102) | `references/gam-s05-character-options.md` | `[x]` |
| 6 | Appendix (p.124) | `references/gam-s06-appendix.md` | `[x]` |

**Progress: 6 / 6 sections complete**

---

## 7. PF2E Bestiary 1
**Source:** `reference documentation/PF2E Bestiary 1.txt`
**Lines:** 40,734 | **Blank-line paragraphs:** ~7,039
> Note: No formal chapters — creature entries A–Z.

| # | Section | File | Status |
|---|---------|------|--------|
| 1 | Introduction (p.5) | `references/b1-s01-introduction.md` | `[x]` |
| 2 | Monsters A-Z (p.8) | `references/b1-s02-monsters-az.md` | `[x]` |
| 3 | Appendix (p.342) | `references/b1-s03-appendix.md` | `[x]` |

**Progress: 3 / 3 sections complete**

---

## 8. PF2E Bestiary 2
**Source:** `reference documentation/PF2E Bestiary 2.txt`
**Lines:** 35,874 | **Blank-line paragraphs:** ~6,046
> Note: No formal chapters — creature entries A–Z.

| # | Section | File | Status |
|---|---------|------|--------|
| 1 | Monsters A-Z | `references/b2-s01-monsters-az.md` | `[x]` |
| 2 | Appendix | `references/b2-s02-appendix.md` | `[x]` |

**Progress: 2 / 2 sections complete**

---

## 9. PF2E Bestiary 3
**Source:** `reference documentation/PF2E Bestiary 3.txt`
**Lines:** 35,869 | **Blank-line paragraphs:** ~5,972
> Note: No formal chapters — creature entries A–Z.

| # | Section | File | Status |
|---|---------|------|--------|
| 1 | Introduction (p.5) | `references/b3-s01-introduction.md` | `[x]` |
| 2 | Monsters A-Z (p.8) | `references/b3-s02-monsters-az.md` | `[x]` |
| 3 | Appendix (p.304) | `references/b3-s03-appendix.md` | `[x]` |

**Progress: 3 / 3 sections complete**

---

## Summary

| Book | Chapters/Sections | Complete | Remaining |
|------|:-----------------:|:--------:|:---------:|
| Core Rulebook | 11 | 11 | 0 |
| Advanced Players Guide | 6 | 6 | 0 |
| Gamemastery Guide | 4 | 4 | 0 |
| Guns and Gears | 5 | 5 | 0 |
| Secrets of Magic | 5 | 5 | 0 |
| Gods and Magic | 6 | 6 | 0 |
| Bestiary 1 | 3 | 3 | 0 |
| Bestiary 2 | 2 | 2 | 0 |
| Bestiary 3 | 3 | 3 | 0 |
| **TOTAL** | **45** | **45** | **0** |

**Overall progress: 45 / 45 chapters/sections extracted**

---

## Next Up
All tracked chapters/sections are now complete.
