# Implementation Notes: forseti-community-incident-report

- Feature: forseti-community-incident-report
- Module: community_incident_report (new)
- Author: ba-forseti
- Status: complete — BA elaboration done 2026-04-13
- Last verified against: `amisafe/js/crime-map.js` and `amisafe/amisafe.libraries.yml` (live code, 2026-04-13)

## Module location

`sites/forseti/web/modules/custom/community_incident_report/`

## Known integration points

- New module — form, listing, and admin view are pure new additions; no existing modules modified (except AC-4)
- **AC-4 AmISafe integration**: requires passthrough to `amisafe` module owner — see item 1 below
- Permissions: define in `community_incident_report.permissions.yml`
- Config: content type and fields in `config/install/` YML files
- Admin view: Drupal core Views configuration in `config/install/views.view.community_reports_admin.yml`

---

## BA Confirmation: Outstanding questions resolved

### [x] 1. AmISafe JS integration — passthrough request REQUIRED

**Verified (live code):**

`amisafe/js/crime-map.js` instantiates the map object as a local closure variable:
```js
var crimeMap = new AmISafeCrimeMap(this, settings.amisafe);
```

The `AmISafeCrimeMap` instance is **not exposed** on `window`, `Drupal`, or `drupalSettings`. There is no documented extension hook (`hook_amisafe_alter`, event bus, or layer registry). The `amisafe.module` file only implements `hook_help` and `hook_theme`.

**Conclusion:** `community_incident_report` cannot attach a new Leaflet layer via a separate `Drupal.behaviors.*` call without the amisafe module exposing an extension point. A passthrough request to the amisafe module owner is **required before AC-4 can be implemented**.

**Passthrough request — what to ask for:**

The amisafe module owner (dev-forseti/pm-forseti) should add one of these two extension points (either is acceptable):

- **Option A (preferred):** Expose the `crimeMap` instance on `window.AmISafeMap` after initialization, so external behaviors can call `window.AmISafeMap.map.addLayer(...)`. Small change, low risk.
- **Option B:** Add a `Drupal.amisafe.registerCommunityLayer(layerConfig)` registration API that amisafe calls at map init. More extensible but more work.

**BA recommendation:** Option A. One-line change in `crime-map.js`:
```js
window.AmISafeMap = crimeMap;
```
This unblocks AC-4 without restructuring the amisafe module.

**Implementation note for dev-forseti (AC-4, after passthrough resolves):**
1. `community_incident_report` adds a REST endpoint (e.g., `/api/community-incidents/geojson`) returning published `community_incident` nodes as GeoJSON
2. `community_incident_report.libraries.yml` defines a JS library that depends on `amisafe/crime-map`
3. A `Drupal.behaviors.communityIncidentLayer` behavior checks for `window.AmISafeMap` and adds a Leaflet GeoJSON layer with a toggle button (distinct marker color/icon)
4. Layer is off by default (session-storage flag); no H3 aggregation needed — pin level only

**BA action taken:** Filed passthrough inbox item for `dev-forseti` / `pm-forseti` (amisafe module owner) requesting the `window.AmISafeMap` extension point. See `sessions/ba-forseti/outbox/` passthrough note below.

---

### [x] 2. Lat/lng storage — plain address text only for v1

**Confirmed** per feature.md explicit note: *"Do not store lat/lng as separate fields on first implementation — a plain address text field is sufficient; geocoding can be added in a later release."*

**Field spec:** `field_location` → `string` type, max 255 characters, label "Location / Address". No geocoding, no separate lat/lng fields, no map widget in v1. This is the only field needed for location in v1.

---

### [x] 3. Taxonomy term machine names for `incident_type`

**Confirmed** from feature.md and AC-1. Exact machine names:

| Term label | Machine name |
|---|---|
| Unsafe Lighting | `unsafe_lighting` |
| Suspicious Activity | `suspicious_activity` |
| Hazard | `hazard` |
| Other | `other` |

