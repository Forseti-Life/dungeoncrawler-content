Now write the outbox:

- Status: done
- Summary: Added a code-review dispatch block to `scripts/release-cycle-start.sh` closing GAP-CR-1. Every release cycle start now creates `sessions/agent-code-review/inbox/<date>-code-review-<site>-<slug>/` with pre-ship code-review instructions (CSRF, authz bypass, schema hook pairing, hardcoded paths) and ROI 10. Dedup-safe, both dungeoncrawler and forseti verified by test runs. Existing QA preflight + PM grooming unaffected. Committed `7eceb1b2`.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260405-release-cycle-code-review-autoqueue
- Generated: 2026-04-06T03:54:02+00:00
