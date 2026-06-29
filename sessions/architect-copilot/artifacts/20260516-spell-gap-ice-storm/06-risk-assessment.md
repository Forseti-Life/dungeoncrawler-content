# Risk Assessment

- **Low implementation risk:** the authoritative full text is already present in the local APG source and in the stored `raw_text_block`, so the fix is grounded in existing local source data rather than external guesswork.
- **Primary risk:** editing the override incorrectly could drift spell metadata beyond the description field if the patch touches unrelated keys in `SOURCE_BACKED_OVERRIDES`.
- **Secondary risk:** if only the DB row is hand-patched without correcting the override and intermediary, the next spell import will reintroduce the bad shortened description.
- **Duplicate-row risk:** low. The live registry currently has only `ice-storm`; underscore compatibility is handled at read time by spell ID normalization.
- **Recommended mitigation:** keep the change surgical to the `ice_storm` source-backed override, regenerate the APG intermediary immediately, then re-import spells so the canonical DB state and packaged seed data stay aligned.
