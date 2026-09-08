<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorFindingsInterface;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;

/**
 * Runtime facade for canonical dungeon generation and campaign projection.
 */
final class RuntimeCanonicalDungeonService {

  public function __construct(
    private readonly ?Connection $database,
    private readonly DungeonEditorService $dungeonEditor,
    private readonly CanonicalDungeonLayoutPlanService $dungeonLayoutPlan,
    private readonly CampaignInitializationService $campaignInitialization,
    private readonly UuidInterface $uuid,
  ) {}

  /**
   * Generate, validate, publish, and instantiate a canonical dungeon version.
   */
  public function generateDungeon(array $context): array {
    $this->requireExplicitGenerationWait($context);
    try {
      $published = $this->publishRuntimeGeneratedDungeon($context);
      $runtime_dungeon_id = $this->runtimeDungeonId($context);
      $instantiated = $this->campaignInitialization->instantiatePublishedDungeonIntoCampaign(
        (int) $context['campaign_id'],
        [
          'kind' => 'published_dungeon',
          'dungeon_id' => (string) $published['dungeon_id'],
          'version_id' => (string) $published['published_version_id'],
        ],
        $runtime_dungeon_id
      );
      return $this->responseFromInstantiation($instantiated, $published, $context);
    }
    catch (CanonicalGenerationException $e) {
      throw new RuntimeGenerationException('runtime_generation_failed', $e->getFindings(), $e->httpStatus(), $e);
    }
    catch (DungeonEditorFindingsInterface $e) {
      throw new RuntimeGenerationException('runtime_generation_failed', $e->getFindings(), 422, $e);
    }
    catch (RuntimeGenerationException $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      throw new RuntimeGenerationException('runtime_generation_failed', [[
        'code' => 'runtime_generation_failed',
        'pointer' => '/canonical_dungeon',
        'message' => $e->getMessage(),
        'severity' => 'error',
      ]], 500, $e);
    }
  }

  /**
   * Generate and publish a canonical dungeon fragment, then append as a level.
   */
  public function generateLevel(array $context, array $existing_dungeon = []): array {
    $this->requireExplicitGenerationWait($context);
    try {
      $published = $this->publishRuntimeGeneratedDungeon($context + ['room_count' => max(3, (int) ($context['room_count'] ?? 3))]);
      $runtime_dungeon_id = trim((string) ($context['dungeon_id'] ?? ''));
      if ($runtime_dungeon_id === '') {
        throw new RuntimeGenerationException('runtime_generation_failed', [[
          'code' => 'runtime_generation_failed',
          'pointer' => '/dungeon_id',
          'message' => 'dungeon_id is required for canonical level projection.',
          'severity' => 'error',
        ]], 400);
      }
      $instantiated = $this->campaignInitialization->instantiatePublishedDungeonIntoCampaign(
        (int) $context['campaign_id'],
        [
          'kind' => 'published_dungeon',
          'dungeon_id' => (string) $published['dungeon_id'],
          'version_id' => (string) $published['published_version_id'],
        ],
        $runtime_dungeon_id,
        ['persist_dungeon' => FALSE, 'persist_sparse_h3' => FALSE]
      );
      $depth = max(1, (int) ($context['depth'] ?? ((count((array) ($existing_dungeon['levels'] ?? []))) + 1)));
      return [
        'level_id' => sprintf('level_%d_%d', (int) $context['campaign_id'], $depth),
        'depth' => $depth,
        'theme' => (string) ($context['theme'] ?? $published['theme'] ?? 'generated'),
        'dungeon_type' => 'canonical_runtime',
        'layout_algorithm' => 'canonical_projection',
        'name' => sprintf('Level %d - %s', $depth, (string) ($published['name'] ?? 'Generated Dungeon')),
        'room_count' => count((array) ($instantiated['rooms'] ?? [])),
        'hex_map' => [
          'map_id' => $runtime_dungeon_id,
          'connections' => (array) ($instantiated['connections'] ?? []),
          'metadata' => [
            'source_dungeon_id' => (string) $published['dungeon_id'],
            'source_version_id' => (string) $published['published_version_id'],
            'runtime_generated' => TRUE,
          ],
        ],
        'rooms' => (array) ($instantiated['rooms'] ?? []),
        'connections' => (array) ($instantiated['connections'] ?? []),
        'generation_rules' => [
          'party_level' => (int) ($context['party_level'] ?? 1),
          'party_size' => (int) ($context['party_size'] ?? 4),
          'source' => 'canonical_generation',
          'seed' => (int) $published['seed'],
        ],
        'source_dungeon_id' => (string) $published['dungeon_id'],
        'source_version_id' => (string) $published['published_version_id'],
      ];
    }
    catch (CanonicalGenerationException $e) {
      throw new RuntimeGenerationException('runtime_generation_failed', $e->getFindings(), $e->httpStatus(), $e);
    }
    catch (DungeonEditorFindingsInterface $e) {
      throw new RuntimeGenerationException('runtime_generation_failed', $e->getFindings(), 422, $e);
    }
    catch (RuntimeGenerationException $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      throw new RuntimeGenerationException('runtime_generation_failed', [[
        'code' => 'runtime_generation_failed',
        'pointer' => '/canonical_dungeon_level',
        'message' => $e->getMessage(),
        'severity' => 'error',
      ]], 500, $e);
    }
  }

