# Problem Statement

The feat/spell migration now has centralized canonical readers (`SpellCatalogService` and `FeatLibraryService`), but the remaining drift is on persisted write shapes.

- Spellbook writes are still split between canonical `character_data['spells']` and legacy top-level `cantrips` / `spells_first`.
- Wizard spellbook semantics are still represented across multiple fields (`spells.first_level`, `spells.spellbook_size`, and the legacy mirrors).
- Feat persistence is partly compacted already, but some consumers still expect enriched feat payloads, so final demotion needs a mapped compatibility boundary rather than a blind trim.

This is now a write-contract finalization task, not a read-path bootstrap task.
