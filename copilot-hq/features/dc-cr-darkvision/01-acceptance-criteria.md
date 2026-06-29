# Acceptance Criteria (PM-owned)
# Feature: dc-cr-darkvision

## Gap analysis reference

Gap analysis: check for existing sense/vision system in dungeoncrawler_content.

```bash
grep -rl "darkvision\|sense\|vision" /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/ 2>/dev/null | head -10
```

All criteria below are `[NEW]` — no existing sense entity system found. Dev builds a standalone `sense` entity for darkvision referenced from ancestry data.

## KB references
- KB: no prior lessons for sense/vision systems in dungeoncrawler. Reference `knowledgebase/lessons/` for general Drupal entity patterns if needed.

## Happy Path

- [ ] `[NEW]` A `darkvision` sense entity exists in the dungeoncrawler data model (id: `darkvision`, type: `vision`).
- [ ] `[NEW]` The sense entity defines effects: no Concealed condition from darkness/dim light, no flat-footed from darkness, vision in darkness is black-and-white.
- [ ] `[NEW]` Ancestry data model supports a `senses` field (multi-value reference); dwarf ancestry references `darkvision`.
- [ ] `[NEW]` The visibility/lighting system checks `character.senses` before applying the Concealed condition or flat-footed from dim light/darkness.
- [ ] `[NEW]` Darkvision is distinct from Low-Light Vision (separate sense entity; Low-Light Vision sees dim light as bright but is blind in darkness).
- [ ] `[NEW]` Multiple ancestries can reference the same `darkvision` sense entity without duplication.

## Edge Cases

- [ ] `[NEW]` A character without darkvision still receives the Concealed condition in darkness (baseline behavior unchanged).
- [ ] `[NEW]` A character with darkvision does NOT receive Concealed in darkness but may still receive it from other sources (e.g., fog, invisibility).
- [ ] `[NEW]` Darkvision does not affect bright-light vision — normal vision rules apply in bright light regardless of this sense.

## Failure Modes

- [ ] `[TEST-ONLY]` Missing or null `senses` field on a character does not crash encounter visibility checks.
- [ ] `[TEST-ONLY]` Adding darkvision to an existing character entity at leveling or ancestry swap does not corrupt prior state.

## Permissions / Access Control

- [ ] Anonymous user: character sheet viewer respects darkvision display (if character sheet is public-facing).
- [ ] Authenticated user: player sees darkvision trait on their character sheet.
- [ ] Admin: can add/edit sense entities via Drupal admin UI.

## Data Integrity

- [ ] No data loss if darkvision sense entity is updated (references from ancestry remain valid).
- [ ] Rollback path: remove darkvision sense reference from ancestry; no schema migration needed.

## Definition of Done

- `darkvision` sense entity exists and is referenceable from ancestry.
- Encounter visibility check correctly bypasses Concealed for darkvision-bearing characters in darkness.
- All criteria above pass QA verification.

## Knowledgebase check
- Related lessons/playbooks: none found for sense entities in dungeoncrawler. Check `knowledgebase/lessons/` for general Drupal entity reference patterns.
