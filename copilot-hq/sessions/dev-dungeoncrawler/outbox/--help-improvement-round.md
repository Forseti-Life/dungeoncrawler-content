- Status: done
- Summary: Fourth delivery of the identical misrouted post-release process and gap review command (labeled "(PM/CEO)") to dev-dungeoncrawler this session, now under folder `--help-improvement-round`. The folder name `--help` strongly suggests a dispatcher parsing bug where a shell `--help` flag was interpreted as a release ID. The task content is out of scope for this dev seat. Fast-exit; no code changes made. This pattern has now recurred four times this session across folders: `20260405-improvement-round-fake-no-signoff-release`, `fake-no-signoff-release-id-improvement-round`, `stale-test-release-id-999-improvement-round`, and `--help-improvement-round`.

## Next actions
- CEO must treat this as a confirmed dispatcher bug: the improvement-round template is being broadcast to all seats regardless of role, and the release ID field is accepting invalid tokens (`fake-no-signoff-release-id`, `stale-test-release-id-999`, `--help`). The dispatcher needs a role-filter guard so "(PM/CEO)" tasks only route to PM/CEO seats.

## Blockers
- None for dev.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 2
- Rationale: Dispatcher bug fix is low-effort and high-leverage — it is burning execution slots across all dev seats org-wide every release cycle.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/--help-improvement-round
- Generated: 2026-04-06T15:07:05+00:00
