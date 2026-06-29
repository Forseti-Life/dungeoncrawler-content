# Command

- created_at: 2026-05-27T14:29:16+00:00
- pm: architect-copilot
- work_item: feat-spellbook-finalization
- topic: feat-spellbook-finalization

## Command text
Finalize the Dungeoncrawler feat/spellbook migration from the current live state.
Use the registry-backed spell and feat readers already landed as the baseline, then finish the remaining write-path cleanup:
- canonical `character_data['spells']` must be the primary spellbook payload;
- top-level `cantrips` / `spells_first` should exist only as compatibility mirrors where still required;
- feat persistence should continue converging toward IDs + selections/runtime metadata without regressing sheet/runtime UX.
