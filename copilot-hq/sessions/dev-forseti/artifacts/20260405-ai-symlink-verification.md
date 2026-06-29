# AI Conversation Symlink Verification — stlouisintegration + theoryofconspiracies

**Date:** 2026-04-05
**Agent:** dev-forseti
**Task:** 20260405-ai-conversation-bedrock-fixes-verify (Task 1)

## Symlink chain verification

### stlouisintegration
```
/var/www/html/stlouisintegration/web/modules/custom/ai_conversation
  -> /home/ubuntu/forseti.life/shared/modules/ai_conversation
     -> /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation  ✅
```
- Symlink type: `lrwxrwxrwx` (confirmed via `ls -la`)
- Target resolves to forseti canonical source ✅

### theoryofconspiracies
```
/var/www/html/theoryofconspiracies/web/modules/custom/ai_conversation
  -> /home/ubuntu/forseti.life/shared/modules/ai_conversation
     -> /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation  ✅
```
- Symlink type: `lrwxrwxrwx` (confirmed via `ls -la`)
- Target resolves to forseti canonical source ✅

## Module status

| Site | Module status | Notes |
|---|---|---|
| stlouisintegration | **Enabled** (drush confirmed) | ai_conversation active |
| theoryofconspiracies | **Disabled** (drush confirmed) | Symlink present but module not enabled — not in active use |

## Error log check

- `/var/log/apache2/stlouisintegration_error.log`: No PHP fatal errors or `ai_conversation`-related errors. Most recent entries are scanner probes (external bots attempting `/cgi-bin/` paths) and `.env` denials — normal.
- `/var/log/apache2/theoryofconspiracies_error.log`: Empty / no errors.

## Decision on theoryofconspiracies

- The symlink is correctly in place.
- The module is disabled — no broken include paths to worry about.
- No action needed; the site is not using AI conversation currently.
- If/when TOC enables the module, it will inherit forseti canonical code (including all Bedrock fixes) via the symlink chain.

## Acceptance criteria status

- [x] Symlinks confirmed resolving to forseti canonical source
- [x] stlouisintegration: module enabled, no PHP errors in logs
- [x] theoryofconspiracies: module disabled (not in use), no errors, symlink intact
- [x] No ai_conversation-related PHP fatal errors in either error log

## Conclusion

✅ Task 1 COMPLETE. Both symlinks are correct and intact. stlouisintegration serves ai_conversation from forseti canonical. theoryofconspiracies has the symlink but the module is disabled (no risk, no action needed).
