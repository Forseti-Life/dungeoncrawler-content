<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionValidationException;
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

    return $this->withGmPanel([
      '#type' => 'table',
      '#header' => [$this->t('Family'), $this->t('Definitions'), $this->t('Schema'), $this->t('Storage'), ''],
      '#rows' => $rows,
      '#attributes' => ['class' => ['dc-definition-index']],
      '#cache' => ['max-age' => 0],
    ], ['family' => 'creature']);
  }

  /**
   * Family list with conformance status per definition and a create link.
   */
  public function family(string $family, Request $request): array {
    $search = trim((string) $request->query->get('search', ''));
    $catalog = $this->definitions->catalog($family, $search, 250, 0);
    $schema = $this->definitions->schemaForFamily($family);

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
      ];
    }

    return $this->withGmPanel([
      'header' => [
        '#type' => 'container',
        'title' => ['#markup' => '<h2>' . $this->t('@title definitions', ['@title' => (string) ($schema['title'] ?? ucfirst($family))]) . '</h2>'],
        'create' => [
          '#type' => 'link',
          '#title' => $this->t('Create @family', ['@family' => $family]),
          '#url' => Url::fromRoute('dungeoncrawler_content.definition_create', ['family' => $family]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'back' => [
          '#type' => 'link',
          '#title' => $this->t('All families'),
          '#url' => Url::fromRoute('dungeoncrawler_content.definition_index'),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'search' => [
        '#type' => 'inline_template',
        '#template' => '<form method="get" class="dc-definition-search"><input type="search" name="search" value="{{ search }}" placeholder="{{ placeholder }}"/> <button type="submit" class="button">{{ label }}</button></form>',
        '#context' => ['search' => $search, 'placeholder' => $this->t('Filter by name or id'), 'label' => $this->t('Filter')],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Name'), $this->t('ID'), $this->t('Category'), $this->t('Version'), $this->t('Schema status')],
        '#rows' => $rows,
        '#empty' => $this->t('No definitions.'),
        '#attributes' => ['class' => ['dc-definition-list']],
      ],
      '#cache' => ['max-age' => 0],
    ], ['family' => $family]);
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
   * Wraps canonical-library pages with the shared editor GM panel.
   */
  private function withGmPanel(array $content, array $scope): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['room-editor', 'definition-editor'], 'data-definition-editor' => ''],
      '#attached' => $this->gmAttachment($scope),
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['definition-editor__body']],
        'content' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['definition-editor__content']],
          'inner' => $content,
        ],
        'gm' => $this->gmPanel(),
      ],
    ];
  }

  /**
   * Browser settings for the definition GM panel.
   */
  private function gmAttachment(array $scope): array {
    return [
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
    ];
  }

  /**
   * Shared GM panel structure consumed by DefinitionEditorShell.js.
   */
  private function gmPanel(): array {
    return [
      '#type' => 'inline_template',
      '#template' => $this->gmPanelMarkup(),
    ];
  }

  private function gmPanelMarkup(): string {
    return <<<'TWIG'
<aside class="room-editor__gm-panel definition-editor__gm-panel" data-definition-editor-gm-panel aria-label="GM assistant">
  <header class="room-editor__gm-header">
    <p class="room-editor__eyebrow">GM Assistant</p>
    <span class="room-editor__gm-state" data-definition-editor-gm-state role="status" aria-live="polite">Idle</span>
  </header>
  <section class="room-editor__gm-disclosure-group" aria-label="Grounded definition context">
    <button type="button" class="room-editor__gm-disclosure" data-definition-editor-action="gm-toggle-context" aria-expanded="false" aria-controls="definition-editor-gm-context-body">Grounded context</button>
    <div id="definition-editor-gm-context-body" class="room-editor__gm-context-body" data-definition-editor-gm-context hidden></div>
  </section>
  <section class="room-editor__gm-disclosure-group" aria-label="Assistant toolset">
    <button type="button" class="room-editor__gm-disclosure" data-definition-editor-action="gm-toggle-tools" aria-expanded="false" aria-controls="definition-editor-gm-tools-body">Toolset</button>
    <div id="definition-editor-gm-tools-body" class="room-editor__gm-tools-body" data-definition-editor-gm-tools hidden></div>
  </section>
  <ol class="room-editor__gm-transcript" data-definition-editor-gm-transcript aria-label="Assistant transcript" aria-live="polite"></ol>
  <div class="room-editor__gm-plan" data-definition-editor-gm-plan hidden>
    <p class="room-editor__eyebrow">Proposed definition update</p>
    <ol class="room-editor__gm-plan-list" data-definition-editor-gm-plan-list></ol>
    <div class="room-editor__gm-plan-actions">
      <button type="button" class="room-editor__button" data-definition-editor-action="gm-preview-definition">Preview</button>
      <button type="button" class="room-editor__button room-editor__button--primary" data-definition-editor-action="gm-apply-definition">Apply</button>
      <button type="button" class="room-editor__button" data-definition-editor-action="gm-discard-definition">Discard</button>
    </div>
  </div>
  <div class="room-editor__gm-composer" data-definition-editor-gm-form>
    <label class="visually-hidden" for="definition-editor-gm-input">Message the GM assistant</label>
    <textarea id="definition-editor-gm-input" data-definition-editor-gm-input rows="3" placeholder="Ask the assistant, or run a tool: describe_definition_schema {}"></textarea>
    <div class="room-editor__gm-composer-actions">
      <button type="button" class="room-editor__button room-editor__button--primary" data-definition-editor-action="gm-send">Send</button>
    </div>
  </div>
</aside>
TWIG;
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
