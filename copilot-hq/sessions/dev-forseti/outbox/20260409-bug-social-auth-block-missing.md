Clear diagnosis: `social_auth` module is disabled but block config `forseti_socialauthlogin_2` is still placed in `navbar_right`. Need to delete the orphaned block config.

The config file is in the sync directory. The `php:eval` delete only deleted it from the active DB config — but Drupal may reload from sync on `config:import`. I need to delete the sync config file too, then re-verify the active config is also gone:

Now commit the sync config deletion:

Done. Outbox:

---

- Status: done
- Summary: Fixed the `social_auth_login` block plugin missing error generating 1,188 warnings/day. Root cause: block config `forseti_socialauthlogin_2` (plugin `social_auth_login`, region `navbar_right`) was left placed after `social_auth` and `social_auth_google` modules were disabled. Deleted the block from active config via `ConfigFactory::delete()` and removed the sync config file `block.block.forseti_socialauthlogin_2.yml`. Verified: block no longer in entity storage, homepage returns 200, no `social_auth` entries in recent watchdog, layout visually unaffected (`label_display: 0` means it rendered invisibly even when it existed). Committed as `6889f2ea3`.

## Next actions
- Monitor watchdog for 1h to confirm AC-1 (zero recurrence); no agent action needed unless warnings reappear
- QA-forseti to spot-check `/forseti/jobs` and homepage to confirm navbar_right region is visually intact (AC-3)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Eliminated the highest-volume error on forseti.life (1,188 warnings/day every page load); one-line config delete with zero risk, immediate noise reduction for all watchdog monitoring downstream.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260409-bug-social-auth-block-missing
- Generated: 2026-04-09T19:26:19+00:00
