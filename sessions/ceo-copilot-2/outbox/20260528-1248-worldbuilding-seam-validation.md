- Status: done
- Summary: Validated the codex/social planning seams against the live Dungeoncrawler codebase and confirmed that the plan is grounded in real services and tables: `RelationshipManagerService` is a bridge seam, `NpcService` is the NPC runtime authority, `CampaignStateService` is a snapshot/cache layer, and `ChatSessionManager` remains correctly out of scope as narrative memory infrastructure.

# Worldbuilding seam validation

## Validated against live code

- `src/Service/RelationshipManagerService.php`
- `src/Service/NpcService.php`
- `src/Service/CampaignStateService.php`
- `src/Service/ChatSessionManager.php`
- `dungeoncrawler_content.services.yml`
- `dungeoncrawler_content.install`

## Outcome

The planning package is now backed by direct code inspection instead of assumption. The remaining gap to implementation readiness is no longer "do these seams exist?" but rather "how exactly do we cut over from current relationship identifiers to codex record ids and runtime subject ids?"
