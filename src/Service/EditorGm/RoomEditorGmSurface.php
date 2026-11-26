<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ApplyRoomCommandsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ApplyRoomCommandTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\EditSelectionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PlaceShapeTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PlacementAnalysisTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PlacementGapsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\RoomDraftLifecycleTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\DefinitionLifecycleTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\CheckPublicationReadinessTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\CreateCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\DescribeCanonicalDefinitionSchemaTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\DiffDraftAgainstPublishedTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ExplainValidationFindingsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\GenerateCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\GenerateRoomLayoutTool;
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
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\DescribeValidationTroubleshootingTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ValidateCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ValidateDraftTool;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\LoadStoryBriefTool;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;

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
    'set_hexes_terrain',
    'set_hex_elevation',
    'place_object',
    'move_object',
    'rotate_object',
    'update_object_overrides',
    'duplicate_object',
    'remove_object',
    'place_objects_bulk',
    'move_objects_bulk',
    'remove_objects_bulk',
    'replace_objects_bulk',
    'dismiss_placement_gaps',
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
    ?EditorCanonicalGenerationPlanService $generation = NULL,
    ?StorylineAuthoringDraftService $storylines = NULL,
  ) {
    $command_executor = new ApplyRoomCommandsTool($uuid);
    $tools = [
      new LoadDraftSnapshotTool(),
      new DescribeValidationTroubleshootingTool(),
      new LoadPublishedSnapshotTool(),
      new SummarizeRoomTopologyTool(),
      new ListCatalogDefinitionsTool(),
      new InspectCatalogEntryTool(),
      new ValidateDraftTool(),
      new ExplainValidationFindingsTool(),
      new CheckPublicationReadinessTool(),
      new DiffDraftAgainstPublishedTool(),
      new DescribeCanonicalDefinitionSchemaTool(),
      new LoadCanonicalDefinitionTool(),
      new ValidateCanonicalDefinitionTool(),
      new SummarizeDefinitionUsageTool(),
      new CreateCanonicalDefinitionTool(),
      new UpdateCanonicalDefinitionTool(),
      new PlanRoomCommandsTool(),
      new PreviewCommandPlanTool(),
      new PlanCanonicalDefinitionPatchTool(),
      $command_executor,
      new PreviewPublicationPayloadTool(),
      new PublishRoomVersionTool(),
    ];
    if ($storylines !== NULL) {
      $tools[] = new LoadStoryBriefTool($storylines, 'room', static fn(EditorGmToolContext $context): string => RoomEditorGmToolContext::of($context)->roomId());
    }
    if ($generation !== NULL) {
      array_splice($tools, 15, 0, [
        new GenerateRoomLayoutTool($generation),
        new GenerateCanonicalDefinitionTool($generation),
      ]);
    }
    foreach (['list_room_drafts', 'create_room_draft', 'load_room_draft', 'abandon_room_draft', 'list_room_command_history'] as $name) {
      $tools[] = new RoomDraftLifecycleTool($name);
    }
    foreach (PlaceShapeTool::NAMES as $name) {
      $tools[] = new PlaceShapeTool($name);
    }
    foreach (PlacementAnalysisTool::NAMES as $name) {
      $tools[] = new PlacementAnalysisTool($name);
    }
    foreach (EditSelectionTool::NAMES as $name) {
      $tools[] = new EditSelectionTool($name);
    }
    foreach (PlacementGapsTool::NAMES as $name) {
      $tools[] = new PlacementGapsTool($name);
    }
    $tools[] = new ApplyRoomCommandTool($command_executor);
    foreach (['inspect_definition_lifecycle', 'retire_canonical_definition', 'restore_canonical_definition'] as $name) {
      $tools[] = new DefinitionLifecycleTool($name);
    }
    $this->registry = new EditorGmToolRegistry($tools, self::SUPPORTED_COMMAND_TYPES, 'room_editor_command.schema.json');
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

  public function scope(): string {
    return self::SCOPE_DRAFT;
  }

  public function createContext(?string $draft_id, string $profile, array $scope = []): EditorGmToolContext {
    return new RoomEditorGmToolContext($draft_id, $profile, $this->roomEditor, $this->definitions);
  }

}
