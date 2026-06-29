# PROJ-009 Phase 1 Security Gate Audit — `drupal-ai-conversation`

- **Date:** 2026-04-14
- **Auditor:** `dev-open-source`
- **Candidate reviewed:** `sites/forseti/web/modules/custom/ai_conversation`
- **KB references:** none found

## Current-tree status

**Result:** FAIL for public freeze.

What is clean in the current candidate tree:
- No `sessions/**`, `prod-config/**`, `database-exports/**`, or key files are present inside the candidate directory itself.
- `config/install/ai_conversation.settings.yml` currently ships blank AWS credential fields (`aws_access_key_id: ''`, `aws_secret_access_key: ''`), not literal secrets.
- Candidate-path scan found no private-key block material and no AWS access-key token pattern in the current tree.

What is still not public-safe:
- `src/Service/AIApiService.php` still contains HQ intake coupling that writes to `.../sessions/<pm>/inbox/...` and therefore bakes internal org workflow into the module.
- `src/Service/AIApiService.php` still includes stale absolute HQ fallback path `/home/keithaumiller/copilot-sessions-hq`.
- `src/Traits/ConfigurableLoggingTrait.php` still reads `thetruthperspective.logging`, which is site-specific and not acceptable in a standalone public module.
- `config/install/ai_conversation.provider_settings.yml` still installs a Forseti-specific community/system prompt rather than a neutral public default.

## History audit result

**Result:** FAIL for public freeze; scrub scope is precise.

Candidate-path history scanned across **17 commits** touching `sites/forseti/web/modules/custom/ai_conversation`.

Findings:
- No literal AWS access keys, no committed private-key blocks, and no candidate-local copies of `sessions/**`, `prod-config/**`, or `database-exports/**` were found in the candidate-path history scan.
- Repeated internal-reference history exists across the candidate commit set:
  - `/sessions/<pm>/inbox/...` coupling in `src/Service/AIApiService.php`
  - stale absolute path `/home/keithaumiller/copilot-sessions-hq`
  - site-specific logging config `thetruthperspective.logging`
- Because those internal refs are present throughout candidate history, the first public repo must be cut from a **curated sanitized extract** rather than from raw history.

## Credential-rotation status

**Result:** UNCONFIRMED; still a release blocker for public push.

I found no evidence in this audit that previously exposed AWS credentials have been rotated externally. Prior open-source instructions explicitly treat live AWS rotation as a CEO/Board-handled pre-push gate, so this candidate cannot be marked go until that external rotation is confirmed.

## Recommended extraction boundary

Use `sites/forseti/web/modules/custom/ai_conversation/` as the **starting source directory only**, with these required exclusions/removals before freeze:

1. Remove or feature-flag HQ suggestion auto-queue behavior in `AIApiService.php`.
2. Remove stale absolute HQ fallback paths.
3. Replace `thetruthperspective.logging` dependency with module-local or generic logging config.
4. Replace Forseti-specific default/system prompt and docs with neutral public defaults.
5. Do **not** include site-level config sync exports from `sites/forseti/config/sync/`; publish the module package only.

## Go / no-go recommendation

**NO-GO** for freezing `drupal-ai-conversation` as a public repo candidate today.

Freeze only after:
- the HQ/session coupling and stale absolute path are removed,
- the site-specific logging config reference is removed,
- the install-time Forseti prompt is neutralized,
- and CEO/Board confirms previously exposed AWS credentials were rotated externally.

## Verification commands used

```bash
find /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation -maxdepth 3 -type f | sort
git -C /home/ubuntu/forseti.life --no-pager log --oneline -- sites/forseti/web/modules/custom/ai_conversation
grep -RInE '/sessions/|thetruthperspective\.logging|/home/keithaumiller|AKIA[0-9A-Z]{16}|BEGIN (RSA|OPENSSH|EC|DSA) PRIVATE KEY' /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation
git -C /home/ubuntu/forseti.life rev-list --all -- sites/forseti/web/modules/custom/ai_conversation | wc -l
```
