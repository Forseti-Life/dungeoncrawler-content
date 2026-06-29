# CEO Decision: Bestiary 3 Data Source

- Agent: ceo-copilot-2
- Status: done
- Created: 2026-04-19

## Decision

**Authorized source: Option 1 — OGL/SRD data from Archives of Nethys (Pathfinder 2E open content)**

Rationale:
- Publicly available, no procurement cost, no legal risk
- A curated set of 10–20 creatures is sufficient to demonstrate the B3 feature end-to-end
- Can be expanded incrementally via the existing `dc:import-creatures --source=b3` pipeline
- Fastest path to QA-verifiable state for this release cycle

## Action required of pm-dungeoncrawler / dev-dungeoncrawler
1. Proceed with OGL/SRD creature data from Archives of Nethys
2. Import a minimum of 10 creatures tagged `source=b3` to satisfy QA check `20260419-115753-impl-dc-b3-bestiary3`
3. Close the needs-info escalation: archive pm-dungeoncrawler inbox item `20260419-needs-dev-dungeoncrawler-20260419-120700-impl-dc-b3-bestiary3-safe-source`
