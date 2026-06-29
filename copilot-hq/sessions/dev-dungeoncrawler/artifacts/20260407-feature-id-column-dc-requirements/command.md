- command: |
    Add `feature_id` column to `dc_requirements` table — roadmap coverage tracking

    The roadmap audit process requires a machine-verifiable link between pending requirements
    and the feature files that plan to implement them. Currently this link does not exist in
    the DB, making coverage claims unverifiable without a full manual re-scan.

    **Schema change:**
    ```sql
    ALTER TABLE dc_requirements
      ADD COLUMN feature_id VARCHAR(64) NOT NULL DEFAULT ''
      COMMENT 'Work item id of the feature file covering this req (e.g. dc-cr-class-alchemist)';

    ALTER TABLE dc_requirements
      ADD INDEX idx_feature_id (feature_id);
    ```

    **Migration:** Leave all existing rows with `feature_id = ''` (not yet mapped).
    PM will backfill via the next audit cycle using the committed audit tracker artifact.

    **Drush command:** Add a new drush command or extend `roadmap-set-status` to accept
    `--feature-id=<id>` so PM can set feature_id without raw SQL:
    ```bash
    drush dungeoncrawler:roadmap-set-feature --book=core --chapter=ch03 --section="Alchemist" \
      --feature-id=dc-cr-class-alchemist
    ```

    Acceptance criteria:
    - `dc_requirements` has a `feature_id VARCHAR(64)` column with index
    - `roadmap-set-feature` drush command sets feature_id for all matching rows (by book/chapter/section)
    - Existing drush `roadmap-set-status` is unchanged
    - A query `SELECT * FROM dc_requirements WHERE status='pending' AND feature_id=''` shows unmapped reqs

    Verification:
    - `sudo mysql dungeoncrawler -e "DESCRIBE dc_requirements;"` shows feature_id column
    - `drush dungeoncrawler:roadmap-set-feature --book=core --chapter=ch09 --section="Range and Reach" --feature-id=dc-cr-encounter-rules` runs without error
    - Follow-up query confirms feature_id is set for those rows

    Reference: runbooks/roadmap-audit.md — "Coverage is only machine-verifiable if feature_id is set"
    ROI: 9 — unlocks machine-verifiable audit completion checks; eliminates manual re-scan dependency
- Agent: dev-dungeoncrawler
- Status: pending
- roi: 9
