# Contributing

Thanks for your interest in improving this repository.

## Before you start
- Read `README.md` and relevant runbooks for operational context.
- Do not commit secrets, credentials, local runtime artifacts, or private session data.
- Keep changes focused and minimal.

## Workflow
1. Open an issue (or reference an existing one) describing the change.
2. Create a branch with a concise name.
3. Make changes with clear commit messages.
4. Run relevant checks and include results in your PR.
5. Open a pull request with:
   - summary of changes
   - motivation / problem statement
   - validation steps

## Pull request checklist
- [ ] No secrets or private data added.
- [ ] Documentation updated where relevant.
- [ ] Changes are scoped to the stated problem.
- [ ] Validation steps and outcomes are included.

## Development notes
- Prefer deterministic scripts and explicit failure modes.
- If adding automation, include safe defaults and rollback guidance.
- For release/publication changes, follow `PUBLIC_REPO_PREP.md`.
