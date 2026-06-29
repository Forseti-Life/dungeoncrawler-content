Now I understand both fixes. Let me implement them:

Now I have full context. Implementing both fixes:

Now implementing both fixes:

Now add `enforceRateLimit` calls to the two direct `invokeModelDirect` call sites (narrateNpcAttitudeShift and generateSessionSummary) and in `invokeNarration`:

Now add rate limiting to `generateSessionSummary` before the direct `invokeModelDirect` call:

Now add rate limiting to `invokeNarration` (used by all other methods):

Now update `services.yml` to add the 10th arg:

Now implement **Fix 2** — update `SessionService::buildAiGmContext` to return multiple prior-session summaries:

Now run PHP lint and drush cr:

Clean. Now commit:

Both fixes committed at `cee051628`. Writing the outbox now:

- Status: done
- Summary: Fixed both QA-blocked test cases for `dc-cr-gm-narrative-engine`. TC-GNE-12 (Security AC — rate limiting): added `enforceRateLimit(int $campaign_id)` to `AiGmService` using Drupal's `keyvalue.expirable` store; enforces 60 calls/hour per campaign and throws HTTP 429 when exceeded; injected via new 10th DI arg (`@keyvalue.expirable`) in `services.yml`; rate limit applied in all three call paths (`invokeNarration`, `narrateNpcAttitudeShift`, `generateSessionSummary`). TC-GNE-02 (AC-001 — multi-session summaries): rewrote `SessionService::buildAiGmContext` to fetch up to 5 ended sessions (was `LIMIT 1`), ordered recent-first, labelled and concatenated, truncated to 3000-char context window budget. PHP lint clean; `drush cr` successful. Commit: `cee051628`.

## Next actions
- QA (`qa-dungeoncrawler`) to re-run unit test suite against `cee051628` for Gate 2 re-verification of `dc-cr-gm-narrative-engine`
- No further dev action needed unless QA issues a new BLOCK

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 90
- Rationale: Unblocking Gate 2 for `dc-cr-gm-narrative-engine` unblocks the full release `20260411-dungeoncrawler-release-b`. Security AC and multi-session continuity are both player-visible quality gates.

---

**Rollback steps:**
```bash
cd /home/ubuntu/forseti.life
git revert cee051628 --no-edit
cd /var/www/html/dungeoncrawler && vendor/bin/drush cr
```

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260411-162600-fix-dc-cr-gm-narrative-engine-qa-block
- Generated: 2026-04-11T16:42:15+00:00
