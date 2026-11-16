<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;

final class RoomDraftLifecycleTool implements EditorGmToolInterface {

  public function __construct(private readonly string $name) {
    if (!in_array($name, ['list_room_drafts', 'create_room_draft', 'load_room_draft', 'abandon_room_draft', 'list_room_command_history'], TRUE)) {
      throw new \LogicException('room_lifecycle_tool_invalid:' . $name);
    }
  }

  public function definition(): EditorGmToolDefinition {
    $mutating = in_array($this->name, ['create_room_draft', 'abandon_room_draft'], TRUE);
    $arguments = match ($this->name) {
      'create_room_draft' => [
        EditorGmToolDefinition::argument('room_id', 'string', FALSE, 'Existing canonical room to edit; omit for a new room. Never ask the author to invent an ID.'),
        EditorGmToolDefinition::argument('name', 'string', FALSE, 'Name for a new room; identity is generated internally.'),
      ],
      'load_room_draft' => [
        EditorGmToolDefinition::argument('draft_id', 'string', TRUE, 'Draft selected from list_room_drafts; must be accessible and active.'),
      ],
      'abandon_room_draft' => [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Current active draft revision. Preserves history; cannot abandon a published draft.'),
      ],
      'list_room_command_history' => [
        EditorGmToolDefinition::argument('limit', 'integer', FALSE, '1..100 commands, newest first; default 50.'),
        EditorGmToolDefinition::argument('before_revision', 'integer', FALSE, 'Exclusive revision cursor for older commands.'),
      ],
      default => [],
    };
    $summary = match ($this->name) {
      'list_room_drafts' => 'List active room drafts the current author may access.',
      'create_room_draft' => 'Start a new room or resume a canonical room draft and switch the editor to it.',
      'load_room_draft' => 'Switch the editor to an accessible active draft.',
      'abandon_room_draft' => 'Abandon the active draft without deleting its payload, history or published room.',
      'list_room_command_history' => 'Inspect command IDs and revisions for undo/redo and audit.',
    };
    return new EditorGmToolDefinition(
      $this->name,
      $mutating ? EditorGmToolDefinition::FAMILY_EXECUTION : EditorGmToolDefinition::FAMILY_CONTEXT,
      $summary,
      $mutating,
      'RoomEditorService',
      $arguments,
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = RoomEditorGmToolContext::of($context);
    if ($this->name === 'list_room_drafts') {
      return ['drafts' => $context->roomEditor->listAccessibleDrafts(), 'rooms' => $context->roomEditor->listRooms()];
    }
    if ($this->name === 'list_room_command_history') {
      return $context->roomEditor->commandHistory(
        $context->draft()['draft_id'],
        array_key_exists('limit', $arguments) ? EditorGmToolContext::requireInt($arguments, 'limit') : 50,
        array_key_exists('before_revision', $arguments) ? EditorGmToolContext::requireInt($arguments, 'before_revision') : NULL,
      );
    }
    if ($this->name === 'abandon_room_draft') {
      $draft = $context->roomEditor->abandonDraft($context->draft()['draft_id'], EditorGmToolContext::requireInt($arguments, 'expected_revision'));
      $context->invalidate();
      return ['draft' => $draft, 'active_draft_id' => NULL];
    }
    $draft = $this->name === 'create_room_draft'
      ? $context->roomEditor->createDraft(
        array_key_exists('room_id', $arguments) ? EditorGmToolContext::requireString($arguments, 'room_id') : NULL,
        array_key_exists('name', $arguments) ? EditorGmToolContext::requireString($arguments, 'name') : NULL,
      )
      : $context->roomEditor->getDraft(EditorGmToolContext::requireString($arguments, 'draft_id'));
    if ($draft['status'] !== 'active') {
      throw new \DomainException('draft_not_active');
    }
    return ['draft' => $draft, 'active_draft_id' => $draft['draft_id']];
  }
}
