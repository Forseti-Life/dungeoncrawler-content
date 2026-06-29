# QA Suites (Central Automated Test-Case Source of Truth)

This folder is the **central source of truth** for automated product test suites.

Policy:
- Tools are **consistent across products** (same runner conventions, same output expectations).
- The *contents* are per-product: each product has its own suite manifest and any product-specific scripts/config.
- Test cases must be **executable automation with PASS/FAIL outcomes**.

## Structure

- `qa-suites/tools/` — shared toolchain documentation (how we run suites, how we record results)
- `qa-suites/products/<product>/suite.json` — canonical manifest for that product’s automated suites
- `qa-suites/products/<product>/` — product-specific scripts/config used by the suites

## Manifest (`suite.json`)

Each product must have a single canonical manifest at:
- `qa-suites/products/<product>/suite.json`

It declares:
- `product_id` (stable identifier)
- `tools` (the shared toolchain used; should be consistent org-wide)
- `suites[]` (the list of automated suites/test groups for the product)

Validation:
- Run `python3 scripts/qa-suite-validate.py` to validate manifests and basic conventions.

## Release-cycle usage

- PM hands the scoped change list to QA.
- QA uses the product’s `suite.json` as the canonical list of suites to run and to keep suite coverage complete.
- Release candidate evidence records which suites were run and PASS/FAIL outcomes.
