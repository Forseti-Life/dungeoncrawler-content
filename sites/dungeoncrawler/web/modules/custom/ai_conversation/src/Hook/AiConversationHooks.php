<?php

namespace Drupal\ai_conversation\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Hook implementations for ai_conversation.
 */
final class AiConversationHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    if ($entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'ai_conversation') {
      return [];
    }

    return [
      'chat' => [
        'title' => $this->t('GM Chat'),
        'url' => Url::fromRoute('ai_conversation.chat_interface', ['node' => $entity->id()]),
        'weight' => 50,
      ],
    ];
  }

}
