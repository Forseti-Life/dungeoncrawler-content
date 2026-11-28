<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\BuildSiteFromStoryBriefTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\ListUnattachedDungeonsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\LoadStoryBriefTool;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomBuildService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\ApplyDungeonCommandsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\DescribePublicationReadinessTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\DelegateRoomEditorTaskTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\ExplainDungeonValidationFindingsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\GenerateDungeonLayoutTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\InspectRoomVersionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\ListPublishedRoomsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\LoadDungeonDraftTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\PlanDungeonCommandsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\BuildDistrictStreetTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\MoveRoomPortTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\PaintDistrictTerrainTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\CarveDistrictCorridorTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\RingDistrictTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\PrepareDistrictNavigationTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\ResizeRoomTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\InspectDistrictTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\PlanDistrictBuildingTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\PlanDistrictBuildingsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\PlanPortLinksTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\PlanRoomPlacementTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\PreviewDungeonCommandPlanTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\PublishDungeonVersionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\SummarizeLevelTopologyTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\ValidateDungeonTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\RevalidateChildRoomsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\InspectCatalogEntryTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ListCatalogDefinitionsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\LoadCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PlanCanonicalDefinitionPatchTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\UpdateCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\DescribeValidationTroubleshootingTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\RouteToEditorTool;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService;

/**
 * Dungeon Editor GM surface (20-gm-harness-extension.md §dungeon_editor).
 *
 * Every read and write resolves through DungeonEditorService; definitions
 * resolve through CanonicalDefinitionService. The five definition tools are
 * the same classes the Room Editor surface registers, because that authority
 * is surface-independent.
 *
 * Publication readiness and publish execute through DungeonEditorService and
 * expose the same findings and hard blockers as the browser route.
 */
final class DungeonEditorGmSurface implements EditorGmSurfaceInterface {

  public const ID = 'dungeon_editor';

  public const VALIDATION_PROFILES = ['editing', 'publication'];

  /**
   * Mirrors DungeonEditorService::SUPPORTED_COMMANDS; the contract test pins
   * that every one of these appears in this surface's planning toolset.
   */
  public const SUPPORTED_COMMAND_TYPES = DungeonEditorService::SUPPORTED_COMMANDS;

  private readonly EditorGmToolRegistry $registry;
  private readonly DungeonEditorGmContextAssembler $assembler;

  public function __construct(
    private readonly DungeonEditorService $dungeonEditor,
    private readonly CanonicalDefinitionService $definitions,
    EditorGmIntentParser $intentParser,
    UuidInterface $uuid,
    ?EditorCanonicalGenerationPlanService $generation = NULL,
    ?StorylineAuthoringDraftService $storylines = NULL,
    ?CanonicalRoomBuildService $roomBuild = NULL,
    ?EditorSuiteService $suite = NULL,
  ) {
    $tools = [
      new LoadDungeonDraftTool(),
      new DescribeValidationTroubleshootingTool(),
      ...($suite === NULL ? [] : [new RouteToEditorTool($suite)]),
      new DelegateRoomEditorTaskTool(),
      new SummarizeLevelTopologyTool(),
      new ListPublishedRoomsTool(),
      new InspectRoomVersionTool(),
      new ListCatalogDefinitionsTool(),
      new InspectCatalogEntryTool(),
      new ValidateDungeonTool(),
      new DescribePublicationReadinessTool(),
      new ExplainDungeonValidationFindingsTool(),
      new RevalidateChildRoomsTool(),
      new LoadCanonicalDefinitionTool(),
      new UpdateCanonicalDefinitionTool(),
      new PlanDungeonCommandsTool(),
      new PlanRoomPlacementTool(),
      new PlanPortLinksTool(),
      new PlanDistrictBuildingTool($uuid),
      new PlanDistrictBuildingsTool($uuid),
      new InspectDistrictTool(),
      new PreviewDungeonCommandPlanTool($uuid),
      new PlanCanonicalDefinitionPatchTool(),
      new ApplyDungeonCommandsTool($uuid),
      new PublishDungeonVersionTool(),
    ];
    if ($storylines !== NULL) {
      $tools[] = new ListUnattachedDungeonsTool();
      $tools[] = new LoadStoryBriefTool($storylines, 'dungeon', static fn(EditorGmToolContext $context): string => (string) (
        DungeonEditorGmToolContext::of($context)->draft()['dungeon_id'] ?? ''
      ));
    }
    if ($storylines !== NULL && $roomBuild !== NULL && $generation !== NULL) {
      $tools[] = new BuildSiteFromStoryBriefTool($storylines, $roomBuild, $generation, $uuid);
    }
    if ($roomBuild !== NULL) {
      $tools[] = new BuildDistrictStreetTool($roomBuild);
      $tools[] = new ResizeRoomTool($roomBuild);
      $tools[] = new MoveRoomPortTool($roomBuild);
      $tools[] = new PaintDistrictTerrainTool($roomBuild);
      $tools[] = new CarveDistrictCorridorTool($roomBuild);
      $tools[] = new PrepareDistrictNavigationTool($roomBuild);
      $tools[] = new RingDistrictTool($roomBuild);
    }
    if ($generation !== NULL) {
      array_splice($tools, 11, 0, [new GenerateDungeonLayoutTool($generation)]);
    }
    $this->registry = new EditorGmToolRegistry($tools, self::SUPPORTED_COMMAND_TYPES, DungeonEditorService::COMMAND_SCHEMA_FILE);
    $this->assembler = new DungeonEditorGmContextAssembler($this->registry, $intentParser);
  }

  public function id(): string {
    return self::ID;
  }

  public function label(): string {
    return 'Dungeon Editor';
  }

  public function validationForDungeon(string $dungeon_id, string $profile): array {
    return $this->dungeonEditor->validationStatus($dungeon_id, $profile);
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
    if ($draft_id === NULL) {
      throw new \LogicException('editor_gm_draft_required:dungeon_editor');
    }
    return new DungeonEditorGmToolContext($draft_id, $profile, $this->dungeonEditor, $this->definitions);
  }

}
