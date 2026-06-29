- Status: done
- Summary: dc-home-suggestion-notice suite activation is complete. 2 suite entries added to `qa-suites/products/dungeoncrawler/suite.json`: (1) `dc-home-suggestion-notice-phpunit` (unit/functional PHPUnit) covering TC-HSN-03 (notice text in anonymous home page HTML) and TC-HSN-04 (notice text for authenticated users), and (2) a role-url-audit entry covering TC-HSN-01/02 (HTTP 200 for anonymous and authenticated roles on /home). No new qa-permissions.json rules added — `/home` is already covered by the existing `public-pages` rule (anonymous allow). Test plan note: TC-HSN-05 (no JS errors on page load) is a manual verification step (requires browser/Playwright; static content only, low risk). Suite validated OK.

## Next actions
- Dev implements the home page suggestion notice block; QA runs PHPUnit and role-url-audit at Stage 4
- Stage 0: verify `public-pages` rule still covers `/home` after implementation and no new routes are added

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 4
- Rationale: Static content block with minimal regression risk. Suite coverage ensures the notice renders and existing home page routing is not broken by the block addition.
