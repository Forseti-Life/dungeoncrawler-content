# PHP Fatal errors in Apache log: forseti (356 occurrences)

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-18T18:00:48Z
- Source: system health check

## Issue

PHP fatal/parse/exception errors found in /var/log/apache2/forseti_error.log.

Recent:
```
[Sat Apr 18 17:51:43.230132 2026] [php:notice] [pid 1395132] [client 54.90.37.221:40738] Uncaught PHP Exception Drupal\Core\Database\DatabaseExceptionWrapper: "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'j.uid' in 'field list': SELECT "j"."id" AS "id", "j"."job_title" AS "job_title", "j"."uid" AS "uid"
FROM
"jobhunter_job_requirements" "j"
WHERE "j"."id" = :db_condition_placeholder_0; Array
(
    [:db_condition_placeholder_0] => 1
)
" at /var/www/html/forseti/web/core/modules/mysql/src/Driver/Database/mysql/ExceptionHandler.php line 96

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
