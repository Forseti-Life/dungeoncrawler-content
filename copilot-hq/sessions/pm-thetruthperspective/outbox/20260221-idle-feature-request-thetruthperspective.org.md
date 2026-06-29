# Outbox update: 20260221-idle-feature-request-thetruthperspective.org

- Agent: pm-thetruthperspective
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/pm-thetruthperspective/inbox/20260221-idle-feature-request-thetruthperspective.org
- Generated: 2026-02-21T23:44:02-05:00

---

- Status: done
- Summary: Idle cycle feature request generated for thetruthperspective.org. Since the site is currently dormant with no active modules, this feature request proposes the foundational first module — article browsing and reading — which is the minimum viable surface area needed to activate the PM/Dev/QA loop and establish a scoreboard baseline. The priority guidance in the command points toward copilot-sessions-hq workflow improvements; this feature request targets thetruthperspective.org as scoped, but a workflow improvement note is included. KB reference: no prior lessons found for this site.

## Next actions
- Executor persists feature request artifact below to `sessions/pm-thetruthperspective/artifacts/feature-request-article-browsing.md`
- CEO decides whether to activate this feature request (which requires adding `thetruthperspective_content` to `module-ownership.yaml` and removing dormant status)
- If activated: delegate BA (requirements gathering), then Dev (implementation), then QA (test plan)

## Blockers
- None for artifact production. Activation requires CEO to lift dormancy for thetruthperspective.org.

## Needs from CEO
- None blocking this deliverable. Activation decision is optional follow-on.

---

## Feature Request Artifact

**Intended path:** `sessions/pm-thetruthperspective/artifacts/feature-request-article-browsing.md`

```markdown
# Feature Request: Article Browsing and Reading — thetruthperspective.org

- Date: 2026-02-22
- Author: pm-thetruthperspective
- Site: thetruthperspective.org
- Proposed module: thetruthperspective_content
- KB reference: no prior lessons found for this site; none applicable.

## Title
Article Browsing and Reading (foundational content module)

## Problem statement
thetruthperspective.org has no active modules and no content surface area.
Visitors arriving at the site have nothing to read or browse. The PM/Dev/QA
loop cannot be exercised, the quality scoreboard cannot be baselined, and
the agent team cannot produce any required outputs until at least one content
module exists. This feature establishes the minimum viable product: a
Drupal content type for articles, a listing page, and a detail page.

## User story / target user
As an anonymous visitor to thetruthperspective.org,
I want to browse a list of published articles and read individual articles,
so that I can consume the site's editorial content without needing an account.

Secondary user: site editor/admin
As a site editor, I want to create, edit, and publish articles from the
Drupal admin UI, so that content is available to anonymous visitors.

## Acceptance criteria
1. A Drupal content type `article` exists with fields:
   - Title (required, plain text)
   - Body (required, rich text / text_long)
   - Published date (date field, required)
   - Author byline (plain text, optional)
   - Tags (taxonomy term reference, optional)
2. Anonymous users can browse a paginated listing page at `/articles` showing
   titles, published dates, and truncated body teaser (150 chars max).
3. Anonymous users can view a full article detail page at `/articles/[slug]`.
4. Unpublished articles are not visible to anonymous users.
5. Admin users can create, edit, publish, and unpublish articles via Drupal
   admin UI (`/admin/content`).
6. Listing page shows only published articles, ordered by published date
   descending.
7. No user registration or login is required to read articles.

### Edge cases
- Empty listing page (no published articles): displays a "no articles yet"
  message rather than a blank page or error.
- Article with no tags renders correctly (tags field is optional).
- Very long article title does not break listing page layout.

### Failure modes
- Unpublished article URL returns 403 for anonymous users (not 404, not 200).
- Non-existent article URL returns 404.

## Non-goals
- No comments or user interaction on articles (v1)
- No search or filtering beyond tag taxonomy (v1)
- No paid/subscriber-only content
- No social sharing widgets
- No email newsletter integration

## Risks
- Low: Standard Drupal content type and view — well-understood pattern,
  low implementation risk.
- Medium: URL slug strategy ([node:title] vs custom pathauto pattern)
  must be decided before dev starts to avoid URL migration later.
- Low: Permissions must be verified — anonymous role must have
  "access content" permission; confirm this is set on install.

## Suggested next delegation
1. BA: Define field-level requirements, URL slug strategy, and
   pathauto pattern. Confirm taxonomy vocabulary name and terms.
2. Dev: Implement content type, fields, views listing page,
   pathauto config, and permissions. Write hook_install for
   default config.
3. QA: Test plan covering happy path (browse + read), unpublished
   access denial, empty listing, and edge cases listed above.
```
