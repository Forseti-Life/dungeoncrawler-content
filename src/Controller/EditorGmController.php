<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * JSON transport for the editor-embedded GM harness.
 *
 * This controller is the only browser-facing entrypoint for the editor GM
 * assistant on every surface. Each route binds its surface through the
 * `_surface` default, so the same controller serves the Room Editor and the
 * Dungeon Editor without either being able to reach the other's toolset. It
 * never reaches campaign runtime authority; all work resolves through
 * EditorGmHarnessService and, beneath it, the surface's editor authority.
 */
class EditorGmController extends ControllerBase {

  public function __construct(
    protected EditorGmHarnessService $harness,
    protected CsrfTokenGenerator $csrfToken,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.editor_gm_harness'),
      $container->get('csrf_token'),
    );
  }

  /**
   * Returns the grounded context snapshot and declared toolset for a draft.
   */
  public function describe(string $_surface, Request $request, ?string $draft_id = NULL): JsonResponse {
    try {
      $profile = trim((string) $request->query->get('profile', 'editing')) ?: 'editing';
      return new JsonResponse(['data' => $this->harness->describe($_surface, $draft_id, $profile)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Executes one editor GM request envelope.
   */
  public function execute(string $_surface, Request $request, ?string $draft_id = NULL): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      return new JsonResponse(['data' => $this->harness->handle($_surface, $draft_id, $this->decodeBody($request))]);
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
    $code = $exception->getMessage() ?: 'editor_gm_error';
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
      $this->getLogger('dungeoncrawler_content')->error('Editor GM harness failure: @message', [
        '@message' => $exception->getMessage(),
      ]);
      $code = 'editor_gm_internal_error';
    }
    return new JsonResponse([
      'error' => [
        'code' => $code,
        'message' => str_replace('_', ' ', ucfirst($code)),
      ],
    ], $status);
  }

}
