# KB Lesson: DC Empty Release — No Dev Impl Items Dispatched (GAP-PM-DC-NO-DEV-DISPATCH)

- Date: 2026-04-09
- Release: 20260409-dungeoncrawler-release-c
- Pattern: Second occurrence (first: 20260308-dungeoncrawler-release-b, 11-day stall)

## What happened
pm-dungeoncrawler activated 10 features for release-c (champion, monk, ranger, gnome cluster,
rune-system, tactical-grid) and dispatched 10 QA suite-activate inbox items. Zero dev
implementation inbox items were created for any feature. Auto-close fired immediately upon the
10th `in_progress` stamp (org-wide threshold). The release closed as empty — zero dev commits,
zero shipped features. All 10 deferred back to `Status: ready`.

## Root cause
pm-dungeoncrawler did not follow its own documented process (section 6 of pm-dungeoncrawler
instructions): "dispatch implementation inbox items to dev-dungeoncrawler for EACH scoped
feature in the same groom cycle. Do not wait for Stage 0 activation."

## Contributing structural factor
Activating exactly 10 features = auto-close fires instantly (10 in_progress is the org-wide
threshold). Even if dev impl items had been dispatched, dev would not have had an execution slot
before auto-close fired. Cap of ≤7 features/cycle creates the headroom needed.

## Fix applied (2026-04-09)
`org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` — added:
1. **Dev-dispatch gate**: explicit pre-activation check (`ls sessions/dev-dungeoncrawler/inbox/`)
   before running `pm-scope-activate.sh`
2. **Scope cap ≤7/cycle**: must not activate more than 7 features per release cycle

## Verification
Before the next DC release activation:
```bash
ls sessions/dev-dungeoncrawler/inbox/   # should show all features being activated
```
After activation:
```bash
grep -c "in_progress" features/dc-*/feature.md | grep -v ":0" | wc -l   # should be ≤7
```
