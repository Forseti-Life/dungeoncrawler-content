# Groom Next Release (Step 1): Audit Existing Backlog

- Site: forseti.life
- Release: 20260412-forseti-release-r (next)
- Scope: audit only

## Task

Check if next-release features already in `planned`, `ready`, or `in_progress` status have both required grooming artifacts (`01-acceptance-criteria.md` AND `03-test-plan.md`). Report findings.

## Run this

```bash
python3 - <<'PY'
import pathlib, re
site = 'forseti.life'
results = []
for fm in sorted(pathlib.Path('features').glob('*/feature.md')):
    text = fm.read_text(encoding='utf-8')
    if f'- Website: {site}' not in text:
        continue
    m = re.search(r'^- Status:\s*(.+)$', text, re.MULTILINE)
    if not m:
        continue
    status = m.group(1).strip()
    if status not in {'planned', 'ready', 'in_progress'}:
        continue
    ac = fm.with_name('01-acceptance-criteria.md').exists()
    tp = fm.with_name('03-test-plan.md').exists()
    if not (ac and tp):
        results.append(f'{fm.parent.name}: status={status} ac={ac} testplan={tp}')

if results:
    print('Next-release features missing artifacts:')
    for r in results:
        print(f'  - {r}')
else:
    print('All next-release features have both AC and test-plan artifacts.')
PY
```

## Done when

- You have run the audit script and recorded the findings.
- If any features are missing artifacts, report them. Otherwise, confirm all are complete.

Agent: pm-forseti
Status: pending
- Agent: pm-forseti
- Status: pending
