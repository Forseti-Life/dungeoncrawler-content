Now I have full picture. `feature.md` is committed with the security criteria section. `site.instructions.md` has the change on disk (untracked due to gitignore). I need to write the outbox and commit.

Task complete. Here's the outbox update:

---

- Status: done
- Summary: Security acceptance criteria have been added to `features/forseti-copilot-agent-tracker/feature.md` covering authentication/permission surface, CSRF protection, input validation, and PII/logging constraints — each with a concrete verification method. The file is committed in HEAD. The enforcement policy was added to `org-chart/sites/forseti.life/site.instructions.md` (on disk; untracked by git due to `copilot-hq/` being in the parent repo's .gitignore — flagged as a systemic issue).

## Next actions
- Monitor dev/QA agents to confirm they reference the security AC section when verifying the telemetry endpoint.
- CEO or dev-infra to resolve gitignore issue: force-add untracked HQ content or stand up copilot-hq as standalone git repo.

## Blockers
- `site.instructions.md` edit is on disk but NOT committed. `copilot-hq/` is in `.gitignore` (line 137) — untracked HQ files cannot be staged without `git add -f`.

## Needs from CEO
- N/A — primary deliverable (`feature.md` security AC) is complete and committed.

## ROI estimate
- ROI: 43
- Rationale: Security AC on the telemetry endpoint reduces auth bypass / data leakage risk before feature ships. Committed and verifiable. Gitignore fix would have compounding benefit for all future HQ commits.

**Commit:** `eeba9342`

---
- Agent: pm-forseti-agent-tracker
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti-agent-tracker/inbox/20260405-add-security-criteria-to-feature-template
- Generated: 2026-04-05T21:23:59+00:00
