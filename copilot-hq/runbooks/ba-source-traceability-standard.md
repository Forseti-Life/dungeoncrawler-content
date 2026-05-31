# Runbook: BA Source Traceability Standard

Use this standard whenever a BA seat works from formal source material such as:
- product requirements documents
- rulebooks / reference corpora
- policy manuals
- technical standards
- audit packs / design baselines

## Required tracking stack

1. **Source ledger**
   - one record per source document
   - tracks requirements status, issue mapping status, feature mapping status, and release handoff status

2. **Source-object tracker**
   - one row per chapter / section / logical source object
   - tracks whether each source object has actually been analyzed

3. **Audit worksheet**
   - detailed checklist proving the object was fully reviewed internally
   - headings, subsections, paragraph groups, or equivalent

4. **Execution cursor** (optional)
   - only for chunked scans or rolling ingestion
   - never used as proof of completeness

## State model

### Requirements
- `pending`
- `in_progress`
- `complete`
- `skipped`

### Issue mapping
- `unmapped`
- `partial`
- `mapped`

### Feature mapping
- `unmapped`
- `partial`
- `mapped`

### Release handoff
- `pending`
- `submitted`
- `triaged`
- `activated`
- `released`

## Minimum workflow

1. Register the source document in the source ledger.
2. Register the source objects in the source-object tracker.
3. Create/update the audit worksheet for the source object being analyzed.
4. Produce the BA artifact (requirements doc, summary, issue set, feature stubs, etc.).
5. Update the source-object tracker.
6. Update the source ledger with downstream traceability.
7. If release-trackable work was produced, record the handoff state.

## Enforcement rules

- A requirements artifact without ledger + tracker updates is incomplete.
- A feature derived from source material must preserve traceability back to that source.
- A release handoff without source traceability is weakly controlled work and should be treated as a process gap.

## Templates

- `templates/ba-source-ledger.template.json`
- `templates/ba-source-object-tracker.template.md`
