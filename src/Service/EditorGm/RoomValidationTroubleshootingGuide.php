<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * One current Room Editor problem and its native resolution path.
 */
final class RoomValidationTroubleshootingGuide {

  public static function packet(RoomEditorGmToolContext $context): array {
    $validation = $context->validation();
    $finding = self::selectedFinding($validation);
    if ($finding === []) {
      return [];
    }
    $code = (string) ($finding['code'] ?? '');
    $workflow = match ($code) {
      'room_has_no_placements' => 'room_contents',
      'room_entry_port_missing', 'room_default_entry_missing', 'room_default_entry_ambiguous' => 'room_ports',
      'placement_overlap' => 'room_layout',
      default => str_starts_with($code, 'schema_') || str_starts_with($code, 'room_') ? 'room_draft_command' : 'room_analysis',
    };
    return [
      'surface' => 'room_editor',
      'finding' => $finding,
      'workflow' => $workflow,
      'owner' => 'RoomEditorService',
      'required_tool' => match ($workflow) {
        'room_contents', 'room_ports', 'room_layout' => 'plan_room_commands',
        'room_draft_command' => 'apply_room_command',
        default => 'explain_validation_findings',
      },
      'object' => [
        'type' => 'room',
        'id' => $context->draft()['room_id'] ?? NULL,
      ],
      'scope_rule' => 'Resolve only this room finding. Do not change dungeon placement or storyline data.',
      'code_change_required' => FALSE,
    ];
  }

  private static function selectedFinding(array $validation): array {
    foreach (['error', 'warning'] as $severity) {
      foreach ((array) ($validation['findings'] ?? []) as $finding) {
        if (is_array($finding) && ($finding['severity'] ?? '') === $severity) {
          return $finding;
        }
      }
    }
    return [];
  }

}
