# Repository Issues Tracker

Internal issue tracker for repository work when GitHub issue creation is unavailable or rate-limited.
This file is also the backup tracker when CLI interface access is denied for creating GitHub issues.

## Status Values
- **Open**: Work is not completed.

## Active Issues

### dungeoncrawler_tester

#### config

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### css

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### root docs/meta

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### js

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### scripts

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### src

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCT-0001 | Testing dashboard flow tracking depends on disabled GitHub context | Open | Copilot | 2026-02-18 | 2026-02-18 | Code issue: TestingDashboardController computes PR/workflow GitHub metrics in buildLifecycleTrackingSection(), but resolveGitHubContext() hard-returns local/Issues.md with token NULL and requestGitHubJsonWithFallback() returns disabled. Result: PR automation cards remain unavailable and lifecycle inference mixes disabled GitHub paths with local-only runtime. |
| DCT-0002 | Issue automation documentation route aliases to generic triage content | Open | Copilot | 2026-02-18 | 2026-02-18 | Code issue: /dungeoncrawler/testing/documentation/issue-automation maps to docsIssueAutomation(), which aliases docsFailureTriage() rather than dedicated issue-automation documentation. Route title/menu imply specialized automation docs that are not implemented. |
#### templates

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### tests

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
### dungeoncrawler_content

