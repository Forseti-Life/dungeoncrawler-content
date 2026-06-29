# dc-gmg-npc-gallery — Implementation Notes

Commit: `5973a7495`

## What was done

### NpcService.php
- Added `setEliteWeakTemplate(int $campaign_id, int $npc_id, ?string $template): array`
  - Validates template is 'elite', 'weak', or NULL
  - Persists to new `elite_weak_template` DB column
  - Returns stat block with overlay applied
- Added `applyEliteWeakOverlay(array $npc): array`
  - Pure computation; base stats unchanged
  - PF2e GMG rules: ±2 to AC/perception/fort/ref/will; HP ±10 (L1-4), ±15 (L5-19), ±20 (L20+)
  - Adds `derived` key to returned NPC with full overlay stats
- Added `getCreatureSelectorEntries(array $filters, int $limit): array`
  - Delegates to `searchGallery()` + tags each entry with `source: npc_gallery`, `type: npc`, `selector_tag: NPC`
- Added private `getById(int $id): ?array`
  - Was called by `createGalleryEntry()` and `assignGalleryEntryToScene()` but never defined — this was a latent bug now fixed

### NpcController.php — new endpoints
| Method | Path | Controller Method |
|--------|------|-------------------|
| GET | /api/gallery/npcs | `searchGallery()` |
| POST | /api/gallery/npcs | `createGalleryEntry()` |
| POST | /api/gallery/npcs/{gallery_id}/assign | `assignGalleryToScene()` |
| POST | /api/campaign/{campaign_id}/npcs/{npc_id}/elite-weak | `applyEliteWeak()` |
| GET | /api/creature-selector | `listCreatureSelector()` |

### dungeoncrawler_content.install — hook 10045
- Adds `elite_weak_template` VARCHAR(8) nullable to `dc_npc`

## Design decisions
- Elite/Weak overlay is **stored** as a template flag (`elite_weak_template` column), not applied mutably. Derived stats are computed at response time and returned under a `derived` key. Base stat block is always preserved.
- `assignGalleryEntryToScene` passes `campaign_id` in the POST body (not URL) so GM scene assignment doesn't require a campaign-scoped URL.
- Creature selector returns NPC Gallery only; comment in controller marks where Bestiary merge goes when that system exists.
- No AC requires gallery NPC rename-specific endpoint; existing `PATCH /api/campaign/{id}/npcs/{id}` with `name` in body is sufficient.

## Acceptance criteria status
- [x] NPC Gallery prebuilt stat blocks browsable (`GET /api/gallery/npcs`)
- [x] Elite/Weak overlay applied per GMG rules (stored as template, computed derived stats)
- [x] Elite and Weak are mutually exclusive (400 on invalid value; NULL clears overlay)
- [x] GM can assign gallery NPC to campaign scene (`POST /api/gallery/npcs/{id}/assign`)
- [x] NPC Gallery entries appear in creature selector with NPC tag (`GET /api/creature-selector`)
- [x] Rename handled by existing `updateNpc()` with `name` field — no new endpoint needed
