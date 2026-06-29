# Stage 0: Scope Selection for 20260407-dungeoncrawler-release-b

**Priority: URGENT — Release-b is active and has 0 features activated in suite.json**

- Site: dungeoncrawler
- Active release: 20260407-dungeoncrawler-release-b
- Release cap: 10 features (auto-close fires at 10)

## Background
Feature statuses were reset by CEO. All groomed features now correctly show:
- `Status: ready` if they have all 3 artifacts (feature.md + 01-acceptance-criteria.md + 03-test-plan.md)
- `Status: planned` if artifacts incomplete

The QA suite is empty. You must run `pm-scope-activate.sh` to add tests and kick off dev.

## Step 1: Review ready pool

```bash
python3 -c "
import os
features = sorted(os.listdir('features'))
ready = [f for f in features if f.startswith('dc-') and os.path.exists(f'features/{f}/feature.md') and '- Status: ready' in open(f'features/{f}/feature.md').read()]
print(f'Ready features: {len(ready)}')
for f in ready: print(f'  {f}')
"
```

## Step 2: Activate up to 10 features

For each selected feature, run:
```bash
bash scripts/pm-scope-activate.sh dungeoncrawler <feature-id>
```

**Recommended activation order (per prior grooming analysis):**
1. `dc-cr-low-light-vision` — standalone, no deps
2. `dc-cr-darkvision` — standalone, no deps
3. `dc-home-suggestion-notice` — standalone, no deps
4. `dc-cr-languages` — foundational, unblocks elf-ancestry chain
5. `dc-cr-conditions` — foundational, unblocks encounter/tactical
6. `dc-cr-difficulty-class` — unblocks DC-check features
7. `dc-cr-equipment-system` — unblocks many ch06 skills
8. `dc-cr-elf-ancestry` — after languages (check Status: ready after languages activated)
9. `dc-cr-elf-heritage-cavern` — after elf-ancestry
10. Up to one more from ready pool (your discretion)

Note: `pm-scope-activate.sh` requires `Status: ready`. Do not activate features with `Status: in_progress` or `planned`.

## Step 3: Write outbox confirming

- Total features activated
- Suite.json test count before/after
- List of activated feature_ids
