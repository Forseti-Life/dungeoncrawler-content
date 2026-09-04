<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmSurface;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Projects a command plan through the non-persisting simulation seam.
 */
final class PreviewDungeonCommandPlanTool implements EditorGmToolInterface {

  public function __construct(private readonly UuidInterface $uuid) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'preview_command_plan',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Project a command plan against the draft and report the resulting aggregate counts and validation without saving. The first rejected step stops the projection.',
      FALSE,
      'DungeonEditorService::simulateCommands()',
      [
        EditorGmToolDefinition::argument('commands', 'array', TRUE, 'Ordered list of {type, payload} command steps.'),
        EditorGmToolDefinition::argument('profile', 'string', FALSE, 'Validation profile: ' . implode('|', DungeonEditorGmSurface::VALIDATION_PROFILES) . '.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $commands = EditorGmToolContext::requireArray($arguments, 'commands');
    $profile = isset($arguments['profile']) ? (string) $arguments['profile'] : $context->validationProfile;
    if (!in_array($profile, DungeonEditorGmSurface::VALIDATION_PROFILES, TRUE)) {
      throw new \InvalidArgumentException('validation_profile_invalid');
    }
    if ($commands === [] || !array_is_list($commands)) {
      throw new \InvalidArgumentException('command_plan_empty');
    }

    $draft = $context->draft();
    $revision = (int) $draft['revision'];
    $envelopes = [];
    $steps = [];
    foreach ($commands as $index => $command) {
      if (!is_array($command)) {
        throw new \InvalidArgumentException(sprintf('command_step_invalid:%d', $index + 1));
      }
      $type = (string) ($command['type'] ?? '');
      if ($type === '') {
        throw new \InvalidArgumentException(sprintf('command_step_type_required:%d', $index + 1));
      }
      $payload = $command['payload'] ?? NULL;
      if (!is_array($payload)) {
        throw new \InvalidArgumentException(sprintf('command_step_payload_invalid:%d', $index + 1));
      }
      $envelopes[] = [
        'command_id' => $this->uuid->generate(),
        'expected_revision' => $revision + $index,
        'type' => $type,
        'payload' => $payload,
        'issued_at' => gmdate(DATE_RFC3339),
      ];
      $steps[] = DungeonPlanSteps::step($index + 1, $type, $payload, (string) ($command['rationale'] ?? 'Previewed command step.'));
    }

    $simulation = $context->dungeonEditor->simulateCommands($context->draftId, $envelopes, $profile);
    $before = $context->dungeon();
    $after = $simulation['dungeon'];

    return [
      'base_revision' => $simulation['base_revision'],
      'projected_revision' => $simulation['projected_revision'],
      'applies_cleanly' => $simulation['rejected'] === NULL,
      'applied' => $simulation['applied'],
      'rejected' => $simulation['rejected'],
      'validation' => $simulation['validation'],
      'projected_changes' => [
        'name' => ['before' => $before['name'] ?? NULL, 'after' => $after['name'] ?? NULL],
        'placement_count' => ['before' => count((array) ($before['room_placements'] ?? [])), 'after' => count((array) ($after['room_placements'] ?? []))],
        'port_link_count' => ['before' => count((array) ($before['port_links'] ?? [])), 'after' => count((array) ($after['port_links'] ?? []))],
        'region_count' => ['before' => count((array) ($before['regions'] ?? [])), 'after' => count((array) ($after['regions'] ?? []))],
      ],
      'command_plan' => DungeonPlanSteps::plan($context, $steps),
    ];
  }

}