#### root docs

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCC-0340 | Writeability test: Issues.md accepts new tracking rows | Open | Unassigned | 2026-02-18 | 2026-02-18 | Probe issue added intentionally before full PF2E handbook feature-gap issue generation. Verification rerun completed 2026-02-18; keep or close after audit completion. |
| DCC-0341 | PF2E handbook review: build chapter/section requirement matrix from Core Rulebook + APG outlines | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only issue. Produce a canonical requirements matrix from PF2E Core Rulebook and APG outlines; include chapter, section, target feature area, and status (present/partial/missing) for dungeoncrawler app. |
| DCC-0342 | PF2E Ch.1 Introduction: identify onboarding/tutorial and leveling workflow feature requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Capture missing product requirements for first-time onboarding, rules primer UX, example-of-play walkthrough, and level-up guidance surfaced in player flows. |
| DCC-0343 | PF2E Ch.2 Ancestries & Backgrounds: audit ancestry, heritage, background, and language feature coverage | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Define needed data models, validation rules, and UI/API support for ancestry/background selection and language management including provenance constraints. |
| DCC-0344 | PF2E Ch.3 Classes: audit class framework, class progression, and companion/familiar support gaps | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Enumerate feature requirements for class mechanics, class feats/progression tracking, multiclass implications, and companion/familiar lifecycle support. |
| DCC-0345 | PF2E Ch.4 Skills: audit skill actions, proficiency scaling, and skill-check resolution requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Capture required mechanics for skill actions, difficulty checks, success tiers, modifiers, and skill-driven exploration/interaction patterns. |
| DCC-0346 | PF2E Ch.5 Feats: audit feat taxonomy, prerequisites, and unlock/progression feature needs | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Identify requirements for ancestry/class/general/skill feat catalogs, eligibility validation, and in-app feat selection/governance workflows. |
| DCC-0347 | PF2E Ch.6 Equipment: audit inventory, bulk, item traits, and economy/shop feature requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Define missing requirements for equipment acquisition, carrying limits, item trait interactions, loadouts, and trade/economy systems. |
| DCC-0348 | PF2E Ch.7 Spells: audit spellcasting engine requirements (traditions, slots, focus, rituals, heightening) | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Capture feature requirements for spell prep/casting, slot accounting, focus points, ritual handling, spell lists, and scaling/heightened variants. |
| DCC-0349 | PF2E Ch.8 Age of Lost Omens: audit lore/world integration requirements for campaign content systems | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Identify worldbuilding, lore surfacing, region/faction metadata, and narrative hooks needed to support player-facing setting context. |
| DCC-0350 | PF2E Ch.9 Playing the Game: audit core action economy, encounter flow, exploration, and downtime requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Produce detailed requirement gaps for turns/actions/reactions, initiative, movement, encounter states, exploration mode, and downtime procedures. |
| DCC-0351 | PF2E Ch.10 Game Mastering (player-impact subset): audit player-visible systems requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Scope only player-visible effects (DC transparency, reward feedback, hazard cues) and list required app features affecting player UX and campaign fairness. |
| DCC-0352 | PF2E Ch.11 Crafting & Treasure: audit crafting, item activation, treasure progression, and reward pipelines | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Identify required crafting workflows, material/cost tracking, item activation UX, and treasure distribution/rarity progression features. |
| DCC-0353 | PF2E Conditions Appendix: audit condition state model, stacking/exclusivity, and lifecycle event requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Define canonical condition schema, application/removal triggers, duration handling, and display/telemetry requirements for all player-affecting conditions. |
| DCC-0354 | PF2E Glossary/Index: audit in-app searchable rules reference and cross-linking requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Capture requirements for indexed rules lookup, keyword search, context-sensitive rule links, and audit-safe citation mapping in UI and API responses. |
| DCC-0355 | APG Ch.1 Ancestries & Backgrounds: audit new ancestries, versatile heritages, and rare background requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Enumerate feature needs for Catfolk/Kobold/Orc/Ratfolk/Tengu, Changeling/Dhampir/Planar Scions, and expanded/rare background support. |
| DCC-0356 | APG Ch.2 Classes: audit Investigator/Oracle/Swashbuckler/Witch and core-class expansion requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Map data model + UX/API requirements for APG class mechanics, new class resources, and expanded options for existing core classes. |
| DCC-0357 | APG Ch.3 Archetypes: audit multiclass and general archetype framework requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Define requirements for archetype acquisition, feat dependency chains, dedication rules, and character-state compatibility checks. |
| DCC-0358 | APG Ch.4+ remaining player options: audit feats/spells/expanded options from uncaptured TOC sections | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Complete feature-gap extraction for APG sections that continue beyond the abbreviated outline TOC capture. |
| DCC-0359 | Paragraph-level traceability audit: map handbook paragraphs to feature requirements and issue IDs | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Build paragraph-by-paragraph trace matrix from source handbook docs to app feature requirements; include source location metadata and linked issue references. |
| DCC-0360 | Full handbook review closure: consolidate all chapter/section findings into prioritized backlog slices | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Aggregate DCC-0341..0359 findings into prioritized implementation-ready epics without coding changes; preserve chapter/section/paragraph provenance. |
| DCC-0361 | PF2E GMG Ch.1 Gamemastery Basics (player-facing impact): audit encounter/exploration/downtime UX requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Translate GMG chapter 1 sections into player-visible app requirements (encounter pacing signals, exploration affordances, downtime action workflows, and rules adjudication transparency). |
| DCC-0362 | PF2E GMG Ch.2 Tools: audit creature/hazard/item-building outputs needed for app content pipelines | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Capture system requirements for importing/generated content from creature/hazard/item design guidance into dungeoncrawler data structures and controls. |
| DCC-0363 | PF2E GMG Ch.3 Subsystems: audit support requirements for influence/research/chase/infiltration/reputation/vehicles | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Define feature gaps for optional subsystem support and identify minimal state/UX/API primitives needed for each subsystem mode. |
| DCC-0364 | PF2E GMG Ch.4 Variant Rules: audit configurability requirements for variant toggles and campaign-level rule sets | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Identify app requirements for per-campaign rule variants (ability score variants, deep backgrounds, feat alternatives) and compatibility constraints. |
| DCC-0365 | PF2E Gods and Magic: audit deity/faith model requirements (edicts, anathema, domains, divine benefits) | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Produce requirements for deity affiliation tracking, faith-based mechanics, and player-facing rule effects across character sheets and encounter actions. |
| DCC-0366 | PF2E Gods and Magic spells/domains/items: audit divine option catalog and unlock requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Capture missing features for deity-linked spells, domains, feats, and sacred equipment including prerequisite/enforcement logic. |
| DCC-0367 | PF2E Guns and Gears Ch.1 Gears Characters: audit inventor/automaton/archetype requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Define requirements for inventor class systems, automaton ancestry data, and gear-focused archetype progression support. |
| DCC-0368 | PF2E Guns and Gears Ch.2 Gears Equipment: audit gadgets/siege/snares/vehicles content and mechanics requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Identify item schema, action hooks, and UI interactions required for technology equipment categories and vehicle operations. |
| DCC-0369 | PF2E Guns and Gears Ch.3 Guns Characters: audit gunslinger and firearm-archetype progression requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Capture class resources, feats, and progression mechanics needed for guns-focused character builds and multiclass compatibility. |
| DCC-0370 | PF2E Guns and Gears Ch.4 Guns Equipment: audit firearms/ammo/combination-weapon mechanics requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Define data/logic requirements for firearm traits, reload/ammo economy, misfire/maintenance behaviors, and item-level rule enforcement. |
| DCC-0371 | PF2E Guns and Gears Ch.5 Rotating Gear: audit region-aware technology availability and rarity gating requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Identify campaign/world metadata requirements to support region-specific tech access, rarity controls, and lore-accurate enablement. |
| DCC-0372 | PF2E Secrets of Magic Ch.1 Essentials: audit traditions/essences/schools and magical background requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Capture rules-engine and UI/API requirements to represent magical fundamentals and background-driven magic access. |
| DCC-0373 | PF2E Secrets of Magic Ch.2 Classes: audit magus/summoner/eidolon framework requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Define needed support for magus spellstrike-style workflows and summoner-eidolon linked state/progression mechanics. |
| DCC-0374 | PF2E Secrets of Magic Ch.3 Spells: audit expanded spell/focus/ritual catalog requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Identify ingestion, indexing, and runtime use requirements for expanded spell lists and associated cast-time constraints. |
| DCC-0375 | PF2E Secrets of Magic Ch.4 Magic Items: audit fulus/grimoires/tattoos/staves/spellhearts support requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Define item-category schema and activation/equipping requirements for new magic-item paradigms. |
| DCC-0376 | PF2E Secrets of Magic Ch.5 Unlimited Magic: audit optional magic subsystem requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Capture configurable support needs for shadow magic, true names, soulforged systems, and other optional subsystems. |
| DCC-0377 | PF2E Bestiaries 1-3: audit creature-catalog and encounter-design feature requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Define requirements for A-Z creature indexing, type/level filtering, statblock consumption, and encounter composition helpers. |
| DCC-0378 | PF2E Bestiaries appendices: audit ability glossary, trait taxonomy, rituals/languages, and type/level index requirements | Open | Unassigned | 2026-02-18 | 2026-02-18 | Review-only. Identify feature requirements for searchable appendix references and rules-linked trait/language systems. |
#### characters

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### config

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### content

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### css

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### module root files

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### js

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### project root/meta

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### src

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### templates

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### tests

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|


|---|---|---|---|---|---|

## Update Workflow
1. Add new items under **Active Issues** with status **Open**.
2. Keep **Last Updated** current when scope/status changes.
4. Link related commits/PRs/issues in **Notes** when available.
