# AC Traceability Brief: forseti-community-incident-report

- Feature: forseti-community-incident-report
- Author: ba-forseti
- Date: 2026-04-12
- Release: 20260412-forseti-release-f
- Project: PROJ-006 (Community Safety)

---

## AC Traceability Table

### AC-1: New `community_incident` content type

| Item | Detail |
|---|---|
| **Module/file** | New module: `sites/forseti/web/modules/custom/community_incident_report/` — content type defined via `config/install/node.type.community_incident.yml` + field config YMLs |
| **Fields to configure** | `field_incident_type` (taxonomy_term ref, required), `field_location` (string, address), `field_lat` / `field_lng` (skip per feature notes — plain address only for v1), `field_occurred_at` (datetime), `field_photo` (image, optional, 5MB max), `body` (description), `uid` (author — standard node field) |
| **Taxonomy** | Vocabulary `incident_type` with terms: `unsafe_lighting`, `suspicious_activity`, `hazard`, `other` — via `config/install/taxonomy.vocabulary.incident_type.yml` + default terms |
| **Dependency** | None — pure new module, no cross-module dependency |
| **Verify** | `drush config:import --partial` after module enable; `drush php-eval "print_r(array_keys(\Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple()));"` → includes `community_incident`; visit `/admin/structure/types/manage/community_incident` → HTTP 200 |
| **Risk** | Low. Standard config-driven content type. |

---

### AC-2: Authenticated report form at `/community/report`

| Item | Detail |
|---|---|
| **Module/file** | New: `community_incident_report/community_incident_report.routing.yml` — route `community_incident_report.report_form`; controller or `node_add` alias. Prefer a custom `ReportForm.php` extending `ContentEntityForm` for cleaner permission wiring. |
| **Auth gate** | Route `_permission: 'submit community incident reports'`; Drupal redirects to login on 403 for anonymous (standard `access_denied` subscriber behavior) |
| **CSRF** | Drupal Form API provides CSRF token automatically for all `FormBase` subclasses — no custom CSRF needed |
| **Dependency** | AC-1 (content type must exist), AC-5 (permission must be defined in `community_incident_report.permissions.yml`) |
| **Verify** | `curl -s https://forseti.life/community/report` → HTTP 302 to `/user/login?destination=/community/report`; authenticated session → HTTP 200 with form |
| **Risk** | Low. Standard Drupal form + route pattern. |

---

### AC-3: Public listing page at `/community-reports`

| Item | Detail |
|---|---|
| **Module/file** | Drupal Views — create a view `community_reports_listing` of `community_incident` nodes (published only), sorted by `created` DESC, paged at 20. Expose via `config/install/views.view.community_reports_listing.yml` |
| **Access** | View page access: public (`_access: 'TRUE'` or no permission check — anonymous can read published nodes) |
| **Dependency** | AC-1 (content type), AC-6 (moderation: only published nodes appear) |
| **Verify** | `curl -s https://forseti.life/community-reports` → HTTP 200; page shows published `community_incident` nodes; anonymous browser session → accessible |
| **Risk** | Low. Views config export is deterministic. |

---

### AC-4: AmISafe crime map — Community Reports layer

| Item | Detail |
|---|---|
| **Required approach** | **Passthrough required** — see full analysis below |
| **Module/file** | `sites/forseti/web/modules/custom/amisafe/js/crime-map.js` — must expose the map instance; then `community_incident_report/js/community-layer.js` attaches via a secondary Drupal behavior |
| **Data endpoint** | New route in `community_incident_report`: `/api/community-incidents/geojson` → JSON array of `{lat, lng, title, type, occurred_at}` from published `community_incident` nodes (no H3, pin-level only) |
| **Dependency** | AmISafe passthrough decision (see below), AC-1 |
| **Verify** | Visit `/amisafe/crime-map` → no JS console errors; "Community Reports" toggle visible in legend; toggling on → community pins render at correct locations; toggling off → pins hidden |
| **Risk** | ⚠ High — see AmISafe integration note below |

---

### AC-5: Permissions

| Item | Detail |
|---|---|
| **Module/file** | New `community_incident_report/community_incident_report.permissions.yml` |
| **Permissions to define** | `submit community incident reports` (default: authenticated user), `view community incident reports` (default: public / anonymous — handled via route, not custom permission) |
| **Verify** | `/admin/people/permissions` → two new permissions visible under `community_incident_report`; anonymous user: cannot access `/community/report` (302); authenticated user: can access `/community/report` (200) |
| **Risk** | Low. Standard permissions.yml. |

---

### AC-6: Moderation admin view

| Item | Detail |
|---|---|
| **Approach** | Drupal core Views + VBO (Views Bulk Operations) — no custom code. Create view `community_reports_moderation` at `/admin/content/community-reports`, shows all `community_incident` nodes including unpublished, with status filter and bulk publish/unpublish operations. |
| **VBO availability** | Drupal core does NOT include VBO out of the box — it requires the `drupal/views_bulk_operations` contributed module. Check if it's already installed: `drush pm:list --status=enabled | grep vbo`. If absent, dev must either add the dependency or implement a lightweight custom admin route (simpler: add `views_bulk_operations` to `composer.json`). |
| **Default publish state** | New `community_incident` nodes must be created with `status: 0` (unpublished). Enforce via `community_incident_report_node_presave()` hook or node type default. |
| **Verify** | Submit form as authenticated user → node created with `status=0`; visit `/admin/content/community-reports` as admin → shows pending node; click publish → node published; `/community-reports` now shows node |
| **Risk** | ⚠ Medium — VBO dependency. If not already installed, dev must add it. BA recommends checking first before assuming it's available. |

