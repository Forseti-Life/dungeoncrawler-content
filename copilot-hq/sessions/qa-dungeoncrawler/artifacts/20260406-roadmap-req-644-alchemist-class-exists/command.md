- command: |
    Roadmap requirement validation — Core Rulebook Ch3 Alchemist.

    Requirement ID: 644
    Requirement text: "Alchemist class must exist as a playable character class."
    Roadmap status: implemented (marked by pm-dungeoncrawler 2026-04-06)
    Feature: dc-cr-character-class (done)

    ## Positive Test Case
    Verify Alchemist exists as character_class node with correct base stats.

    ```php
    // TC-644-P: Alchemist class node exists with required base data
    $db = \Drupal::database();
    $nid = $db->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'character_class')
      ->condition('n.title', 'Alchemist')
      ->execute()->fetchField();
    assert($nid > 0, 'TC-644-P FAIL: Alchemist character_class node not found');

    $hp = $db->select('node__field_class_hp_per_level', 'h')
      ->fields('h', ['field_class_hp_per_level_value'])
      ->condition('entity_id', $nid)
      ->execute()->fetchField();
    assert($hp == 8, "TC-644-P FAIL: Alchemist HP per level should be 8, got {$hp}");

    $key = $db->select('node__field_class_key_ability', 'k')
      ->fields('k', ['field_class_key_ability_value'])
      ->condition('entity_id', $nid)
      ->execute()->fetchField();
    assert($key === 'Intelligence', "TC-644-P FAIL: Alchemist key ability should be Intelligence, got {$key}");

    echo "TC-644-P PASS: Alchemist exists as character_class, HP=8, key_ability=Intelligence\n";
    ```

    Run: `cd /home/ubuntu/forseti.life/sites/dungeoncrawler && ./vendor/bin/drush ev '<code above>'`

    ## Negative Test Case
    Verify that a non-existent class cannot be loaded.

    ```php
    // TC-644-N: Non-existent class returns no result
    $db = \Drupal::database();
    $nid = $db->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'character_class')
      ->condition('n.title', 'FakeClass_DoesNotExist')
      ->execute()->fetchField();
    assert($nid === FALSE, 'TC-644-N FAIL: FakeClass_DoesNotExist should not exist');
    echo "TC-644-N PASS: Non-existent class correctly returns no result\n";
    ```

    Required actions:
    1) Run both test cases via drush ev against production (ALLOW_PROD_QA=1).
    2) Record PASS/FAIL results in verification report.
    3) Add TC-644-P and TC-644-N to qa-regression-checklist.md under "Character Classes".

    Deliverable:
    - Verification report with APPROVE/BLOCK per test case.
- Agent: qa-dungeoncrawler
- Status: pending
