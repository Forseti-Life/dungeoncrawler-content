- command: |
    Roadmap requirement validation — Core Rulebook Ch3 Alchemist.

    Requirement ID: 649
    Requirement text: "At 5th level, alchemist gains Field Discovery based on research field."
    Roadmap status: pending (dev inbox: 20260406-impl-alchemist-class-advancement)
    Feature dependency: dc-cr-alchemical-items (deferred), dc-cr-character-class (gap)

    NOTE: Activate after dev inbox 20260406-impl-alchemist-class-advancement is complete.

    ## Positive Test Case
    Verify Field Discovery is present in Alchemist class features at level 5.

    ```php
    // TC-649-P: Alchemist class features include Field Discovery at level 5
    $db = \Drupal::database();
    $nid = $db->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'character_class')
      ->condition('n.title', 'Alchemist')
      ->execute()->fetchField();
    $featuresRaw = $db->select('node__field_class_features', 'f')
      ->fields('f', ['field_class_features_value'])
      ->condition('entity_id', $nid)
      ->execute()->fetchField();
    $features = json_decode($featuresRaw, TRUE);
    $fieldDiscovery = array_filter($features, fn($f) => $f['level'] == 5 && str_contains(strtolower($f['id']), 'field-discovery'));
    assert(count($fieldDiscovery) > 0, 'TC-649-P FAIL: No Field Discovery feature found at level 5 for Alchemist');
    echo "TC-649-P PASS: Field Discovery exists at level 5 in Alchemist advancement table\n";
    ```

    ## Negative Test Case
    Verify Field Discovery is NOT present at level 1 (must be gated to level 5).

    ```php
    // TC-649-N: Field Discovery must not appear at level 1
    $db = \Drupal::database();
    $nid = $db->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'character_class')
      ->condition('n.title', 'Alchemist')
      ->execute()->fetchField();
    $featuresRaw = $db->select('node__field_class_features', 'f')
      ->fields('f', ['field_class_features_value'])
      ->condition('entity_id', $nid)
      ->execute()->fetchField();
    $features = json_decode($featuresRaw, TRUE);
    $earlyFD = array_filter($features, fn($f) => $f['level'] < 5 && str_contains(strtolower($f['id']), 'field-discovery'));
    assert(count($earlyFD) === 0, 'TC-649-N FAIL: Field Discovery appears before level 5');
    echo "TC-649-N PASS: Field Discovery correctly absent before level 5\n";
    ```

    Required actions:
    1) Hold until dev inbox 20260406-impl-alchemist-class-advancement is done.
    2) Run both test cases via drush ev (ALLOW_PROD_QA=1).
    3) Add to qa-regression-checklist.md under "Character Classes / Alchemist / Advancement".
- Agent: qa-dungeoncrawler
- Status: pending
