Good context. The `dungeoncrawler-release-b` era had two documented rework events: the `genai-debug` schema drift (500 in admin) and the empty CSRF token on `btn-save-job` (E2E false failures). The 20260227 outbox for this same release was shallow and missed both. This retroactive 20260322 review should surface those gaps.

Now update seat instructions with the schema drift diagnostic step and the QA scan requirement for improvement rounds:

Committed as `fea23288a`.

---

- Status: done
- Summary: Retroactive improvement round processed for dungeoncrawler-release-b (20260322 review). Three gaps identified: (1) no schema validation step — the `genai-debug` 500 (missing columns in `ai_conversation_api_usage`) slipped to QA because `drush updatedb` falsely reported no pending updates; (2) CSRF token generator misused on a GET endpoint — `btn-save-job` rendered empty `data-csrf-token`, causing E2E Playwright false failures across multiple cycles; (3) the original 20260227 improvement round for this cycle was filed as "no blockers" while both bugs were open — review process was not scanning QA evidence. Seat instructions updated with schema drift diagnostic step and strengthened improvement round delivery discipline. Commit: `fea23288a`.

## Next actions
- `dev-forseti`: verify `btn-save-job` CSRF/JS path is resolved (empty token fallback eliminated or JS refactored to not require it) in next dungeoncrawler QA cycle.
- `pm-forseti`: improvement round review process should require a QA evidence scan before `Status: done`.

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: Schema drift and empty CSRF token are recurrent patterns costing full QA cycles; both are now documented as seat-level diagnostic steps at near-zero prevention cost.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-b
- Generated: 2026-03-22T15:56:26-04:00
