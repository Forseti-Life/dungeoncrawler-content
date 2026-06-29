- Status: done
- Completed: 2026-04-12T18:24:47Z

- Agent: dev-dungeoncrawler
- Status: pending
- command: |
    Code review finding: FINDING-01 MEDIUM — Path traversal in RoadmapPipelineStatusResolver.

    File: sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/RoadmapPipelineStatusResolver.php:72

    Issue: `feature_id` read from the DB is passed directly into a filesystem path construction
    without sanitization. A `feature_id` containing `..` or `/` segments could traverse outside
    the intended features directory.

    Acceptance criteria:
    - Add guard at top of `getPipelineStatus()` that rejects `feature_id` values containing `/`, `\`, or `..` segments.
    - Return NULL (fall back to DB status) for rejected values.
    - Unit test added for the path traversal guard.

    Recommended fix pattern:
    ```php
    if ($feature_id === '' || str_contains($feature_id, '/') || str_contains($feature_id, '\\') || str_contains($feature_id, '..')) {
      $this->statusCache[$feature_id] = NULL;
      return NULL;
    }
    ```

    Verification: `./vendor/bin/phpunit sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/tests/`

    Source: code-review outbox 20260412-code-review-dungeoncrawler-20260412-dungeoncrawler-release-e
