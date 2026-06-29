- Status: done
- Completed: 2026-04-20T16:13:39Z

# Task: Prepare drupal-ai-conversation Repo Extraction (Phase 2a)

## Owner
dev-open-source

## Objective
Begin Phase 2 extraction work on drupal-ai-conversation. Establish clean extraction branch, document boundaries, and prepare GitHub Actions CI/CD.

## Acceptance Criteria
- [ ] Extraction branch created with clean history
- [ ] Extraction boundary documented (what's in/out)
- [ ] GitHub Actions workflow created (phpcs + install test)
- [ ] Verified no secrets in target files
- [ ] Ready for BA to add docs
- [ ] Handed off to qa-open-source for CI validation

## Phase 2a Scope
**Repository:** Forseti-Life/drupal-ai-conversation  
**Source:** shared/modules/ai_conversation/  
**Commit:** 5e9f8e553 (Phase 1 clean tree)

## Extraction Steps

1. **Create extraction branch**
   ```bash
   git checkout -b extract/drupal-ai-conversation-v1.0 5e9f8e553
   ```

2. **Document extraction boundary**
   Create file: `_EXTRACTION_METADATA.md` in root
   ```
   # Extraction Metadata
   - Source: shared/modules/ai_conversation/
   - Source commit: 5e9f8e553
   - Extraction date: 2026-04-20
   - Included: All module code, config, tests, docs
   - Excluded: sessions/, tmp/, keys/, prod-config/, database-exports/
   - History: Scrubbed via BFG (pending completion)
   ```

3. **Set up GitHub Actions**
   Create `.github/workflows/ci.yml`:
   ```yaml
   name: CI
   on: [push, pull_request]
   jobs:
     phpcs:
       runs-on: ubuntu-latest
       steps:
         - uses: actions/checkout@v3
         - name: Lint
           run: vendor/bin/phpcs --standard=Drupal src/ config/
     
     install-test:
       runs-on: ubuntu-latest
       steps:
         - uses: actions/checkout@v3
         - name: Install
           run: |
             composer install
             drush en ai_conversation --yes
             drush cr
   ```

4. **Verify no secrets**
   ```bash
   # Scan for common secret patterns
   grep -r "AKIA\|aws_secret\|private_key\|password.*=" . || echo "PASS: No secrets found"
   ```

5. **Document in README.md** (for BA to enhance)
   ```markdown
   # Drupal AI Conversation Module
   
   AWS Bedrock integration for conversational AI in Drupal.
   
   (BA will expand with features, config, examples)
   ```

## Parallel Work: BFG History Scan
- Schedule BFG Repo Cleaner against full monorepo history
- Document findings for sec-analyst-open-source
- Can run while other work proceeds

## Deliverables
1. Extraction branch: `extract/drupal-ai-conversation-v1.0`
2. Extraction boundary documented
3. GitHub Actions CI/CD ready
4. README skeleton (for BA enhancement)
5. Verification that all secrets cleared

## Route To
- ba-open-source: "Extraction ready, add docs"
- qa-open-source: "CI setup ready for validation"
- pm-open-source: "Phase 2a progress update"

## Context
- Phase 1 complete and validated (waiting for gates)
- This starts actual Phase 2 execution
- Parallel with PM gate closure and BFG history scan
- Timeline: Target repo push 2026-05-01
- ROI: 75 (unblocks repo creation and docs)

## Related
- Phase 1 evidence: sessions/dev-open-source/outbox/20260420-remediate-ai-conversation-*.md
- BA docs plan: sessions/ba-open-source/outbox/20260414-proj-009-first-candidate-packaging.md
- Feature spec: features/forseti-open-source-initiative/feature.md
