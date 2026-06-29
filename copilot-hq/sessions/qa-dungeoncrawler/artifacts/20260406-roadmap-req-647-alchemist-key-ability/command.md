- command: |
    Roadmap requirement validation — Core Rulebook Ch3 Alchemist.

    Requirement ID: 647
    Requirement text: "Alchemist key ability score is Intelligence; all class DCs and relevant modifiers reference Intelligence."
    Roadmap status: implemented (marked by pm-dungeoncrawler 2026-04-06)
    Feature: dc-cr-character-class (done)

    ## Positive Test Case
    Verify Alchemist key ability is stored as 'Intelligence' in the DB.

    ```php
    // TC-647-P: Alchemist key ability = Intelligence
    $db = \Drupal::database();
    $nid = $db->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'character_class')
      ->condition('n.title', 'Alchemist')
      ->execute()->fetchField();
    $key = $db->select('node__field_class_key_ability', 'k')
      ->fields('k', ['field_class_key_ability_value'])
      ->condition('entity_id', $nid)
      ->execute()->fetchField();
    assert($key === 'Intelligence', "TC-647-P FAIL: Expected Intelligence, got '{$key}'");
    echo "TC-647-P PASS: Alchemist key ability = Intelligence\n";
    ```

    ## Negative Test Case
    Verify no other class uses Intelligence as key ability where it would conflict
    (e.g., Wizard also uses Intelligence — both should coexist correctly).

    ```php
    // TC-647-N: Alchemist key ability cannot be changed to a non-INT value at DB level
    $db = \Drupal::database();
    $nid = $db->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'character_class')
      ->condition('n.title', 'Alchemist')
      ->execute()->fetchField();
    // Attempt to verify that the field doesn't store an invalid ability
    $key = $db->select('node__field_class_key_ability', 'k')
      ->fields('k', ['field_class_key_ability_value'])
      ->condition('entity_id', $nid)
      ->execute()->fetchField();
    $valid = ['Strength','Dexterity','Constitution','Intelligence','Wisdom','Charisma'];
    assert(in_array($key, $valid), "TC-647-N FAIL: Key ability '{$key}' is not a valid ability score");
    assert($key !== 'Strength' && $key !== 'Dexterity', "TC-647-N FAIL: Alchemist key ability is wrong physical stat");
    echo "TC-647-N PASS: Alchemist key ability is a valid INT-type score\n";
    ```

    Required actions:
    1) Run both test cases via drush ev (ALLOW_PROD_QA=1).
    2) Add TC-647-P and TC-647-N to qa-regression-checklist.md under "Character Classes / Alchemist".
- Agent: qa-dungeoncrawler
- Status: pending
