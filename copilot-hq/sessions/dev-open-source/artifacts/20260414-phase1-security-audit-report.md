# Phase 1 Pre-Publish Security Audit — `forseti-open-source-initiative`

- **Date:** 2026-04-14
- **Auditor:** `dev-open-source`
- **Project:** `PROJ-009`
- **Overall gate result:** **FAIL / NO-GO**
- **KB references:** none found

## Scope completed

- Current-tree audit for RSA keys, `.env.example`, config-sync secrets, and private directories
- Git-history checks for `sites/forseti/keys`, `ai_conversation.settings.yml`, `copilot-hq/sessions`, `prod-config`, and `database-exports`
- Repo-wide secret scan using TruffleHog from a disposable venv
- Public-mirror extraction rule review
- 9-script audit for hardcoded token/IP or host-specific publication blockers

## Acceptance criteria status

| AC | Result | Evidence |
|---|---|---|
| 1. RSA private keys removed from current tree and history | **FAIL** | Current tree still contains `sites/forseti/keys/private.key`, `sites/forseti/keys/public.key`, `sites/forseti/keys/keys/private.key`, `sites/forseti/keys/keys/public.key`. Git history for `sites/forseti/keys` still exists. |
| 2. AWS credentials scrubbed from git history | **FAIL** | Current `sites/forseti/config/sync/ai_conversation.settings.yml` and `sites/dungeoncrawler/config/sync/ai_conversation.settings.yml` are blank now, but history commit `74da62ed4` contains literal AWS access-key and secret-key pairs in both files. HQ history commit `e9f005f93` also contains an explicit AWS key in a session command artifact. Rotation remains unconfirmed. |
| 3. Full history scan completed with remaining-item list | **DONE — findings remain** | TruffleHog repo-history scan completed and found 1453 matches; the vast majority are high-entropy noise in private artifacts, but confirmed sensitive history remains in the AWS config commits and HQ session history listed above. |
| 4. `sessions/**` exclusion confirmed for public mirror | **PARTIAL PASS** | `copilot-hq/.public-mirror-ignore` excludes `sessions/**` and `inbox/responses/**`, but `scripts/export-public-mirror.sh` recreates `inbox/responses/.gitkeep`, which conflicts with the site instruction to keep `inbox/responses/**` private by default. |
| 5. `.env.example` sanitized | **PASS** | Root `.env.example` uses placeholders such as `YOUR_DB_PASSWORD` and `YOUR_ADMIN_PASSWORD`; no literal credentials found. |
| 6. 9 copilot scripts audited | **DONE — 1 ISSUE** | 8 scripts clean for hardcoded token/IP patterns; `scripts/bedrock-assist.sh` still contains host-specific `/var/www/html/...` assumptions. |
| 7. `prod-config/` and `database-exports/` excluded from extractable history | **FAIL** | Both directories are present and both have git history (`prod-config`: 4 commits, `database-exports`: 2 commits). Raw-history publication is not safe. |

## Confirmed blockers

1. **Current-tree key material still present**
   - `sites/forseti/keys/private.key`
   - `sites/forseti/keys/public.key`
   - `sites/forseti/keys/keys/private.key`
   - `sites/forseti/keys/keys/public.key`

2. **Confirmed secret-bearing git history still present**
   - `74da62ed4` — both `sites/forseti/config/sync/ai_conversation.settings.yml` and `sites/dungeoncrawler/config/sync/ai_conversation.settings.yml` contain literal AWS credentials in history
   - `e9f005f93` — `copilot-hq/sessions/pm-forseti/inbox/20260405-ai-conversation-bedrock-fixes-review/command.md` contains an explicit AWS key in history

3. **Private history surface is large**
   - `copilot-hq/sessions`: 1529 history entries
   - `prod-config`: 4 history entries
   - `database-exports`: 2 history entries

4. **Public export rules still need tightening**
   - `copilot-hq/.public-mirror-ignore` excludes `sessions/**`, `inbox/responses/**`, and `tmp/**`
   - `copilot-hq/scripts/export-public-mirror.sh` still recreates `inbox/responses/.gitkeep`

## 9-script audit

| Script | Finding |
|---|---|
| `scripts/hq-bedrock-chat.sh` | CLEAN |
| `scripts/bedrock-assist.sh` | ISSUE — host-specific `/var/www/html/forseti` and `/var/www/html/dungeoncrawler` paths |
| `scripts/validate-production-bedrock.sh` | CLEAN |
| `scripts/setup-public-mirror.sh` | CLEAN |
| `scripts/export-public-mirror.sh` | CLEAN for token/IP scan; separate publication-boundary issue noted above |
| `scripts/verify-agent-repo-access.sh` | CLEAN |
| `scripts/org-control.sh` | CLEAN |
| `scripts/hq-status.sh` | CLEAN |
| `scripts/ceo-dispatch.sh` | CLEAN |

## Recommendation

Do **not** publish any raw-history mirror of the current monorepo. Use curated sanitized extracts only, and do not freeze the first public candidate until:

1. current-tree key files are removed,
2. secret-bearing history is scrubbed with `git-filter-repo` or equivalent on a mirror clone,
3. `sessions/**`, `inbox/responses/**`, `prod-config/**`, and `database-exports/**` are excluded from the public extraction path,
4. `scripts/export-public-mirror.sh` stops recreating private placeholder directories,
5. and CEO/Board confirms external rotation of the previously exposed AWS credentials.

## Verification commands used

```bash
find /home/ubuntu/forseti.life/sites/forseti/keys -type f \( -name '*.pem' -o -name '*.key' -o -name '*.rsa' \)
grep -RInE 'aws_access_key_id:|aws_secret_access_key:' /home/ubuntu/forseti.life/sites/forseti/config/sync /home/ubuntu/forseti.life/sites/dungeoncrawler/config/sync
git -C /home/ubuntu/forseti.life rev-list --all -- sites/forseti/keys | wc -l
git -C /home/ubuntu/forseti.life rev-list --all -- prod-config | wc -l
git -C /home/ubuntu/forseti.life rev-list --all -- database-exports | wc -l
git -C /home/ubuntu/forseti.life rev-list --all -- copilot-hq/sessions | wc -l
```
