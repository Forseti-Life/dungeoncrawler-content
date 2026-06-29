# PHP Fatal errors in Apache log: forseti (154 active, 362 in 24h)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-25T20:30:09Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Active window: last 30 minutes.
Recent lines:
```
[Sat Apr 25 20:18:25.373388 2026] [php:notice] [pid 1182672] [client 195.7.11.63:1794] Uncaught PHP Exception Drupal\Core\Database\DatabaseExceptionWrapper: "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'forseti_prod.path_alias' doesn't exist: SELECT "base_table"."id" AS "id", "base_table"."path" AS "path", "base_table"."alias" AS "alias", "base_table"."langcode" AS "langcode"
FROM
"path_alias" "base_table"
WHERE ("base_table"."status" = :db_condition_placeholder_0) AND ("base_table"."alias" LIKE :db_condition_placeholder_1 ESCAPE '\\') AND ("base_table"."langcode" IN (:db_condition_placeholder_2, :db_condition_placeholder_3))
ORDER BY "base_table"."langcode" ASC, "base_table"."id" DESC; Array
(
    [:db_condition_placeholder_0] => 1
    [:db_condition_placeholder_1] => /taxonomy/term/9847/feed
    [:db_condition_placeholder_2] => en
    [:db_condition_placeholder_3] => und
)
" at /var/www/html/forseti/web/core/modules/mysql/src/Driver/Database/mysql/ExceptionHandler.php line 96

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
