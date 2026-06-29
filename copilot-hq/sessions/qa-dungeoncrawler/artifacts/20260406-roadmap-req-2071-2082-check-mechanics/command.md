- command: |
    Roadmap requirement validation — Core Rulebook Ch9 Core Check Mechanics.

    Requirements: 2071–2082 (12 reqs, Core Check Mechanics)
    Implemented (2071–2078, 2080–2081): marked 2026-04-06
    Pending (2079, 2082): bonus-type engine not built — dev inbox: 20260406-impl-bonus-type-engine

    ## Positive Test Cases — Implemented reqs

    ```php
    // TC-2071-P: d20 roll exists and total = roll + modifier
    $result = \Drupal::service('dungeoncrawler_content.combat_calculator')
      ->determineDegreOfSuccess(22, 15, FALSE, FALSE);
    assert($result === 'critical_success', "TC-2071-P FAIL: 22 vs DC 15 should be critical_success, got {$result}");
    echo "TC-2071-P PASS: degree resolution correct for roll > DC+10\n";

    // TC-2073-P: All four degrees exist
    $cc = \Drupal::service('dungeoncrawler_content.combat_calculator');
    assert($cc->determineDegreOfSuccess(25, 15) === 'critical_success', 'TC-2073-P: crit success');
    assert($cc->determineDegreOfSuccess(16, 15) === 'success',          'TC-2073-P: success');
    assert($cc->determineDegreOfSuccess(14, 15) === 'failure',          'TC-2073-P: failure');
    assert($cc->determineDegreOfSuccess(4,  15) === 'critical_failure', 'TC-2073-P: crit fail');
    echo "TC-2073-P PASS: All four degrees of success resolved correctly\n";

    // TC-2074-P: Natural 20 bumps degree up
    $cc = \Drupal::service('dungeoncrawler_content.combat_calculator');
    $degree = $cc->determineDegreOfSuccess(14, 15, TRUE, FALSE); // would be failure, nat20 bumps to success
    assert($degree === 'success', "TC-2074-P FAIL: nat20 should bump failure→success, got {$degree}");
    echo "TC-2074-P PASS: Natural 20 bumps degree up\n";

    // TC-2075-P: Natural 1 bumps degree down
    $cc = \Drupal::service('dungeoncrawler_content.combat_calculator');
    $degree = $cc->determineDegreOfSuccess(16, 15, FALSE, TRUE); // would be success, nat1 bumps to failure
    assert($degree === 'failure', "TC-2075-P FAIL: nat1 should bump success→failure, got {$degree}");
    echo "TC-2075-P PASS: Natural 1 bumps degree down\n";

    // TC-2076-P: Natural 20 cannot exceed critical success
    $cc = \Drupal::service('dungeoncrawler_content.combat_calculator');
    $degree = $cc->determineDegreOfSuccess(25, 15, TRUE, FALSE); // already crit success, nat20 has no further effect
    assert($degree === 'critical_success', "TC-2076-P FAIL: nat20 on crit_success should stay critical_success, got {$degree}");
    echo "TC-2076-P PASS: Degree correctly capped at critical_success\n";

    // TC-2077-P: Proficiency rank bonuses
    $calc = \Drupal::service('dungeoncrawler_content.character_calculator');
    assert($calc->calculateProficiencyBonus('untrained', 5) === 0,       'TC-2077-P: untrained=0');
    assert($calc->calculateProficiencyBonus('trained', 5)   === 7,       'TC-2077-P: trained=level+2=7');
    assert($calc->calculateProficiencyBonus('expert', 5)    === 9,       'TC-2077-P: expert=level+4=9');
    assert($calc->calculateProficiencyBonus('master', 5)    === 11,      'TC-2077-P: master=level+6=11');
    assert($calc->calculateProficiencyBonus('legendary', 5) === 13,      'TC-2077-P: legendary=level+8=13');
    echo "TC-2077-P PASS: All proficiency rank formulas correct\n";
    ```

    ## Negative Test Cases — Implemented reqs

    ```php
    // TC-2076-N: Natural 1 cannot go below critical failure
    $cc = \Drupal::service('dungeoncrawler_content.combat_calculator');
    $degree = $cc->determineDegreOfSuccess(4, 15, FALSE, TRUE); // already crit failure, nat1 has no further effect
    assert($degree === 'critical_failure', "TC-2076-N FAIL: nat1 on crit_failure should stay critical_failure, got {$degree}");
    echo "TC-2076-N PASS: Degree correctly floored at critical_failure\n";

    // TC-2077-N: Invalid rank returns 0 (treated as untrained)
    $calc = \Drupal::service('dungeoncrawler_content.character_calculator');
    $bonus = $calc->calculateProficiencyBonus('godlike', 5);
    assert($bonus === 0, "TC-2077-N FAIL: Unknown rank 'godlike' should return 0, got {$bonus}");
    echo "TC-2077-N PASS: Unknown rank treated as untrained (0)\n";
    ```

    ## Pending Test Cases — Reqs 2079, 2082 (blocked on dev inbox 20260406-impl-bonus-type-engine)

    ```php
    // TC-2079-P: Same bonus type — only highest applies (HOLD until BonusResolver exists)
    // $resolver = \Drupal::service('dungeoncrawler_content.bonus_resolver');
    // $result = $resolver->resolve([['type'=>'circumstance','value'=>2],['type'=>'circumstance','value'=>3]]);
    // assert($result === 3, "TC-2079-P FAIL: Two circumstance bonuses should yield 3 (highest), got {$result}");

    // TC-2082-P: Same penalty type — only worst applies
    // $result = $resolver->resolvePenalties([['type'=>'circumstance','value'=>-2],['type'=>'circumstance','value'=>-3]]);
    // assert($result === -3, "TC-2082-P FAIL: Two circumstance penalties should yield -3 (worst), got {$result}");
    ```

    Required actions:
    1) Run all ACTIVE test cases via drush ev (ALLOW_PROD_QA=1).
    2) Add all TCs to qa-regression-checklist.md under "Playing the Game / Core Check Mechanics".
    3) Activate pending TCs (2079, 2082) once dev inbox 20260406-impl-bonus-type-engine is complete.
- Agent: qa-dungeoncrawler
- Status: pending
