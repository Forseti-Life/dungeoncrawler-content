<?php

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;

/**
 * R3 runtime room facade over the canonical generation/selection path.
 */
final class RuntimeCanonicalRoomService {

  public function __construct(
    private readonly RuntimeCanonicalContentResolver $resolver,
    private readonly CanonicalRoomProjectionService $projection,
    private readonly ?EditorCanonicalGenerationPlanService $editorGenerationPlan = NULL,
    private readonly ?RoomEditorService $roomEditor = NULL,
    private readonly ?CanonicalDefinitionService $definitions = NULL,
    private readonly ?UuidInterface $uuid = NULL,
  ) {}

  /**
   * Select or explicitly generate a canonical room, then project runtime data.
   */
  public function generateRoom(array $context): array {
    $criteria = $this->selectionCriteria($context);
    $resolved = !empty($context['canonical_generation_wait'])
      ? $this->generatePublishAndResolveRuntimeRoom($context, $criteria)
      : $this->resolver->selectPublishedRoom($criteria);

    $runtime_room_id = trim((string) ($context['runtime_room_id'] ?? $context['room_id'] ?? ''));
    if ($runtime_room_id === '') {
      $runtime_room_id = $this->buildRoomId($context);
    }
    $room = $this->projection->buildRuntimeRoom($resolved, [
      'runtime_room_id' => $runtime_room_id,
      'source_kind' => !empty($resolved['room_payload']['metadata']['runtime_generated']) ? 'runtime_generated' : 'published_room',
    ]);
    if (empty($context['defer_room_persistence'])) {
      $persisted = $this->projection->persistProjectedRoom((int) ($context['campaign_id'] ?? 0), $room);
      $room = $persisted['room'];
    }
    $room['from_canonical_runtime'] = TRUE;
    $room['room_version_id'] = $resolved['room_version_id'];
    return $room;
  }

  /**
   * Generate a canonical room via the canonical JSON pipeline and publish it.
   *
   * @return array{room_version_id:string,room_payload:array<string,mixed>,room_id:string,version:string,row:array<string,mixed>,selection:array<string,mixed>}
   */
  private function generatePublishAndResolveRuntimeRoom(array $context, array $criteria): array {
    if (!$this->editorGenerationPlan || !$this->roomEditor || !$this->definitions || !$this->uuid) {
      throw new RuntimeGenerationException('runtime_generation_failed', [[
        'code' => 'runtime_generation_failed',
        'pointer' => '/services',
        'message' => 'Runtime canonical generation requires editor generation, room editor, definition, and uuid services.',
        'severity' => 'error',
      ]], 500);
    }

    try {
      $draft = $this->roomEditor->createDraft(NULL);
      $draft_id = (string) $draft['draft_id'];
      $seed = (int) ($criteria['seed'] ?? 0);
      $tool_context = new RoomEditorGmToolContext($draft_id, 'editing', $this->roomEditor, $this->definitions);
      $plan_result = $this->editorGenerationPlan->generateRoomLayout([
        'prompt' => $this->runtimeRoomPrompt($context),
        'theme' => (string) ($context['theme'] ?? ''),
        'size_category' => $this->canonicalSizeCategory((string) ($context['room_size'] ?? 'medium')),
        'room_type' => $this->canonicalRoomType((string) ($context['room_type'] ?? 'chamber')),
        'level' => max(-1, min(25, (int) ($context['party_level'] ?? 1))),
        'seed' => $seed,
      ], $tool_context);
      $command_plan = is_array($plan_result['command_plan'] ?? NULL) ? $plan_result['command_plan'] : [];
      $revision = (int) ($command_plan['base_revision'] ?? $draft['revision'] ?? 0);
      foreach ((array) ($command_plan['steps'] ?? []) as $step) {
        if (!is_array($step)) {
          continue;
        }
        $result = $this->roomEditor->applyCommand($draft_id, [
          'command_id' => $this->uuid->generate(),
          'expected_revision' => $revision,
          'type' => (string) ($step['command_type'] ?? ''),
          'payload' => is_array($step['payload'] ?? NULL) ? $step['payload'] : [],
        ]);
        $revision = (int) ($result['draft']['revision'] ?? ($revision + 1));
      }

      $draft = $this->roomEditor->getDraft($draft_id);
      $room = is_array($draft['room'] ?? NULL) ? $draft['room'] : [];
      $generated_by = is_array($room['metadata']['generated_by'] ?? NULL) ? $room['metadata']['generated_by'] : [];
      $generated_by = $generated_by + [
        'source' => 'runtime_generated',
        'service' => 'canonical_generation',
      ];
      $generated_by['requested_by_uid'] = (int) ($context['requested_by_uid'] ?? 0);
      $generated_by['campaign_id'] = (int) ($context['campaign_id'] ?? 0);
      $generated_by['origin_room_id'] = (string) ($context['origin_room_id'] ?? '');
      $generated_by['generated_at'] = gmdate(DATE_RFC3339);
      $tags = array_values(array_unique(array_filter(array_map('strval', array_merge(
        (array) ($room['metadata']['tags'] ?? []),
        (array) ($criteria['tags'] ?? []),
        ['runtime_generated']
      )))));
      $metadata_result = $this->roomEditor->applyCommand($draft_id, [
        'command_id' => $this->uuid->generate(),
        'expected_revision' => (int) $draft['revision'],
        'type' => 'set_room_metadata',
        'payload' => [
          'changes' => [
            'metadata' => [
              'generated_by' => $generated_by,
              'runtime_generated' => TRUE,
              'tags' => $tags,
            ],
          ],
        ],
      ]);
      $draft = $metadata_result['draft'];
      $validation = $this->roomEditor->validateDraft($draft_id, 'publication');
      if (is_array($validation['errors'] ?? NULL) && $validation['errors'] !== []) {
        throw new RuntimeGenerationException('runtime_generation_failed', (array) $validation['errors']);
      }
      $room = is_array($draft['room'] ?? NULL) ? $draft['room'] : [];
      $version = $this->runtimeVersion((string) ($room['room_id'] ?? ''), $seed);
      $published = $this->roomEditor->publish($draft_id, [
        'expected_revision' => (int) $draft['revision'],
        'expected_base_version_id' => $draft['base_version_id'] ?? NULL,
        'version' => $version,
        'publication_note' => 'Runtime-generated canonical room pending curation.',
      ]);
      return [
        'room_version_id' => (string) $published['version_id'],
        'room_payload' => $room,
        'room_id' => (string) $published['room_id'],
        'version' => $version,
        'row' => [
          'version_id' => (string) $published['version_id'],
          'room_id' => (string) $published['room_id'],
          'version' => $version,
        ],
        'selection' => [
          'criteria' => $criteria,
          'generated' => TRUE,
        ],
      ];
    }
    catch (CanonicalGenerationException $e) {
      throw new RuntimeGenerationException('runtime_generation_failed', $e->getFindings(), $e->httpStatus(), $e);
    }
    catch (RuntimeGenerationException $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      throw new RuntimeGenerationException('runtime_generation_failed', [[
        'code' => 'runtime_generation_failed',
        'pointer' => '/canonical_room',
        'message' => $e->getMessage(),
        'severity' => 'error',
      ]], 500, $e);
    }
  }

