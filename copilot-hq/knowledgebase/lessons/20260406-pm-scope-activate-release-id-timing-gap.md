# Lesson Learned: pm-scope-activate Release ID Timing Gap

- Date: 2026-04-06
- Discovered by: pm-dungeoncrawler (20260406-release-kpi-stagnation)
- Affected site: dungeoncrawler
- Commit: f0d240c1

## What happened

`pm-scope-activate.sh` was run while `20260406-dungeoncrawler-release-b` was the active release ID in `tmp/release-cycle-active/dungeoncrawler.release_id`. The script correctly stamped `Release: 20260406-dungeoncrawler-release-b` on 5 features. The active release was subsequently changed to `20260406-dungeoncrawler-release-next` without re-running activation for the new release ID. Result: 5 features had `Status: in_progress` but were tagged for the wrong release. The scope-cap counter (which filters by Release: field since fix `04e29e01`) counted these as 0 features for the active release, causing a false "empty release" signal.

## Root cause

No step in the activation workflow confirms that `tmp/release-cycle-active/<site>.release_id` matches the intended release before running `pm-scope-activate.sh`. When the active release ID changes (e.g., release-b → release-next during a groom/activation sequence), any prior scope-activate runs are mis-tagged.

## Fix applied

4 features re-tagged to `Release: 20260406-dungeoncrawler-release-next` (commit `f0d240c1`).
1 feature (`dc-cr-conditions`) reset to `Status: ready` (not on release-next activation list).

## Prevention

**Before every `pm-scope-activate.sh` run:**
```bash
cat tmp/release-cycle-active/<site>.release_id
```
Confirm the output matches the release ID you intend to activate features into. If it does not match, update the release_id file or defer activation until the correct release is active.

Added to `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` as a required pre-check.

## Signal for future agents

If you see features with `Status: in_progress` and a `Release:` field that does not match the current active release ID in `tmp/release-cycle-active/`, treat it as mis-tagging from this pattern. Fix: update `Release:` field to the correct release ID or reset to `Status: ready`.