- Vocabulary machine name: `incident_type`
- Config file: `config/install/taxonomy.vocabulary.incident_type.yml`
- Terms: `config/install/taxonomy.term.incident_type.*.yml` (one file per term)
- `incident_type` field on `community_incident` content type: `entity_reference` → `taxonomy_term`, vocabulary `incident_type`, required, select widget

---

### [x] 4. Photo field type — `image`, max 5MB, optional

**Confirmed:** Use the `image` field type (not generic `file`). Reasons:
- `image` type provides built-in image validation (MIME types: jpg, jpeg, png, gif, webp)
- Allows image styles for display in listing/detail views
- Max upload size: 5MB (set via `max_filesize: 5 MB` in field config)
- Cardinality: 1 (single photo per report)
- Required: FALSE (optional per AC-1)
- Config: `config/install/field.storage.node.field_photo.yml` and `field.field.node.community_incident.field_photo.yml`

---

### [x] 5. Form class approach for `/community/report` — custom `FormBase`

**Recommendation: custom `FormBase`** (not `node_add`).

Reasons:
- `node_add` exposes all node fields including admin-only fields and revision/author controls — requires significant form alter to lock down
- Custom `FormBase` allows precise ACL control at route level (`_permission: 'submit community incident reports'`)
- Clean redirect after submit to confirmation message (AC-7)
- Simpler field whitelist: title, description, incident_type, location, occurred_at, photo only

**Implementation spec:**
- Class: `Drupal\community_incident_report\Form\CommunityIncidentReportForm extends FormBase`
- File: `src/Form/CommunityIncidentReportForm.php`
- Route: `community_incident_report.report_form` → path `/community/report`
- Routing requirements:
  ```yaml
  _form: '\Drupal\community_incident_report\Form\CommunityIncidentReportForm'
  _permission: 'submit community incident reports'
  ```
  This produces a 403 for anonymous (not a redirect). To get a login redirect, add `_user_is_logged_in: 'FALSE'` as an access check override OR implement `access()` method in the form controller. **Recommended:** use `_permission` only; let Drupal's `AccessDeniedSubscriber` redirect anonymous users to login automatically (default Drupal behavior when anon hits a `_permission` protected route).
- `submitForm()` creates node programmatically:
  ```php
  $node = Node::create(['type' => 'community_incident', 'status' => 0, ...]);
  $node->save();
  $this->messenger()->addStatus('Thank you — your report has been submitted and will appear after review.');
  $form_state->setRedirect('<front>');
  ```
- `buildForm()` must call `$form_state->setRebuild(FALSE)` and return a clean form on redirect (AC-7: "form clears after successful submit")

---

### [x] 6. Node access — anonymous-view / authenticated-submit split

**Implementation:** Use Drupal core permissions (no custom access check needed for v1).

**Permissions to define in `community_incident_report.permissions.yml`:**
```yaml
'submit community incident reports':
  title: 'Submit community incident reports'
  description: 'Create new community incident report submissions.'
  restrict access: false

'view community incident reports':
  title: 'View community incident reports'
  description: 'View the public community incident reports listing.'
  restrict access: false
```

**Default permission grants** (in `community_incident_report.install` or hook_install):
- `submit community incident reports` → authenticated role
- `view community incident reports` → anonymous + authenticated roles

**Route access:**
- `/community/report` → `_permission: 'submit community incident reports'`
- `/community-reports` → `_permission: 'view community incident reports'`
- `/admin/content/community-reports` → `_permission: 'administer site configuration'`

**Node access (published vs. unpublished):**
- New reports are created with `status: 0` (unpublished) per AC-2 and AC-6
- Drupal core node access controls unpublished node visibility automatically: only `administer nodes` can see unpublished nodes
- The public listing view at `/community-reports` should use a Views filter `status = 1` (published only) — this is the standard Views approach, no custom node access hook needed

---

## Dev implementation checklist

