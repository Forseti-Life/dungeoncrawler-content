# Shared Repo Creation Template — First-Wave Open-Source Repos (2026-04)

- Project: `PROJ-009`
- Owner: `architect-copilot`
- Status: `planned`
- Mode: `planning/documentation only`

## Purpose

Use this template when a first-wave repo is ready to move from planning into actual creation work. It ensures every public repo starts with the same minimum documentation, boundary review, and release gate shape.

## Required brief sections

Every repo-specific planning brief should answer:

1. repo name
2. purpose
3. intended audience
4. canonical source root(s)
5. included content
6. excluded/private content
7. known sanitization work
8. docs required before creation
9. CI/validation expectations
10. creation gate / go-no-go criteria

## Required repo files at creation time

Each repo should have, at minimum:

1. `README.md`
2. `LICENSE`
3. `CONTRIBUTING.md`
4. `CODE_OF_CONDUCT.md`
5. `SECURITY.md`
6. `QUICKSTART.md` or equivalent setup guide
7. issue templates
8. CI workflow baseline

## README outline

Recommended structure:

1. what this repo is
2. why it exists
3. who it is for
4. installation/setup
5. architecture or component overview
6. dependencies and environment variables
7. contribution path
8. security/reporting path
9. relationship to the wider Forseti platform

## Boundary review checklist

Before creation, confirm:

- [ ] canonical source root is correct
- [ ] symlink aliases are documented if relevant
- [ ] private paths are excluded
- [ ] no secrets or private keys are present in current tree
- [ ] repo-specific sanitization work is identified
- [ ] public docs avoid private hostnames/paths/credentials
- [ ] history scrub requirements are known

## Validation expectations

Each repo should define its own minimum validation lane, for example:

- docs repo: link and structure review
- Drupal module repo: clean Drupal install and enable path
- framework repo: setup/import/basic runtime smoke path
- site/product repo: local dev bootstrap plus targeted automated checks

## Standard go/no-go gate

Mark a repo ready for creation only when:

1. its planning brief is complete,
2. the include/exclude boundary is explicit,
3. sanitization work is either complete or clearly queued,
4. required docs are drafted,
5. validation expectations are defined,
6. the broader security/history gate does not block that repo.

## Important rule for this project phase

This template supports **later creation work**.

Using it now does **not** authorize:

- GitHub repo creation
- extraction work
- public pushes
- history rewrites
- candidate freezes

Those remain separate execution tasks after planning approval.
