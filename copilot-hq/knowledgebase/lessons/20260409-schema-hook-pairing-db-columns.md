# Lesson: Schema Hook Pairing — DB Column Updates Must Sync hook_schema()

## Lesson ID
20260409-schema-hook-pairing-db-columns

## Date
2026-04-09

## Source incidents
- **FR-RB-02** (2026-04-08): `age_18_or_older` column added to DB via `job_hunter_update_9039` but NOT added to `hook_schema()`. Flagged LOW at code review; PM accepted risk; fix deferred to forseti-release-d (commit `835d8290c`).
- **DungeonCrawler gap** (April 2026): Same pattern in `dc_chat_sessions` and `dc_campaign_characters.version` — update hook added columns without corresponding `hook_schema()` update.

## Pattern (the rule)

**When adding, renaming, or removing a DB column via an update hook, you MUST also update `hook_schema()` in the same commit.**

### Why both must stay in sync

| Install path | What Drupal uses |
|---|---|
| Fresh install (new site or `drush si`) | `hook_schema()` exclusively |
| Upgrade (existing site, `drush updb`) | `hook_update_N()` exclusively |

If they diverge:
- Fresh installs silently create tables without the new column → runtime crashes on first access.
- Upgrades produce tables with the new column but `hook_schema()` returns incorrect DDL → schema mismatch warnings and potential migration breakage.

## Detection

Run this after any `hook_update_N()` implementation to catch unmatched field operations:

```bash
# List all addField/changeField/dropField calls in the .install file
grep -n 'addField\|changeField\|dropField\|addIndex\|dropIndex' \
  web/modules/custom/<module>/<module>.install | head -30

# For each field name found above, verify it also appears in hook_schema()
grep -n '<column_name>' web/modules/custom/<module>/<module>.install | head -10
```

For `job_hunter` specifically:
```bash
JH=/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter
grep -n 'addField\|changeField\|dropField' "$JH/job_hunter.install" | head -20
```

Each `addField('table', 'column', ...)` must have a matching `'column' => [...]` entry in the `hook_schema()` definition for the same table.

## Fix template

When you find a gap (column in update hook but not in schema):

```php
// In hook_schema(), find the table definition and add:
'age_18_or_older' => [
  'description' => 'Whether the job seeker is 18 or older.',
  'type' => 'int',
  'size' => 'tiny',
  'not null' => TRUE,
  'default' => 0,
],
```

The column spec must match the spec in `hook_update_N()` exactly.

## Implementation checklist item

When writing a `hook_update_N()` that calls `$schema->addField()`:

- [ ] Open `hook_schema()` in the same `.install` file.
- [ ] Find the table definition.
- [ ] Add the matching column entry with identical type/default/not null spec.
- [ ] Commit both changes together.

## Affected roles
- `dev-forseti` (primary)
- `dev-dungeoncrawler` (same pattern confirmed in dungeoncrawler modules)

## References
- FR-RB-02 outbox: `sessions/agent-code-review/outbox/20260409-improvement-round-20260408-forseti-release-b.md`
- Fix commit: `835d8290c` (forseti-release-d, deferred)
