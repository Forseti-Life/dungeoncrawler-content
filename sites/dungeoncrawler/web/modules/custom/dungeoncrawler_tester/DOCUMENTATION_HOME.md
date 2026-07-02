# Dungeon Crawler Tester Documentation Home

This is the canonical entry point for all tester module documentation.

## Standard Testing Documentation Structure

- [Getting Started](/dungeoncrawler/testing/documentation/getting-started)
- [Test Execution Playbook](/dungeoncrawler/testing/documentation/execution-playbook)
- [Failure Triage and Issue Workflow](/dungeoncrawler/testing/documentation/failure-triage)
- [Automated Testing Process Flow](/dungeoncrawler/testing/documentation/process-flow)
- [SDLC Process Flow](/dungeoncrawler/testing/documentation/sdlc-process-flow)
- [Release Process Flow](/dungeoncrawler/testing/documentation/release-process-flow)

## Legacy Route Aliases (Compatibility)

- [Module README page](/dungeoncrawler/testing/documentation/module-readme)
- [Testing Module README page](/dungeoncrawler/testing/documentation/testing-module-readme)
- [Tests README page](/dungeoncrawler/testing/documentation/tests-readme)
- [Testing Strategy Design page](/dungeoncrawler/testing/documentation/strategy-design)
- [Testing Quick Start Guide page](/dungeoncrawler/testing/documentation/quick-start)
- [Testing Issues Directory page](/dungeoncrawler/testing/documentation/issues-directory)
- [Copilot Issue Automation page](/dungeoncrawler/testing/documentation/issue-automation)

## Supporting References

- [Testing Dashboard](/dungeoncrawler/testing)
- [Import Open Issues](/dungeoncrawler/testing/import-open-issues)
- [Issue/PR Report](/dungeoncrawler/testing/import-open-issues/issue-pr-report) — operational triage page with documented close-decision logic.

## Verification Notes (2026-02-18)

- Documentation pages at `/dungeoncrawler/testing/documentation/*` are rendered from `TestingDashboardDocsController` and are the operational source for documentation-home content.
- Stage failure automation writes to local tracker rows in repository-root `Issues.md`; GitHub mutations for issue creation/import run through `/dungeoncrawler/testing/import-open-issues`.
- SDLC and Release flow pages are process guidance and live-inference framing, not hard-enforced merge orchestration in module runtime.

## Live Tracking

- [GitHub Issues (testing-related)](https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting)

## Dashboard Integration

The testing dashboard (`/dungeoncrawler/testing`) should use this page as the primary documentation landing point.
