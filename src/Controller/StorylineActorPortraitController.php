<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Generates a storyline actor portrait through the character image authority.
 */
final class StorylineActorPortraitController extends ControllerBase {

  public function __construct(
    private readonly StorylineAuthoringDraftService $drafts,
    private readonly CharacterManager $characters,
    private readonly CharacterPortraitGenerationService $portraits,
    private readonly AccountProxyInterface $account,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('dungeoncrawler_content.storyline_authoring_drafts'),
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.character_portrait_generator'),
      $container->get('current_user'),
    );
  }

  public function generate(string $draft_id, string $actor_id): JsonResponse {
    $draft = $this->drafts->getDraftForViewing($draft_id);
    $actor = NULL;
    foreach ((array) ($draft['payload']['objects']['actors']['generation_specs'] ?? []) as $candidate) {
      if (is_array($candidate) && (string) ($candidate['actor_id'] ?? '') === $actor_id) {
        $actor = $candidate;
        break;
      }
    }
    if ($actor === NULL || (int) ($actor['character_id'] ?? 0) <= 0) {
      throw new NotFoundHttpException('storyline_actor_sheet_not_found');
    }

    $character_id = (int) $actor['character_id'];
    $record = $this->characters->loadCharacter($character_id);
    if (!$record) {
      throw new NotFoundHttpException('character_not_found');
    }
    if (!$this->characters->isOwner($record)
      && !$this->account->hasPermission('administer site configuration')) {
      throw new AccessDeniedHttpException('character_portrait_access_denied');
    }

    $character_data = $this->characters->getCharacterData($record);
    $result = $this->portraits->generatePortrait(
      $character_data,
      $character_id,
      (int) $this->account->id(),
      NULL,
      [
        'generate' => TRUE,
        'force_regenerate' => TRUE,
        'replace_existing' => TRUE,
        'user_prompt' => (string) ($character_data['portrait_prompt'] ?? ''),
      ],
    );
    if (empty($result['storage']['stored'])) {
      $reason = (string) ($result['storage']['reason'] ?? $result['reason'] ?? 'portrait_generation_failed');
      $provider_message = trim((string) (
        $result['storage']['message']
        ?? $result['result']['message']
        ?? ''
      ));
      return new JsonResponse([
        'error' => [
          'code' => $reason,
          'message' => $provider_message !== ''
            ? sprintf('Portrait was not stored (%s). %s', $reason, $provider_message)
            : sprintf('Portrait was not stored (%s).', $reason),
        ],
      ], 503);
    }

    return new JsonResponse([
      'data' => [
        'actor_id' => $actor_id,
        'character_id' => $character_id,
        'portrait_url' => (string) ($result['storage']['url'] ?? ''),
      ],
    ]);
  }

}
