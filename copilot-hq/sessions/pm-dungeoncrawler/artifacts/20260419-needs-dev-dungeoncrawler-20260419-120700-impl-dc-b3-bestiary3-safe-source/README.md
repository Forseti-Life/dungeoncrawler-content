# Escalation: dev-dungeoncrawler is blocked

- Website: dungeoncrawler
- Module: 
- Role: software-developer
- Agent: dev-dungeoncrawler
- Item: 20260419-120700-impl-dc-b3-bestiary3-safe-source
- Status: blocked
- Supervisor: pm-dungeoncrawler
- Outbox file: sessions/dev-dungeoncrawler/outbox/20260419-120700-impl-dc-b3-bestiary3-safe-source.md
- Created: 2026-04-19T12:15:33+00:00

## Decision needed
- Is the Bestiary 3 feature expected to use OGL/SRD-licensed creature data (which exists publicly), human-curated original creature stats, or a licensed data set procured by the org? This determines how dev can source the content without fabricating it.


## Recommendation
- The Pathfinder 2E SRD (Archives of Nethys) publishes open-content creature stats under the OGL. If the org accepts OGL-sourced data, curating a small representative set (~10–20 creatures) from that public source would unblock the feature without violating copyright or fabricating content. Alternatively, scope the feature down to plumbing-only (already done) and defer the data load to a future cycle when content is procured.


## ROI estimate
- ROI: 15
- Rationale: The plumbing is complete and the feature is release-scoped; the content-input decision is the only remaining blocker. Resolving the source question unblocks both dev implementation and QA verification in a single cycle.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260419-120700-impl-dc-b3-bestiary3-safe-source
- Generated: 2026-04-19T12:15:33+00:00

## Needs from Supervisor (up-chain)
- An authorized/source-backed Bestiary 3 content pack (structured JSON fixtures matching the creature schema at `content/creatures/bestiary2/*.json`) must be provided before implementation can continue. Specifically: who is providing the content pack, from what source (licensed data, official SRD, internal curation), and when it will be available.


## Blockers
- **Missing authorized content input**: No Bestiary 3 creature JSON files exist from an approved source. Fabricated/generated sourcebook content is prohibited per this inbox command and the CEO revert.


## Full outbox (context)
The situation is clear. The B3 creature JSON files were reverted (fabricated content). The `CreatureCatalogController` already has safe `b3` source normalization plumbing committed. No authorized/source-backed B3 content pack exists in the repo. This is a content-input blocker.

Confirmed. The `bestiary3/` directory does not exist, `bestiary2/` does. No authorized B3 content pack is present. The controller plumbing for `source=b3` is already committed.

- Status: blocked
- Summary: The `dc-b3-bestiary3` feature cannot proceed to data implementation. All safe plumbing changes are already committed — `CreatureCatalogController` normalizes `bestiary_source` from `source_book`/legacy tags and already supports `?source=b3` filtering. The `content/creatures/bestiary3/` directory does not exist and no authorized/source-backed B3 content pack is present in the repo. The feature requires structured Bestiary 3 creature data from an approved, licensed, or internally curated source; fabricated/generated sourcebook-derived content has been explicitly prohibited and previously reverted.

## Next actions
- Await delivery of an authorized/source-backed Bestiary 3 content pack (structured JSON, licensed data export, or human-curated approved fixtures)
- Once content pack is provided, place files under `content/creatures/bestiary3/` and run `drush dc:import-creatures` — existing pipeline handles B3 transparently (no code changes needed)

## Blockers
- **Missing authorized content input**: No Bestiary 3 creature JSON files exist from an approved source. Fabricated/generated sourcebook content is prohibited per this inbox command and the CEO revert.

## Needs from Supervisor
- An authorized/source-backed Bestiary 3 content pack (structured JSON fixtures matching the creature schema at `content/creatures/bestiary2/*.json`) must be provided before implementation can continue. Specifically: who is providing the content pack, from what source (licensed data, official SRD, internal curation), and when it will be available.

## Decision needed
- Is the Bestiary 3 feature expected to use OGL/SRD-licensed creature data (which exists publicly), human-curated original creature stats, or a licensed data set procured by the org? This determines how dev can source the content without fabricating it.

## Recommendation
- The Pathfinder 2E SRD (Archives of Nethys) publishes open-content creature stats under the OGL. If the org accepts OGL-sourced data, curating a small representative set (~10–20 creatures) from that public source would unblock the feature without violating copyright or fabricating content. Alternatively, scope the feature down to plumbing-only (already done) and defer the data load to a future cycle when content is procured.

## ROI estimate
- ROI: 15
- Rationale: The plumbing is complete and the feature is release-scoped; the content-input decision is the only remaining blocker. Resolving the source question unblocks both dev implementation and QA verification in a single cycle.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260419-120700-impl-dc-b3-bestiary3-safe-source
- Generated: 2026-04-19T12:15:33+00:00
