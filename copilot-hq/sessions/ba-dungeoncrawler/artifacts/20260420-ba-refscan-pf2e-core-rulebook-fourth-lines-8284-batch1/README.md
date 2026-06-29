# Re-dispatch: PF2E Core Rulebook Refscan — lines 8284-8583 (batch 1)

- Agent: ba-dungeoncrawler
- Site: dungeoncrawler
- Next release: 20260412-dungeoncrawler-release-r
- Dispatched-by: ceo-copilot-2
- Dispatched-at: 2026-04-20T07:44:30Z

## Background

Prior batch (lines 7984–8283, 2026-04-19) completed successfully: 30 feature stubs created. Next batch (lines 7984–8283 on 2026-04-20) was quarantined after 3 executor failures — likely too large. Re-dispatching in 300-line batches with max 15 features per batch.

## Task

Scan PF2E Core Rulebook (Fourth Printing) lines **8284–8583**:

```
/home/ubuntu/forseti.life/docs/dungeoncrawler/reference documentation/PF2E Core Rulebook - Fourth Printing.txt
```

1. Read lines 8284–8583 of the file (use `sed -n '8284,8583p'` or equivalent)
2. Extract **up to 15** implementable game feature stubs (skip lore/prose, focus on mechanics)
3. For each feature, create `features/dc-<slug>/feature.md` (check existing `features/` to avoid duplicates)
4. Update `tmp/ba-scan-progress/dungeoncrawler.json`: set `books[0].last_line` to 8583
5. Commit changes with message: `ba-dungeoncrawler: refscan PF2E CRB lines 8284-8583`

## Output format

Write outbox with `Status: done` and summary of lines scanned + feature count. Keep outbox short.

## Acceptance criteria
- `tmp/ba-scan-progress/dungeoncrawler.json` `last_line` updated to 8583
- Feature stubs created in `features/dc-*/feature.md` (0 to 15)
- Git commit present
- Outbox: `Status: done`

## Verification
- `cat tmp/ba-scan-progress/dungeoncrawler.json | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['books'][0]['last_line'])"` → 8583
