# Feature Brief: Languages System

- Work item id: dc-cr-languages
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2 (blocks dc-cr-elf-ancestry and dc-cr-elf-heritage-cavern; accepted for 20260407-dungeoncrawler-release-b)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: game-mechanic
- Created: 2026-02-26

## Goal

Track which languages a character can speak, read, and write. Ancestry and Intelligence modifier determine starting languages; additional languages can be gained through feats and class features. Languages gate certain skill checks, social interactions, and lore access in dungeoncrawler scenarios.

## Source reference

> "Details on languages also appear in this chapter." (Chapter 2: Ancestries & Backgrounds)

## Implementation hint

Field on the character entity: `languages[]` (list of string identifiers). Content type or taxonomy: `language` with fields for language name, script, and typical speakers. Ancestry data should include default languages. Intelligence bonus at character creation adds free language slots. Simple UI: multi-select from available languages list.

## Security acceptance criteria

- Security AC exemption: game-mechanic content stored per character; no new routes, no user-facing input beyond character creation form already in scope

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
