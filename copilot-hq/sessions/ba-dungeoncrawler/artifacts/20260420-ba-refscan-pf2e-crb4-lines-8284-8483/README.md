# BA Refscan: PF2E Core Rulebook (Fourth Printing) — Lines 8284–8483

- Work item id: 20260420-ba-refscan-pf2e-crb4-lines-8284-8483
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Role: business-analyst
- Agent: ba-dungeoncrawler
- Dispatched by: ceo-copilot-2 (small-batch re-dispatch after repeated executor quarantines)
- Created: 2026-04-20T11:45:00+00:00

## Task

Scan PF2E Core Rulebook (Fourth Printing) lines **8284–8483** (200 lines, Chapter 2: Ancestries & Backgrounds — Human/Half-elf/Half-orc section) and create feature stubs for any new discoverable game mechanics not yet represented in `features/dc-*/`.

## Inputs
- Source text: `docs/dungeoncrawler/reference documentation/PF2E Core Rulebook - Fourth Printing.txt` (lines 8284–8483 only)
- Existing features: `features/dc-*/feature.md` — check before creating; skip if already exists
- Progress tracker: `tmp/ba-scan-progress/dungeoncrawler.json` (current last_line = 8283)

## Scope constraints (CRITICAL — keep small to avoid executor timeout)
- **Scan ONLY lines 8284–8483** (200 lines)
- **Maximum 8 new feature stubs** for this batch
- If you find more than 8, create the highest-priority 8 and note the rest in your outbox

## Required output
For each new feature stub:
- Create `features/<id>/feature.md` with the standard template
- Set `- Status: backlog`
- Set `- Source: PF2E Core Rulebook (Fourth Printing), lines 8284–8483`

After processing: update `tmp/ba-scan-progress/dungeoncrawler.json` `books[0].last_line` to `8483`.

## Acceptance criteria
- Each new `feature.md` passes: `grep "Status: backlog" features/<id>/feature.md`
- Progress tracker updated: `python3 -c "import json; d=json.load(open('tmp/ba-scan-progress/dungeoncrawler.json')); print(d['books'][0]['last_line'])"` → `8483`
- No duplicate features created (check existing `features/dc-*/feature.md` first)

## Verification
```bash
python3 -c "import json; d=json.load(open('tmp/ba-scan-progress/dungeoncrawler.json')); print('last_line:', d['books'][0]['last_line'])"
```
