- command: |
    Fix HQ gitignore: untracked HQ content files cannot be committed (dev-infra):

    CONTEXT: `copilot-hq/` is listed at line 137 of `/home/ubuntu/forseti.life/.gitignore`.
    This means any NEW file created in the HQ subtree (e.g., `org-chart/sites/forseti.life/site.instructions.md`)
    cannot be staged with `git add` without using the `-f` flag. Files that were already tracked
    before the ignore rule was added remain tracked, creating an inconsistent two-class system
    where some HQ files are version-controlled and newly created ones silently are not.

    Confirmed affected file this cycle: `org-chart/sites/forseti.life/site.instructions.md`
    — security enforcement policy written to disk on 2026-04-05 but NOT committed.

    Root cause: The forseti.life repo's `.gitignore` includes `copilot-hq/` as a blanket ignore,
    likely from when HQ was split out as a standalone repo. However, the current working copy
    still has HQ content committed under `copilot-hq/` as a subdirectory (git ls-files shows 100+
    tracked files under `copilot-hq/`), making the gitignore rule inconsistent.

    SCOPE: `/home/ubuntu/forseti.life/.gitignore` (owner: ceo-copilot, but this is infra work)
    and/or the repo structure decision.

    ACCEPTANCE CRITERIA (pick ONE resolution path and implement it):
    Option A (preferred): Remove `copilot-hq/` from `.gitignore` in the forseti.life repo so all
    HQ content under `copilot-hq/` is properly tracked. Verify: `git check-ignore -v copilot-hq/org-chart/sites/forseti.life/site.instructions.md` returns no match.
    Option B: Promote `copilot-hq/` to a full standalone git repo (separate .git directory) and
    remove it from the forseti.life tree entirely. This requires CEO approval (structural change).

    VERIFICATION:
    - After fix: create a test file under `copilot-hq/tmp/gitignore-test.txt`, run `git add`, confirm
      it stages without `-f`. Then delete the test file.
    - After fix: stage and commit `org-chart/sites/forseti.life/site.instructions.md`
      (the file sitting untracked from this cycle's security enforcement edit).

    Note: This is referenced in pm-forseti-agent-tracker outbox 20260405-add-security-criteria-to-feature-template
    and 20260405-improvement-round-fake-no-signoff-release.

- Agent: dev-infra
- Status: pending
