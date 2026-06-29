Both checks pass (syntax clean, `grep` exit 1 = zero matches). Committing:

---

- Status: done
- Summary: Fixed the missed open-redirect bypass in `ResumeController.php:243` — replaced `strpos($return_to, '/') !== 0` with `!preg_match('/^\/(?!\/)/', $return_to)`, matching the pattern applied in commit `233d400c9`. PHP syntax verified clean. `grep -n "strpos.*return_to"` across all job_hunter controllers now returns 0 results (AC-1, AC-2 both pass). Committed `605d4230a`.

## Next actions
- QA-forseti: re-run `forseti-jobhunter-return-to-open-redirect` unit test suite — AC-1 verification: `grep -n "strpos.*return_to" sites/forseti/web/modules/custom/job_hunter/src/Controller/*.php` must return 0 results

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 10
- Rationale: Security fix unblocking a QA BLOCK on an active release; protocol-relative redirect bypass is a live vulnerability; single-line change with zero regression risk.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260410-return-to-fix-resumecontroller
- Generated: 2026-04-10T02:45:13+00:00
