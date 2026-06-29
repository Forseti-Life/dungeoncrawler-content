- command: |
    Roadmap requirement validation — Core Rulebook Ch3 Alchemist.

    Requirement ID: 646
    Requirement text: "Alchemist's primary combat roles include ranged explosive attacks (bombs), support via elixirs, and at higher levels melee/resilience via mutagens."
    Roadmap status: pending (blocked — dc-cr-alchemical-items and dc-cr-encounter-rules alchemist integration deferred)
    Feature dependency: dc-cr-alchemical-items (deferred)

    NOTE: Activate once dc-cr-alchemical-items reaches in_progress.

    ## Positive Test Case
    Verify that bomb, elixir, and mutagen alchemical item subtypes exist and are
    assignable to Alchemist characters in the item system.

    ```php
    // TC-646-P: Bomb, elixir, mutagen item subtypes exist
    $db = \Drupal::database();
    foreach (['bomb', 'elixir', 'mutagen'] as $subtype) {
      $count = $db->select('node_field_data', 'n')
        ->condition('n.type', 'alchemical_item')
        ->execute()->rowCount();
      // At minimum, the alchemical_item content type must have entries of each subtype
      $subtypeCount = $db->select('node__field_alchemical_type', 't')
        ->condition('t.field_alchemical_type_value', $subtype)
        ->countQuery()->execute()->fetchField();
      assert($subtypeCount > 0, "TC-646-P FAIL: No alchemical items of subtype '{$subtype}' found");
      echo "TC-646-P PASS: Found {$subtypeCount} item(s) of subtype '{$subtype}'\n";
    }
    ```

    ## Negative Test Case
    Verify a non-Alchemist class (Fighter) cannot use Advanced Alchemy to create
    items (Alchemist feature must be class-gated).

    ```php
    // TC-646-N: Fighter does not have alchemical crafting class feature
    $db = \Drupal::database();
    $fighterNid = $db->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'character_class')
      ->condition('n.title', 'Fighter')
      ->execute()->fetchField();
    assert($fighterNid > 0, 'TC-646-N SETUP FAIL: Fighter class not found');

    // Fighter should have no alchemical class features
    $alchFeatures = $db->select('node__field_class_features', 'f')
      ->fields('f', ['field_class_features_value'])
      ->condition('f.entity_id', $fighterNid)
      ->execute()->fetchAll();
    $alchKeys = array_filter($alchFeatures, fn($r) => str_contains(strtolower($r->field_class_features_value), 'alch'));
    assert(count($alchKeys) === 0, 'TC-646-N FAIL: Fighter should not have alchemical class features');
    echo "TC-646-N PASS: Fighter has no alchemical class features\n";
    ```

    Required actions:
    1) Hold until dc-cr-alchemical-items is in_progress.
    2) Run both test cases via drush ev (ALLOW_PROD_QA=1).
    3) Add to qa-regression-checklist.md under "Character Classes / Alchemist".
- Agent: qa-dungeoncrawler
- Status: pending
