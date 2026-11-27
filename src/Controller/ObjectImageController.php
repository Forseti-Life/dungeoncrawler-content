<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\ObjectImageService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ObjectImageController extends ControllerBase {

  public function __construct(
    private readonly ObjectImageService $images,
    private readonly AccountProxyInterface $account,
    private readonly CsrfTokenGenerator $csrfToken,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('dungeoncrawler_content.object_image'),
      $container->get('current_user'),
      $container->get('csrf_token'),
    );
  }

  public function image(string $kind, string $object_id, Request $request): JsonResponse {
    $allowed = match ($kind) {
      'room' => $this->account->hasPermission('edit canonical dungeoncrawler rooms'),
      'dungeon' => $this->account->hasPermission('edit canonical dungeoncrawler dungeons'),
      'institution', 'storyline' => $this->account->hasPermission('edit canonical dungeoncrawler storylines')
        || $this->account->hasPermission('edit canonical dungeoncrawler definitions'),
      default => $this->account->hasPermission('edit canonical dungeoncrawler definitions'),
    };
    if (!$allowed) {
      return new JsonResponse(['error' => [
        'code' => 'object_image_access_denied',
        'message' => 'You do not have permission to edit this image target.',
      ]], 403);
    }
    try {
      if ($request->isMethod('GET')) {
        $versions = $this->images->versions($kind, $object_id);
        return new JsonResponse(['data' => [
          'kind' => $kind,
          'object_id' => $object_id,
          'preview_url' => (string) ($versions[0]['url'] ?? ''),
          'versions' => $versions,
        ]]);
      }
      $token = (string) $request->headers->get('X-CSRF-Token');
      if ($token === '' || !$this->csrfToken->validate($token, CsrfRequestHeaderAccessCheck::TOKEN_KEY)) {
        return new JsonResponse(['error' => [
          'code' => 'csrf_token_invalid',
          'message' => 'A valid CSRF token is required.',
        ]], 403);
      }
      $body = json_decode((string) $request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($body)) {
        throw new \InvalidArgumentException('object_image_request_invalid');
      }
      $stored = ($body['action'] ?? '') === 'promote'
        ? $this->images->promote(
          $kind,
          $object_id,
          trim((string) ($body['image_uuid'] ?? '')),
        )
        : $this->images->generate(
          $kind,
          $object_id,
          trim((string) ($body['prompt'] ?? '')),
          (int) $this->account->id(),
        );
      if (empty($stored['stored'])) {
        return new JsonResponse(['error' => [
          'code' => (string) ($stored['reason'] ?? 'object_image_failed'),
          'message' => (string) ($stored['message'] ?? 'Image generation did not store an image.'),
        ]], 503);
      }
      return new JsonResponse(['data' => [
        'kind' => $kind,
        'object_id' => $object_id,
        'preview_url' => (string) ($stored['url'] ?? ''),
        'version_number' => (int) ($stored['version_number'] ?? 1),
        'versions' => (array) ($stored['versions'] ?? []),
      ]]);
    }
    catch (\LogicException $exception) {
      return new JsonResponse(['error' => [
        'code' => $exception->getMessage(),
        'message' => str_replace('_', ' ', $exception->getMessage()),
      ]], 503);
    }
    catch (\InvalidArgumentException | \JsonException $exception) {
      return new JsonResponse(['error' => [
        'code' => $exception->getMessage(),
        'message' => str_replace('_', ' ', $exception->getMessage()),
      ]], 400);
    }
    catch (\OutOfBoundsException $exception) {
      return new JsonResponse(['error' => [
        'code' => $exception->getMessage(),
        'message' => str_replace('_', ' ', $exception->getMessage()),
      ]], 404);
    }
  }

}
