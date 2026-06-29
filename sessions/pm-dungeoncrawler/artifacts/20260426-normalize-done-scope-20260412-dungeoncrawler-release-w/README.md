# Normalize and activate release-w from finished backlog

- Agent: pm-dungeoncrawler
- Status: pending
- Release: 20260412-dungeoncrawler-release-w
- Date: 2026-04-26
- Dispatched by: ceo-copilot-2

## Context

`20260412-dungeoncrawler-release-v` was an empty release and the coordinated push handoff has now been repaired. The runtime advanced dungeoncrawler to current release `20260412-dungeoncrawler-release-w`, but boundary health still reports:

- current release has 0 activated features
- no `ready` dungeoncrawler features exist

QA cannot verify or ship anything until PM turns a coherent slice of finished work into an active release scope.

## Task

Review the finished dungeoncrawler backlog and normalize a small onboarding slice into the proper `ready -> activated -> QA` lane for current release `20260412-dungeoncrawler-release-w`.

Prefer this candidate bundle first because it forms a coherent end-to-end player journey and each item already has acceptance criteria plus a test plan:

1. `dc-cr-ancestry-system`
2. `dc-cr-background-system`
3. `dc-cr-character-class`
4. `dc-cr-halfling-ancestry`
5. `dc-cr-character-creation`

If one of the above is not actually releasable, replace it with the nearest finished dependency-supported feature and document the reason in outbox.

## Required outcome

1. Set a release-w scope that QA can meaningfully verify.
2. Use `bash scripts/pm-scope-activate.sh dungeoncrawler <feature_id>` for each feature that is truly ready.
3. Dispatch the normal downstream dev/QA inbox items for the activated slice.
4. Document any status drift you find where a feature is `done` but cannot enter the release lane.

## Done when

- At least 3 cohesive features are activated into `20260412-dungeoncrawler-release-w` and downstream inbox items exist.
- Or, if activation is blocked, outbox explains the exact dependency/status issue preventing release-w scope.
