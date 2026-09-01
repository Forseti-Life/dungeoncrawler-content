<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Access\CsrfTokenGenerator;

/**
 * Canonical Room Editor page and JSON API.
 */
class RoomEditorController extends ControllerBase {

  public function __construct(
    protected RoomEditorService $roomEditor,
    protected CsrfTokenGenerator $csrfToken,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.room_editor'),
      $container->get('csrf_token'),
    );
  }

  /**
   * Renders the editor shell.
   */
  public function page(?string $room_id = NULL): array {
    $placeholder = '00000000-0000-4000-8000-000000000000';
    return [
      '#theme' => 'room_editor',
      '#rooms' => $this->roomEditor->listRooms(),
      '#selected_room_id' => $room_id,
      '#attached' => [
        'library' => ['dungeoncrawler_content/room-editor'],
        'drupalSettings' => [
          'dungeoncrawlerContent' => [
            'roomEditor' => [
              'rooms' => $this->roomEditor->listRooms(),
              'selectedRoomId' => $room_id,
              'csrfToken' => $this->csrfToken->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY),
              'urls' => [
                'create' => Url::fromRoute('dungeoncrawler_content.room_editor_draft_create')->toString(),
                'draft' => str_replace($placeholder, '{draft_id}', Url::fromRoute('dungeoncrawler_content.room_editor_draft_get', ['draft_id' => $placeholder])->toString()),
                'command' => str_replace($placeholder, '{draft_id}', Url::fromRoute('dungeoncrawler_content.room_editor_command', ['draft_id' => $placeholder])->toString()),
                'validate' => str_replace($placeholder, '{draft_id}', Url::fromRoute('dungeoncrawler_content.room_editor_validate', ['draft_id' => $placeholder])->toString()),
                'publish' => str_replace($placeholder, '{draft_id}', Url::fromRoute('dungeoncrawler_content.room_editor_publish', ['draft_id' => $placeholder])->toString()),
                'catalog' => Url::fromRoute('dungeoncrawler_content.room_editor_catalog')->toString(),
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Creates a new or room-based draft.
   */
  public function createDraft(Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $room_id = array_key_exists('room_id', $body) && $body['room_id'] !== NULL
        ? (string) $body['room_id']
        : NULL;
      return new JsonResponse(['data' => $this->roomEditor->createDraft($room_id)], 201);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Returns one draft.
   */
  public function getDraft(string $draft_id): JsonResponse {
    try {
      return new JsonResponse(['data' => $this->roomEditor->getDraft($draft_id)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Applies one room editing command.
   */
  public function command(string $draft_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      return new JsonResponse(['data' => $this->roomEditor->applyCommand($draft_id, $body)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Validates one draft.
   */
  public function validateRoom(string $draft_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $profile = (string) ($body['profile'] ?? 'editing');
      if (!in_array($profile, ['editing', 'preview', 'publication'], TRUE)) {
        throw new \InvalidArgumentException('validation_profile_invalid');
      }
      return new JsonResponse(['data' => $this->roomEditor->validateDraft($draft_id, $profile)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Publishes one immutable room version.
   */
  public function publish(string $draft_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      return new JsonResponse([
        'data' => $this->roomEditor->publish($draft_id, $this->decodeBody($request)),
      ], 201);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Returns normalized placeable object definitions.
   */
  public function catalog(Request $request): JsonResponse {
    try {
      $family = trim((string) $request->query->get('family', '')) ?: NULL;
      $search = trim((string) $request->query->get('search', ''));
      $limit = (int) $request->query->get('limit', 100);
      $offset = (int) $request->query->get('offset', 0);
      return new JsonResponse([
        'data' => $this->roomEditor->catalog($family, $search, $limit, $offset),
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
    $body = json_decode($request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($body) || array_is_list($body)) {
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
   * Maps domain failures to stable API errors.
   */
  private function errorResponse(\Throwable $exception): JsonResponse {
    $code = $exception->getMessage() ?: 'room_editor_error';
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
      $this->getLogger('dungeoncrawler_content')->error('Room Editor failure: @message', [
        '@message' => $exception->getMessage(),
      ]);
      $code = 'room_editor_internal_error';
    }
    return new JsonResponse([
      'error' => [
        'code' => $code,
        'message' => str_replace('_', ' ', ucfirst($code)),
      ],
    ], $status);
  }

}
