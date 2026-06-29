# PROJ-009 Gate 2 validation plan — `drupal-ai-conversation`

## Scope
Prepare QA for the first public publication candidate: `drupal-ai-conversation`.

KB references: none found specific to open-source candidate publication QA in `knowledgebase/`.

## Candidate assumptions
- Candidate source today: `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation`
- Public target shape: standalone extracted Drupal module repo under `github.com/Forseti-Life/`
- Validation target: frozen candidate export, not the live private monorepo copy

## Gate 2 clean-machine validation matrix

| Lane | Environment | Goal | Required evidence | APPROVE threshold |
|---|---|---|---|---|
| CM-1 | Clean Ubuntu 24.04 + PHP CLI + Composer | Repo clones and dependency metadata is valid | clone command, `composer validate` output, tree snapshot | exits 0; no missing package metadata needed for the chosen packaging model |
| CM-2 | Clean Drupal 10 install | Module enables, updates run, caches rebuild | install commands, module enable output, `drush updb -y`, `drush cr` | no fatal errors; module enabled successfully |
| CM-3 | Clean Drupal 11 install | Same as Drupal 10 on current supported core | same as CM-2 | no fatal errors; module enabled successfully |
| CM-4 | Authenticated functional smoke | Core user path works for the frozen candidate | route list, curl/HTTP output or automated smoke output for key routes | chat entry route responds as documented; protected routes enforce auth/permissions |
| CM-5 | Provider config safety | Public docs/config examples are safe and complete | redacted config examples, env var examples, settings path screenshots/logs | no credentials, internal paths, or Forseti-only defaults required to start |
| CM-6 | Uninstall/reinstall sanity | Module can be disabled/uninstalled per docs without corrupting the install | uninstall/reinstall commands and outputs | documented behavior matches actual outcome; no unexpected fatal errors |

## Candidate-specific functional smoke scope
- Verify installable module metadata from `ai_conversation.info.yml` still matches the public support claim (`^9 || ^10 || ^11` today; PM/Dev must freeze the actual support statement).
- Verify documented routes still exist for the public candidate:
  - `/node/{nid}/chat`
  - `/ai-conversation/send-message`
  - `/api/ai-conversation/create`
  - `/api/ai-conversation/{conversation_id}/message`
  - `/api/ai-conversation/{conversation_id}/history`
- Verify permission boundaries remain intact:
  - anonymous user cannot access authenticated chat flows
  - state-changing endpoints reject missing/invalid CSRF where applicable
  - admin-only settings/reporting routes remain admin-only if they ship in the candidate
- Verify no HQ-only behavior remains in the public package, especially suggestion/inbox automation called out by BA.

## CI baseline that must pass before public release
1. Packaging/lint baseline
   - `composer validate --strict`
   - any repo-local metadata check required by the chosen packaging model
2. Automated test baseline
   - module test suite defined by Dev for the extracted repo (at minimum the frozen candidate's required unit/functional suites)
3. Matrix baseline
   - CI must run on every supported Drupal major version promised in README
4. Publication hygiene baseline
   - secret scan/sanitization check for the extracted repo
   - no failure in required GitHub Actions jobs for the frozen candidate commit

If Dev ships a narrower support matrix than Drupal 10 + 11, PM must explicitly freeze that reduced promise before QA approval.

## Documentation required for QA APPROVE
- `README.md` with install, enable, update, cache-rebuild, configuration, permissions, and troubleshooting sections
- `LICENSE`
- `CONTRIBUTING.md`
- `SECURITY.md`
- `CODE_OF_CONDUCT.md`
- public-safe configuration examples (`.env.example` or equivalent placeholders if env vars are required)
- explicit supported-version statement for Drupal and PHP
- provider configuration guidance for Bedrock and/or Ollama, matching what actually ships
- note of any intentionally omitted private/internal integrations from the public candidate

## Frozen-candidate inputs required from PM/Dev for Gate 2
- Frozen repo path or archive path to test
- Frozen commit SHA
- Packaging decision: standalone module repo vs extracted package layout
- CI run URL or exported CI artifact bundle for the frozen commit
- Exact supported-version matrix to validate (Drupal/PHP)
- Public README and config docs included in the frozen candidate
- Sanitized config/env examples needed to boot the module
- List of intentional deltas from the private module (removed files, disabled features, known accepted limitations)
- Any required test credentials or local provider stub instructions if automated tests need them

## APPROVE decision path
APPROVE only if:
- required CI jobs are green on the frozen commit,
- clean-machine Drupal install passes for every promised support lane,
- functional smoke checks pass,
- docs are sufficient to reproduce the install without private tribal knowledge,
- no secrets or private org-only integrations remain in the candidate.

BLOCK if any lane above fails, if docs are incomplete, or if the frozen export differs from what CI validated.

## Recommended execution order once candidate freezes
1. Confirm freeze packet completeness.
2. Run CI/result review first; do not spend clean-machine time on a red candidate.
3. Execute Drupal 11 lane, then Drupal 10 lane.
4. Run authenticated route/permission smoke.
5. Write Gate 2 verification report with APPROVE/BLOCK and attach logs/artifacts.
