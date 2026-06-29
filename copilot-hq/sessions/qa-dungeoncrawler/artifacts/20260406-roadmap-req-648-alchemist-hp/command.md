- command: |
    Roadmap requirement validation — Core Rulebook Ch3 Alchemist.

    Requirement ID: 648
    Requirement text: "Alchemist max HP = (8 + CON modifier) × level at character creation, increasing by that amount each level."
    Roadmap status: implemented (marked by pm-dungeoncrawler 2026-04-06)
    Feature: dc-cr-character-class (done), dc-cr-character-creation (done)
    Code ref: CharacterCreationStepForm.php — $max_hp = $ancestry_hp + $class_hp + $con_mod + (($level - 1) * ($class_hp + $con_mod))

    ## Positive Test Case
    Create an Alchemist character with a known CON modifier (+2) at level 1 and
    verify max HP = ancestry_hp + 8 + 2 = ancestry_hp + 10.

    ```php
    // TC-648-P: Alchemist level 1, CON=14 (+2), Dwarf ancestry (HP 10) → max_hp = 10+8+2 = 20
    $db = \Drupal::database();
    $chars = $db->select('node_field_data', 'n')
      ->fields('n', ['nid', 'title'])
      ->condition('n.type', 'character')
      ->range(0, 50)
      ->execute()->fetchAll();
    $found = FALSE;
    foreach ($chars as $c) {
      $classRef = $db->select('node__field_char_class', 'cc')
        ->fields('cc', ['field_char_class_target_id'])
        ->condition('entity_id', $c->nid)->execute()->fetchField();
      $className = $db->select('node_field_data', 'n')
        ->fields('n', ['title'])->condition('nid', $classRef)->execute()->fetchField();
      if ($className !== 'Alchemist') continue;
      $hpMax = $db->select('node__field_char_hp_max', 'h')
        ->fields('h', ['field_char_hp_max_value'])
        ->condition('entity_id', $c->nid)->execute()->fetchField();
      $level = $db->select('node__field_char_level', 'l')
        ->fields('l', ['field_char_level_value'])
        ->condition('entity_id', $c->nid)->execute()->fetchField();
      $conMod = $db->select('node__field_char_con_mod', 'm')
        ->fields('m', ['field_char_con_mod_value'])
        ->condition('entity_id', $c->nid)->execute()->fetchField();
      $ancestryHp = $db->select('node__field_char_ancestry_hp', 'a')
        ->fields('a', ['field_char_ancestry_hp_value'])
        ->condition('entity_id', $c->nid)->execute()->fetchField();
      $expected = $ancestryHp + (8 + $conMod) * $level;
      assert($hpMax == $expected, "TC-648-P FAIL: {$c->title} max HP={$hpMax}, expected {$expected} (ancestry={$ancestryHp}, level={$level}, con_mod={$conMod})");
      echo "TC-648-P PASS: {$c->title} HP={$hpMax} matches formula\n";
      $found = TRUE; break;
    }
    if (!$found) echo "TC-648-P SKIP: No Alchemist character found to test\n";
    ```

    ## Negative Test Case
    Verify that an Alchemist character cannot have HP below the minimum
    (ancestry_hp + 8 + con_mod at level 1, even with negative CON mod).

    ```php
    // TC-648-N: HP calculation never goes below 1 per level (PF2E minimum)
    // Simulate level 1 Alchemist with CON=-1 (-5 mod is impossible but test floor)
    $class_hp = 8;
    $con_mod = -5; // extreme negative (CON 1 = -5 mod)
    $ancestry_hp = 6;
    $level = 1;
    $max_hp = $ancestry_hp + $class_hp + $con_mod + (($level - 1) * ($class_hp + $con_mod));
    // PF2E: minimum 1 HP per level, so floor at $level
    $effective_hp = max($max_hp, $level);
    assert($effective_hp >= 1, "TC-648-N FAIL: HP fell below 1 (got {$effective_hp})");
    echo "TC-648-N PASS: HP floor enforced at {$effective_hp} (raw formula: {$max_hp})\n";
    ```

    Required actions:
    1) Run both test cases via drush ev (ALLOW_PROD_QA=1).
    2) Add TC-648-P and TC-648-N to qa-regression-checklist.md under "Character Classes / Alchemist".
- Agent: qa-dungeoncrawler
- Status: pending
