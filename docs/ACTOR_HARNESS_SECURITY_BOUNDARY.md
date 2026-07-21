# Actor Harness Security Boundary (Authoritative)

## Status

- **Status:** in_progress
- **Owner:** CEO (`ceo-copilot-2`) for PROJ-013 security hardening phase
- **Effective date:** 2026-07-13

## Goal

Ensure in-game actor execution (PC/NPC/harness actor behaviors) is strictly limited to canonical gameplay capabilities and cannot access host/system command surfaces.

## Hard policy (non-negotiable)

1. Actor runtime is **deny-by-default**.
2. Actor runtime may execute only canonical gameplay intents validated by server authority.
3. Actor runtime must not execute OS/system commands, shell commands, package managers, git, drush CLI, or arbitrary process execution.
4. Actor runtime must not have access to privileged tokens/secrets used for system automation.
5. Contract violations hard-fail with explicit diagnostics; no fallback/recovery masking.

## Allowed actor capability classes

| Capability class | Allowed | Notes |
|---|---|---|
| Canonical gameplay intents (`talk`, `interact`, `search`, `transition`, turn actions) | yes | Must pass server-side action contract validation |
| Read actor snapshot/state from canonical runtime surfaces | yes | Read-only runtime context |
| Mutate game state via canonical action ingress | yes | Through policy-gated server authority only |
| Host shell/process execution (`subprocess`, CLI wrappers, drush shell) | no | Forbidden in actor gameplay paths |
| Filesystem/system administration | no | Forbidden |
| External network/admin APIs unrelated to gameplay | no | Forbidden by default |

## Enforcement architecture

1. **Ingress gate:** actor requests are validated against an allowlist of canonical gameplay intents; all others are rejected.
2. **Execution path:** actor actions route through in-process service methods or a locked internal gameplay API, not host command execution.
3. **Runtime identity:** actor runtime executes under least privilege (no sudo, no privileged groups, no shell-login operations).
4. **Secrets boundary:** actor runtime environment excludes admin/system tokens.
5. **Verification gate:** security contract tests fail build/release if forbidden APIs or capabilities are detected.

## Immediate implementation checklist

1. Inventory and remove command execution calls from actor gameplay paths.
2. Add a centralized actor-capability policy guard at canonical action ingress.
3. Add regression tests asserting forbidden command execution surfaces are absent in actor runtime code.
4. Add runtime startup assertion that actor context is missing privileged tokens.