  /**
   * Publish a runtime-generated canonical dungeon version without projection.
   */
  public function publishRuntimeGeneratedDungeon(array $context): array {
    $draft = $this->dungeonEditor->createDraft(NULL);
    $draft_id = (string) $draft['draft_id'];
    $seed = $this->seed($context);
    $room_count = max(3, min(20, (int) ($context['room_count'] ?? $context['room_count_override'] ?? 3)));
    $theme = trim((string) ($context['theme'] ?? ''));
    if ($theme === '') {
      $theme = 'generated';
    }
    $prompt = trim((string) ($context['prompt'] ?? ''));
    if ($prompt === '') {
      $prompt = sprintf('Generate a connected %d-room %s dungeon for party level %d.', $room_count, $theme, (int) ($context['party_level'] ?? 1));
    }
    $plan = $this->dungeonLayoutPlan->generateDungeonLayoutPlan([
      'prompt' => $prompt,
      'theme' => $theme,
      'room_count' => $room_count,
      'level' => max(-1, min(25, (int) ($context['party_level'] ?? 1))),
      'seed' => $seed,
      'operation' => 'runtime_generation_dungeon_layout',
      'publication_ready' => TRUE,
    ], $draft, is_array($draft['dungeon'] ?? NULL) ? $draft['dungeon'] : [], $this->runtimeEligibleRoomLibrary(),
      fn(array $commands, string $profile): array => $this->dungeonEditor->simulateCommands($draft_id, $commands, $profile)
    );

    $revision = (int) ($plan['command_plan']['base_revision'] ?? $draft['revision'] ?? 0);
    foreach ((array) ($plan['command_plan']['steps'] ?? []) as $step) {
      if (!is_array($step)) {
        continue;
      }
      $result = $this->dungeonEditor->applyCommand($draft_id, [
        'command_id' => $this->uuid->generate(),
        'expected_revision' => $revision,
        'type' => (string) ($step['command_type'] ?? ''),
        'payload' => is_array($step['payload'] ?? NULL) ? $step['payload'] : [],
        'issued_at' => gmdate(DATE_RFC3339),
      ]);
      $revision = (int) ($result['draft']['revision'] ?? ($revision + 1));
    }

    $draft = $this->dungeonEditor->getDraft($draft_id);
    $dungeon = is_array($draft['dungeon'] ?? NULL) ? $draft['dungeon'] : [];
    $generated_by = is_array($dungeon['metadata']['generated_by'] ?? NULL) ? $dungeon['metadata']['generated_by'] : [];
    $generated_by = $generated_by + [
      'source' => 'runtime_generated',
      'service' => 'canonical_generation',
    ];
    $generated_by['requested_by_uid'] = (int) ($context['requested_by_uid'] ?? 0);
    $generated_by['campaign_id'] = (int) ($context['campaign_id'] ?? 0);
    $generated_by['generated_at'] = gmdate(DATE_RFC3339);
    $tags = array_values(array_unique(array_filter(array_map('strval', array_merge(
      (array) ($dungeon['metadata']['tags'] ?? []),
      [$theme, 'runtime_generated']
    )))));
    $metadata_result = $this->dungeonEditor->applyCommand($draft_id, [
      'command_id' => $this->uuid->generate(),
      'expected_revision' => (int) $draft['revision'],
      'type' => 'set_dungeon_metadata',
      'payload' => [
        'changes' => [
          'metadata' => [
            'generated_by' => $generated_by,
            'runtime_generated' => TRUE,
            'tags' => $tags,
          ],
        ],
      ],
      'issued_at' => gmdate(DATE_RFC3339),
    ]);
    $draft = $metadata_result['draft'];
    $validation = $this->dungeonEditor->validateDraft($draft_id, 'publication');
    $errors = array_values(array_filter((array) ($validation['findings'] ?? []), static fn(array $finding): bool => ($finding['severity'] ?? 'error') === 'error'));
    if ($errors !== []) {
      throw new RuntimeGenerationException('runtime_generation_failed', $errors);
    }
    $version = $this->runtimeVersion((string) ($draft['dungeon']['dungeon_id'] ?? $draft['dungeon_id'] ?? ''), $seed);
    $published = $this->dungeonEditor->publish($draft_id, (int) $draft['revision'], (int) ($context['requested_by_uid'] ?? 0), [
      'expected_base_version_id' => $draft['base_version_id'] ?? NULL,
      'version' => $version,
      'publication_note' => 'Runtime-generated canonical dungeon pending curation.',
    ]);
    return array_replace($published, [
      'seed' => $seed,
      'model' => (string) ($generated_by['model'] ?? ''),
      'provider' => (string) ($generated_by['provider'] ?? ''),
      'generated_by' => $generated_by,
      'preview_summary' => $plan['preview_summary'] ?? [],
    ]);
  }

