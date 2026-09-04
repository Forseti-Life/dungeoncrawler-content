<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Planning tool: previews a command plan without mutating the draft.
 *
 * The projection runs through RoomEditorService::simulateCommands(), which
 * reuses the same mutation and validation code as execution. A plan that
 * previews cleanly is therefore the same plan apply_room_commands will run.
 */
final class PreviewCommandPlanTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'preview_command_plan',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Project a command plan against the draft and report the resulting shape and validation without saving.',
      FALSE,
      'RoomEditorService::simulateCommands()',
      [
        EditorGmToolDefinition::argument('commands', 'array', TRUE, 'Ordered list of {type, payload} command steps.'),
        EditorGmToolDefinition::argument('profile', 'string', FALSE, 'Validation profile: editing|preview|publication.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $commands = EditorGmToolContext::requireArray($arguments, 'commands');
    $profile = isset($arguments['profile']) ? (string) $arguments['profile'] : $context->validationProfile;
    if (!in_array($profile, ['editing', 'preview', 'publication'], TRUE)) {
      throw new \InvalidArgumentException('validation_profile_invalid');
    }

    $simulation = $context->roomEditor->simulateCommands($context->draftId, $commands, $profile);
    $before = $context->room();
    $after = $simulation['projected_room'];

    return [
      'base_revision' => $simulation['base_revision'],
      'projected_revision' => $simulation['projected_revision'],
      'applies_cleanly' => $simulation['applies_cleanly'],
      'steps' => $simulation['steps'],
      'validation' => $simulation['validation'],
      'projected_changes' => [
        'name' => ['before' => $before['name'] ?? NULL, 'after' => $after['name'] ?? NULL],
        'hex_count' => [
          'before' => count((array) ($before['hexes'] ?? [])),
          'after' => count((array) ($after['hexes'] ?? [])),
        ],
        'placement_count' => [
          'before' => count((array) ($before['placements'] ?? [])),
          'after' => count((array) ($after['placements'] ?? [])),
        ],
        'entry_port_count' => [
          'before' => count((array) ($before['entry_ports'] ?? [])),
          'after' => count((array) ($after['entry_ports'] ?? [])),
        ],
        'exit_port_count' => [
          'before' => count((array) ($before['exit_ports'] ?? [])),
          'after' => count((array) ($after['exit_ports'] ?? [])),
        ],
      ],
      'command_plan' => [
        'schema_version' => 'editor-gm-command-plan-v1',
        'draft_id' => $context->draftId,
        'base_revision' => $simulation['base_revision'],
        'steps' => $this->planSteps($commands),
      ],
    ];
  }

  /**
   * Normalizes the previewed commands back into plan steps for re-approval.
   */
  private function planSteps(array $commands): array {
    $steps = [];
    foreach (array_values($commands) as $index => $command) {
      if (!is_array($command)) {
        continue;
      }
      $steps[] = [
        'step' => $index + 1,
        'command_type' => (string) ($command['type'] ?? ''),
        'payload' => is_array($command['payload'] ?? NULL) ? $command['payload'] : [],
        'rationale' => (string) ($command['rationale'] ?? 'Previewed command step.'),
      ];
    }
    return $steps;
  }

}
