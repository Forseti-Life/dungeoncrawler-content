# Conflict Resolution

## Inputs
- Conflicting requests (links)
- Affected websites/modules
- Deadlines and impact

## CEO decision framework
1. **Safety first**: security, data integrity, access control.
2. **Locality**: keep changes within the owning website/module when possible.
3. **Contracts over coupling**: prefer stable interfaces (services/routes) instead of cross-module hacks.
4. **Smallest shippable unit**: deliver incremental value.

## Outputs
- Decision note (who/what/why)
- Updated acceptance criteria (if scope changes)
- Passthrough request(s) created if needed
