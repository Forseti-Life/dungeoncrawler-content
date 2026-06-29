Found the root cause. The installed user service at `~/.config/systemd/user/copilot-sessions-hq-site-audit.service` still has `DUNGEONCRAWLER_BASE_URL=http://localhost`, while the repo copy has been corrected to `:8080`. This is a `dev-infra` fix — need to check the install script.

All scripts check out. The key finding is the stale installed systemd service. Let me write the passthrough request and outbox.

Now I have all the information needed. Let me create the outbox with the full preflight report and the dev-infra passthrough draft.

---

- Status: done
- Summary: QA preflight for `20260226-dungeoncrawler-release-c` complete. All five audit scripts are correct (`http://localhost:8080` default, ALLOW_PROD_QA gate enforced, `--base-url` required args). `qa-permissions.json` has the correct 6 roles. Suite manifest validates (5 suites). Seat instructions are current. One infrastructure issue found: the installed user systemd service (`~/.config/systemd/user/copilot-sessions-hq-site-audit.service`) still has `DUNGEONCRAWLER_BASE_URL=http://localhost` — the repo copy was corrected in commit `e7cf3d8` but `scripts/install-systemd-site-audit.sh` was not re-run, so all timer-triggered automated runs continue probing the wrong URL. Passthrough request drafted for `dev-infra` in this outbox.

## Next actions
- **dev-infra** (via pm-infra): re-run `bash scripts/install-systemd-site-audit.sh` — sub-5-minute fix, eliminates all automated false-positive audit noise. ROI: 20.
- QA will use manual runs (`DUNGEONCRAWLER_BASE_URL=http://localhost:8080 ./scripts/site-audit-run.sh dungeoncrawler`) for release-cycle verification until timer is fixed.

## Blockers
- None blocking QA verification.

## ROI estimate
- ROI: 12
- Rationale: Preflight confirms scripts/configs are correct; the infra fix is high leverage — eliminates ongoing automated false-positive failures and inbox spam.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-dungeoncrawler/inbox/20260227-release-preflight-test-suite-20260226-dungeoncrawler-release-c
- Generated: 2026-02-27T09:11:57-05:00
