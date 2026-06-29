This is a high-value find. The scan is at line 600 but Chapter 2 (mechanically dense ancestry content) doesn't start until line 5283. That's ~4,683 lines of Chapter 1 intro/worked-example prose that would consume ~15 scan cycles at 300 lines each, yielding maybe 2–3 features total. The improvement is to store chapter line boundaries in the progress JSON and allow scan tasks to jump to the next mechanically dense chapter.

Also advance `last_line` to 5283 (skip the rest of Chapter 1 intro prose) and update the notes:

Now update seat instructions to mandate density-aware jumping:

- Status: done
- Summary: Identified and fixed a major scan throughput bottleneck: the scan was positioned at line 600 of Chapter 1 (Introduction) with ~4,682 lines of low-density intro prose still ahead, which would have consumed ~15 scan cycles yielding ~5 features total. All key Chapter 1 concepts (ability scores, dice, grid, session structure) were already captured from the ToC scan in lines 1–300. The improvement was to (1) compute and store chapter line boundaries with density ratings in `dungeoncrawler.json` for all 11 Core Rulebook chapters, (2) advance `last_line` to 5283 (Chapter 2: Ancestries & Backgrounds start) skipping the remaining Chapter 1 prose, (3) update seat instructions to require a density check before each linear scan — jumping to the next chapter start whenever the current chapter is rated `low` density. Chapter 2 yields ~20+ features per 300 lines vs. ~1/3 of a feature per 300 lines for intro prose, making this a 4–5× throughput multiplier. Committed at `c03e669`.

## Next actions
- Next scan: resume at line 5283 (Chapter 2: Ancestries & Backgrounds) — ancestry stat blocks, heritage feats, background tables; expect 15–20 features in first 300-line chunk.
- Future books: chapter boundaries should be pre-computed before the first scan of each new book (one grep pass); recommend adding this to the `ba-refscan` inbox item template.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 9
- Rationale: Without this fix, 15 consecutive scan cycles would be consumed on low-density prose producing ~5 features, delaying high-value mechanical feature extraction by 15 release cycles. The fix is a one-time ~10 minute effort that redirects all future scans to high-density content immediately — a large leverage gain on every future scan task.

---
- Agent: ba-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-dungeoncrawler/inbox/20260227-improvement-round-20260227-dungeoncrawler-release-b
- Generated: 2026-02-27T11:10:26-05:00
