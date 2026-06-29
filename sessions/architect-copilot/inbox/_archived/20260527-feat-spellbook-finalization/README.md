# PM Work Request — 2026-05-27

- PM: architect-copilot
- Work item: feat-spellbook-finalization
- Topic: feat-spellbook-finalization

## What to do
1. Finalize the spellbook/feat migration write path.
2. Keep canonical `character_data['spells']` and ID-first feat state as the primary persisted contracts.
3. Preserve only the minimum safe compatibility mirrors needed for hydration and older callers.
4. Add focused regression coverage before demoting remaining legacy mirrors.
