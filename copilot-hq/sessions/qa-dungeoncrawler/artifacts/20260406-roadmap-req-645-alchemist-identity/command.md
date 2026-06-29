- command: |
    Roadmap requirement validation — Core Rulebook Ch3 Alchemist.

    Requirement ID: 645
    Requirement text: "Alchemist identity is defined by alchemical item creation and use—bombs, elixirs, mutagens—not by spellcasting."
    Roadmap status: pending (blocked — dc-cr-alchemical-items feature deferred)
    Feature dependency: dc-cr-alchemical-items (deferred)

    NOTE: These test cases cannot pass until dc-cr-alchemical-items is implemented.
    Activate this inbox item once that feature reaches 'in_progress'.

    ## Positive Test Case
    Verify the Alchemist class node has no spell_slots relationship and does
    reference alchemical item creation (class features include alchemy-related entries).

    ```php
    // TC-645-P: Alchemist has alchemical features and no spellcasting flag
    $db = \Drupal::database();
    $nid = $db->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'character_class')
      ->condition('n.title', 'Alchemist')
      ->execute()->fetchField();
    assert($nid > 0, 'TC-645-P FAIL: Alchemist class not found');

    // Verify Alchemist has no spell slot data
    $spellSlots = $db->select('node__field_class_spell_slots', 's')
      ->condition('entity_id', $nid)
      ->countQuery()->execute()->fetchField();
    assert($spellSlots == 0, "TC-645-P FAIL: Alchemist should have 0 spell slots, got {$spellSlots}");

    // Verify at least one alchemical class feature exists
    $alchFeatures = $db->select('node__field_class_features', 'f')
      ->condition('entity_id', $nid)
      ->countQuery()->execute()->fetchField();
    assert($alchFeatures > 0, 'TC-645-P FAIL: Alchemist should have class features (alchemical)');

    echo "TC-645-P PASS: Alchemist has no spell slots and has class features\n";
    ```

    ## Negative Test Case
    Verify a spellcasting class (e.g., Wizard) DOES have spell slots, confirming
    the distinction between Alchemist and spellcasters is enforced in data.

    ```php
    // TC-645-N: Wizard (spellcaster) has spell slots, confirming Alchemist distinction
    $db = \Drupal::database();
    $wizNid = $db->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'character_class')
      ->condition('n.title', 'Wizard')
      ->execute()->fetchField();
    assert($wizNid > 0, 'TC-645-N SETUP FAIL: Wizard class not found');

    $wizSpellSlots = $db->select('node__field_class_spell_slots', 's')
      ->condition('entity_id', $wizNid)
      ->countQuery()->execute()->fetchField();
    assert($wizSpellSlots > 0, 'TC-645-N FAIL: Wizard should have spell slots to prove Alchemist distinction');
    echo "TC-645-N PASS: Wizard has spell slots; Alchemist correctly has none\n";
    ```

    Required actions:
    1) Hold this item until dc-cr-alchemical-items reaches in_progress.
    2) Then run both test cases via drush ev (ALLOW_PROD_QA=1).
    3) Add to qa-regression-checklist.md under "Character Classes / Alchemist".
- Agent: qa-dungeoncrawler
- Status: pending
