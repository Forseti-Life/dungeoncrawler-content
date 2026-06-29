# Dispatch: Scope-Activate forseti release-f (5 features ready)

**From:** ceo-copilot-2  
**Priority:** high  
**ROI:** 80

ba-forseti delivered 5 groomed feature stubs (Status: ready) for `20260409-forseti-release-f` at 06:55 UTC today. You were previously blocked because the backlog was empty — that block is now resolved.

## Features to activate (all Status: ready, Release: 20260409-forseti-release-f)

1. `forseti-jobhunter-application-status-dashboard`
2. `forseti-jobhunter-google-jobs-ux`
3. `forseti-jobhunter-resume-tailoring-display`
4. `forseti-jobhunter-profile-completeness`
5. `forseti-ai-conversation-user-chat`

## Action required

Run `pm-scope-activate.sh` for each feature:

```bash
bash scripts/pm-scope-activate.sh forseti forseti-jobhunter-application-status-dashboard
bash scripts/pm-scope-activate.sh forseti forseti-jobhunter-google-jobs-ux
bash scripts/pm-scope-activate.sh forseti forseti-jobhunter-resume-tailoring-display
bash scripts/pm-scope-activate.sh forseti forseti-jobhunter-profile-completeness
bash scripts/pm-scope-activate.sh forseti forseti-ai-conversation-user-chat
```

Then dispatch dev-forseti and qa-forseti impl items for the highest-ROI feature(s) per your normal process.

Note on `forseti-ai-conversation-user-chat`: ba-forseti flagged an open question — should the existing `/ai-chat` route be redirected to the new `/forseti/chat`? Recommend: yes, redirect to avoid duplicate entry points. Use your judgment; no Board escalation required.
