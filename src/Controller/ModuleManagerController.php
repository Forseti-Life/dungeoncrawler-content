<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\DungeonEditorFindingsInterface;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionArchiveInspectorService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleArchiveExplorerService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionCatalogService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionGapAnalysisService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionPlanService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReleaseService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReadException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Module Manager: discovery + read-only intake preview.
 */
class ModuleManagerController extends ControllerBase {

  public function __construct(
    private readonly ModuleConversionCatalogService $catalog,
    private readonly ModuleConversionArchiveInspectorService $archiveInspector,
    private readonly ModuleArchiveExplorerService $explorer,
    private readonly ModuleConversionPlanService $planService,
    private readonly ModuleConversionReleaseService $releaseService,
    private readonly CsrfTokenGenerator $csrfToken,
    private readonly ModuleConversionGapAnalysisService $gapAnalysis,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.module_conversion.catalog'),
      $container->get('dungeoncrawler_content.module_conversion.archive_inspector'),
      $container->get('dungeoncrawler_content.module_conversion.explorer'),
      $container->get('dungeoncrawler_content.module_conversion.plan'),
      $container->get('dungeoncrawler_content.module_conversion.release'),
      $container->get('csrf_token'),
      $container->get('dungeoncrawler_content.module_conversion.gap_analysis'),
    );
  }

  /**
   * Render the Module Manager shell.
   */
  public function page(): array {
    return [
      '#theme' => 'module_manager',
      '#attached' => [
        'library' => ['dungeoncrawler_content/module-manager'],
        'drupalSettings' => [
          'dungeoncrawlerContent' => [
            'moduleManager' => [
              'csrfToken' => $this->csrfToken->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY),
              'urls' => [
                'catalog' => Url::fromRoute('dungeoncrawler_content.module_manager_catalog')->toString(),
                'localArchives' => Url::fromRoute('dungeoncrawler_content.module_manager_local_archives')->toString(),
                'localArchiveOverview' => Url::fromRoute('dungeoncrawler_content.module_manager_local_archive_overview')->toString(),
                'inspect' => Url::fromRoute('dungeoncrawler_content.module_manager_inspect')->toString(),
                'generateDefinition' => Url::fromRoute('dungeoncrawler_content.module_manager_generate_definition')->toString(),
                'createDefinition' => Url::fromUri('base:/api/canonical-definitions/__family__')->toString(),
                'bindDefinition' => Url::fromUri('base:/api/module-manager/releases/__release_id__/bind-definition')->toString(),
                'actorPlacementReview' => Url::fromUri('base:/api/module-manager/releases/__release_id__/actor-placement-review')->toString(),
                'releases' => Url::fromRoute('dungeoncrawler_content.module_manager_releases')->toString(),
                'releaseCoverage' => Url::fromUri('base:/api/module-manager/releases/__release_id__/coverage')->toString(),
                'releaseValidation' => Url::fromUri('base:/api/module-manager/releases/__release_id__/validation')->toString(),
                'releaseReceipts' => Url::fromUri('base:/api/module-manager/releases/__release_id__/receipts')->toString(),
                'releasePublish' => Url::fromUri('base:/api/module-manager/releases/__release_id__/publish')->toString(),
                'releaseExecution' => Url::fromUri('base:/api/module-manager/releases/__release_id__/execution')->toString(),
                'releaseExecutionRun' => Url::fromUri('base:/api/module-manager/releases/__release_id__/execution/run')->toString(),
                'moduleExplorerPage' => Url::fromRoute('dungeoncrawler_content.module_explorer')->toString(),
              ],
            ],
          ],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Read-only discovery catalog.
   */
  public function catalog(): JsonResponse {
    try {
      return new JsonResponse([
        'data' => [
          'schema_version' => 'module-manager-catalog-v1',
          'generated_at' => gmdate(DATE_RFC3339),
          'references' => $this->catalog->listAdventureReferences(),
        ],
      ]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Read-only list of inspectable local module archives.
   */
  public function localArchives(): JsonResponse {
    try {
      $archives = [];
      foreach ($this->latestArchives($this->explorer->archives()) as $archive) {
        $name = (string) ($archive['name'] ?? '');
        $archives[] = [
          'name' => $name,
          'bytes' => (int) ($archive['bytes'] ?? 0),
          'sha256' => (string) ($archive['sha256'] ?? ''),
          'archive_path' => $this->explorer->archivePath($name),
        ];
      }
      return new JsonResponse([
        'data' => [
          'schema_version' => 'module-manager-local-archives-v1',
          'archives' => $archives,
        ],
      ]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Read-only per-module readiness overview for local archives.
   */
  public function localArchiveOverview(Request $request): JsonResponse {
    try {
      $limit = max(1, min(25, (int) $request->query->get('limit', 12)));
      $archives = $this->latestArchives($this->explorer->archives());
      $overview = [];
      foreach (array_slice($archives, 0, $limit) as $archive) {
        $name = (string) ($archive['name'] ?? '');
        $path = $this->explorer->archivePath($name);
        try {
          $inventory = $this->archiveInspector->inspectArchive($path);
          $overview[] = [
            'name' => $name,
            'archive_path' => $path,
            'bytes' => (int) ($archive['bytes'] ?? 0),
            'sha256' => (string) ($archive['sha256'] ?? ''),
            'manifest' => [
              'id' => (string) ($inventory['manifest']['id'] ?? ''),
              'title' => (string) ($inventory['manifest']['title'] ?? ''),
              'version' => (string) ($inventory['manifest']['version'] ?? ''),
            ],
            'analysis' => $this->moduleStateAnalysisFromInventory($inventory),
          ];
        }
        catch (\Throwable $exception) {
          $overview[] = [
            'name' => $name,
            'archive_path' => $path,
            'bytes' => (int) ($archive['bytes'] ?? 0),
            'sha256' => (string) ($archive['sha256'] ?? ''),
            'error' => [
              'code' => $exception->getMessage() ?: 'module_overview_error',
            ],
          ];
        }
      }
      return new JsonResponse([
        'data' => [
          'schema_version' => 'module-manager-local-archive-overview-v1',
          'overview' => $overview,
        ],
      ]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Inspect one archive and return inventory + deterministic dry-run plan.
   */
  public function inspect(Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      if (array_key_exists('mapping', $body) && $body['mapping'] !== NULL && $body['mapping'] !== []) {
        throw new ModuleConversionReadException('module_release_mapping_requires_native_receipts', [[
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_release_mapping_requires_native_receipts',
          'pointer' => '/mapping',
          'message' => 'Intake cannot create or validate native conversion mappings.',
          'context' => [],
        ]], 422);
      }
      $archive_path = trim((string) ($body['archive_path'] ?? ''));
      if ($archive_path === '') {
        throw new ModuleConversionReadException('module_conversion_archive_path_required', [[
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_conversion_archive_path_required',
          'pointer' => '/archive_path',
          'message' => 'archive_path is required.',
          'context' => [],
        ]], 400);
      }
      if (isset($body['limits']) && !is_array($body['limits'])) {
        throw new \InvalidArgumentException('module_conversion_limits_invalid');
      }
      if (isset($body['decisions']) && !is_array($body['decisions'])) {
        throw new \InvalidArgumentException('module_conversion_decisions_invalid');
      }
      $limits = $body['limits'] ?? [];
      $inventory = $this->archiveInspector->inspectArchive($archive_path, $limits);

      $expected_sha256 = strtolower(trim((string) ($body['expected_sha256'] ?? '')));
      if ($expected_sha256 !== '' && strtolower((string) ($inventory['archive']['sha256'] ?? '')) !== $expected_sha256) {
        throw new ModuleConversionReadException('module_conversion_archive_hash_mismatch', [[
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_conversion_archive_hash_mismatch',
          'pointer' => '/expected_sha256',
          'message' => 'Expected archive SHA-256 does not match inspected bytes.',
          'context' => [
            'expected_sha256' => $expected_sha256,
            'actual_sha256' => (string) ($inventory['archive']['sha256'] ?? ''),
          ],
        ]]);
      }

      $decisions = $body['decisions'] ?? [];
      $plan = $this->planService->buildPlan($inventory, $decisions);
      $generationGaps = $this->collectGenerationGaps($plan);
      $expected_plan_hash = strtolower(trim((string) ($body['expected_plan_hash'] ?? '')));
      $register_release = (bool) ($body['register_release'] ?? FALSE);
      if ($register_release && $expected_plan_hash === '') {
        throw new ModuleConversionReadException('module_conversion_plan_hash_required_for_release', [[
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_conversion_plan_hash_required_for_release',
          'pointer' => '/expected_plan_hash',
          'message' => 'expected_plan_hash is required when register_release=true to prevent stale release registration.',
          'context' => [],
        ]], 422);
      }
      if ($register_release && $expected_sha256 === '') {
        throw new ModuleConversionReadException('module_conversion_archive_hash_required_for_release', [[
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_conversion_archive_hash_required_for_release',
          'pointer' => '/expected_sha256',
          'message' => 'expected_sha256 is required when register_release=true to pin release bytes.',
          'context' => [],
        ]], 422);
      }
      if ($expected_plan_hash !== '' && strtolower((string) ($plan['plan_hash'] ?? '')) !== $expected_plan_hash) {
        throw new ModuleConversionReadException('module_conversion_plan_hash_mismatch', [[
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_conversion_plan_hash_mismatch',
          'pointer' => '/expected_plan_hash',
          'message' => 'Expected plan hash does not match the deterministic plan for the current bytes and decisions.',
          'context' => [
            'expected_plan_hash' => $expected_plan_hash,
            'actual_plan_hash' => (string) ($plan['plan_hash'] ?? ''),
          ],
        ]], 409);
      }
      $release = NULL;
      if ($register_release) {
        $mapping = is_array($body['mapping'] ?? NULL) ? $body['mapping'] : NULL;
        $overrides = is_array($body['release'] ?? NULL) ? $body['release'] : [];
        $release = $this->releaseService->registerRelease($inventory, $plan, $mapping, (int) $this->currentUser()->id(), $overrides);
      }
      return new JsonResponse([
        'data' => [
          'inventory' => $inventory,
          'plan' => $plan,
          'generation_gaps' => $generationGaps,
          'module_state_analysis' => $this->moduleStateAnalysis($inventory, $plan, $generationGaps),
          'release' => $release,
        ],
      ]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Request one validated original definition proposal; never publish it.
   */
  public function generateDefinition(Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $archive_path = $body['archive_path'] ?? NULL;
      $expected_sha256 = $body['expected_sha256'] ?? NULL;
      $expected_plan_hash = $body['expected_plan_hash'] ?? NULL;
      $resource_id = $body['resource_id'] ?? NULL;
      if (!is_string($archive_path) || trim($archive_path) === ''
        || !is_string($expected_sha256) || !preg_match('/^[a-f0-9]{64}$/D', $expected_sha256)
        || !is_string($expected_plan_hash) || !preg_match('/^[a-f0-9]{64}$/D', $expected_plan_hash)
        || !is_string($resource_id) || $resource_id === '') {
        throw new \InvalidArgumentException('module_conversion_generation_identity_invalid');
      }
      if (isset($body['decisions']) && !is_array($body['decisions'])) {
        throw new \InvalidArgumentException('module_conversion_decisions_invalid');
      }
      $inventory = $this->archiveInspector->inspectArchive($archive_path);
      if (!hash_equals($expected_sha256, (string) ($inventory['archive']['sha256'] ?? ''))) {
        throw new \InvalidArgumentException('module_conversion_archive_hash_mismatch');
      }
      $plan = $this->planService->buildPlan($inventory, $body['decisions'] ?? []);
      if (!hash_equals($expected_plan_hash, $plan['plan_hash'])) {
        throw new \InvalidArgumentException('module_conversion_plan_hash_mismatch');
      }
      $resources = $plan['resources'];
      $resource = NULL;
      foreach ($resources as $candidate) {
        if ($candidate['resource_id'] === $resource_id) {
          $resource = $candidate;
          break;
        }
      }
      if ($resource === NULL) {
        throw new \OutOfBoundsException('module_conversion_generation_resource_not_found');
      }
      $seed = $body['seed'] ?? hexdec(substr(hash('sha256', $expected_sha256 . ':' . $resource_id), 0, 7));
      if (!is_int($seed) || $seed < 0 || $seed > 2147483647) {
        throw new \InvalidArgumentException('module_conversion_generation_seed_invalid');
      }
      $analysis = $this->gapAnalysis->analyzeResource($resource);
      if (!$analysis['proposal_available']) {
        throw new ModuleConversionReadException('module_conversion_definition_generation_unavailable', [[
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_conversion_definition_generation_unavailable',
          'pointer' => '/resource_id',
          'message' => 'This resource requires manual review or room generation; no definition proposal is available.',
          'context' => ['resource_id' => $resource_id, 'generation_path' => $analysis['generation_path']],
        ]], 422);
      }
      return new JsonResponse([
        'data' => $this->gapAnalysis->generateDefinitionProposal($resource, $seed),
      ]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Stored module conversion releases.
   */
  public function releases(Request $request): JsonResponse {
    try {
      $limit = (int) $request->query->get('limit', 100);
      return new JsonResponse([
        'data' => [
          'schema_version' => 'module-manager-releases-v1',
          'releases' => $this->releaseService->listCampaignSourceSummaries($limit),
          'campaign_sources' => $this->releaseService->listPublishedCampaignSources(),
        ],
      ]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Validate a pinned import using the same coverage gate as publication.
   */
  public function releaseCoverage(string $release_id): JsonResponse {
    try {
      return new JsonResponse(['data' => $this->releaseService->sceneCoverageReport($release_id)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Validate one release across source-object completeness and native outputs.
   */
  public function releaseValidation(string $release_id, Request $request): JsonResponse {
    try {
      $profile = $request->query->get('profile');
      $dungeon_id = trim((string) $request->query->get('dungeon_id', ''));
      $version_id = trim((string) $request->query->get('version_id', ''));
      $options = ['profile' => is_string($profile) ? $profile : NULL];
      if ($dungeon_id !== '' && $version_id !== '') {
        $options['manifest'] = ['dungeon_id' => $dungeon_id, 'version_id' => $version_id];
      }
      return new JsonResponse(['data' => $this->releaseService->validateConversion($release_id, $options)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Persist immutable native receipts for one release.
   */
  public function registerReceipts(string $release_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $receipts = $body['receipts'] ?? NULL;
      if (!is_array($receipts) || !array_is_list($receipts) || $receipts === []) {
        throw new ModuleConversionReadException('module_release_receipts_required', [[
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_release_receipts_required',
          'pointer' => '/receipts',
          'message' => 'receipts must be a non-empty array.',
          'context' => [],
        ]], 422);
      }
      $release = $this->releaseService->registerNativeReceipts($release_id, $receipts, (int) $this->currentUser()->id());
      return new JsonResponse([
        'data' => [
          'schema_version' => 'module-manager-release-receipts-v1',
          'release' => $release,
        ],
      ]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Attach one separately reviewed canonical version to a release resource.
   */
  public function bindDefinition(string $release_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      return new JsonResponse(['data' => $this->releaseService->bindReviewedDefinition(
        $release_id,
        $body,
        (int) $this->currentUser()->id(),
      )]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Persist one reviewed actor placement/categorization decision.
   */
  public function registerActorPlacementReview(string $release_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      return new JsonResponse(['data' => $this->releaseService->registerActorPlacementReview(
        $release_id,
        $body,
        (int) $this->currentUser()->id(),
      )]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Persist one verified launch manifest for a release.
   */
  public function publishRelease(string $release_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $manifest = $body['manifest'] ?? NULL;
      if (!is_array($manifest) || array_is_list($manifest)) {
        throw new ModuleConversionReadException('module_release_manifest_invalid', [[
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_release_manifest_invalid',
          'pointer' => '/manifest',
          'message' => 'manifest must be a JSON object.',
          'context' => [],
        ]], 422);
      }
      $release = $this->releaseService->registerPublishedManifest($release_id, $manifest, (int) $this->currentUser()->id());
      return new JsonResponse([
        'data' => [
          'schema_version' => 'module-manager-release-publish-v1',
          'release' => $release,
        ],
      ]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Persist one execution-step update for a release resource.
   */
  public function registerExecutionStep(string $release_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $step = $body['step'] ?? NULL;
      if (!is_array($step) || array_is_list($step)) {
        throw new ModuleConversionReadException('module_release_execution_step_invalid', [[
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_release_execution_step_invalid',
          'pointer' => '/step',
          'message' => 'step must be a JSON object.',
          'context' => [],
        ]], 422);
      }
      $release = $this->releaseService->registerExecutionStep($release_id, $step, (int) $this->currentUser()->id());
      return new JsonResponse([
        'data' => [
          'schema_version' => 'module-manager-release-execution-v1',
          'release' => $release,
        ],
      ]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Execute deterministic plan commands for a release.
   */
  public function runExecution(string $release_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $result = $this->releaseService->executePlannedCommands($release_id, $body, (int) $this->currentUser()->id());
      return new JsonResponse([
        'data' => [
          'schema_version' => 'module-manager-release-execution-run-v1',
          'release' => $result['release'],
          'run' => $result['run'],
        ],
      ]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Decodes a strict JSON object request body.
   */
  private function decodeBody(Request $request): array {
    if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
      throw new \InvalidArgumentException('content_type_must_be_json');
    }
    $content = (string) $request->getContent();
    $body = $content === '' ? [] : json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($body) || ($body !== [] && array_is_list($body))) {
      throw new \InvalidArgumentException('json_object_required');
    }
    return $body;
  }

  /**
   * Validates Drupal's REST CSRF header token.
   */
  private function validateCsrf(Request $request): ?JsonResponse {
    $token = $request->headers->get('X-CSRF-Token');
    if (!$token || !$this->csrfToken->validate($token, CsrfRequestHeaderAccessCheck::TOKEN_KEY)) {
      return new JsonResponse([
        'error' => [
          'code' => 'csrf_token_invalid',
          'message' => 'A valid X-CSRF-Token is required.',
        ],
      ], 403);
    }
    return NULL;
  }

  /**
   * Map failures to stable API responses.
   */
  private function errorResponse(\Throwable $exception): JsonResponse {
    $code = $exception->getMessage() ?: 'module_manager_error';
    $status = match (TRUE) {
      $exception instanceof ModuleConversionReadException => $exception->httpStatus(),
      $exception instanceof \JsonException, $exception instanceof \InvalidArgumentException => 400,
      $exception instanceof \OutOfBoundsException => 404,
      default => 500,
    };
    $error = [
      'code' => $code,
      'message' => str_replace('_', ' ', strtok($code, ':') ?: 'module_manager_error'),
    ];
    if ($exception instanceof DungeonEditorFindingsInterface) {
      $error['findings'] = $exception->getFindings();
    }
    if ($status >= 500) {
      $this->getLogger('dungeoncrawler_content')->error('Module manager request failed: @code @message', [
        '@code' => $code,
        '@message' => $exception->getMessage(),
      ]);
    }
    return new JsonResponse(['error' => $error], $status);
  }

  /**
   * @param array<string,mixed> $plan
   *
   * @return array{count:int,authoring_review_count:int,by_generation_path:array<string,int>,sample:array<int,array<string,mixed>>}
   */
  private function collectGenerationGaps(array $plan): array {
    $generationGapCounts = [];
    $generationReviewCount = 0;
    $generationGapSamples = [];
    $generationSampleCounts = [];
    foreach ((array) ($plan['resources'] ?? []) as $resource) {
      if (!is_array($resource)) {
        continue;
      }
      $analysis = $this->gapAnalysis->analyzeResource($resource);
      if ((array) ($analysis['unresolved_gaps'] ?? []) === []) {
        continue;
      }
      $path = (string) ($analysis['generation_path'] ?? 'unknown');
      $generationGapCounts[$path] = ($generationGapCounts[$path] ?? 0) + 1;
      if ((bool) ($analysis['requires_review'] ?? FALSE)) {
        $generationReviewCount++;
      }
      $sampleKey = $path . ':' . ((bool) ($analysis['requires_review'] ?? FALSE) ? 'authoring' : 'reference');
      $generationSampleCounts[$sampleKey] = ($generationSampleCounts[$sampleKey] ?? 0) + 1;
      if ($generationSampleCounts[$sampleKey] <= 5) {
        $generationGapSamples[] = $analysis;
      }
    }
    return [
      'count' => array_sum($generationGapCounts),
      'authoring_review_count' => $generationReviewCount,
      'by_generation_path' => $generationGapCounts,
      'sample' => $generationGapSamples,
    ];
  }

  /**
   * @param array<int,array{name:string,bytes:int,sha256:string}> $archives
   *
   * @return array<int,array{name:string,bytes:int,sha256:string}>
   */
  private function latestArchives(array $archives): array {
    $latestByModule = [];
    foreach ($archives as $archive) {
      $name = (string) ($archive['name'] ?? '');
      if ($name === '') {
        continue;
      }
      $parsed = $this->parseArchiveIdentity($name);
      $moduleKey = $parsed['module_key'];
      if (!isset($latestByModule[$moduleKey])) {
        $latestByModule[$moduleKey] = $archive;
        continue;
      }
      $current = $latestByModule[$moduleKey];
      $currentParsed = $this->parseArchiveIdentity((string) ($current['name'] ?? ''));
      $versionCmp = $this->compareArchiveVersions($parsed['version'], $currentParsed['version']);
      if ($versionCmp > 0 || ($versionCmp === 0 && strcmp($name, (string) ($current['name'] ?? '')) > 0)) {
        $latestByModule[$moduleKey] = $archive;
      }
    }
    $result = array_values($latestByModule);
    usort($result, static fn(array $a, array $b): int => strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    return $result;
  }

  /**
   * @return array{module_key:string,version:string}
   */
  private function parseArchiveIdentity(string $archiveName): array {
    if (preg_match('/^(?P<module>.+)-(?P<version>\d+(?:\.\d+)*(?:[-+][0-9A-Za-z.-]+)?)\.zip$/D', $archiveName, $matches) === 1) {
      return [
        'module_key' => strtolower((string) $matches['module']),
        'version' => strtolower((string) $matches['version']),
      ];
    }
    return [
      'module_key' => strtolower(preg_replace('/\.zip$/i', '', $archiveName) ?? $archiveName),
      'version' => '',
    ];
  }

  private function compareArchiveVersions(string $left, string $right): int {
    if ($left !== '' && $right !== '') {
      return version_compare($left, $right);
    }
    if ($left !== '') {
      return 1;
    }
    if ($right !== '') {
      return -1;
    }
    return 0;
  }

  private function moduleStateAnalysisFromInventory(array $inventory): array {
    $summary = is_array($inventory['resource_summary'] ?? NULL) ? $inventory['resource_summary'] : [];
    $byDisposition = is_array($summary['by_disposition'] ?? NULL) ? $summary['by_disposition'] : [];
    $findings = is_array($inventory['findings'] ?? NULL) ? $inventory['findings'] : [];
    $errorCount = 0;
    foreach ($findings as $finding) {
      if (strtolower((string) ($finding['severity'] ?? 'error')) !== 'warning') {
        $errorCount++;
      }
    }
    $isPublishable = ((int) ($byDisposition['unsupported'] ?? 0) === 0)
      && ((int) ($byDisposition['requires_authoring'] ?? 0) === 0)
      && $errorCount === 0;
    return $this->moduleStateAnalysis(
      $inventory,
      ['is_publishable' => $isPublishable],
      ['count' => 0, 'authoring_review_count' => 0, 'by_generation_path' => [], 'sample' => []],
    );
  }

  private function moduleStateAnalysis(array $inventory, array $plan, array $generationGaps): array {
    $summary = is_array($inventory['resource_summary'] ?? NULL) ? $inventory['resource_summary'] : [];
    $byType = is_array($summary['by_type'] ?? NULL) ? $summary['by_type'] : [];
    $byDisposition = is_array($summary['by_disposition'] ?? NULL) ? $summary['by_disposition'] : [];
    $findings = is_array($inventory['findings'] ?? NULL) ? $inventory['findings'] : [];

    $total = max(1, (int) ($summary['total'] ?? 0));
    $dungeonCount = (int) (($byType['adventure'] ?? 0));
    $roomCount = (int) (($byType['scene'] ?? 0));
    $actorCount = (int) (($byType['actor'] ?? 0));
    $itemCount = (int) (($byType['item'] ?? 0) + ($byType['embedded_item'] ?? 0));
    $journalQuestCount = (int) (($byType['journal'] ?? 0) + ($byType['journal_page'] ?? 0));
    $sourceRandomTableCount = (int) (($byType['rolltable'] ?? 0) + ($byType['rolltable_result'] ?? 0));
    $unsupported = (int) ($byDisposition['unsupported'] ?? 0);
    $requiresAuthoring = (int) ($byDisposition['requires_authoring'] ?? 0);
    $referenceOnly = (int) ($byDisposition['reference_only'] ?? 0);
    $gapCount = (int) ($generationGaps['count'] ?? 0);
    $authoringReviewCount = (int) ($generationGaps['authoring_review_count'] ?? 0);
    $isPublishable = (bool) ($plan['is_publishable'] ?? FALSE);

    $errorCount = 0;
    $warningCount = 0;
    foreach ($findings as $finding) {
      $severity = strtolower((string) ($finding['severity'] ?? 'error'));
      if ($severity === 'warning') {
        $warningCount++;
      }
      else {
        $errorCount++;
      }
    }

    $score = 100;
    $score -= min(35, (int) round(($unsupported / $total) * 90));
    $score -= min(25, (int) round(($requiresAuthoring / $total) * 60));
    $score -= min(20, (int) round(($gapCount / $total) * 45));
    $score -= min(15, ($errorCount * 6) + ($warningCount * 2));
    if (!$isPublishable) {
      $score -= 10;
    }
    if ($roomCount === 0) {
      $score -= 20;
    }
    if ($dungeonCount === 0) {
      $score -= 10;
    }
    $score = max(0, min(100, $score));

    $grade = match (TRUE) {
      $score >= 85 => 'A',
      $score >= 70 => 'B',
      $score >= 55 => 'C',
      $score >= 40 => 'D',
      default => 'F',
    };

    $state = 'ready_for_validation';
    $summaryText = 'Mostly coherent Forseti object inventory with manageable adaptation gaps.';
    if ($roomCount > 0
      && $sourceRandomTableCount >= max(20, $roomCount * 2)
      && $journalQuestCount <= max(3, (int) ceil($roomCount * 0.5))) {
      $state = 'gm_toolkit_compendium';
      $summaryText = 'Primarily a toolkit/compendium: heavy random-table payload with limited narrative structure.';
    }
    elseif ($unsupported > 0 || $score < 40) {
      $state = 'high_adaptation_required';
      $summaryText = 'Conversion can proceed, but this module is structurally incomplete for direct play without substantial authoring.';
    }
    elseif ($requiresAuthoring > (int) floor($total * 0.35) || $authoringReviewCount > (int) floor($total * 0.25)) {
      $state = 'adaptation_required';
      $summaryText = 'Playable baseline is possible, but significant manual authoring and review are still required.';
    }

    $recommendations = [];
    if ($state === 'gm_toolkit_compendium') {
      $recommendations[] = 'Treat source scenes and journals as prompts; author explicit dungeon/room narrative beats and encounter chains.';
      $recommendations[] = 'Prioritize actor placement review and quest objective scaffolding before publication validation.';
    }
    if ($unsupported > 0 || $requiresAuthoring > 0) {
      $recommendations[] = 'Resolve unsupported and requires_authoring resources first to improve release readiness score.';
    }
    if ($gapCount > 0) {
      $recommendations[] = 'Use generation-gap samples to create reviewed original definitions and bind them to this release.';
    }
    if ($recommendations === []) {
      $recommendations[] = 'Run full conversion validation to verify publication gates for the selected profile.';
    }

    $inventoryRows = [
      [
        'label' => 'Dungeons',
        'foundry_object' => 'adventure',
        'foundry_count' => $dungeonCount,
        'forseti_object' => 'dungeon',
        'forseti_count' => $dungeonCount,
        'note' => 'Adventure roots convert into Forseti dungeon records.',
      ],
      [
        'label' => 'Rooms',
        'foundry_object' => 'scene',
        'foundry_count' => $roomCount,
        'forseti_object' => 'room',
        'forseti_count' => $roomCount,
        'note' => 'Foundry scenes are treated as source inputs for Forseti rooms.',
      ],
      [
        'label' => 'Actors',
        'foundry_object' => 'actor',
        'foundry_count' => $actorCount,
        'forseti_object' => 'actor',
        'forseti_count' => $actorCount,
        'note' => 'Actor definitions/placements require review before publish gates.',
      ],
      [
        'label' => 'Items',
        'foundry_object' => 'item + embedded_item',
        'foundry_count' => $itemCount,
        'forseti_object' => 'item',
        'forseti_count' => $itemCount,
        'note' => 'Embedded and top-level Foundry items map into Forseti item inventory.',
      ],
      [
        'label' => 'Journal / Quest Items',
        'foundry_object' => 'journal + journal_page',
        'foundry_count' => $journalQuestCount,
        'forseti_object' => 'journal_quest_item',
        'forseti_count' => $journalQuestCount,
        'note' => 'Narrative/journal source objects map to Forseti journal/quest inventory.',
      ],
      [
        'label' => 'Random Tables',
        'foundry_object' => 'rolltable + rolltable_result',
        'foundry_count' => $sourceRandomTableCount,
        'forseti_object' => 'n/a',
        'forseti_count' => 0,
        'note' => 'No direct Forseti runtime object; treated as source reference content.',
      ],
      [
        'label' => 'Folders',
        'foundry_object' => 'folder',
        'foundry_count' => (int) ($byType['folder'] ?? 0),
        'forseti_object' => 'n/a',
        'forseti_count' => 0,
        'note' => 'Source organization metadata only; no direct Forseti runtime object.',
      ],
    ];
    $missingRows = array_values(array_filter($inventoryRows, static fn(array $row): bool => (int) ($row['foundry_count'] ?? 0) > (int) ($row['forseti_count'] ?? 0)));

    return [
      'schema_version' => 'module-manager-state-analysis-v1',
      'score' => $score,
      'grade' => $grade,
      'state' => $state,
      'summary' => $summaryText,
      'counts' => [
        'resources_total' => (int) ($summary['total'] ?? 0),
        'dungeons' => $dungeonCount,
        'rooms' => $roomCount,
        'actors' => $actorCount,
        'items' => $itemCount,
        'journal_quest_items' => $journalQuestCount,
        'unsupported' => $unsupported,
        'requires_authoring' => $requiresAuthoring,
        'reference_only' => $referenceOnly,
        'generation_gaps' => $gapCount,
        'authoring_review_gaps' => $authoringReviewCount,
        'inventory_errors' => $errorCount,
        'inventory_warnings' => $warningCount,
        'source_random_tables' => $sourceRandomTableCount,
      ],
      'signals' => [
        'room_density_pct' => round(($roomCount / $total) * 100, 1),
        'quest_density_pct' => round(($journalQuestCount / $total) * 100, 1),
        'authoring_pressure_pct' => round((($requiresAuthoring + $unsupported) / $total) * 100, 1),
      ],
      'object_inventory' => [
        'rows' => $inventoryRows,
        'missing_or_non_direct_rows' => $missingRows,
      ],
      'publishable' => $isPublishable,
      'recommendations' => $recommendations,
    ];
  }

}
