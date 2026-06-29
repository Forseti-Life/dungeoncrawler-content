- command: |
    Roadmap requirement validation — Core Rulebook Ch9 Three Modes of Play.

    Requirement IDs: 2069, 2070
    Req 2069: "System must support three distinct play modes: Encounter, Exploration, and Downtime."
    Req 2070: "Transitioning between modes must be supported; e.g., an encounter ending drops back into exploration mode."
    Roadmap status: implemented (marked by pm-dungeoncrawler 2026-04-06)
    Feature: dc-cr-encounter-rules (done)
    Code ref: GameCoordinatorService.php — VALID_TRANSITIONS matrix, transitionPhase()

    ## Positive Test Cases

    ```php
    // TC-2069-P: All three phase handlers are registered
    $coordinator = \Drupal::service('dungeoncrawler_content.game_coordinator');
    $handlers = ['exploration', 'encounter', 'downtime'];
    foreach ($handlers as $phase) {
      $handler = $coordinator->getPhaseHandler($phase);
      assert($handler !== NULL, "TC-2069-P FAIL: No handler registered for phase '{$phase}'");
      echo "TC-2069-P PASS: '{$phase}' phase handler exists\n";
    }

    // TC-2070-P: encounter → exploration transition is valid
    $transitions = \Drupal\dungeoncrawler_content\Service\GameCoordinatorService::VALID_TRANSITIONS;
    assert(in_array('exploration', $transitions['encounter']),
      'TC-2070-P FAIL: encounter → exploration transition not registered');
    assert(in_array('encounter', $transitions['exploration']),
      'TC-2070-P FAIL: exploration → encounter transition not registered');
    assert(in_array('downtime', $transitions['exploration']),
      'TC-2070-P FAIL: exploration → downtime transition not registered');
    echo "TC-2070-P PASS: All expected mode transitions are registered\n";
    ```

    ## Negative Test Cases

    ```php
    // TC-2069-N: Invalid phase name is rejected
    // Simulate call to transitionPhase with a bad phase
    $transitions = \Drupal\dungeoncrawler_content\Service\GameCoordinatorService::VALID_TRANSITIONS;
    $validTargets = $transitions['exploration'] ?? [];
    assert(!in_array('combat', $validTargets), 'TC-2069-N FAIL: "combat" should not be a valid phase name');
    assert(!in_array('rest', $validTargets), 'TC-2069-N FAIL: "rest" should not be a valid phase name');
    echo "TC-2069-N PASS: Invalid phase names not in transition table\n";

    // TC-2070-N: encounter → downtime transition is NOT directly valid (must go via exploration)
    assert(!in_array('downtime', $transitions['encounter'] ?? []),
      'TC-2070-N FAIL: encounter → downtime should not be a direct transition');
    echo "TC-2070-N PASS: Direct encounter → downtime transition correctly blocked\n";
    ```

    Required actions:
    1) Run all test cases via drush ev (ALLOW_PROD_QA=1).
    2) Add TC-2069-P, TC-2070-P, TC-2069-N, TC-2070-N to qa-regression-checklist.md
       under "Playing the Game / Three Modes of Play".
- Agent: qa-dungeoncrawler
- Status: pending
