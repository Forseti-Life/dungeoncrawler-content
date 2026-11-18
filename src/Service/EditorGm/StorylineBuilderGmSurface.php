<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\CreateCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\DescribeCanonicalDefinitionSchemaTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ExplainValidationFindingsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\GenerateCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\InspectCatalogEntryTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ListCatalogDefinitionsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\LoadCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PlanCanonicalDefinitionPatchTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\ListDungeonsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\FindDungeonsUsingRoomTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\ListRoomsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\RouteToEditorTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\UpdateCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ValidateCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ValidateDraftTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\ApplyStorylineDraftOperationsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\ApplyStorylineRemediationTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\CheckStorylineCreationReadinessTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\DescribeStorylineAuthoringContractTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\GetStorylineDraftStatusTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\BindLibraryCharactersTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\FindExistingInstitutionsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\FindLibraryCharactersTool;
use Drupal\dungeoncrawler_content\Service\StorylineInstitutionCatalog;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\LoadExistingQuestsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\GetStorylineBranchingTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\GetStorylineSynopsisTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\LoadStorylineDraftSnapshotTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineDraftOperationsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineRemediationTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PreviewStorylineDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PreviewStorylineNarrativeAnalysisTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PublishStorylineDraftTool;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Actor\DefinitionActorTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\StorylineActorSheetTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\StorylineWorkTool;
use Drupal\dungeoncrawler_content\Service\StorylineActorSheetService;
use Drupal\dungeoncrawler_content\Service\CanonicalQuestTemplateService;

/**
 * Storyline Builder GM surface with draft-scoped storyline authoring tools.
 */
final class StorylineBuilderGmSurface implements EditorGmSurfaceInterface {

  public const ID = 'storyline_builder';

  public const VALIDATION_PROFILES = ['editing'];

  private readonly EditorGmToolRegistry $registry;
  private readonly StorylineBuilderGmContextAssembler $assembler;

  public function __construct(
    private readonly StorylineAuthoringDraftService $drafts,
    private readonly CanonicalDefinitionService $definitions,
    private readonly EditorSuiteService $suite,
    EditorGmIntentParser $intentParser,
    CanonicalDefinitionGenerationService $definitionGeneration,
    EditorCanonicalGenerationPlanService $roomGeneration,
    RoomEditorService $roomEditor,
    UuidInterface $uuid,
    StorylineActorSheetService $actorSheets,
    CanonicalQuestTemplateService $questTemplates,
    StorylineGmWorkService $work,
  ) {
    $remediationPlanner = new PlanStorylineRemediationTool($questTemplates);
    $remediationExecutor = new ApplyStorylineRemediationTool($definitionGeneration, $questTemplates);
    $this->registry = new EditorGmToolRegistry([
      new DescribeStorylineAuthoringContractTool(),
      new LoadStorylineDraftSnapshotTool(),
      new GetStorylineDraftStatusTool(),
      new GetStorylineBranchingTool(),
      new GetStorylineSynopsisTool(),
      new FindLibraryCharactersTool(),
      new BindLibraryCharactersTool(),
      new FindExistingInstitutionsTool(new StorylineInstitutionCatalog(\Drupal::database())),
      new LoadExistingQuestsTool($questTemplates),
      new StorylineActorSheetTool($actorSheets, FALSE),
      new StorylineActorSheetTool($actorSheets, TRUE),
      new PreviewStorylineDefinitionTool(),
      new ListCatalogDefinitionsTool(),
      new InspectCatalogEntryTool(),
      new DescribeCanonicalDefinitionSchemaTool(),
      new LoadCanonicalDefinitionTool(),
      new ValidateCanonicalDefinitionTool(),
      // A storyline's characters are created by the sheet build only.
      new GenerateCanonicalDefinitionTool($roomGeneration, ['actor']),
      new PlanCanonicalDefinitionPatchTool(),
      new CreateCanonicalDefinitionTool(['actor']),
      new UpdateCanonicalDefinitionTool(),
      new ListRoomsTool($this->suite),
      new ListDungeonsTool($this->suite),
      new FindDungeonsUsingRoomTool($this->suite),
      new RouteToEditorTool($this->suite),
      new PlanStorylineDraftOperationsTool(),
      new StorylineWorkTool($work, FALSE),
      $remediationPlanner,
      new ValidateDraftTool(),
      new ExplainValidationFindingsTool(),
      new PreviewStorylineNarrativeAnalysisTool(),
      new CheckStorylineCreationReadinessTool(),
      new ApplyStorylineDraftOperationsTool(),
      new StorylineWorkTool($work, TRUE),
      $remediationExecutor,
      new PublishStorylineDraftTool($remediationPlanner, $remediationExecutor),
      new DefinitionActorTool('inspect_actor'),
      new DefinitionActorTool('plan_actor_changes'),
      new DefinitionActorTool('apply_actor_changes'),
    ], [], NULL);
    $this->assembler = new StorylineBuilderGmContextAssembler($this->registry, $intentParser, $remediationPlanner, $work);
  }

  public function id(): string {
    return self::ID;
  }

  public function label(): string {
    return 'Storyline Builder';
  }

  public function registry(): EditorGmToolRegistry {
    return $this->registry;
  }

  public function assembler(): EditorGmContextAssemblerInterface {
    return $this->assembler;
  }

  public function supportedCommandTypes(): array {
    return [];
  }

  public function validationProfiles(): array {
    return self::VALIDATION_PROFILES;
  }

  public function scope(): string {
    return self::SCOPE_DRAFT;
  }

  public function createContext(?string $draft_id, string $profile, array $scope = []): EditorGmToolContext {
    if ($draft_id === NULL || trim($draft_id) === '') {
      throw new \LogicException('editor_gm_draft_required:storyline_builder');
    }
    return new StorylineBuilderGmToolContext($draft_id, $profile, $this->drafts, $this->definitions);
  }

}
