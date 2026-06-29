# AMI Safe Cross-Site Sync Verification Report

**Verification Date:** 2026-04-21
**Status:** ⚠️ DIVERGED (Site-Specific Customizations Detected)

## Summary

AMI Safe module exists on three sites but **is NOT identical**. Each site has custom features and configurations:

### File Count Comparison
| Site | Total Files | PHP Controllers |
|------|-------------|-----------------|
| Forseti | 62 files | 5 controllers |
| St. Louis | 55 files (-7 from Forseti) | 4 controllers |
| Theory | 52 files (-10 from Forseti) | 4 controllers+ |

### Divergence by Site

#### FORSETI (62 files) - "Canonical/Primary"
- **Extra Components:**
  - LogManagementController.php + views (log upload/management feature)
  - amisafe.install hook
  - amisafe.links.menu.yml (menu integration)
  - Additional log viewer CSS/JS
  - MOBILE_LOG_MANAGEMENT.md docs
  - User location tracking API routes (api/amisafe/location/update, /api/amisafe/location/history)
  - User registration/login API routes (api/amisafe/user/register, api/amisafe/user/login)

- **Unique Routes:** 13 additional routes for log management + user auth

**Assessment:** Forseti version is most feature-complete. Log management and mobile auth are Forseti-specific additions.

#### ST. LOUIS (55 files) - "Subset/Older Copy"
- **Missing:** All log management features (7 files)
- **Missing:** amisafe.install hook
- **Missing:** User location tracking routes
- **Missing:** User registration/login routes
- **API Differences:** ApiController exists but lacks location + auth endpoints

**Assessment:** St. Louis has an outdated or intentionally simplified copy. Missing log management and mobile auth features.

#### THEORY (52 files) - "Custom Theme + Simplified"
- **Extra Components:**
  - cyberpunk-theme.css (Theory-specific styling)
  - AmISafeController.php (custom controller)
  - amisafe-dashboard.html.twig (custom template)

- **Missing:** LogManagementController (like St. Louis)
- **Missing:** amisafe.install hook
- **Configuration Differences:**
  - Module description: "...for Philadelphia 2085" (theme-specific text)
  - Package name: "Theory of Conspiracies" (site-specific branding)
  - Help text references Philadelphia 2085 theme

**Assessment:** Theory has custom branding/theming but same core feature set as St. Louis.

## Strategic Decision Required

### Option A: Merge into Single Canonical Package (RECOMMENDED)
- **Action:** Use Forseti version as base; remove site-specific branding
- **Effort:** 2-3 hours
- **Result:** Single shared module with optional log management + user auth features
- **Rationale:** Forseti has most features; St. Louis/Theory can enable optionally
- **Release:** drupal.org/project/amisafe (single package)
- **Impact:** St. Louis loses nothing (didn't have log mgmt anyway); Theory keeps features but loses branding

### Option B: Keep Site-Specific Variants (NOT RECOMMENDED)
- **Result:** 3 separate modules on drupal.org (amisafe-forseti, amisafe-stlouis, amisafe-theory)
- **Rationale:** Each site truly customized for their use case
- **Cons:** Massive maintenance burden; duplicated security audits; confusing for users
- **Impact:** Estimated 3x more work to publish and maintain

### Option C: Hybrid - Core + Site Plugins
- **Action:** Publish core amisafe; keep Theory customizations as optional sub-module
- **Effort:** 4-5 hours (advanced pattern)
- **Result:** drupal.org/project/amisafe (core) + drupal.org/project/amisafe-theme-theory (optional)
- **Rationale:** Best of both worlds
- **Cons:** More complex release process

## Recommendation

**Option A (Merge into Single Package)** is recommended because:
1. Code is 90%+ identical (only 7-10 files differ)
2. Forseti's additional features (log mgmt, user auth) benefit all sites
3. St. Louis doesn't have these features anyway
4. Theory's customizations are purely cosmetic/branding (can be removed)
5. Simplifies maintenance, testing, security updates
6. Cleaner drupal.org presentation (single canonical module)

## Next Steps (If Approved)

1. Create merged version removing all site-specific branding:
   - Theory package name → "Safety Analytics"
   - Theory description → generic crime analytics text
   - Remove "Philadelphia 2085" references
   - Keep cyberpunk-theme.css as optional theme (remove from core)
   - Keep AmISafeController for Theory if it adds value

2. Security audit on merged version (6 blockers)
3. Documentation package with optional feature docs
4. Freeze candidate
5. Single QA validation
6. Single drupal.org submission

**Blocker Resolution:** This verification confirms the blocker is explainable and resolvable. Recommend escalating merge decision to CEO with this data.