---

### AC-7: Confirmation message + form reset

| Item | Detail |
|---|---|
| **Module/file** | `ReportForm::submitForm()` method — `\Drupal::messenger()->addStatus($this->t("Thank you..."))` before redirect; form uses `$form_state->setRedirect()` to redirect to `/community/report` (fresh form) rather than the node page |
| **Verify** | Submit valid form → see "Thank you — your report has been submitted and will appear after review." status message; form page reloads empty |
| **Risk** | Low. Standard Drupal messenger API. |

---

## AmISafe integration note (AC-4) — detailed

### How the existing map initializes

`amisafe/js/crime-map.js` wraps everything in `(function ($, Drupal, drupalSettings) { ... })` IIFE. The map is created via:

```js
Drupal.behaviors.amisafeCrimeMap = {
  attach: function (context, settings) {
    $(context).find('#crime-map-container').each(function () {
      if (!this.hasAttribute('data-amisafe-initialized')) {
        this.setAttribute('data-amisafe-initialized', 'true');
        var crimeMap = new AmISafeCrimeMap(this, settings.amisafe);
        crimeMap.initialize();
      }
    });
  }
};
```

`crimeMap` is a **local variable** inside the `attach` closure. The `AmISafeCrimeMap` instance is NOT stored on `window`, NOT stored in `$(container).data()`, and NOT exposed on `Drupal.amisafe` or any other global. 

The `this.map` (Leaflet map instance) is also private to the `AmISafeCrimeMap` object.

### Can `community_incident_report` attach a Drupal JS behavior to extend the map without touching `amisafe`?

**No, not cleanly.** A secondary Drupal behavior could:
1. Wait for `#crime-map-container[data-amisafe-initialized]` to appear via a polling/MutationObserver approach
2. But it would have no reference to the Leaflet `L.map` instance or the `crimeMap` object to call `addLayer()` on

Without a reference to the map instance, the secondary behavior would have to re-read `window.L` and re-initialize a second Leaflet map on the same container — which would conflict.

### Recommendation: Passthrough required

**Recommendation: passthrough to amisafe module owner.** The minimal required change to `amisafe` is:

```js
// In crime-map.js attach(), after crimeMap.initialize():
$(this).data('amisafe-crime-map', crimeMap);
// OR:
Drupal.amisafe = Drupal.amisafe || {};
Drupal.amisafe.mapInstances = Drupal.amisafe.mapInstances || {};
Drupal.amisafe.mapInstances[container.id || 'default'] = crimeMap;
```

With either change, `community_incident_report`'s JS behavior can:
```js
var crimeMap = $('#crime-map-container').data('amisafe-crime-map');
if (crimeMap && crimeMap.map) {
    var communityLayer = L.layerGroup();
    crimeMap.map.addLayer(communityLayer);
    // fetch /api/community-incidents/geojson and add pins
}
```

**Passthrough scope**: Small — one line added to `amisafe/js/crime-map.js` in the `attach()` function. BA draft of the patch is above. pm-forseti should initiate passthrough via `runbooks/passthrough-request.md` to the amisafe module owner before dev-forseti starts AC-4 work.

**Alternative (self-contained but fragile):** `community_incident_report` injects its layer toggle button into the AmISafe map container DOM and listens for `leafletmapcreated` or similar event. However, AmISafe does NOT fire any such custom event — so this would require polling `window.L` and the container's `data` attributes until the map is ready. This is fragile and not recommended.

---

## Risk flags summary

| AC | Risk | Issue | Action needed |
|---|---|---|---|
| AC-4 | ⚠ High | `crimeMap` instance not externally accessible; passthrough required | pm-forseti initiates passthrough to amisafe owner |
| AC-6 | ⚠ Medium | VBO module may not be installed; required for bulk operations admin view | dev-forseti verifies `drush pm:list | grep views_bulk_operations`; add to composer.json if absent |
| AC-1 | ℹ Low | "lat/lng as separate fields" explicitly deferred per feature notes — confirm address-only is accepted by QA | No action needed unless QA disagrees |
| AC-3 | ℹ Low | "published only" filter relies on AC-6 default-unpublished behavior being wired correctly | Sequence: AC-1 → AC-6 (presave hook) → AC-3 |

---

## Dev task sequence (recommended)

1. **AC-1 + AC-5**: Module scaffold + content type config + permissions (no dependencies)
2. **AC-2**: Report form (depends on AC-1, AC-5)
3. **AC-6**: Admin view + default-unpublished presave hook (depends on AC-1)
4. **AC-3**: Public listing view (depends on AC-1, AC-6)
5. **AC-7**: Confirmation message (part of AC-2 form submit handler)
6. **AC-4**: Community reports map layer (depends on AmISafe passthrough being resolved first; do last)

---

## Passthrough request draft (for pm-forseti to send)

**To:** amisafe module owner  
**From:** pm-forseti  
**Re:** forseti-community-incident-report — AC-4 map layer (PROJ-006)  
**Required change:** In `amisafe/js/crime-map.js`, attach(), after `crimeMap.initialize()`:

```js
$(this).data('amisafe-crime-map', crimeMap);
```

This exposes the map instance to external Drupal behaviors without any logic change to AmISafe itself. The community_incident_report module will use it read-only to add a new toggleable layer. No AmISafe functionality is modified.
