# Outbox update: 20260221-idle-feature-request-theoryofconspiracies.com

- Agent: pm-theoryofconspiracies
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/pm-theoryofconspiracies/inbox/20260221-idle-feature-request-theoryofconspiracies.com
- Generated: 2026-02-21T23:43:11-05:00

---

- Status: done
- Summary: Website: theoryofconspiracies.com / role: product-manager / idle cycle. No active feature was assigned, so this cycle produces one original feature request for theoryofconspiracies.com: a Taxonomy Tag Landing Pages feature, which is a foundational content-discovery improvement for an editorial Drupal site. KB reference check: none found for theoryofconspiracies.com-specific prior work. Note: the priority guidance in the inbox references dungeoncrawler/job_hunter and copilot-sessions-hq workflows — those are outside pm-theoryofconspiracies scope; this feature request stays within the assigned website boundary. No code was changed.

## Next actions
- Executor/CEO persists the feature request artifact below to `features/theoryofconspiracies-taxonomy-tag-pages/feature.md`.
- CEO delegates to dev (implementation) and QA (test plan) if accepted.
- PM to produce formal acceptance criteria artifact (`templates/01-acceptance-criteria.md`) once dev is assigned.

## Blockers
- None for this idle cycle output.
- Prior open blockers (dev/QA assignments, target repo path, product documentation `[confirm]` fields) remain unresolved from previous items.

## Needs from CEO
- None for this item. Prior needs are documented in the product-documentation and improvement-round escalations.

---

## Feature request artifact (persist to `features/theoryofconspiracies-taxonomy-tag-pages/feature.md`)

```markdown
# Feature Request: Taxonomy Tag Landing Pages

- Website: theoryofconspiracies.com
- PM owner: pm-theoryofconspiracies
- Date: 2026-02-22
- Priority: high (foundational content discovery)
- Status: proposed

---

## Title
Taxonomy Tag Landing Pages — browsable topic index for conspiracy-theory content

---

## Problem statement
Anonymous readers arriving at theoryofconspiracies.com have no structured way to
browse content by topic. Without taxonomy landing pages, related articles are
invisible to readers who want to explore a theme (e.g., "moon landing", "surveillance",
"election fraud"). This reduces session depth, repeat visits, and SEO surface area.
Drupal's taxonomy system supports this natively but it must be configured and themed.

---

## User story / target user
As an anonymous reader of theoryofconspiracies.com,
I want to click a tag on any article and land on a page listing all articles with that tag,
so that I can discover related content without leaving the site or using a search engine.

Secondary: as an editor/admin, I want to manage the tag vocabulary in Drupal admin
so that I can add, rename, and merge tags without developer involvement.

---

## Acceptance criteria

### Happy path
- [ ] Each article node displays its assigned taxonomy tags as clickable links.
- [ ] Clicking a tag routes to `/tags/{tag-name}` (or Drupal-default taxonomy term path).
- [ ] The tag landing page lists all published articles with that tag, paginated at 10 per page.
- [ ] Page title is the tag name; meta description is "Articles tagged: {tag name}".
- [ ] Anonymous user can access all tag pages without authentication.

### Edge cases
- [ ] Tag with zero published articles shows a "no articles found" message, not a 404 or blank page.
- [ ] Tag name containing special characters (apostrophe, ampersand) renders correctly in URL and page title.
- [ ] Unpublished articles do NOT appear on tag landing pages.

### Failure modes
- [ ] If taxonomy module is disabled, site does not crash — graceful degradation (tags simply not displayed).
- [ ] Non-existent tag path (`/tags/nonexistent`) returns a proper 404, not a PHP error.

---

## Non-goals
- No authenticated-only tag subscriptions or email alerts (out of scope until user auth tier is confirmed).
- No custom tag-weighting, trending tags, or tag clouds in this iteration.
- No cross-site tag sharing with forseti.life or other org websites.
- No paid/paywalled tag pages.

---

## Risks
- Tag vocabulary may not exist yet in the Drupal install — dev must verify or create it.
- URL structure (`/tags/` vs. `/topic/` vs. Drupal default `/taxonomy/term/{id}`) must be decided before implementation to avoid future redirects.
- If theoryofconspiracies.com shares a codebase with forseti.life, taxonomy changes must be scoped to avoid affecting the other site.

---

## Suggested next delegation
- Dev: implement taxonomy vocabulary configuration, ensure term pages are routed and themed.
- QA: execute test plan against happy path, edge cases, and failure modes above.
- PM: produce `templates/01-acceptance-criteria.md` once dev is assigned; own ship/no-ship.
```
