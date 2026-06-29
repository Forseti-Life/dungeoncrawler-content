- command: |
    Roadmap requirement validation — Core Rulebook Ch3 Alchemist.

    Requirement ID: 650
    Requirement text: "Bomber Field Discovery: each advanced alchemy batch may produce any 3 bombs (not required to be identical)."
    Roadmap status: pending (blocked — dc-cr-alchemical-items deferred)
    Feature dependency: dc-cr-alchemical-items (deferred)

    NOTE: Activate after dc-cr-alchemical-items is implemented.

    ## Positive Test Case
    Verify that an Alchemist character with research_field='bomber' can produce
    3 distinct bomb items from one Advanced Alchemy batch.

    ```php
    // TC-650-P: Bomber Field Discovery allows 3 varied bombs per batch
    // Requires: Alchemist character with research_field='bomber', level >= 5
    $db = \Drupal::database();
    // Find a level 5+ Alchemist with bomber field
    $chars = $db->select('node_field_data', 'n')
      ->fields('n', ['nid', 'title'])
      ->condition('n.type', 'character')
      ->execute()->fetchAll();
    $tested = FALSE;
    foreach ($chars as $c) {
      $classNid = $db->select('node__field_char_class', 'cc')
        ->fields('cc', ['field_char_class_target_id'])
        ->condition('entity_id', $c->nid)->execute()->fetchField();
      $class = $db->select('node_field_data', 'n')
        ->fields('n', ['title'])->condition('nid', $classNid)->execute()->fetchField();
      if ($class !== 'Alchemist') continue;
      $field = $db->select('node__field_char_research_field', 'rf')
        ->fields('rf', ['field_char_research_field_value'])
        ->condition('entity_id', $c->nid)->execute()->fetchField();
      if ($field !== 'bomber') continue;
      $level = $db->select('node__field_char_level', 'l')
        ->fields('l', ['field_char_level_value'])
        ->condition('entity_id', $c->nid)->execute()->fetchField();
      if ($level < 5) continue;
      // Simulate one advanced alchemy batch → should yield 3 bomb-type items
      // This requires the AdvancedAlchemyService to be invoked; verify batch size = 3
      $batchSize = \Drupal::service('dungeoncrawler_content.advanced_alchemy')
        ->getBatchSize($c->nid, 'bomb');
      assert($batchSize === 3, "TC-650-P FAIL: Bomber batch size should be 3, got {$batchSize}");
      echo "TC-650-P PASS: Bomber Field Discovery yields batch size 3\n";
      $tested = TRUE; break;
    }
    if (!$tested) echo "TC-650-P SKIP: No level 5+ Bomber Alchemist found\n";
    ```

    ## Negative Test Case
    Verify a Chirurgeon Alchemist does NOT get 3 bombs per batch from Field Discovery.

    ```php
    // TC-650-N: Chirurgeon field does not produce bomb batch of 3
    // The Chirurgeon Field Discovery is for elixirs, not bombs
    $db = \Drupal::database();
    $chars = $db->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'character')
      ->execute()->fetchAll();
    foreach ($chars as $c) {
      $field = $db->select('node__field_char_research_field', 'rf')
        ->fields('rf', ['field_char_research_field_value'])
        ->condition('entity_id', $c->nid)->execute()->fetchField();
      if ($field !== 'chirurgeon') continue;
      $bombBatch = \Drupal::service('dungeoncrawler_content.advanced_alchemy')
        ->getBatchSize($c->nid, 'bomb');
      // Chirurgeon gets normal batch (2) for bombs, not Field Discovery bonus (3)
      assert($bombBatch === 2, "TC-650-N FAIL: Chirurgeon bomb batch should be 2, not 3 (Field Discovery shouldn't apply)");
      echo "TC-650-N PASS: Chirurgeon correctly gets normal bomb batch size (2)\n";
      break;
    }
    ```

    Required actions:
    1) Hold until dc-cr-alchemical-items is implemented.
    2) Run both test cases via drush ev (ALLOW_PROD_QA=1).
    3) Add to qa-regression-checklist.md under "Character Classes / Alchemist / Field Discovery".
- Agent: qa-dungeoncrawler
- Status: pending
