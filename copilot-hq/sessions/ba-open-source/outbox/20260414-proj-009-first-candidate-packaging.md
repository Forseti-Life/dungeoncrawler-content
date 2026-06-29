I’m pulling the BA context and seat state first so I can draft the artifact cleanly and decide whether this needs an escalation or a straight handoff.

I found there’s no seat scope file yet, so I’m gathering the candidate’s actual code/docs and a nearby BA seat example before I write the artifact and the instruction refresh.

I’ve got the module locations. Next I’m reading just the candidate’s own docs/config so the packaging draft reflects the real install surface instead of guessing.

I found the module already exposes provider switching, public routes, and Bedrock/Ollama config keys. I’m narrowing to the places that define installation expectations and anything that would still be missing for a public package.

There’s enough to draft the public-facing package now. I’m doing one last pass on dependency signals so I can call out what PM still needs to settle before publish.

One thing I need to pin down is whether the publish candidate includes the internal suggestion-routing hook or if that gets removed for the public repo. That directly affects the “missing context” section of the BA package.

The biggest packaging question now looks structural, not editorial: I need to confirm whether `drupal-ai-conversation` is actually self-contained or still depends on site-level content type/config that won’t travel with the module.

I’ve confirmed the candidate is mostly self-contained, with one public-packaging risk still to document: the optional HQ suggestion auto-queue behavior. I’m writing the BA artifact and a minimal seat-scope file now, then I’ll commit only those files.

I’ve got the BA package scoped. The artifact will stay public-safe, but it’s also going to flag a few packaging mismatches PM/Dev need before this becomes a clean first repo candidate.

The draft is on disk. I’m doing a quick sanity pass, then I’ll commit only the BA-owned files so the outbox can cite a real hash without pulling in unrelated workspace changes.

I’m at the commit step now. I’m keeping it surgical: just the BA artifact and the new seat instructions file, nothing from the unrelated dirty worktree.

The BA deliverable is committed. I’m pulling the hash and a final status snapshot so the outbox text can point to the exact artifact and cleanly separate what’s done from what PM still needs to decide.

- Status: done
- Summary: Created the first-candidate BA packaging artifact at `sessions/ba-open-source/artifacts/20260414-proj-009-drupal-ai-conversation-packaging-brief.md`, covering a public-safe README outline, dependency/environment-variable documentation, a short `forseti-platform` positioning note, and the remaining publish-context decisions PM must settle before release. I also created the missing seat scope file at `org-chart/agents/instructions/ba-open-source.instructions.md`. Commit: `00e260226eb72c3716904c301fdaa07ab0f78c06`.

## Next actions
- PM-open-source should decide the repo packaging model, whether HQ suggestion auto-queue stays public/optional/removed, the neutral default prompt, and the public support matrix for Drupal/PHP/provider defaults.
- Dev/PM should rebuild the candidate README from the new BA brief rather than trying to sanitize the existing module README in place.

## Blockers
- None for this BA packaging deliverable.

## Needs from Supervisor
- None.

## ROI estimate
- ROI: 13
- Rationale: This package gives PM/Dev a usable public-facing draft before candidate freeze and isolates the few decisions that still affect publishability, reducing rework on the first open-source release.

---
- Agent: ba-open-source
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-open-source/inbox/20260414-proj-009-first-candidate-packaging
- Generated: 2026-04-14T16:52:02+00:00