  private function responseFromInstantiation(array $instantiated, array $published, array $context): array {
    $dungeon_data = is_array($instantiated['dungeon_data'] ?? NULL) ? $instantiated['dungeon_data'] : [];
    return array_replace($dungeon_data, [
      'persisted' => TRUE,
      'published_dungeon_id' => (string) $published['dungeon_id'],
      'published_dungeon_version_id' => (string) $published['published_version_id'],
      'source_dungeon_id' => (string) $published['dungeon_id'],
      'source_version_id' => (string) $published['published_version_id'],
      'seed' => (int) $published['seed'],
      'model' => (string) ($published['model'] ?? ''),
      'provider' => (string) ($published['provider'] ?? ''),
      'generation_context' => [
        'party_level' => (int) ($context['party_level'] ?? 1),
        'party_size' => (int) ($context['party_size'] ?? 4),
        'seed' => (int) $published['seed'],
        'source' => 'canonical_generation',
        'generated_at' => gmdate(DATE_RFC3339),
      ],
    ]);
  }

  private function requireExplicitGenerationWait(array $context): void {
    if (empty($context['canonical_generation_wait'])) {
      throw new RuntimeGenerationException('runtime_generation_failed', [[
        'code' => 'runtime_generation_failed',
        'pointer' => '/canonical_generation_wait',
        'message' => 'Explicit GM wait mode is required for runtime canonical dungeon generation.',
        'severity' => 'error',
      ]], 409);
    }
  }

  private function runtimeDungeonId(array $context): string {
    $dungeon_id = trim((string) ($context['dungeon_id'] ?? ''));
    if ($dungeon_id !== '') {
      return $dungeon_id;
    }
    return sprintf('dungeon_%d_%d_%d', (int) $context['campaign_id'], (int) ($context['location_x'] ?? 0), (int) ($context['location_y'] ?? 0));
  }

