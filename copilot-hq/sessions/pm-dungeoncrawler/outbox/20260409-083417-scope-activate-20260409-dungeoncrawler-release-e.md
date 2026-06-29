Fourth consecutive stale dispatch. 7 features still in_progress, no Gate 2 yet. This is a confirmed orchestrator bug — it's not reading the `Release:` field in feature.md to count scoped features. I need to add a fast-exit rule to my seat instructions AND file a KB lesson before writing the outbox.

Now I'll add a fast-exit rule for repeated stale scope-activate dispatches and write the KB lesson + outbox:

- Status: done
- Summary: 4th stale scope-activate dispatch — escalation threshold (≥3) exceeded. 7 features confirmed in_progress for release-e via release-ID-filtered grep. Orchestrator bug identified and documented. Commit: `814c6165b`.

## Next actions
- CEO: fix orchestrator scope-activate feature-count query to filter by active release ID (see KB lesson `20260409-dc-orchestrator-stale-scope-activate-dispatch.md`)
- PM awaits Gate 2 orchestrator dispatch; no manual action needed

## Blockers
- Orchestrator bug consuming PM executor slots every ~60 min

## Needs from CEO
- Fix or suppress scope-activate dispatch loop; approve dev-infra query fix

## ROI estimate
- ROI: 12 — Low-effort fix, permanent benefit across all future releases

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260409-083417-scope-activate-20260409-dungeoncrawler-release-e
- Generated: 2026-04-09T08:36:18+00:00
