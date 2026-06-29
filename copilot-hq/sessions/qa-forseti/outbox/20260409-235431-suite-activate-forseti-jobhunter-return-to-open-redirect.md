- Status: done
- Summary: Activated 3 suite entries and 1 permission rule for `forseti-jobhunter-return-to-open-redirect`. Static TC-01 verifies no unguarded `strpos`-only `return_to` validation remains in `CompanyController.php` or `ApplicationActionController.php`; static TC-02 (preg) confirms `preg_match` guard is present in both controllers; functional TC-03 verifies `//evil.com` is rejected (redirects to default), valid `/path` is accepted, and `http://evil.com` is rejected. One `qa-permissions.json` rule added documenting `return_to` ACL expectations on the company delete route. All entries tagged `feature_id: forseti-jobhunter-return-to-open-redirect`. Suite count 95→98, permissions 60→61, validator clean. Committed `33cb5cb64`.

## Next actions
- Dev implements `preg_match('/^\/(?!\/)/', $return_to)` guard in both controllers
- Static TCs run immediately after Dev commits — no env vars needed
- Functional TC requires `FORSETI_COOKIE_AUTHENTICATED` and an actual company-delete route with `return_to` param; note in test that route discovery may be needed at verification time

## Blockers
- None

## ROI estimate
- ROI: 40
- Rationale: P2 security fix (open redirect bypass) — static tests catch the pattern immediately; functional test provides ongoing regression protection and confirms the fix closes the actual attack vector.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260409-235431-suite-activate-forseti-jobhunter-return-to-open-redirect
- Generated: 2026-04-10T00:16:15+00:00
