# Issue #5: Rules Vocabulary, Abbreviations & Glossary System

**Status**: Open  
**Type**: Feature Request  
**Priority**: Low  
**Source**: PF2E Core Rulebook Chapter 1 — Format of Rules Elements  
**Created**: 2026-02-27

## Overview

The game engine must maintain a defined vocabulary of named mechanical entities (statistics, skills, feats, actions, conditions) and distinguish them from narrative text and from spell/item references. A glossary lookup mechanism is required for players and QA to resolve abbreviations and game terms at runtime.

## Requirements

### REQ-5.1 — Named Mechanical Entity Vocabulary
- The system must maintain a canonical list of named game terms: statistics, skills, feats, actions, conditions.
- These terms must be distinct from plain prose/narrative text in the data model.
- Any UI surface that displays rules text should be able to identify and link named game terms.

### REQ-5.2 — Entity Type Distinction: Spells vs. Items vs. Narrative
- The data model must distinguish between: (a) spell references, (b) magic/alchemical item references, and (c) free narrative text.
- A reference to a spell name in rules text is a typed link to a spell entity, not a string.

### REQ-5.3 — Core Abbreviations as First-Class Fields
- The following abbreviations must exist as structured, queryable fields (not just display labels): AC (Armor Class), DC (Difficulty Class), HP (Hit Points).
- Any stat block or character record referencing these values must use the canonical field, not a freeform string.

### REQ-5.4 — Glossary Lookup Mechanism
- The system must provide a lookup endpoint or data structure that maps game terms and abbreviations to their full definitions.
- Minimum required entries: all named statistics (AC, DC, HP, STR, DEX, CON, INT, WIS, CHA, Fort, Ref, Will, Speed, Bulk), all action type symbols, all proficiency rank abbreviations (T, E, M, L), all alignment abbreviations (LG, LN, LE, NG, N, NE, CG, CN, CE).

## Acceptance Criteria
- [ ] A glossary data structure exists and is queryable by abbreviation and by full term name.
- [ ] Spell references and item references in rules text are stored as typed entity links, not inline strings.
- [ ] AC, DC, and HP appear as first-class fields on all relevant data models (character, stat block, encounter).
- [ ] A test confirms glossary returns correct definitions for at least 10 core abbreviations.

## Source Paragraphs
- "The names of specific statistics, skills, feats, actions, and some other mechanical elements in Pathfinder are capitalized." (Ch1, Format of Rules Elements, §2)
- "If a word or a phrase is italicized, it is describing a spell or a magic item." (Ch1, §3)
- "Pathfinder also uses many terms that are typically expressed as abbreviations, like AC for Armor Class, DC for Difficulty Class, and HP for Hit Points." (Ch1, §4)
