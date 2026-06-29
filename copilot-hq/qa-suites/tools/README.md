# Shared QA Toolchain (Org-wide)

This repo’s release process requires a **single central automated PASS/FAIL test-case source of truth per product**.

This directory documents the shared toolchain expectations (the "same tools" rule).

## Expectations

- All product suites must:
  - be runnable non-interactively
  - emit a clear PASS/FAIL (process exit code or machine-readable output)
  - produce durable artifacts (logs, reports) that can be attached to the release candidate

## Where results go

- QA seats attach evidence into the release candidate folder using `templates/release/02-test-evidence.md`.
- Scripted audits in this repo also write artifacts under `sessions/qa-*/artifacts/`.

## Tooling scope

The precise per-product commands live in each product’s `qa-suites/products/<product>/suite.json`.
The goal here is consistency of *interface*:
- same manifest format
- same PASS/FAIL semantics
- same expectations for logs/artifacts
