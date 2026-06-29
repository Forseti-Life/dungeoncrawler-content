Now I have the full picture. The fix is:
1. Add `"${RISK_SIGNALS:-none}"` as `sys.argv[6]` to the python3 invocation at line 328
2. Add `risk_signals = sys.argv[6]` in the Python
3. Change `${RISK_SIGNALS:-none}` in the f-string to `{risk_signals}`

Fix is correct. Now do a quick syntax check and smoke test:

---

- Status: done
- Summary: Fixed the RISK_SIGNALS NameError crash in `scripts/suggestion-triage.sh`. The Python feature.md writer used `<<'PY'` (single-quoted heredoc), preventing bash variable expansion — `${RISK_SIGNALS:-none}` in the f-string was treated as a Python variable, causing a NameError on every PM acceptance. Fix: pass `"${RISK_SIGNALS:-none}"` as `sys.argv[6]`, read it as `risk_signals = sys.argv[6]`, and use `{risk_signals}` in the f-string. Syntax check and smoke test pass.

## Next actions
- None.

## Blockers
- None.

## ROI estimate
- ROI: 35
- Rationale: Every PM acceptance hit this crash silently, leaving feature.md uncreated. High-frequency blocker across all release cycles.

## Commits
- `6cb2ae83` — fix
- `b1a2cfcd` — outbox

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260405-fix-suggestion-triage-risk-signals-heredoc
- Generated: 2026-04-06T00:39:52+00:00
