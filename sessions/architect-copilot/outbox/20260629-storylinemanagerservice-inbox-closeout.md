- Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-storylinemanagerservice` with contract-focused decomposition planning and an implemented bootstrap-connector extraction increment.

## Delivered
- Audited `src/Service/StorylineManagerService.php` and documented decomposition boundaries for:
  1. storyline normalization/runtime instantiation seams,
  2. metadata/contact/entry-point canonicalization seams,
  3. progression-validation graph seams.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `buildDefaultBootstrapProgressionConnectors(...)`,
  - rewired bootstrap metadata normalization branch through the shared connector builder.
- Expanded targeted unit coverage in `StorylineManagerServiceTest` for:
  - canonical bootstrap connector id derivation,
  - connector mechanism value,
  - source/target location and room anchors.
- Ran targeted tests:
  - `/var/www/html/dungeoncrawler/vendor/bin/phpunit -c phpunit.xml tests/src/Unit/Service/StorylineManagerServiceTest.php --filter '/NormalizeTemplateDefinitionBackfillsCanonicalMetadataContract|NormalizeTemplateDefinitionNormalizesContactsAndSeedsBrokerFallback/'`
- Pushed implementation commit in `dungeoncrawler-content`: `a328a6f759`.

## Next Action
1. Queue complete for current monolith hotspot inbox stream.
