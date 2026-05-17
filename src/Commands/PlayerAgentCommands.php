<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\dungeoncrawler_content\Service\PlayerAgentHarnessService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the headless player-agent harness.
 */
class PlayerAgentCommands extends DrushCommands {

  protected PlayerAgentHarnessService $harness;

  public function __construct(PlayerAgentHarnessService $harness) {
    parent::__construct();
    $this->harness = $harness;
  }

  /**
   * Run the player-agent harness for one or more steps.
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $actor_id
   *   The runtime actor entity ID to control.
   * @param array $options
   *   Command options.
   *
   * @command dungeoncrawler_content:player-agent-run
   * @option steps Number of autonomous steps to execute.
   * @option character-id Numeric character ID for XP/progression lookups.
   * @option character-name Display name used for persona framing.
   * @option profile JSON object merged into the generated base profile.
   * @usage dungeoncrawler_content:player-agent-run 123 930001 --steps=3 --character-name="Torgar Ironforge"
   *   Run three autonomous steps for actor 930001 in campaign 123.
   * @aliases dc:player-agent-run
   */
  public function run(int $campaign_id, string $actor_id, array $options = [
    'steps' => 1,
    'character-id' => NULL,
    'character-name' => NULL,
    'profile' => NULL,
  ]): int {
    $steps = max(1, (int) ($options['steps'] ?? 1));
    $profile = [
      'actor_id' => $actor_id,
      'character_name' => (string) ($options['character-name'] ?? $actor_id),
      'goals' => ['explore', 'gain_xp', 'level_up'],
      'persona' => ['tone' => 'curious'],
    ];

    $character_id = (int) ($options['character-id'] ?? 0);
    if ($character_id > 0) {
      $profile['character_id'] = $character_id;
    }

    $profile_json = trim((string) ($options['profile'] ?? ''));
    if ($profile_json !== '') {
      $decoded = json_decode($profile_json, TRUE);
      if (!is_array($decoded)) {
        $this->io()->error('The --profile option must be a valid JSON object.');
        return self::EXIT_FAILURE;
      }
      $profile = array_replace_recursive($profile, $decoded);
    }

    $this->io()->title('Running Player Agent Harness');
    $run = $this->harness->runSteps($campaign_id, $profile, $steps, [], TRUE);

    $rows = [];
    foreach ($run['results'] ?? [] as $result) {
      $decision = $result['decision'] ?? [];
      $intent = is_array($decision['intent'] ?? NULL) ? $decision['intent'] : [];
      $rows[] = [
        (string) ($result['run_state']['step_count'] ?? count($rows) + 1),
        (string) ($result['snapshot']['phase'] ?? 'unknown'),
        (string) ($intent['type'] ?? ($decision['type'] ?? 'wait')),
        !empty($result['success']) ? 'yes' : 'no',
        (string) ($decision['reason'] ?? $result['error'] ?? ''),
      ];
    }

    if ($rows !== []) {
      $this->io()->table(['Step', 'Phase', 'Decision', 'Success', 'Reason'], $rows);
    }

    $progress = $run['run_state']['progress'] ?? [];
    if ($progress !== []) {
      $summary_rows = [];
      foreach ($progress as $key => $value) {
        $summary_rows[] = [$key, is_scalar($value) ? (string) $value : json_encode($value)];
      }
      $this->io()->section('Progress');
      $this->io()->table(['Metric', 'Value'], $summary_rows);
    }

    if (!empty($run['success'])) {
      $this->io()->success('Player agent run completed.');
      return self::EXIT_SUCCESS;
    }

    $this->io()->error('Player agent run failed.');
    return self::EXIT_FAILURE;
  }

}