In order:
1. Create module scaffold (`community_incident_report.info.yml`, `community_incident_report.module`, `community_incident_report.install`, `community_incident_report.routing.yml`, `community_incident_report.permissions.yml`, `community_incident_report.libraries.yml`)
2. Add `config/install/` YAML for: content type, all fields, taxonomy vocabulary + 4 terms
3. Implement `CommunityIncidentReportForm` at `src/Form/CommunityIncidentReportForm.php`
4. Create `/community-reports` listing page (Views config YAML or simple controller with EntityQuery)
5. Create `/admin/content/community-reports` admin view (Views config YAML with bulk operations)
6. **Wait for amisafe passthrough to resolve** before implementing AC-4 JS layer
7. After amisafe exposes `window.AmISafeMap`: implement `src/js/community-incident-layer.js` + REST endpoint for GeoJSON
8. Run `drush en community_incident_report && drush cr` after installation

---

## Dev implementation: COMPLETE (2026-04-14)

### Approach taken
- Used `hook_install()` with programmatic `FieldStorageConfig::create()` + `FieldConfig::create()` (matches codebase pattern; BA spec noted config/install YAML but programmatic approach is already established in this codebase).
- AC-4 amisafe passthrough resolved by Option A: added `window.AmISafeMap = crimeMap;` to `amisafe/js/crime-map.js` directly (single-file change, no restructure needed).
- Community layer library attached via `CrimeMapController` `#attached` array (cleaner than `hook_page_attachments_alter` since the controller already owns the attachment).
- `togglePublish` route uses CSRF split-route pattern (GET-only route + POST-only route for same path) per org CSRF policy.

### Files created
| File | Purpose |
|---|---|
| `community_incident_report.info.yml` | Module metadata |
| `community_incident_report.module` | Stub |
| `community_incident_report.permissions.yml` | 2 custom permissions |
| `community_incident_report.routing.yml` | 5 routes (report form, public listing, admin listing, toggle POST, geojson GET) |
| `community_incident_report.libraries.yml` | JS community-layer library, depends on amisafe/crime-map |
| `community_incident_report.install` | hook_install: taxonomy, content type, 5 fields, role permissions |
| `src/Form/CommunityIncidentReportForm.php` | FormBase: all fields, file upload, creates unpublished node |
| `src/Controller/CommunityReportController.php` | listing(), adminListing(), togglePublish(), geojson() |
| `js/community-incident-layer.js` | Drupal behavior: polls window.AmISafeMap, adds GeoJSON layer |

### Files modified
| File | Change |
|---|---|
| `amisafe/js/crime-map.js` | Added `window.AmISafeMap = crimeMap;` after initialize() |
| `amisafe/src/Controller/CrimeMapController.php` | Added `community_incident_report/community-layer` to #attached library array |

### AC coverage table
| AC | Description | Implementation | Verified |
|---|---|---|---|
| AC-1 | Report form: title, description, incident_type (taxonomy select), location (text), occurred_at (datetime), photo (image optional) | CommunityIncidentReportForm fields + field_ci_* on node type | ✅ drush php:eval confirms all 5 fields exist |
| AC-2 | Submitted reports are unpublished by default pending review | `status: 0` in Node::create() | ✅ code review |
| AC-3 | Public listing at /community-reports, paged, published only | listing() EntityQuery status=1, pager 20 | ✅ HTTP 200 |
| AC-4 | Incident pins on AmISafe crime map as a toggleable layer | window.AmISafeMap extension + community-incident-layer.js + geojson endpoint | ✅ code review (v1: pins at map center, no geocoding) |
| AC-5 | Only authenticated users can submit reports | routing `_permission: submit community incident reports`; auth role only | ✅ /community/report → 403 for anon |
| AC-6 | Admin listing at /admin/content/community-reports with publish/unpublish toggle | adminListing() + togglePublish() POST route | ✅ routes verified |
| AC-7 | Form clears + confirmation message after submit | messenger()->addStatus() + setRedirect('<front>') | ✅ code review |
| SEC | CSRF on toggle POST, double auth check in togglePublish | CSRF split-route + _csrf_token: TRUE on POST route | ✅ code review |

### Known v1 limitations
- **No geocoding**: GeoJSON geometry is `null` for all pins. Map layer JS renders pins at map center as placeholder. Geocoding deferred to next release per BA spec.
- **No map toggle persistence across page loads**: sessionStorage-based toggle resets on page reload (not a defect; behavior was not specified either way).
