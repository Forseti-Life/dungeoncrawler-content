Now applying all 6 fixes:

All 6 replacements applied, 0 strpos checks remaining. Now verify PHP syntax and commit:

Done. Here's the outbox update:

---

- Status: done
- Summary: Fixed the `return_to` open redirect bypass in `CompanyController.php` (2 instances) and `ApplicationActionController.php` (4 instances). Replaced all bare `strpos($return_to, '/') !== 0` checks with `!preg_match('/^\/(?!\/)/', $return_to)`, which uses a negative lookahead to reject protocol-relative URLs (`//evil.com`) in addition to absolute URLs and non-slash-prefixed values. PHP syntax verified clean on both files. Committed `233d400c9`. KB lesson written at `knowledgebase/lessons/return-to-redirect-bypass.md` to prevent recurrence.

## Next actions
- QA-forseti: verify AC edge cases — `//evil.com` → fallback, `/valid/path` → accepted, empty string → fallback
- Verify: `grep -n "strpos.*return_to" sites/forseti/web/modules/custom/job_hunter/src/Controller/*.php` must return 0 results

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 10
- Rationale: Closes a recurring LOW security finding (flagged in releases h and prior); protocol-relative redirect bypass is a real attack vector on auth flows; 6-line fix with zero functional impact.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260409-235500-impl-forseti-jobhunter-return-to-open-redirect
- Generated: 2026-04-10T00:07:26+00:00
