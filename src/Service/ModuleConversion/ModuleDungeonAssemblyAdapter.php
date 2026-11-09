<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDungeonLayoutPlanService;

/**
 * Assembles an original Forseti topology from exact, playable scene receipts.
 *
 * This is an explicit integration entry point, not a release publication hook.
 * The caller attests that the supplied display metadata is publication-safe
 * and project-original. Source navigation order is not a traversal graph.
 */
final class ModuleDungeonAssemblyAdapter {

  public function __construct(
    private readonly CanonicalDungeonLayoutPlanService $generation,
    private readonly DungeonEditorService $editor,
  ) {}

  /**
   * Builds and publishes a new canonical dungeon, returning its pinned version.
   *
   * Receipts are a list of ['resource_id', 'owner' => 'RoomEditorService',
   * 'target_id' => room ID, 'target_version' => published version UUID].
   * Metadata is exactly ['name', 'description', 'theme',
   * 'rights_status' => 'project_original']. No source text is sent to the
   * generator. Links are generated original Forseti topology, not source links.
   *
   * A generation/validation failure leaves an active editor draft but never
   * publishes it. The caller must handle that draft operationally.
   */
  public function assembleDungeon(array $receipts, array $metadata, string $version, int $uid, int $seed, string $map_state = 'unattached', ?callable $quest_author = NULL): array {
    // The shared generator exposes at most 15 nonterminal rooms in its prompt.
    if (!array_is_list($receipts) || count($receipts) < 3 || count($receipts) > 16) {
      throw new \InvalidArgumentException('module_dungeon_receipt_count_invalid');
    }
    if (count($metadata) !== 4
      || array_diff(array_keys($metadata), ['name', 'description', 'theme', 'rights_status']) !== []
      || array_diff(['name', 'description', 'theme', 'rights_status'], array_keys($metadata)) !== []
      || $metadata['rights_status'] !== 'project_original'
      || !is_string($metadata['name']) || trim($metadata['name']) === '' || mb_strlen($metadata['name']) > 200
      || !is_string($metadata['description']) || mb_strlen($metadata['description']) > 8000
      || !is_string($metadata['theme']) || trim($metadata['theme']) === '' || mb_strlen($metadata['theme']) > 100) {
      throw new \InvalidArgumentException('module_dungeon_publication_metadata_invalid');
    }
    if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $version) || strlen($version) > 32
      || $uid <= 0 || $seed < 0 || $seed > 2147483647
      || !in_array($map_state, ['unattached', 'staged'], TRUE)) {
      throw new \InvalidArgumentException('module_dungeon_publication_input_invalid');
    }

    $library = $this->editor->roomLibrary();
    $by_id = [];
    foreach ($library as $room) {
      $by_id[$room['room_id']] = $room;
    }
    $required = [];
    $targets = [];
    $resources = [];
    $selected_library = [];
    $terminal_count = 0;
    foreach ($receipts as $receipt) {
      if (!is_array($receipt) || array_keys($receipt) !== ['resource_id', 'owner', 'target_id', 'target_version']
        || !is_string($receipt['resource_id']) || $receipt['resource_id'] === ''
        || $receipt['owner'] !== 'RoomEditorService'
        || !is_string($receipt['target_id']) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $receipt['target_id'])
        || !is_string($receipt['target_version']) || $receipt['target_version'] === '') {
        throw new \InvalidArgumentException('module_dungeon_receipt_invalid');
      }
      $id = $receipt['target_id'];
      $pin = $receipt['target_version'];
      if (isset($targets[$id]) || isset($resources[$receipt['resource_id']])) {
        throw new \DomainException('module_dungeon_duplicate_receipt_target');
      }
      $targets[$id] = TRUE;
      $resources[$receipt['resource_id']] = TRUE;
    }
    foreach ($receipts as $receipt) {
      $id = $receipt['target_id'];
      $pin = $receipt['target_version'];
      $room = $by_id[$id] ?? NULL;
      $payload = $this->editor->roomVersion($pin);
      if ($room === NULL || $room['version_id'] !== $pin || !is_array($payload)
        || ($payload['room_id'] ?? NULL) !== $id) {
        throw new \DomainException('module_dungeon_room_version_not_published:' . $id);
      }
      $conversion = $payload['metadata']['module_conversion'] ?? [];
      $state = $conversion['source_scene']['conversion_state'] ?? NULL;
      if (($conversion['resource_id'] ?? NULL) !== $receipt['resource_id']
        || !in_array($state, $map_state === 'staged'
          ? ['original_gameplay_generated_map_staged', 'playable_validated']
          : ['original_gameplay_generated_map_unattached'], TRUE)
        || ($conversion['reviewed_original']['rights_status'] ?? NULL) !== 'project_original'
        || ($conversion['reviewed_original']['map_attachment_state'] ?? NULL) !== $map_state
        || !is_array($payload['placements'] ?? NULL) || $payload['placements'] === []) {
        throw new \DomainException('module_dungeon_room_not_playable:' . $id);
      }
      $exits = $room['exit_port_count'] ?? NULL;
      if (($room['entry_port_count'] ?? 0) < 1 || !in_array($exits, [0, 1], TRUE)) {
        throw new \DomainException('module_dungeon_room_ports_ineligible:' . $id);
      }
      $terminal_count += (int) ($exits === 0);
      $required[] = $id;
      $selected_library[] = $room;
    }
    if ($terminal_count !== 1) {
      throw new \DomainException('module_dungeon_exactly_one_terminal_required');
    }

    $draft = $this->editor->createDraft(NULL);
    $draft_id = $draft['draft_id'] ?? NULL;
    if (!is_string($draft_id) || ($draft['revision'] ?? NULL) !== 0
      || ($draft['status'] ?? NULL) !== 'active'
      || ($draft['dungeon']['room_placements'] ?? NULL) !== []) {
      throw new \DomainException('module_dungeon_fresh_draft_required');
    }
    $plan = $this->generation->generateDungeonLayoutPlan([
      'prompt' => 'Create an original connected Forseti dungeon using exactly the provided rooms. Invent no source-module topology.',
      'room_count' => count($required),
      'required_room_ids' => $required,
      'publication_ready' => TRUE,
      'seed' => $seed,
    ], $draft, $draft['dungeon'], $selected_library,
      fn(array $commands, string $profile): array => $this->editor->simulateCommands($draft_id, $commands, $profile)
    );
    $steps = $plan['command_plan']['steps'] ?? NULL;
    if (($plan['command_plan']['draft_id'] ?? NULL) !== $draft_id
      || ($plan['command_plan']['base_revision'] ?? NULL) !== 0
      || !is_array($steps) || $steps === [] || count($steps) > 119
      || $plan['seed'] !== $seed) {
      throw new \DomainException('module_dungeon_generation_plan_invalid');
    }
    $selected = $plan['selected_rooms'] ?? NULL;
    if (!is_array($selected) || count($selected) !== count($required)) {
      throw new \DomainException('module_dungeon_generation_selection_invalid');
    }
    $pins = [];
    foreach ($selected as $room) {
      if (!is_array($room) || !is_string($room['room_id'] ?? NULL)
        || !isset($targets[$room['room_id']]) || isset($pins[$room['room_id']])
        || ($room['version_id'] ?? NULL) !== $by_id[$room['room_id']]['version_id']) {
        throw new \DomainException('module_dungeon_generation_selection_invalid');
      }
      $pins[$room['room_id']] = TRUE;
    }
    $commands = $this->generation->envelopesFromSteps($steps, 0, $seed, $draft_id);
    $commands[] = [
      'command_id' => $this->uuid(),
      'expected_revision' => count($commands),
      'type' => 'set_dungeon_metadata',
      'payload' => ['changes' => [
        'name' => trim($metadata['name']),
        'description' => $metadata['description'],
        'theme' => trim($metadata['theme']),
        'metadata' => ['module_conversion' => [
          'execution_mode' => 'original_scene_generation',
          'map_attachment_state' => $map_state,
        ]],
      ]],
      'issued_at' => gmdate(DATE_RFC3339),
    ];
    $simulation = $this->editor->simulateCommands($draft_id, $commands, 'publication');
    $projected = $simulation['dungeon'] ?? [];
    $placed = [];
    foreach ((array) ($projected['room_placements'] ?? []) as $placement) {
      $id = $placement['room_id'] ?? '';
      if (!isset($targets[$id]) || isset($placed[$id])
        || ($placement['version_id'] ?? NULL) !== $by_id[$id]['version_id']) {
        throw new \DomainException('module_dungeon_projection_receipts_mismatch');
      }
      $placed[$id] = TRUE;
    }
    if (($simulation['rejected'] ?? NULL) !== NULL
      || ($simulation['validation']['is_valid'] ?? FALSE) !== TRUE
      || count($placed) !== count($required)
      || count((array) ($projected['port_links'] ?? [])) < count($required) - 1
      || count(array_filter((array) ($projected['room_placements'] ?? []), static fn(array $p): bool => ($p['is_level_entrance'] ?? FALSE) === TRUE)) !== 1) {
      throw new \DomainException('module_dungeon_publication_projection_invalid');
    }
    if ($quest_author !== NULL) {
      $quest_template_id = $quest_author($projected);
      if (!is_string($quest_template_id) || trim($quest_template_id) === '') {
        throw new \DomainException('module_dungeon_quest_template_invalid');
      }
      $commands[array_key_last($commands)]['payload']['changes']['metadata']['module_conversion']['quest_template_id'] = trim($quest_template_id);
      $reviewed = $this->editor->simulateCommands($draft_id, $commands, 'publication');
      if (($reviewed['rejected'] ?? NULL) !== NULL || ($reviewed['validation']['is_valid'] ?? FALSE) !== TRUE
        || ($reviewed['dungeon']['room_placements'] ?? NULL) !== $projected['room_placements']) {
        throw new \DomainException('module_dungeon_quest_publication_projection_invalid');
      }
    }

    foreach ($commands as $command) {
      $this->editor->applyCommand($draft_id, $command);
    }
    $readiness = $this->editor->publicationReadiness($draft_id);
    if (($readiness['ready'] ?? FALSE) !== TRUE || ($readiness['revision'] ?? NULL) !== count($commands)) {
      throw new \DomainException('module_dungeon_publication_not_ready');
    }
    $published = $this->editor->publish($draft_id, count($commands), $uid, ['version' => $version]);
    return ['published' => $published, 'generation_plan' => $plan, 'draft_id' => $draft_id, 'topology_origin' => 'original_forseti_generated'];
  }

  private function uuid(): string {
    $hex = bin2hex(random_bytes(16));
    $hex[12] = '4';
    $hex[16] = dechex((hexdec($hex[16]) & 3) | 8);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
  }

}
