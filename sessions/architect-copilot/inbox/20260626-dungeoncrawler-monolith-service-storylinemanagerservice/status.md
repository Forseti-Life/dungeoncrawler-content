# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Implementation in progress
- Started contract-focused decomposition pass for `src/Service/StorylineManagerService.php`.
- Preparing a behavior-preserving extraction seam with targeted unit coverage to lock canonical storyline-entry and progression contracts.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/StorylineManagerService.php` (~3.8k lines) as a mixed-responsibility monolith spanning:
  1. storyline template normalization + runtime instantiation,
  2. metadata/entry-point/contact canonicalization,
  3. questline progression synchronization,
  4. multi-stage storyline validation graph and dependency checks.
- Coupling profile:
  - bootstrap progression-connector defaults were assembled inline in metadata normalization logic,
  - connector-shaping policy was not isolated as a reusable seam for contract enforcement.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - bootstrap metadata must include canonical connector payload fields (`connector_id`, mechanism, source/target anchors),
  - generated connector ids must stay deterministic from entry dungeon id,
  - bootstrap handoff must preserve location and room anchors for progression flow validation.
- Drift risks:
  1. inline connector assembly in metadata normalization increases mutation risk and coupling,
  2. lack of a dedicated connector builder obscures connector contract intent and hampers safe reuse.

### 2026-06-29 — Phased extraction strategy
1. **Bootstrap connector seam**
   - extract a dedicated builder for default bootstrap progression connectors.
2. **Callsite convergence**
   - route metadata normalization through the shared connector builder.
3. **Coverage lock**
   - expand metadata backfill tests to assert canonical connector payload fields.
4. **Service thinning continuation**
   - continue isolating runtime synchronization and validation-graph seams in later increments.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure/no-swallow behavior.
- Preserve canonical bootstrap progression connector shape and deterministic connector-id semantics.
- Preserve entry-handoff location and room targeting contracts.

### 2026-06-29 — Test/conformance coverage gaps
- Existing metadata backfill test validated bootstrap outline scaffolding but did not assert progression connector contract fields directly.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `buildDefaultBootstrapProgressionConnectors(...)`,
  - rewired bootstrap metadata normalization branch to consume shared connector builder.
- Expanded targeted unit coverage in `StorylineManagerServiceTest` for:
  - connector id derivation,
  - connector mechanism,
  - source location id and target room id fields.
- Targeted test command:
  - `/var/www/html/dungeoncrawler/vendor/bin/phpunit -c phpunit.xml tests/src/Unit/Service/StorylineManagerServiceTest.php --filter '/NormalizeTemplateDefinitionBackfillsCanonicalMetadataContract|NormalizeTemplateDefinitionNormalizesContactsAndSeedsBrokerFallback/'`
- Pushed in `dungeoncrawler-content` commit: `a328a6f759`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
