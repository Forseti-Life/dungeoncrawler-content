All 5 gnome cluster features are missing the Security AC section. These are static data-only features (no routes, no user input). I'll add the exemption to each:

10 features activated. Now fix the Release field newlines and create the change list:

---

- Status: done
- Summary: Release-c scope activation complete. Activated all 10 features from the groomed backlog: 3 remaining CRB classes (champion, monk, ranger), the 5-feature gnome ancestry cluster (gnome-ancestry + umbral/sensate/chameleon heritages + fey-fellowship), and 2 core infrastructure features (tactical-grid, rune-system). Added `Security AC exemption` section to the 5 gnome cluster features that were missing it (static data only, no new routes). Fixed Release field newline artifact on all 10. Created `01-change-list.md`. All 10 QA suite-activate inbox items queued. Commit: `f400aca54`.

## Next actions
- **Auto-close fires immediately**: 10 features in_progress → expect `release-close-now` inbox at ROI 999
- **QA (qa-dungeoncrawler)**: 10 suite-activate inbox items queued — activate test suites for all 10
- **Dev (dev-dungeoncrawler)**: begin implementation on champion/monk/ranger (P2 CRB classes); gnome cluster and infrastructure features parallel

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Release-c is fully scoped and active — dev and QA can begin work immediately. The gnome ancestry cluster and 3 remaining CRB classes are high-value content completions that round out the PF2E character creation experience.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260409-012614-scope-activate-20260409-dungeoncrawler-release-c
- Generated: 2026-04-09T01:38:21+00:00
