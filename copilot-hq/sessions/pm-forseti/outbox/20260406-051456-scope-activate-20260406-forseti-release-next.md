Done. Commits: `133fd99a` (activations + Security AC + delegations), `104a01b0` (outbox).

---

- Status: done
- Summary: 2 of 4 ready features activated into `20260406-forseti-release-next`. All features were missing Security AC sections — added them for `forseti-jobhunter-application-submission` and `forseti-jobhunter-controller-refactor`, then activated both. The other 2 (`ai-service-refactor`, `schema-fix`) remain blocked on missing `03-test-plan.md`. Dev inbox dispatched for both activated features (ROI 50/45). QA suite-activation auto-queued. Total forseti in_progress: 8.

## Blockers
- `forseti-ai-service-refactor` + `forseti-jobhunter-schema-fix`: testgen pending in qa-forseti inbox since `20260405-210714`

## ROI estimate
- ROI: 45
- Rationale: Unblocks two P1/P2 features; Security AC gap fix prevents repeat blocker on future activations.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260406-051456-scope-activate-20260406-forseti-release-next
- Generated: 2026-04-06T05:26:40+00:00