  private function seed(array $context): int {
    if (isset($context['seed']) && is_numeric($context['seed'])) {
      return max(0, min(2147483647, (int) $context['seed']));
    }
    return (int) (sprintf('%u', crc32(json_encode([
      $context['campaign_id'] ?? 0,
      $context['location_x'] ?? 0,
      $context['location_y'] ?? 0,
      $context['theme'] ?? '',
      $context['party_level'] ?? 1,
      $context['room_count'] ?? $context['room_count_override'] ?? 3,
    ], JSON_UNESCAPED_SLASHES))) % 2147483647);
  }

  private function runtimeEligibleRoomLibrary(): array {
    if (!$this->database) {
      return $this->dungeonEditor->roomLibrary();
    }
    $library = $this->dungeonEditor->roomLibrary();
    $h3_coordinates = $this->authoritativeH3Coordinates($library);
    $eligible = [];
    foreach ($library as $room) {
      if (!is_array($room) || !$this->roomHexesHaveAuthoritativeH3($room, $h3_coordinates) || !$this->portsHaveAuthoritativeH3($room, $h3_coordinates)) {
        continue;
      }
      $entries = (int) ($room['entry_port_count'] ?? 0);
      $exits = (int) ($room['exit_port_count'] ?? 0);
      if ($entries > 0 && in_array($exits, [0, 1], TRUE)) {
        $eligible[] = $room;
      }
    }
    return $eligible;
  }

  /**
   * @param array<int,array<string,mixed>> $library
   *
   * @return array<string,array<string,bool>>
   */
  private function authoritativeH3Coordinates(array $library): array {
    $room_ids = [];
    foreach ($library as $room) {
      if (is_array($room)) {
        $room_id = trim((string) ($room['room_id'] ?? ''));
        if ($room_id !== '') {
          $room_ids[$room_id] = $room_id;
        }
      }
    }
    if ($room_ids === []) {
      return [];
    }
    $coordinates = [];
    $rows = $this->database->select('dungeoncrawler_content_h3_room_cells', 'c')
      ->fields('c', ['room_id', 'source_q', 'source_r'])
      ->condition('room_id', array_values($room_ids), 'IN')
      ->condition('cell_role', ['room_hex', 'exit_gateway'], 'IN')
      ->execute();
    foreach ($rows as $row) {
      $room_id = (string) $row->room_id;
      $coordinates[$room_id][((int) $row->source_q) . ':' . ((int) $row->source_r)] = TRUE;
    }
    return $coordinates;
  }

  /**
   * @param array<string,array<string,bool>> $h3_coordinates
   */
  private function portsHaveAuthoritativeH3(array $room, array $h3_coordinates): bool {
    $room_id = trim((string) ($room['room_id'] ?? ''));
    if ($room_id === '') {
      return FALSE;
    }
    foreach ((array) ($room['ports'] ?? []) as $port) {
      if (!is_array($port) || !is_int($port['q'] ?? NULL) || !is_int($port['r'] ?? NULL)) {
        return FALSE;
      }
      if (empty($h3_coordinates[$room_id][((int) $port['q']) . ':' . ((int) $port['r'])])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * @param array<string,array<string,bool>> $h3_coordinates
   */
  private function roomHexesHaveAuthoritativeH3(array $room, array $h3_coordinates): bool {
    $room_id = trim((string) ($room['room_id'] ?? ''));
    if ($room_id === '') {
      return FALSE;
    }
    foreach ((array) ($room['footprint'] ?? []) as $hex) {
      if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
        return FALSE;
      }
      if (empty($h3_coordinates[$room_id][((int) $hex['q']) . ':' . ((int) $hex['r'])])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private function runtimeVersion(string $dungeon_id, int $seed): string {
    return '0.0.1-runtime.' . gmdate('ymd') . '.' . substr(hash('sha256', $dungeon_id . ':' . $seed . ':' . microtime(TRUE)), 0, 6);
  }

}
