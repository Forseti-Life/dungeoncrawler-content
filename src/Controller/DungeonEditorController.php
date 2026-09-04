<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\DungeonEditorFindingsInterface;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Canonical Dungeon Editor page and JSON API.
 *
 * Mirrors RoomEditorController: permission gate, initial read model, and the
 * URL map the shell needs. All domain logic lives in DungeonEditorService.
 */
class DungeonEditorController extends ControllerBase {

  private const DRAFT_PLACEHOLDER = '00000000-0000-4000-8000-000000000000';

  public function __construct(
    protected DungeonEditorService $dungeonEditor,
    protected CsrfTokenGenerator $csrfToken,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.dungeon_editor'),
      $container->get('csrf_token'),
    );
  }

  /**
   * Renders the editor shell.
   */
  public function page(?string $dungeon_id = NULL): array {
    $dungeons = $this->dungeonEditor->listDungeons();
    return [
      '#theme' => 'dungeon_editor',
      '#dungeons' => $dungeons,
      '#selected_dungeon_id' => $dungeon_id,
      '#attached' => [
        'library' => ['dungeoncrawler_content/dungeon-editor'],
        'drupalSettings' => [
          'dungeoncrawlerContent' => [
            'dungeonEditor' => [
              'dungeons' => $dungeons,
              'selectedDungeonId' => $dungeon_id,
              'csrfToken' => $this->csrfToken->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY),
              'urls' => [
                'create' => Url::fromRoute('dungeoncrawler_content.dungeon_editor_draft_create')->toString(),
                'draft' => $this->draftUrl('dungeoncrawler_content.dungeon_editor_draft_get'),
                'describe' => $this->draftUrl('dungeoncrawler_content.dungeon_editor_draft_describe'),
                'rooms' => Url::fromRoute('dungeoncrawler_content.dungeon_editor_rooms')->toString(),
                'command' => $this->draftUrl('dungeoncrawler_content.dungeon_editor_draft_command'),
                'simulate' => $this->draftUrl('dungeoncrawler_content.dungeon_editor_draft_simulate'),
                'validate' => $this->draftUrl('dungeoncrawler_content.dungeon_editor_draft_validate'),
                'gm' => $this->draftUrl('dungeoncrawler_content.dungeon_editor_gm_describe'),
                'roomEditor' => str_replace('placeholder-room', '{room_id}', Url::fromRoute('dungeoncrawler_content.room_editor_edit', ['room_id' => 'placeholder-room'])->toString()),
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Creates a draft from a published dungeon or a blank level.
   */
  public function createDraft(Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $dungeon_id = array_key_exists('dungeon_id', $body) && $body['dungeon_id'] !== NULL
        ? (string) $body['dungeon_id']
        : NULL;
      return new JsonResponse(['data' => $this->dungeonEditor->createDraft($dungeon_id)], 201);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Returns one draft with its validation result.
   */
  public function getDraft(string $draft_id): JsonResponse {
    try {
      return new JsonResponse(['data' => $this->dungeonEditor->getDraft($draft_id)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Returns the resolved read model of one draft.
   */
  public function describe(string $draft_id): JsonResponse {
    try {
      return new JsonResponse(['data' => $this->dungeonEditor->describe($draft_id)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Applies one authoring command; the response carries the new read model.
   */
  public function command(string $draft_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $result = $this->dungeonEditor->applyCommand($draft_id, $this->decodeBody($request));
      return new JsonResponse(['data' => $result], $result['idempotent'] ? 200 : 201);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Projects a command list without persisting.
   */
  public function simulate(string $draft_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $commands = $body['commands'] ?? NULL;
      if (!is_array($commands)) {
        throw new \InvalidArgumentException('dungeon_command_list_invalid');
      }
      $profile = $body['profile'] ?? 'editing';
      if (!is_string($profile)) {
        throw new \InvalidArgumentException('validation_profile_invalid');
      }
      return new JsonResponse(['data' => $this->dungeonEditor->simulateCommands($draft_id, $commands, $profile)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Validates a draft at `?profile=editing|publication`.
   */
  public function validate(string $draft_id, Request $request): JsonResponse {
    try {
      $profile = $request->query->get('profile', 'editing');
      if (!is_string($profile)) {
        throw new \InvalidArgumentException('validation_profile_invalid');
      }
      return new JsonResponse(['data' => $this->dungeonEditor->validateDraft($draft_id, $profile)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Returns the published room library for the author drawer.
   */
  public function rooms(): JsonResponse {
    try {
      return new JsonResponse(['data' => $this->dungeonEditor->roomLibrary()]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  private function draftUrl(string $route): string {
    return str_replace(
      self::DRAFT_PLACEHOLDER,
      '{draft_id}',
      Url::fromRoute($route, ['draft_id' => self::DRAFT_PLACEHOLDER])->toString()
    );
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
   * Maps domain failures to the stable codes in 12-api-and-error-contracts.md.
   */
  private function errorResponse(\Throwable $exception): JsonResponse {
    $code = $exception->getMessage();
    $error = ['code' => $code, 'message' => str_replace('_', ' ', ucfirst(strtok($code, ':')))];
    if ($exception instanceof DungeonEditorFindingsInterface) {
      $error['findings'] = $exception->getFindings();
    }
    $status = match (TRUE) {
      $exception instanceof \JsonException,
      $exception instanceof \InvalidArgumentException => 400,
      $exception instanceof \OutOfBoundsException => 404,
      $exception instanceof \UnexpectedValueException => 403,
      $exception instanceof \RuntimeException && in_array($code, ['revision_conflict', 'idempotency_conflict', 'base_version_conflict'], TRUE) => 409,
      $exception instanceof \DomainException => 422,
      default => 500,
    };
    if ($status === 500) {
      $this->getLogger('dungeoncrawler_content')->error('Dungeon Editor failure: @message', ['@message' => $exception->getMessage()]);
      $error = ['code' => 'dungeon_editor_internal_error', 'message' => 'Dungeon editor internal error'];
    }
    return new JsonResponse(['error' => $error], $status);
  }

}
