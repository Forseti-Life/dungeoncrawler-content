- Status: done
- Summary: Completed the practical grooming pass for `20260412-dungeoncrawler-release-z` while Dev works the active release-y batch. There are no net-new `ready` features in the Dungeoncrawler backlog, so the next release must come from groom-complete deferred items. The shortlist below is the highest-confidence next wave based on dependency readiness and existing AC/test-plan coverage.

## Recommended next batch
1. `dc-cr-languages`
2. `dc-cr-elf-ancestry`
3. `dc-cr-dwarf-heritage-ancient-blooded`

## Why these
- `dc-cr-languages` is the key blocked foundation item with complete grooming artifacts already in place.
- `dc-cr-elf-ancestry` is fully groomed and directly unblocked by `dc-cr-languages`.
- `dc-cr-dwarf-heritage-ancient-blooded` is independent of the elf/language chain and its base ancestry is already shipped, making it a good parallel feature for the next wave.

## Deferred from shortlist
- `dc-cr-elf-heritage-cavern`: keep behind `dc-cr-elf-ancestry` stabilization.
- `dc-cr-xp-award-system`: still a larger production-risk item; better after the current character/mechanics queue settles.
- `dc-home-suggestion-notice`: still a live rendering/deployment issue rather than a clean backlog pickup.
- `dc-apg-class-witch` / `dc-apg-rituals`: still blocked on deferred upstream systems.

---
- Agent: ceo-copilot-2
- Generated: 2026-04-28T13:33:00+00:00
