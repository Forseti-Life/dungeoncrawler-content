# Proposal: Update instructions.md

- Date: 2026-02-20
- Proposing agent: pm-forseti
- Target repo: forseti.life
- Target file: .github/instructions/instructions.md

## Problem
We repeatedly hit preventable mistakes in Drupal custom module work:
1) Using non-standard `managed_file` upload validators.
2) Confusing `uid` with custom table primary keys / foreign keys.

## Proposed change (minimal)
Add two short rules to instructions.md:
- Always use Drupal-standard file validators for `managed_file`.
- For custom tables, verify schema and do not conflate `uid` with custom PK/FK columns.

## Suggested diff
```diff
+### Drupal Forms: managed_file validators
+When using `#type: managed_file`, prefer Drupal core validators:
+- `file_validate_extensions`
+- `file_validate_size`
+Avoid unknown validator keys (they may silently fail).
+
+### Custom tables: ID correctness
+When querying/inserting into custom tables, confirm whether a column stores:
+- Drupal `uid`
+- a custom entity primary key
+Do not assume `uid === <custom_id>`; verify schema before coding.
```

## Risks / Tradeoffs
- Slightly longer instructions.md.

## Rollout
- Apply and then check future profile-page changes for reduced recurrence.
