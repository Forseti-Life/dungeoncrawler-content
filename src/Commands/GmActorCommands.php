<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\dungeoncrawler_content\Service\GmActorHarnessService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the GM actor harness.
 */
class GmActorCommands extends DrushCommands {

  protected GmActorHarnessService $harness;

  public function __construct(GmActorHarnessService $harness) {
    parent::__construct();
    $this->harness = $harness;
  }

  /**
   * Run one GM-routed room chat turn.
   *
   * @command dungeoncrawler_content:gm-actor-run
   * @option actor-id Runtime actor ID owning the turn.
   * @option character-id Character ID owning the turn.
   * @option suppress-gm Suppress visible GM response.
   * @option speaker Optional speaker label override.
   * @aliases dc:gm-actor-run
   */
  public function run(
    int $campaign_id,
    string $room_id,
    string $message,
    array $options = [
      'actor-id' => NULL,
      'character-id' => NULL,
      'suppress-gm' => FALSE,
      'speaker' => NULL,
    ]
  ): int {
    $actor_id = trim((string) ($options['actor-id'] ?? ''));
    $character_id = (int) ($options['character-id'] ?? 0);
    if ($actor_id === '' || $character_id <= 0) {
      $this->io()->error('Both --actor-id and --character-id are required.');
      return self::EXIT_FAILURE;
    }

    try {
      $result = $this->harness->handlePlayerRoomChat(
        $campaign_id,
        $room_id,
        $actor_id,
        $character_id,
        $message,
        (bool) ($options['suppress-gm'] ?? FALSE),
        (string) ($options['speaker'] ?? ''),
        [
          'workflow' => 'authoritative_room_chat',
          'route' => 'free_player_room_chat',
          'route_family' => 'gm_backstop_chat',
        ]
      );
    }
    catch (\Throwable $e) {
      $this->io()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }

    $this->io()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return self::EXIT_SUCCESS;
  }

  /**
   * List canonical GM privileged tools.
   *
   * @command dungeoncrawler_content:gm-actor-tools
   * @aliases dc:gm-actor-tools
   */
  public function tools(): int {
    $this->io()->writeln(json_encode($this->harness->listTools(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return self::EXIT_SUCCESS;
  }

  /**
   * Execute one privileged GM tool call.
   *
   * @command dungeoncrawler_content:gm-actor-tool
   * @option payload JSON payload for the selected tool.
   * @option actor-role Runtime role; must be gm.
   * @option actor-id Runtime GM actor id (principal binding).
   * @option character-id Runtime GM character id (principal binding).
   * @option correlation-id Correlation id for immutable audit ledger.
   * @aliases dc:gm-actor-tool
   */
  public function tool(
    string $tool,
    array $options = [
      'payload' => NULL,
      'actor-role' => 'gm',
      'actor-id' => NULL,
      'character-id' => NULL,
      'correlation-id' => NULL,
    ]
  ): int {
    $payload_raw = trim((string) ($options['payload'] ?? ''));
    if ($payload_raw === '') {
      $this->io()->error('The --payload option is required and must be valid JSON.');
      return self::EXIT_FAILURE;
    }
    $payload = json_decode($payload_raw, TRUE);
    if (!is_array($payload)) {
      $this->io()->error('The --payload option must be a JSON object.');
      return self::EXIT_FAILURE;
    }

    $actor_id = trim((string) ($options['actor-id'] ?? ''));
    $character_id = (int) ($options['character-id'] ?? 0);
    $correlation_id = trim((string) ($options['correlation-id'] ?? ''));
    if ($actor_id === '' || $character_id <= 0 || $correlation_id === '') {
      $this->io()->error('The --actor-id, --character-id, and --correlation-id options are required.');
      return self::EXIT_FAILURE;
    }

    try {
      $result = $this->harness->runTool($tool, $payload, [
        'actor_role' => (string) ($options['actor-role'] ?? 'gm'),
        'gm_actor_id' => $actor_id,
        'gm_character_id' => $character_id,
        'correlation_id' => $correlation_id,
      ]);
    }
    catch (\Throwable $e) {
      $this->io()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }

    $this->io()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return self::EXIT_SUCCESS;
  }

  /**
   * Replay a GM actor room-chat payload for deterministic harness comparison.
   *
   * @command dungeoncrawler_content:gm-actor-replay
   * @option payload JSON replay payload with campaign_id, room_id, actor_id, character_id, message.
   * @option iterations Number of replay iterations.
   * @aliases dc:gm-actor-replay
   */
  public function replay(array $options = [
    'payload' => NULL,
    'iterations' => 1,
  ]): int {
    $payload_raw = trim((string) ($options['payload'] ?? ''));
    if ($payload_raw === '') {
      $this->io()->error('The --payload option is required and must be valid JSON.');
      return self::EXIT_FAILURE;
    }
    $payload = json_decode($payload_raw, TRUE);
    if (!is_array($payload)) {
      $this->io()->error('The --payload option must be a JSON object.');
      return self::EXIT_FAILURE;
    }

    try {
      $result = $this->harness->replayRoomChat($payload, max(1, (int) ($options['iterations'] ?? 1)));
    }
    catch (\Throwable $e) {
      $this->io()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }

    $this->io()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return self::EXIT_SUCCESS;
  }

}
