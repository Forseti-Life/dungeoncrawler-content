# Generated Image Storage Design (DungeonCrawler)

## Goal
Design a durable image storage system for Gemini-generated assets that:
- fits the current `template / campaign / fact` object model,
- is queryable by game object context (character, room, dungeon, encounter, item, campaign),
- is safe for client access,
- supports future providers and regeneration/versioning.

## Current Object Table Review

### Template Objects (`dungeoncrawler_content_*`)
- `dungeoncrawler_content_registry`
- `dungeoncrawler_content_loot_tables`
- `dungeoncrawler_content_encounter_templates`
- `dungeoncrawler_content_campaigns`
- `dungeoncrawler_content_characters`
- `dungeoncrawler_content_rooms`
- `dungeoncrawler_content_dungeons`
- `dungeoncrawler_content_encounter_instances`
- `dungeoncrawler_content_room_states`
- `dungeoncrawler_content_item_instances`
- `dungeoncrawler_content_log`

### Active Campaign Objects (`dc_campaign*`)
- `dc_campaigns`
- `dc_campaign_characters`
- `dc_campaign_content_registry`
- `dc_campaign_loot_tables`
- `dc_campaign_encounter_templates`
- `dc_campaign_rooms`
- `dc_campaign_dungeons`
- `dc_campaign_encounter_instances`
- `dc_campaign_room_states`
- `dc_campaign_item_instances`
- `dc_campaign_log`

### Fact / Runtime Support Objects
- `combat_encounters`
- `combat_participants`
- `combat_conditions`
- `combat_actions`
- `combat_damage_log`

## Observations from Existing Schema
- Image-like field exists today only in `dc_campaign_characters.portrait` (string path), so image storage is currently ad-hoc and character-only.
- Most gameplay objects use `*_id` string identifiers + JSON payload columns; this is a good fit for a generic image-association table.
- No current file-entity (`file_managed`) integration in module services/controllers.
- Object inventory UI already discovers `dc_*` + `dungeoncrawler_content_*`, so new tables in this namespace are immediately auditable.

---

## Proposed Storage Model

Use a **two-layer model**:
1. **Image asset table** (fact-like canonical record)
2. **Image association table** (campaign/template/object attachment)

Optional third layer for variants/thumbnails.

### 1) Canonical Generated Image Asset

**Table:** `dc_generated_images`

Represents one generated image payload regardless of where it is used.

Suggested columns:
- `id` (serial, PK)
- `image_uuid` (varchar 36, unique)
- `owner_uid` (int, not null)
- `provider` (varchar 32) — `gemini`, future providers
- `provider_request_id` (varchar 128, nullable)
- `provider_model` (varchar 128, nullable)
- `status` (varchar 24) — `pending`, `ready`, `failed`, `deleted`
- `mime_type` (varchar 64)
- `width` (int, nullable)
- `height` (int, nullable)
- `bytes` (int, nullable)
- `storage_scheme` (varchar 24) — `public`, `private`, `s3`, etc.
- `file_uri` (varchar 1024) — canonical storage URI/path
- `public_url` (varchar 1024, nullable) — optional precomputed URL for CDN/public assets
- `sha256` (varchar 64, nullable, indexed)
- `prompt_text` (text)
- `negative_prompt` (text, nullable)
- `generation_params` (text big, JSON)
- `safety_metadata` (text big, JSON)
- `created` (int)
- `updated` (int)
- `deleted` (int, default 0)

Indexes:
- unique: `image_uuid`
- index: `(owner_uid, created)`
- index: `(status, created)`
- index: `sha256`
- index: `(provider, provider_request_id)`

### 2) Object/Image Attachment Table

**Table:** `dc_generated_image_links`

Links image assets to game objects at template or campaign scope.

Suggested columns:
- `id` (serial, PK)
- `image_id` (int, FK-style reference to `dc_generated_images.id`)
- `scope_type` (varchar 24) — `template`, `campaign`, `fact`
- `campaign_id` (int, nullable; required when `scope_type=campaign`)
- `table_name` (varchar 128) — e.g. `dc_campaign_characters`, `dungeoncrawler_content_rooms`
- `object_id` (varchar 128) — supports string IDs (`room_id`, `dungeon_id`, etc.) and numeric ids as strings
- `slot` (varchar 32) — `portrait`, `token`, `card`, `splash`, `background`, `thumbnail`
- `variant` (varchar 32) — `original`, `small`, `medium`, `large`, `webp`, etc.
- `is_primary` (tinyint, default 0)
- `sort_weight` (int, default 0)
- `visibility` (varchar 24) — `owner`, `campaign_party`, `public`
- `created` (int)
- `updated` (int)

Indexes:
- index: `(table_name, object_id, slot, is_primary)`
- index: `(campaign_id, table_name, object_id)`
- index: `(image_id)`
- index: `(scope_type, campaign_id)`

