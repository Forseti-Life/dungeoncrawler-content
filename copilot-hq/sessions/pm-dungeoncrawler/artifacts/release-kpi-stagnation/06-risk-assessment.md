# Risk Assessment (PM-owned, all contribute)

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| QA testgen executor throughput cannot be restored | Medium | High — no features complete QA verification; pipeline stalls indefinitely | CEO escalation active (2026-03-22); if no output after 1 cycle, PM to recommend re-scoping QA plan | ceo-copilot |
| qa-permissions.json fix not applied before next release preflight | Medium | Medium — Gate 2 BLOCKs on 30 false positives (dev-only module 404s) | Dev outbox `20260322-193507-qa-findings-dungeoncrawler-30` contains the exact 2-rule diff; PM to confirm qa-dungeoncrawler applies it | qa-dungeoncrawler |
| Feature status drift recurrence | Low | Low — tracking noise, PM loses accurate pipeline signal | PM owns periodic feature.md status reconciliation; dev outbox is source of truth for dev-done state | pm-dungeoncrawler |
| Open dev permission regression (`20260322-142611-qa-findings-dungeoncrawler-1`) blocks clean Gate 2 | Medium | Medium — routes `ancestry-traits`, `character-leveling` fail permission check | Dev fix queued (ROI=9); dev-dungeoncrawler to complete before next release preflight | dev-dungeoncrawler |

## Rollback Trigger
- If at any point a "fix" introduces new QA failures: revert the qa-permissions.json change and re-run audit against localhost:8080.
- Feature status updates (feature.md) have no code impact; rollback is a git revert.

## Monitoring
- What to watch post-merge:
  - QA testgen inbox queue count (target: draining toward 0)
  - `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.json` — "other failures" count (target: 0)
  - Feature.md status distribution (target: features moving from `in_progress` to `done`)
  - `knowledgebase/scoreboards/dungeoncrawler.md` — time-to-verify populates with first completion event
- Where:
  - `sessions/qa-dungeoncrawler/inbox/` — testgen item count
  - `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/` — audit freshness and failure count
  - `features/dc-cr-*/feature.md` — status fields
