---
date: 2026-04-11
team: dungeoncrawler
severity: medium
---

# dc_requirements.feature_id Has NOT NULL Constraint

## Issue
`dc_requirements.feature_id` column has a NOT NULL constraint with a default empty string.

When using `drush sql-query "UPDATE ... SET feature_id=NULL ..."` the query silently fails (drush swallows the MySQL error 1048).

## Fix
Use direct MySQL for resets that set feature_id to empty:
```bash
mysql -uroot -p<password> dungeoncrawler -e \
  "UPDATE dc_requirements SET status='pending', feature_id='' WHERE ..."
```

## Context
Discovered 2026-04-11 during backlog requirements backfill. 90 rows for deferred books (b2, b3, gng, som) persisted as `in_progress` after drush batch reset appeared to succeed silently.
