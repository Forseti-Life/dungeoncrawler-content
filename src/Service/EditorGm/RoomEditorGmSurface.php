<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ApplyRoomCommandsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\CheckPublicationReadinessTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\DiffDraftAgainstPublishedTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ExplainValidationFindingsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\InspectCatalogEntryTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ListCatalogDefinitionsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\LoadCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\LoadDraftSnapshotTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\LoadPublishedSnapshotTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PlanCanonicalDefinitionPatchTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PlanRoomCommandsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PreviewCommandPlanTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PreviewPublicationPayloadTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PublishRoomVersionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\SummarizeDefinitionUsageTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\SummarizeRoomTopologyTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\UpdateCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ValidateDraftTool;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;

/**
 * Room Editor GM surface: every read and write resolves through
 * RoomEditorService; definitions resolve through CanonicalDefinitionService.
 */
final class RoomEditorGmSurface implements EditorGmSurfaceInterface {

  public const ID = 'room_editor';

  public const VALIDATION_PROFILES = ['editing', 'preview', 'publication'];

  /**
   * Room Editor command types the harness is allowed to plan and execute.
   *
   * Mirrors the room_editor_command contract so assistant parity with manual
   * editing is verifiable rather than assumed.
   */
  public const SUPPORTED_COMMAND_TYPES = [
    'set_room_metadata',
    'add_hex',
    'remove_hex',
    'set_hex_terrain',
    'set_hex_elevation',
    'place_object',
    'move_object',
    'rotate_object',
    'update_object_overrides',
    'duplicate_object',
    'remove_object',
    'add_entry_port',
    'update_entry_port',
    'remove_entry_port',
    'add_exit_port',
    'update_exit_port',
    'remove_exit_port',
    'undo',
    'redo',
  ];

  private readonly EditorGmToolRegistry $registry;
  private readonly RoomEditorGmContextAssembler $assembler;

  public function __construct(
    private readonly RoomEditorService $roomEditor,
    private readonly CanonicalDefinitionService $definitions,
    EditorGmIntentParser $intentParser,
    UuidInterface $uuid,
  ) {
    $this->registry = new EditorGmToolRegistry([
      new LoadDraftSnapshotTool(),
      new LoadPublishedSnapshotTool(),
      new SummarizeRoomTopologyTool(),
      new ListCatalogDefinitionsTool(),
      new InspectCatalogEntryTool(),
      new ValidateDraftTool(),
      new ExplainValidationFindingsTool(),
      new CheckPublicationReadinessTool(),
      new DiffDraftAgainstPublishedTool(),
      new LoadCanonicalDefinitionTool(),
      new SummarizeDefinitionUsageTool(),
      new UpdateCanonicalDefinitionTool(),
      new PlanRoomCommandsTool(),
      new PreviewCommandPlanTool(),
      new PlanCanonicalDefinitionPatchTool(),
      new ApplyRoomCommandsTool($uuid),
      new PreviewPublicationPayloadTool(),
      new PublishRoomVersionTool(),
    ], self::SUPPORTED_COMMAND_TYPES, 'room_editor_command.schema.json');
    $this->assembler = new RoomEditorGmContextAssembler($this->registry, $intentParser);
  }

  public function id(): string {
    return self::ID;
  }

  public function label(): string {
    return 'Room Editor';
  }

  public function registry(): EditorGmToolRegistry {
    return $this->registry;
  }

  public function assembler(): EditorGmContextAssemblerInterface {
    return $this->assembler;
  }

  public function supportedCommandTypes(): array {
    return self::SUPPORTED_COMMAND_TYPES;
  }

  public function validationProfiles(): array {
    return self::VALIDATION_PROFILES;
  }

  public function createContext(string $draft_id, string $profile): EditorGmToolContext {
    return new RoomEditorGmToolContext($draft_id, $profile, $this->roomEditor, $this->definitions);
  }

}
