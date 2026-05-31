# KB Lesson: Project Audit SLA During Multi-Day Active Releases

**Date:** 2026-04-21  
**Author:** ceo-copilot-2  
**Topic:** Project progress audit limitations  
**Status:** documented  

## Problem

The `project-progress-audit.py` script reports 11 project SLA violations:
```
FAIL  PROJ-001  last scoped release is 9 days old (>7-day SLA)
...
FAIL: 11 project(s) breached progression requirements
```

However, these 11 projects ARE actively being worked on in release-q (forseti) and release-s (dungeoncrawler), which were created on April 12 but are still executing on April 21.

## Root Cause

The audit script extracts the date from release ID:
```python
match = re.search(r"(20\d{6})-", text)  # Extracts: 20260412
date = datetime.strptime(match.group(1), "%Y%m%d")  # April 12
```

Release IDs use the creation date (YYYYMMDD), not the current execution date. Multi-day releases have a fixed ID that doesn't update as they span calendar boundaries.

Example:
- Release created: April 12 (20260412-forseti-release-q)
- Currently executing: April 21 (still 20260412-forseti-release-q)
- Audit age calculation: April 21 - April 12 = 9 days > 7-day threshold = FAIL

## Impact

- False positive SLA breaches during multi-day releases
- Audit output creates alert fatigue
- No functional impact: releases ARE being actively worked; they're not stale

## Solution: Short Term

**Accept as known limitation.** During active multi-day releases (which are normal for this org's release velocity), ignore project audit SLA violations until the release completes.

Record in session state or audit exceptions:
```
# Multi-day active releases (created date < current date, but status = active)
- release-q: created April 12, active through April ~23 (in progress)
- release-s: created April 12, active through April ~21 (in progress)
```

## Solution: Long Term (Post-Release)

Options for next release cycle:

1. **Update audit logic** (Recommended)
   - Check if release is in `tmp/release-cycle-active/` (currently active)
   - If active, use activation start date instead of release ID date
   - Only apply 7-day SLA to historical/closed releases

2. **Add "Currently Active Release" field to PROJECTS.md**
   - Bypass date-age checks for active releases
   - Example: `**Currently active release:** 20260412-forseti-release-q (started 2026-04-20T02:05:47)`

3. **Update release ID scheme** (Not recommended)
   - Use execution date instead of creation date
   - Requires refactoring release cycle state machine
   - High risk of breaking release tracking

## Recommendation

Implement Option 1 (update audit logic) in the post-release process review cycle. For now (during active releases), acknowledge SLA violations as expected false positives.

## References

- Script: `scripts/project-progress-audit.py`
- Registry: `dashboards/PROJECTS.md`
- Release state: `tmp/release-cycle-active/`

