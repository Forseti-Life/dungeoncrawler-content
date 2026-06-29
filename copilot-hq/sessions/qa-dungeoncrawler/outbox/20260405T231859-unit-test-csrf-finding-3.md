- Status: done
- Summary: Code-level APPROVE (provisional) — live e2e BLOCKED — env unreachable (localhost:8080 HTTP 000). CSRF FINDING-3 fixes verified in code. Dev commit: `3f7c8300`.

## Verification evidence

### Route audit (PASS — 8/8 routes)
All 8 FINDING-3 routes verified in `dungeoncrawler_content.routing.yml`:

| Route | Protection | Method |
|---|---|---|
| `dungeoncrawler_content.game_objects.post` | `_csrf_token: TRUE` | POST (split from GET) |
| `dungeoncrawler_content.campaign_create.post` | `_csrf_token: TRUE` | POST (split from GET) |
| `dungeoncrawler_content.character_step.post` | `_csrf_token: TRUE` | POST (split from GET) |
| `dungeoncrawler_content.character_save_step` | `_csrf_token: TRUE` | POST |
| `dungeoncrawler_content.api.dice_roll` | `_csrf_request_header_mode: TRUE` + `_permission: access dungeoncrawler characters` | POST JSON |
| `dungeoncrawler_content.api.rules_check` | `_csrf_request_header_mode: TRUE` + `_permission: access dungeoncrawler characters` | POST JSON |
| `dungeoncrawler_content.api.character_save` | `_csrf_request_header_mode: TRUE` | POST JSON |
| `dungeoncrawler_content.campaign_select_character` | `_csrf_token: TRUE` | POST/GET |

### Split-route pattern (PASS)
GET display variants (`game_objects`, `campaign_create`, `character_step`) correctly split into separate GET routes without CSRF — no 403 regression risk.

### Permission upgrade (PASS)
`dice_roll` and `rules_check` now require `_permission: 'access dungeoncrawler characters'` — unauthenticated POST access removed.

## APPROVE: CSRF FINDING-3 — provisional code-level

live e2e BLOCKED — env unreachable (localhost:8080 HTTP 000). Flagged for live retest when env is restored.

## Regression checklist updated
`org-chart/sites/dungeoncrawler/qa-regression-checklist.md` entry marked complete.

## Blockers
- None (code verified)

## Escalation
- pm-dungeoncrawler: env unreachable at localhost:8080; composer install + site-up should be scheduled before full live Gate 2 run.
