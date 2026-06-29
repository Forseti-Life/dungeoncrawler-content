# Acceptance Criteria

- Canonical spellbook writes are authored through `character_data['spells']` first, not through legacy top-level spell arrays.
- Any remaining top-level `cantrips` / `spells_first` mirrors are clearly compatibility-only and derive from the canonical spell payload.
- Wizard spellbook persistence remains stable for `first_level`, `spellbook_size`, and spell-slot/resource normalization.
- Feat persistence continues to move toward ID-first storage while preserving sheet/runtime description hydration through canonical lookup.
- Regression coverage exists for the spellbook write path and the affected feat persistence path.
