Good — I have enough evidence. Let me now write the verification report, update the regression checklist, and commit.

Now I have enough evidence to write the full report.

Now update the regression checklist:

Commit: `f7606c76d`

---

- Status: done
- Summary: Verified reqs 2190–2218 (29 basic actions) via static source trace and live drush probes against EncounterPhaseHandler. Inbox "Expected Failures" annotation was stale — all 29 actions are present in `getLegalIntents()` with full case handlers. 21/29 PASS. 8 defects/gaps found: DEF-2194 (Delay does not fire start/end-of-turn effects immediately), DEF-2218 (cover_active not cleared on attack/move), GAP-2204 (map_at_ready stored but not applied when readied reaction fires), GAP-2212 (Sense Motive retry cooldown tracked but not enforced — same-round repeats allowed), GAP-2201 (Leap min speed ≥15 not checked), GAP-2209 (Seek object path absent), DEF-2195 (delayed actions not zeroed after full round), GAP-2215 (Step via non-land Speed not blocked). Committed `f7606c76d`.

## Next actions
- Dev-dungeoncrawler to address medium defects: DEF-2218 (1-line fix in strike/stride/step/crawl/leap: clear cover_active), GAP-2212 (add pre-check in sense_motive case), DEF-2194 (call processEndOfTurnEffects in delay case), GAP-2204 (apply map_at_ready when reaction fires)
- Next inbox items: `20260406-roadmap-req-2145-2150-counteract`

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 40
- Rationale: Basic actions are the highest-frequency gameplay layer — Strike, Stride, Seek, Cover, Aid, Escape, Ready are used every encounter. 3 medium defects affect combat outcomes directly. Fixes are low-effort (1-5 lines each) relative to correctness gain.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-roadmap-req-2190-2218-basic-actions
- Generated: 2026-04-06T23:40:27+00:00
