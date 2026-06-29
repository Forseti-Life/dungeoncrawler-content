# Risk Assessment

- **Low implementation risk:** the authoritative CRB text is already present locally in `raw_text_block`, so the repair is grounded in existing source text rather than inferred prose.
- **Primary risk:** battle-form overrides carry dense rules text, so editing the wrong fields could accidentally drift form stats or heightening metadata.
- **Secondary risk:** regenerating the CRB intermediary and importing before the extractor change lands would preserve the shortened description, so sequencing matters.
- **Duplicate-row risk:** low. The canonical live row is hyphenated (`aerial-form`) and underscore spell-ID compatibility is already handled at read time.
- **Recommended mitigation:** keep the fix surgical to the `aerial_form` override, add a regression test, regenerate the CRB intermediary, and then import spells sequentially so the live row picks up the corrected description.
