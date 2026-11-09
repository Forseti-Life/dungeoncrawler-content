<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\AgentHarness;

/**
 * Central registry for all DungeonCrawler agent harnesses.
 */
class AgentHarnessRegistry {

  /**
   * @var array<string, \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessInterface>
   */
  private array $harnesses = [];

  /**
   * @param iterable<\Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessInterface> $harnesses
   */
  public function __construct(iterable $harnesses = []) {
    foreach ($harnesses as $harness) {
      if (!$harness instanceof AgentHarnessInterface) {
        throw new \LogicException('agent_harness_invalid');
      }
      $id = $harness->agentId();
      if ($id === '' || isset($this->harnesses[$id])) {
        throw new \LogicException(sprintf('agent_harness_duplicate_or_empty:%s', $id));
      }
      $this->harnesses[$id] = $harness;
    }
  }

  /**
   * Returns a registered agent harness or hard-fails.
   */
  public function get(string $agent_id): AgentHarnessInterface {
    if (!isset($this->harnesses[$agent_id])) {
      throw new \InvalidArgumentException(sprintf('agent_harness_unsupported:%s', $agent_id));
    }
    return $this->harnesses[$agent_id];
  }

  /**
   * Returns all registered agent harness IDs.
   *
   * @return string[]
   */
  public function agentIds(): array {
    return array_keys($this->harnesses);
  }

  /**
   * Describes all registered agent harnesses and their profiles/scopes.
   *
   * @return array<string, array<string, mixed>>
   */
  public function describeAll(): array {
    $descriptors = [];
    foreach ($this->harnesses as $id => $harness) {
      $descriptors[$id] = [
        'agent_id' => $id,
        'label' => $harness->label(),
        'scope' => $harness->scope(),
        'default_execution_profile' => $harness->defaultExecutionProfile(),
        'tools' => $harness->listAgentTools(),
      ];
    }
    return $descriptors;
  }

}
