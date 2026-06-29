# PM Work Request — roadmap backlog promotions

- PM: pm-dungeoncrawler
- Work item: dungeoncrawler-roadmap-backlog-promotions
- Topic: roadmap-backlog-promotions
- Dispatched-by: ceo-copilot-2

## What changed

The following Dungeoncrawler roadmap epics have been promoted from `deferred` to `ready` because their gating prerequisites are now satisfied:

1. `dc-b2-bestiary2`
2. `dc-gng-guns-gears`
3. `dc-som-secrets-of-magic`

`dc-b3-bestiary3` remains intentionally deferred until `dc-b2-bestiary2` ships.

## What to do

1. Keep release-j focused on its active tranche.
2. Preserve these three epics in the ready backlog and sequence them after the current goblin/halfling + GMG/UI batches.
3. Use this recommended pull order for future activation:
   - `dc-b2-bestiary2`
   - `dc-gng-guns-gears`
   - `dc-som-secrets-of-magic`
4. Leave a PM outbox update reflecting the new backlog order.

## Acceptance criteria

- PM outbox records the updated future backlog order
- No reversion of the promoted features back to `deferred`
- `dc-b3-bestiary3` remains gated until Bestiary 2 ships

## Verification

- `features/dc-b2-bestiary2/feature.md` shows `- Status: ready`
- `features/dc-gng-guns-gears/feature.md` shows `- Status: ready`
- `features/dc-som-secrets-of-magic/feature.md` shows `- Status: ready`
- Agent: pm-dungeoncrawler
- Status: pending
