<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Exception\StorylineDraftValidationException;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * JSON API for lightweight storyline authoring drafts.
 */
class StorylineAuthoringDraftController extends ControllerBase {

  public function __construct(
    protected StorylineAuthoringDraftService $drafts,
    protected CsrfTokenGenerator $csrfToken,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.storyline_authoring_drafts'),
      $container->get('csrf_token'),
    );
  }

  public function createDraft(Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $template_id = array_key_exists('template_id', $body) ? (string) $body['template_id'] : '';
      return new JsonResponse(['data' => $this->drafts->createDraft($template_id)], 201);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function listDrafts(): JsonResponse {
    try {
      return new JsonResponse(['data' => $this->drafts->listStorylineOptions()]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function getDraft(string $draft_id): JsonResponse {
    try {
      return new JsonResponse(['data' => $this->drafts->getDraftForViewing($draft_id)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function listDraftRevisions(string $draft_id): JsonResponse {
    try {
      return new JsonResponse(['data' => $this->drafts->listDraftRevisions($draft_id)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function getDraftRevision(string $draft_id, int $revision): JsonResponse {
    try {
      return new JsonResponse(['data' => $this->drafts->getDraftRevisionForViewing($draft_id, $revision)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function copyDraft(string $draft_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $revision = $body['revision'] ?? NULL;
      if ($revision !== NULL && (!is_int($revision) || $revision < 1)) {
        throw new \InvalidArgumentException('storyline_draft_revision_invalid');
      }
      return new JsonResponse(['data' => $this->drafts->copyDraft($draft_id, $revision)], 201);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function saveDraft(string $draft_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      if (!is_int($body['expected_revision'] ?? NULL)) {
        throw new \InvalidArgumentException('expected_revision_required');
      }
      if (!is_array($body['payload'] ?? NULL)) {
        throw new \InvalidArgumentException('storyline_draft_payload_required');
      }
      return new JsonResponse(['data' => $this->drafts->saveDraft(
        $draft_id,
        (int) $body['expected_revision'],
        $body['payload']
      )]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function deleteDraft(string $draft_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      return new JsonResponse(['data' => $this->drafts->deleteDraft($draft_id)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function publishDraft(string $draft_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      if (!is_int($body['expected_revision'] ?? NULL)) {
        throw new \InvalidArgumentException('expected_revision_required');
      }
      return new JsonResponse(['data' => $this->drafts->publishDraft(
        $draft_id,
        (int) $body['expected_revision']
      )], 201);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function validateDraft(string $draft_id, Request $request): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $expected_revision = array_key_exists('expected_revision', $body) ? (int) ($body['expected_revision']) : NULL;
      if ($expected_revision !== NULL) {
        $draft = $this->drafts->getDraft($draft_id);
        if ((int) ($draft['revision'] ?? 0) !== $expected_revision) {
          throw new \DomainException('storyline_draft_revision_conflict');
        }
      }
      return new JsonResponse(['data' => $this->drafts->validateDraft($draft_id)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  private function decodeBody(Request $request): array {
    if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
      throw new \InvalidArgumentException('content_type_must_be_json');
    }
    $decoded = json_decode($request->getContent(), FALSE, 512, JSON_THROW_ON_ERROR);
    if (!is_object($decoded)) {
      throw new \InvalidArgumentException('json_object_required');
    }
    return (array) $decoded;
  }

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

  private function errorResponse(\Throwable $exception): JsonResponse {
    $code = $exception->getMessage() ?: 'storyline_draft_error';
    $status = match (TRUE) {
      $exception instanceof \JsonException,
      $exception instanceof \InvalidArgumentException => 400,
      $exception instanceof \OutOfBoundsException => 404,
      $exception instanceof \UnexpectedValueException => 403,
      $exception instanceof StorylineDraftValidationException => 422,
      $exception instanceof \DomainException => 409,
      default => 500,
    };
    if ($status === 500) {
      $this->getLogger('dungeoncrawler_content')->error('Storyline draft API failure: @message', [
        '@message' => $exception->getMessage(),
      ]);
      $code = 'storyline_draft_internal_error';
    }
    $error = [
      'code' => $code,
      'message' => str_replace('_', ' ', ucfirst($code)),
    ];
    if ($exception instanceof StorylineDraftValidationException) {
      $error['findings'] = $exception->findings;
    }
    return new JsonResponse(['error' => $error], $status);
  }

}
