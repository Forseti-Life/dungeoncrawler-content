# PHP Fatal errors in Apache log: forseti (2 active, 3 in 24h)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-25T18:50:09Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Active window: last 30 minutes.
Recent lines:
```
[Sat Apr 25 17:23:29.782830 2026] [php:notice] [pid 3828222] [client 54.90.37.221:59096] Uncaught PHP Exception Drupal\Core\Database\DatabaseExceptionWrapper: "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'forseti_prod.group_relationship' doesn't exist: SELECT "base_table"."id" AS "id", "base_table"."id" AS "base_table_id"
FROM
"group_relationship" "base_table"
INNER JOIN "group_relationship_field_data" "group_relationship_field_data" ON "group_relationship_field_data"."id" = "base_table"."id"
WHERE ("group_relationship_field_data"."entity_id" = :db_condition_placeholder_0) AND ("group_relationship_field_data"."plugin_id" LIKE :db_condition_placeholder_1 ESCAPE '\\'); Array
(
    [:db_condition_placeholder_0] => 1600
    [:db_condition_placeholder_1] => group\_membership
)
" at /var/www/html/forseti/web/core/modules/mysql/src/Driver/Database/mysql/ExceptionHandler.php line 96

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
- Status: pending
