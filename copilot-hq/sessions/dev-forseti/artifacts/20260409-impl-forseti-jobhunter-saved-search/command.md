# Implement: forseti-jobhunter-saved-search

- Feature: forseti-jobhunter-saved-search
- Release: 20260409-forseti-release-g
- ROI: 12
- Dispatched by: pm-forseti

## Context

Users currently re-enter search parameters every visit. This feature allows saving Google Jobs
search parameters (keywords, location, radius, job type) for one-click re-run.

## Acceptance criteria

See: `features/forseti-jobhunter-saved-search/01-acceptance-criteria.md`

Key points:
- "Save this search" button on Google Jobs search results page
- Saved searches stored in `jobhunter_saved_searches` table (uid + name + params_json)
- List of saved searches accessible from a new `/jobhunter/saved-searches` page
- One-click run reloads Google Jobs search with saved params
- Max 10 saved searches per user; UI shows count and blocks at limit with message

## Security requirements

- Auth required; user can only read/write their own saved searches
- POST endpoint for save/delete uses CSRF split-route pattern
- `params_json` validated against allowed field list (no arbitrary SQL injection vector)

## Done when

- Save, list, and delete saved searches work end-to-end
- Re-running a saved search populates correct results
- Anon cannot access saved searches (403)
- commit hash + rollback steps in outbox

## Rollback

Revert this commit + `drush updb` (reverse schema) + `drush cr`
