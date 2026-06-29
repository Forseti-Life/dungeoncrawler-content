# Acceptance Criteria (PM-owned)
# Feature: dc-home-suggestion-notice

## Gap analysis reference

Gap analysis: quick check for existing home page block or front-page content region in dungeoncrawler_content.

```bash
grep -rl "home\|front.page\|suggestion" /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/ 2>/dev/null | head -10
```

All criteria below are `[NEW]` or `[CONTENT]` — this is a content/UI addition only. No existing suggestion-notice block found. Dev builds a simple block or edits the front page template.

## KB references
- KB: no prior lessons for home page content blocks in dungeoncrawler. Reference `knowledgebase/lessons/` for general Drupal block patterns if needed.

## Happy Path

- [ ] `[NEW]` A short notice is displayed on the Dungeoncrawler home page visible to anonymous and authenticated users.
- [ ] `[CONTENT]` Notice text (or equivalent): "We are actively implementing player suggestions. Keep the ideas coming!"
- [ ] `[NEW]` The notice is positioned prominently on the home page (above the fold or in a banner/info region).
- [ ] `[NEW]` The notice is dismissible OR always visible (PM decision: always visible is acceptable for simplicity).
- [ ] `[NEW]` The notice does not break the existing home page layout on desktop or mobile.

## Edge Cases / Failure Modes

- [ ] `[TEST]` Verify notice renders correctly for anonymous users (no login required).
- [ ] `[TEST]` Verify notice renders correctly for authenticated users.
- [ ] `[TEST]` Verify home page HTTP response is still 200 after change.
- [ ] `[TEST]` Verify no JS errors introduced on page load.

## Definition of Done

- Notice text is visible on the dungeoncrawler home page at `http://localhost:8080/` (dev) and `https://dungeoncrawler.forseti.life/` (prod).
- All criteria above pass QA verification.
- `drush cr` run after any template/block changes.

## Rollback

- Revert the block config or template change.
- Run `drush cr`.
- No database schema changes expected.
