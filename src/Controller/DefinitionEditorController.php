<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\InstitutionLibraryService;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionValidationException;
use Drupal\dungeoncrawler_content\Render\DefinitionEditorPage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Canonical definition editor: family index, family lists and the JSON API.
 *
 * Normative specification:
 * copilot-hq 20260904-dc-canonical-dungeon-editor-architecture/
 *   19-definition-editor-spec.md, 12-api-and-error-contracts.md
 *
 * The edit/create form is SchemaDrivenDefinitionForm. Every read and write
 * here goes through CanonicalDefinitionService; this controller holds no
 * storage knowledge and no schema knowledge.
 */
final class DefinitionEditorController extends ControllerBase {

  public function __construct(
    private readonly CanonicalDefinitionService $definitions,
    private readonly CsrfTokenGenerator $csrfToken,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.canonical_definitions'),
      $container->get('csrf_token'),
    );
  }

  /**
   * Actor definitions open on the shared actor character sheet.
   */
  public function show(string $family, string $definition_id): array|\Symfony\Component\HttpFoundation\RedirectResponse {
    $this->definitions->definitionPayload($family, $definition_id);
    if ($family === 'actor') {
      return new \Symfony\Component\HttpFoundation\RedirectResponse(
        Url::fromRoute('dungeoncrawler_content.actor_view', ['actor_id' => $definition_id])->toString(),
      );
    }
    return $this->formBuilder()->getForm(
      \Drupal\dungeoncrawler_content\Form\SchemaDrivenDefinitionForm::class,
      $family,
      $definition_id,
    );
  }

  /**
   * Family index: one card per family with live conformance counts.
   */
  public function index(): array {
    $rows = [];
    foreach ($this->definitions->families() as $family) {
      $catalog = $this->definitions->catalog($family, '', 1, 0);
      $rows[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => ucfirst($family),
            '#url' => Url::fromRoute('dungeoncrawler_content.definition_family', ['family' => $family]),
          ],
        ],
        (string) $catalog['total'],
        CanonicalDefinitionService::SCHEMA_FILES[$family],
        $this->definitions->sourceTable($family),
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('New @family', ['@family' => $family]),
            '#url' => Url::fromRoute('dungeoncrawler_content.definition_create', ['family' => $family]),
          ],
        ],
      ];
    }

    $total = array_sum(array_map(static fn(array $row): int => (int) $row[1], $rows));
    return $this->inEditorPage([
      '#type' => 'table',
      '#header' => [$this->t('Family'), $this->t('Definitions'), $this->t('Schema'), $this->t('Storage'), ''],
      '#rows' => $rows,
      '#attributes' => ['class' => ['dc-definition-index']],
      '#cache' => ['max-age' => 0],
    ], ['family' => 'actor'], DefinitionEditorPage::wrap(
      $this->definitions,
      (string) $this->t('Canonical Library'),
      NULL,
      [
        ['label' => $this->t('Families'), 'value' => (string) count($rows)],
        ['label' => $this->t('Definitions'), 'value' => (string) $total],
      ],
    ));
  }

  /**
   * Family list with conformance status per definition and a create link.
   */
  public function family(string $family, Request $request): array {
    $search = trim((string) $request->query->get('search', ''));
    $catalog = $this->definitions->catalog($family, $search, 250, 0);
    $schema = $this->definitions->schemaForFamily($family);

    // An actor's critical ties, read from its library character.
    $characters = [];
    if ($family === 'actor') {
      $library = \Drupal::service('dungeoncrawler_content.institution_library');
      $library_rows = \Drupal::database()->query("SELECT instance_id, ancestry, class, character_data FROM {dc_campaign_characters} WHERE campaign_id = 0 AND status <> 2")->fetchAllAssoc('instance_id', \PDO::FETCH_ASSOC);
      foreach ($library_rows as $instance_id => $character) {
        $data = json_decode((string) $character['character_data'], TRUE) ?: [];
        $sheet = [
          'ancestry' => trim((string) $character['ancestry']) ?: (is_string($data['ancestry'] ?? NULL) ? $data['ancestry'] : ''),
          'class' => trim((string) $character['class']) ?: (is_string($data['class'] ?? NULL) ? $data['class'] : ''),
          'alignment' => (string) ($data['alignment'] ?? ''),
        ];
        if (!InstitutionLibraryService::isSocialActor($sheet)) {
          $characters[$instance_id] = $this->t('not a person');
          continue;
        }
        $gaps = InstitutionLibraryService::criticalTieGaps($sheet, array_column($library->characterMemberships($data), 'domain'));
        $characters[$instance_id] = $gaps === [] ? $this->t('complete') : $this->t('missing: @gaps', ['@gaps' => implode(', ', $gaps)]);
      }
    }

    $rows = [];
    foreach ($catalog['definitions'] as $entry) {
      $findings = $this->definitions->validateDefinition($family, $this->definitions->definitionPayload($family, $entry['definition_id']));
      $rows[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => $entry['label'],
            '#url' => Url::fromRoute('dungeoncrawler_content.definition_edit', ['family' => $family, 'definition_id' => $entry['definition_id']]),
          ],
        ],
        $entry['definition_id'],
        $entry['category'],
        $entry['definition_version'],
        $findings === []
          ? $this->t('conforms')
          : $this->formatPlural(count($findings), '1 finding', '@count findings'),
        ...($family === 'actor' ? [$characters[$entry['definition_id']] ?? $this->t('no library character')] : []),
      ];
    }

    $listed = count($rows);
    $conforming = count(array_filter($rows, fn(array $row): bool => (string) $row[4] === (string) $this->t('conforms')));
    $create_url = Url::fromRoute('dungeoncrawler_content.definition_create', ['family' => $family])->toString();
    return $this->inEditorPage([
      'actions' => [
        '#type' => 'inline_template',
        '#template' => '<div class="definition-editor__actions"><a class="btn btn-primary btn-sm" href="{{ url }}">{{ label }}</a></div>',
        '#context' => ['url' => $create_url, 'label' => $this->t('Create @family', ['@family' => $family])],
      ],
      'search' => [
        '#type' => 'inline_template',
        '#template' => '<form method="get" class="dc-definition-search"><input type="search" name="search" value="{{ search }}" placeholder="{{ placeholder }}"/> <button type="submit" class="button">{{ label }}</button></form>',
        '#context' => ['search' => $search, 'placeholder' => $this->t('Filter by name or id'), 'label' => $this->t('Filter')],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Name'), $this->t('ID'), $this->t('Category'), $this->t('Version'), $this->t('Schema status'), ...($family === 'actor' ? [$this->t('Critical ties')] : [])],
        '#rows' => $rows,
        '#empty' => $this->t('No definitions.'),
        '#attributes' => ['class' => ['dc-definition-list']],
      ],
      '#cache' => ['max-age' => 0],
    ], ['family' => $family], DefinitionEditorPage::wrap(
      $this->definitions,
      (string) $this->t('@title definitions', ['@title' => (string) ($schema['title'] ?? ucfirst($family))]),
      $family,
      [
        ['label' => $search !== '' ? $this->t('Match the filter') : $this->t('In this family'), 'value' => (string) $catalog['total']],
        ['label' => $this->t('Listed here'), 'value' => (string) $listed],
        ['label' => $this->t('Conform to the schema'), 'value' => sprintf('%d of %d', $conforming, $listed)],
        ...($search !== '' ? [['label' => $this->t('Filter'), 'value' => $search]] : []),
      ],
      $listed > 0 ? (int) round($conforming / $listed * 100) : NULL,
      $listed > $conforming ? [(string) $this->formatPlural($listed - $conforming, '1 listed definition has schema findings', '@count listed definitions have schema findings')] : [],
      [['label' => $this->t('Create @family', ['@family' => $family]), 'url' => $create_url]],
    ));
  }

  /**
   * Page title for the family list.
   */
  public function familyTitle(string $family): string {
    return ucfirst($family) . ' definitions';
  }

  /**
   * GET /api/canonical-definitions/{family}.
   */
  public function apiList(string $family, Request $request): JsonResponse {
    try {
      $search = trim((string) $request->query->get('search', ''));
      $limit = (int) $request->query->get('limit', 100);
      $offset = (int) $request->query->get('offset', 0);
      return new JsonResponse(['data' => $this->definitions->catalog($family, $search, $limit, $offset)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * GET /api/canonical-definitions/{family}/schema.
   */
  public function apiSchema(string $family): JsonResponse {
    try {
      return new JsonResponse([
        'data' => [
          'family' => $family,
          'id_property' => $this->definitions->idProperty($family),
          'name_property' => $this->definitions->nameProperty($family),
          'schema' => $this->definitions->schemaForFamily($family),
        ],
      ]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * GET /api/canonical-definitions/{family}/{definition_id}.
   */
  public function apiLoad(string $family, string $definition_id): JsonResponse {
    try {
      $payload = $this->definitions->definitionPayload($family, $definition_id);
      return new JsonResponse([
        'data' => [
          'family' => $family,
          'definition_id' => $definition_id,
          'version' => $this->definitions->currentVersion($family, $definition_id),
          'payload' => $payload,
          'findings' => $this->definitions->validateDefinition($family, $payload),
          'affected_published_rooms' => $this->definitions->publishedRoomsReferencing($family, $definition_id),
        ],
      ]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * PUT /api/canonical-definitions/{family}/{definition_id}.
   *
   * Body: {"payload": {...}, "expected_version": "1.0.0"}.
   */
  public function apiSave(string $family, string $definition_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = json_decode((string) $request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($body) || !is_array($body['payload'] ?? NULL)) {
        throw new \InvalidArgumentException('definition_payload_required');
      }
      $expected_version = isset($body['expected_version']) ? (string) $body['expected_version'] : NULL;
      $result = $this->definitions->saveDefinition($family, $definition_id, $body['payload'], $expected_version);
      return new JsonResponse(['data' => $result], $result['created'] ? 201 : 200);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * POST /api/canonical-definitions/{family}.
   *
   * Body: {"payload": {...}}. The id comes from the payload's id property;
   * an existing id fails with `definition_exists`.
   */
  public function apiCreate(string $family, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = json_decode((string) $request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($body) || !is_array($body['payload'] ?? NULL)) {
        throw new \InvalidArgumentException('definition_payload_required');
      }
      return new JsonResponse(['data' => $this->definitions->saveDefinition($family, NULL, $body['payload'], NULL)], 201);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Places library content in the shared editor page.
   *
   * @param array{prefix: string, suffix: string} $page
   *   From DefinitionEditorPage::wrap().
   */
  private function inEditorPage(array $content, array $scope, array $page): array {
    return [
      '#prefix' => Markup::create($page['prefix']),
      '#suffix' => Markup::create($page['suffix']),
      '#attached' => [
        'library' => ['dungeoncrawler_content/definition-editor'],
        'drupalSettings' => [
          'dungeoncrawlerContent' => [
            'definitionEditor' => [
              'gmUrl' => Url::fromRoute('dungeoncrawler_content.definition_editor_gm_describe')->toString(),
              'csrfToken' => $this->csrfToken->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY),
              'scope' => $scope,
            ],
          ],
        ],
      ],
      'content' => $content,
    ];
  }

  private function validateCsrf(Request $request): ?JsonResponse {
    $token = $request->headers->get('X-CSRF-Token');
    if (!$token || !$this->csrfToken->validate($token, CsrfRequestHeaderAccessCheck::TOKEN_KEY)) {
      return new JsonResponse([
        'error' => ['code' => 'csrf_token_invalid', 'message' => 'A valid X-CSRF-Token is required.'],
      ], 403);
    }
    return NULL;
  }

  /**
   * Maps domain failures to the stable codes in 12-api-and-error-contracts.md.
   */
  private function errorResponse(\Throwable $exception): JsonResponse {
    $code = $exception->getMessage();
    $error = ['code' => $code, 'message' => str_replace('_', ' ', ucfirst(strtok($code, ':')))];
    if ($exception instanceof DefinitionValidationException) {
      $error['findings'] = $exception->findings;
      return new JsonResponse(['error' => $error], 422);
    }
    $status = match (TRUE) {
      $exception instanceof \InvalidArgumentException && $code === 'definition_exists' => 409,
      $exception instanceof \JsonException,
      $exception instanceof \InvalidArgumentException => 400,
      $exception instanceof \OutOfBoundsException => 404,
      $exception instanceof \RuntimeException && $code === 'definition_version_conflict' => 409,
      $exception instanceof \RuntimeException && str_starts_with($code, 'definition_schema_missing:') => 500,
      $exception instanceof \DomainException => 422,
      default => 500,
    };
    if ($status === 500) {
      $this->getLogger('dungeoncrawler_content')->error('Definition editor failure: @message', ['@message' => $exception->getMessage()]);
      if (!str_starts_with($code, 'definition_schema_missing:')) {
        $error = ['code' => 'definition_editor_internal_error', 'message' => 'Definition editor internal error'];
      }
    }

    return new JsonResponse(['error' => $error], $status);
  }

}
