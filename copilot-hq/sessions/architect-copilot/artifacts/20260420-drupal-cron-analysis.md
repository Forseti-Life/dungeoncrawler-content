# DRUPAL CONTENT CRON JOBS — DEEP ANALYSIS

## Overview

**2 Jobs Analyzed**: forseti_cron (every 3h), dungeoncrawler_cron (every 30m)

**Key Finding**: Frequency mismatch (6x difference) without clear justification

**Status**: Both working correctly with proper flock protection

---

## JOB 1: forseti_cron (every 3 hours)

### What It Does
```bash
flock -n /tmp/forseti_cron.lock \
  ./vendor/bin/drush --uri=https://forseti.life cron 2>&1 | logger -t forseti_cron
```

1. Acquires lock
2. Runs Drupal cron for forseti.life site
3. Logs output to syslog

### Purpose
**Drupal Cron**: Scheduled maintenance tasks

**What Drupal Cron Does** (standard hooks):
- Search indexing (update search indexes)
- Cache cleanup (remove stale cache entries)
- Module hooks (modules register custom cron tasks)
- Database maintenance (analyze tables, cleanup)
- Session cleanup (clear expired sessions)

**Forseti-Specific Hooks** (likely):
- Job Hunter integration (may have cron tasks)
- Custom module cleanup
- Report generation

### Frequency: Every 3 Hours
- **Runs per day**: 8 (00:00, 03:00, 06:00, 09:00, 12:00, 15:00, 18:00, 21:00)
- **Cost**: ~2-5 seconds per run (search indexing is slow)
- **Total daily cost**: ~16-40 seconds

