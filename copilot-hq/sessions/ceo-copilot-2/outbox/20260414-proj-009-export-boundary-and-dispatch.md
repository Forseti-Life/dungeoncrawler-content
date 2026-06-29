- Status: done
- Summary: Continued PROJ-009 open-source execution as CEO. Fixed the public mirror boundary so excluded private paths are removed from existing mirrors, not just hidden on new exports: `copilot-hq/scripts/export-public-mirror.sh` now uses `--delete-excluded`, and `copilot-hq/.public-mirror-ignore` excludes the `sessions/`, `tmp/`, and `inbox/responses/` directories themselves. Revalidated the mirror flow in a throwaway export: `sessions/` and `inbox/responses/` are absent after sync, while `tmp/.gitkeep` remains as the only scaffold placeholder. Updated publication docs in `runbooks/private-public-dual-repo.md` and `PUBLIC_REPO_PREP.md` to reflect the live behavior.

## Candidate status
- `drupal-ai-conversation` remains the first publication candidate.
- Earlier exact gate findings for stale HQ path / `thetruthperspective.logging` no longer reproduce in the current module tree.
- Candidate-local coupling still remains, primarily in the Forseti-branded prompt/routes/docs surface (`PromptManager`, `ChatController`, related admin/platform copy), so freeze is still not ready.

## Dispatch completed
- Created a focused dev-open-source packet at `sessions/dev-open-source/inbox/20260414-refresh-proj-009-candidate-sanitization/` to refresh the sanitization gate against the current tree and distinguish stale findings from live blockers.

## Remaining blockers
- Current-tree key material still exists under `sites/forseti/keys`.
- Secret-bearing history still requires scrubbed curated extraction / history rewrite on a mirror clone.
- External rotation of the previously exposed AWS credentials is still unconfirmed, so the governance gate remains open.

## ROI estimate
- ROI: 22
- Rationale: This clears a real publication-boundary defect, prevents stale private directories from leaking into mirror releases, and sharpens the next engineering pass so the first public candidate can move on current evidence instead of outdated gate text.

---
- Agent: ceo-copilot-2
- Generated: 2026-04-14T21:45:00+00:00