Uniqueness recommendation:
- unique partial behavior (enforced in app logic): one primary image per `{scope, campaign_id, table_name, object_id, slot}`.

### 3) Optional Variant Table (if binary transforms are common)

**Table:** `dc_generated_image_variants`

Use only if resize/crop jobs become frequent and separate files per variant are required.

---

## Why This Fits Current Object Architecture
- Keeps generated images out of large JSON state blobs.
- Avoids schema churn across all object tables.
- Works uniformly for template and campaign objects.
- Preserves existing `portrait` path field during migration; no hard break.

---

## Client Accessibility Design

## API Endpoints (proposed)

### Read APIs
- `GET /api/image/{image_uuid}`
  - Returns metadata + accessible URL (or signed URL token flow for private storage).
- `GET /api/images/object/{table_name}/{object_id}`
  - Query params: `campaign_id`, `slot`, `variant`.
  - Returns ordered list of linked images.
- `GET /api/campaign/{campaign_id}/images`
  - Query params: `table_name`, `object_id`, `slot`.

### Mutation APIs
- `POST /api/images/generate`
  - Existing Gemini payload + target object context (`table_name`, `object_id`, `campaign_id`, `slot`).
  - Creates image record + link.
- `POST /api/images/{image_uuid}/link`
  - Link existing image to another object.
- `POST /api/images/{image_uuid}/primary`
  - Set as primary for a slot.
- `DELETE /api/images/{image_uuid}`
  - Soft delete (`status=deleted`, `deleted` timestamp).

## Delivery strategy
- **Preferred:** store files in private storage + signed/expiring URL endpoint for non-public assets.
- **Allow:** public storage for explicitly public assets (marketing/splash) with static URL.
- Return both:
  - `url` for immediate render,
  - `cache_ttl` + `etag` metadata for client caching.

---

## Access Control Rules
- Owner (`owner_uid`) can always read/manage own generated images.
- Campaign assets require campaign access check (`_campaign_access`) for non-public visibility.
- Template/fact assets default to admin-only write, read by authenticated users if marked `public`.
- Never expose private storage paths directly; expose routed URLs/tokens.

---

## Storage Backends

Phase-safe approach:
1. Start with local filesystem (`public://generated-images` or `private://generated-images`).
2. Abstract storage through service (`GeneratedImageStorageInterface`) so S3/CloudFront can be added without schema changes.

---

## Migration Plan from Existing Portrait Field

Current field: `dc_campaign_characters.portrait`.

Migration steps:
1. Add new image tables.
2. Backfill each non-empty portrait path into `dc_generated_images` + `dc_generated_image_links` with:
   - `table_name=dc_campaign_characters`
   - `object_id` = character `id`
   - `slot=portrait`
   - `is_primary=1`
3. Keep writing `portrait` for backward compatibility in first release.
4. Move read paths to image-link lookup first, fallback to `portrait`.
5. Deprecate and eventually remove `portrait` writes.

---

## Recommended Implementation Phases

### Phase 1 (Foundational)
- Add `dc_generated_images` and `dc_generated_image_links` via update hook.
- Add service interfaces and repository methods.
- Add read API endpoints.
- Link Gemini form submissions to these tables.

### Phase 2 (Client Integration)
- Update character, dungeon, room, encounter payload APIs to include resolved image URLs.
- Add primary/slot management endpoints.
- Add object manager filters for image tables.

### Phase 3 (Hardening)
- Signed URL flow for private storage.
- Dedup via `sha256`.
- Variant generation workers + lifecycle cleanup.

---

## Example Query Patterns

### Primary portrait for a campaign character
```sql
SELECT gi.*
FROM dc_generated_image_links gil
JOIN dc_generated_images gi ON gi.id = gil.image_id
WHERE gil.table_name = 'dc_campaign_characters'
  AND gil.object_id = :character_id
  AND gil.campaign_id = :campaign_id
  AND gil.slot = 'portrait'
  AND gil.is_primary = 1
  AND gi.status = 'ready'
LIMIT 1;
```

### All dungeon splash images for a campaign
```sql
SELECT gi.*, gil.sort_weight
FROM dc_generated_image_links gil
JOIN dc_generated_images gi ON gi.id = gil.image_id
WHERE gil.table_name = 'dc_campaign_dungeons'
  AND gil.campaign_id = :campaign_id
  AND gil.object_id = :dungeon_id
  AND gil.slot = 'splash'
  AND gi.status = 'ready'
ORDER BY gil.sort_weight ASC, gil.created DESC;
```

---

## Design Decision Summary
- **Do not** add image columns to every object table.
- **Do** centralize generated image metadata in `dc_generated_images`.
- **Do** use polymorphic association links in `dc_generated_image_links`.
- **Do** serve client-facing URLs through controlled API responses.
- **Do** maintain backward compatibility with `dc_campaign_characters.portrait` during transition.