  /**
   * @return array<string,mixed>
   */
  private function selectionCriteria(array $context): array {
    $theme = (string) ($context['theme'] ?? '');
    $terrain = (string) ($context['terrain_type'] ?? '');
    $room_type = $this->canonicalRoomType((string) ($context['room_type'] ?? 'chamber'));
    return [
      'tags' => array_values(array_unique(array_filter([$theme, $terrain, $room_type, (string) ($context['room_size'] ?? '')]))),
      'required_tags' => array_values(array_unique(array_filter(array_map('strval', (array) ($context['required_tags'] ?? []))))),
      'size_category' => $this->canonicalSizeCategory((string) ($context['room_size'] ?? 'medium')),
      'room_type' => $room_type,
      'terrain_type' => $terrain,
      'min_entry_ports' => 1,
      'min_exit_ports' => 1,
      'seed' => $this->runtimeRoomSeed($context),
    ];
  }

  private function runtimeRoomSeed(array $context): int {
    if (isset($context['seed']) && is_numeric($context['seed'])) {
      return max(0, min(2147483647, (int) $context['seed']));
    }
    return (int) (sprintf('%u', crc32(json_encode([
      $context['campaign_id'] ?? 0,
      $context['dungeon_id'] ?? '',
      $context['level_id'] ?? '',
      $context['room_index'] ?? 0,
      $context['theme'] ?? '',
      $context['room_type'] ?? '',
    ], JSON_UNESCAPED_SLASHES))) % 2147483647);
  }

  private function canonicalSizeCategory(string $size): string {
    return in_array($size, ['tiny', 'small', 'medium', 'large', 'huge', 'gargantuan'], TRUE) ? $size : 'medium';
  }

  private function canonicalRoomType(string $type): string {
    $type = strtolower(trim($type));
    return in_array($type, ['chamber', 'corridor', 'entrance', 'boss', 'treasure', 'trap', 'puzzle', 'social', 'wilderness'], TRUE) ? $type : 'chamber';
  }

  private function runtimeRoomPrompt(array $context): string {
    $prompt = trim((string) ($context['prompt'] ?? $context['description'] ?? ''));
    if ($prompt !== '') {
      return $prompt;
    }
    return sprintf(
      'Generate a %s %s canonical room for a %s dungeon at party level %d using %s terrain.',
      (string) ($context['room_size'] ?? 'medium'),
      (string) ($context['room_type'] ?? 'chamber'),
      (string) ($context['theme'] ?? 'dungeon'),
      (int) ($context['party_level'] ?? 1),
      (string) ($context['terrain_type'] ?? 'stone_floor')
    );
  }

  private function runtimeVersion(string $room_id, int $seed): string {
    return '0.0.1-runtime.' . gmdate('ymd') . '.' . substr(hash('sha256', $room_id . ':' . $seed . ':' . microtime(TRUE)), 0, 6);
  }

  private function buildRoomId(array $context): string {
    return sprintf(
      'room_%s_%s_%s',
      $this->normalizeRoomIdPart($context['dungeon_id'] ?? 0),
      $this->normalizeRoomIdPart($context['level_id'] ?? 0),
      $this->normalizeRoomIdPart($context['room_index'] ?? 0)
    );
  }

  private function normalizeRoomIdPart($value): string {
    $normalized = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim((string) $value));
    $normalized = trim((string) $normalized, '_-');
    return $normalized !== '' ? $normalized : '0';
  }

}