### Is 3 Hours Right?
- ✅ Good: Not too frequent (index updates don't need to be real-time)
- ✅ Good: Spaced throughout day (8 runs distribute load)
- ⚠️ Question: Why different from dungeoncrawler (30 min)?

### Current Status
- ✅ **WORKING**: Cron executes successfully
- ✅ **PROTECTED**: Flock prevents concurrent runs
- ⚠️ **UNOPTIMIZED**: Frequency differs from dungeoncrawler without clear reason

### Recommendations
✅ **KEEP AS-IS** (3 hours is reasonable)
  1. But clarify why it's different from dungeoncrawler
  2. Monitor execution time (is search indexing slow?)
  3. If search is bottleneck, consider moving to off-peak hours

---

## JOB 2: dungeoncrawler_cron (every 30 minutes)

### What It Does
```bash
flock -n /tmp/dungeoncrawler_cron.lock \
  ./vendor/bin/drush --uri=https://dungeoncrawler.forseti.life cron 2>&1 | logger -t dungeoncrawler_cron
```

1. Acquires lock
2. Runs Drupal cron for dungeoncrawler.forseti.life site
3. Logs output to syslog

### Purpose
**Drupal Cron**: Scheduled maintenance for PF2E assistant site

**Dungeoncrawler-Specific Cron Tasks** (likely):
- Rules/search indexing
- Cache cleanup
- Custom module hooks
- Report generation
- Data sync (if any)

### Frequency: Every 30 Minutes
- **Runs per day**: 48 (00:00, 00:30, 01:00, 01:30, ...)
- **Cost**: ~1-3 seconds per run (lighter than forseti)
- **Total daily cost**: ~48-144 seconds (1-2 minutes)

### Why 30 Minutes (vs 3 Hours)?
**Possible Reasons**:
1. DungeonCrawler has time-sensitive features (real-time caching?)
2. Heavier content sync (updates from PF2E API?)
3. Different module requirements
4. Historical accident (copied from forseti, never updated)

**Most Likely**: Reason unknown (should be documented)

### Is 30 Minutes Right?
- ✅ Good: Real-time-ish (within 30 min)
- ⚠️ Question: Is this necessary or over-engineered?
- ⚠️ Cost: 48 runs/day is 6x forseti

### Current Status
- ✅ **WORKING**: Cron executes successfully
- ✅ **PROTECTED**: Flock prevents concurrent runs
- ⚠️ **SUSPICIOUS**: 6x frequency difference from forseti needs justification

### Recommendations
⚠️ **INVESTIGATE BEFORE CHANGING**:
  1. Why is dungeoncrawler 6x more frequent than forseti?
  2. What cron hooks are registered? (Check module .module files)
  3. Is 30 min necessary or historical?
  4. Could it be 1-3 hours like forseti?

**If no reason found**: Could reduce to 1-2 hours (cost savings: 46 fewer runs/day)

---

## Comparison: Frequency Mismatch

| Site | Frequency | Runs/Day | Cost (est) | Reason |
|------|-----------|----------|-----------|--------|
| **forseti.life** | 3 hours | 8 | ~16-40 sec | ? |
| **dungeoncrawler** | 30 min | 48 | ~48-144 sec | ? |
| **Ratio** | 1:6 | 1:6 | 1:6 | ❓ Unknown |

**Question**: Is this intentional or accidental?

---

## Drupal Search Indexing Deep Dive

### Why Search Indexing Matters
Drupal's search index powers:
- Site search functionality
- Views with search filters
- Admin search

**If index is stale**:
- Users can't find content
- New jobs not searchable (Job Hunter!)
- Admin experience degraded

### Search Indexing Cost
```
Time = O(N) where N = new/modified content since last index
- Job Hunter: High churn (new jobs daily)
- DungeonCrawler: Moderate churn (PF2E content changes)
- Forseti: Unknown churn
```

**Hypothesis**:
- **forseti.life (3h)**: Job Hunter has high churn, might need more frequent indexing
- **dungeoncrawler (30m)**: PF2E has constant updates, needs more frequent indexing

**Counter-hypothesis**:
- If Job Hunter is high-churn, forseti should be MORE frequent, not LESS
- This contradicts current schedule

### Recommendation
🔍 **INVESTIGATE**: Check Drupal's search_index table
```bash
drush sqlq "SELECT COUNT(*) FROM search_index WHERE sid IN (1,2,3);"
```
If growing unbounded, search indexing is failing.

---

## Architecture Observations

### Strengths
- ✅ Proper flock protection
- ✅ Separate logging per site
- ✅ Both sites configured independently (good isolation)

### Weaknesses
- ⚠️ **Frequency inconsistency**: No documentation why different
- ⚠️ **No monitoring**: Can't tell if cron tasks are slow/failing
- ⚠️ **No timeout**: `drush cron` could run forever
- ⚠️ **No alerts**: If search indexing fails, nobody knows

### Monitoring Gaps
Currently missing:
- [ ] Alert if cron execution > 10 seconds
- [ ] Alert if search_index table growing (indexing falling behind)
- [ ] Metrics: cron execution time by site
- [ ] Metrics: search index size over time

---

## Critical Questions

**Q1: Why is frequency different?**
- Is this intentional?
- Different module requirements?
- Different content churn?
- Need to document reason

**Q2: Is search indexing failing?**
- Check search_index table for growth
- Check drush search:rebuild logs
- Are users complaining about search?

**Q3: Should they be unified?**
- Could both run every 1 hour (compromise)?
- Could both run every 30 min (match dungeoncrawler)?
- Or keep different (if justified)?

**Q4: Are other cron tasks needed?**
- Check each module's .module file for hook_cron
- Ensure all hooks are registering properly
- Check for failed hook executions

---

## Recommendations Summary

| Job | Status | Necessity | Recommendation |
|-----|--------|-----------|-----------------|
| **forseti_cron** | ✅ Working | YES (content sync) | **KEEP** (document frequency choice) |
| **dungeoncrawler_cron** | ✅ Working | YES (content sync) | **INVESTIGATE** frequency justification |

---

## Action Items (Prioritized)

### Immediate (Safety)
1. [ ] Document why dungeoncrawler is 6x more frequent than forseti
2. [ ] Check if search indexing is falling behind (monitor search_index table)
3. [ ] Verify no cron hooks are failing silently

### Short Term (Optimization)
4. [ ] Profile cron execution time (is it <5 seconds?)
5. [ ] Add monitoring for cron failures
6. [ ] Set up alerts for slow cron runs (>10 seconds)

### Medium Term (Cost Optimization)
7. [ ] If no reason found for 30-min frequency, consider reducing to 1-2 hours
8. [ ] Profile search indexing specifically (time per item)
9. [ ] Consider moving heavy cron tasks to off-peak hours

---

## Conclusion

**Both Drupal cron jobs are NECESSARY** (content sync, search indexing).

**But frequency mismatch is SUSPICIOUS** and should be investigated:
- Why 3h vs 30m?
- Is it intentional?
- Could they be unified?
- Is search indexing keeping up?

**No urgent changes needed**, but recommend clarifying the design decision and adding monitoring.

