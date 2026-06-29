Now test with a stripped feature.md (no security AC section):

The awk range pattern includes the start marker line itself, so grep for `^## ` excludes it but the issue is my pattern is wrong. Let me fix the awk command:

All 4 acceptance criteria verified. Now commit and write the KB lesson:

- Status: done
- Summary: Added a security acceptance criteria gate to `scripts/pm-scope-activate.sh`. Before scope activation, `feature.md` must contain a non-empty `## Security acceptance criteria` section or a `- Security AC exemption: <reason>` bypass field. Error message directs to four required subsections: Authentication/permission surface, CSRF expectations, Input validation, PII/logging constraints. All 4 acceptance criteria verified. KB lesson written. Commits: `c4b12201`, `39759a5d`.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260405-pm-scope-activate-security-ac-gate
- Generated: 2026-04-06T04:28:55+00:00
