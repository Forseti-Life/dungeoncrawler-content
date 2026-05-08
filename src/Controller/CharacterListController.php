<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the /characters page — lists all characters for the user.
 */
class CharacterListController extends ControllerBase {

  protected CharacterManager $characterManager;
  protected Connection $database;
  protected GeneratedImageRepository $imageRepository;

  public function __construct(CharacterManager $character_manager, Connection $database, GeneratedImageRepository $image_repository) {
    $this->characterManager = $character_manager;
    $this->database = $database;
    $this->imageRepository = $image_repository;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('database'),
      $container->get('dungeoncrawler_content.generated_image_repository'),
    );
  }

  /**
   * Renders the character list page.
   */
  public function listCharacters(int $campaign_id) {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'name', 'uid'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchObject();

    if (!$campaign || (int) $campaign->uid !== (int) $this->currentUser()->id()) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $campaign_name = $campaign->name;
    $characters = $this->characterManager->getUserCharacters(NULL, $campaign_id);

    $character_cards = [];
    foreach ($characters as $record) {
      $data = $this->characterManager->getCharacterData($record);
      $char = $data['character'] ?? [];
      $hot = $this->characterManager->resolveHotColumnsForRecord($record, $data);

      $view_url = Url::fromRoute('dungeoncrawler_content.character_view', ['character_id' => $record->id]);
      $view_url->setOption('query', ['campaign_id' => $campaign_id]);

      $select_url = NULL;
      $continue_url = NULL;
      $archive_url = NULL;
      $status = (int) $record->status;
      $step = (int) ($char['step'] ?? 8);

      // Campaign selection only allows completed characters.
      if ($status === 1 && $step >= 8) {
        $select_url = Url::fromRoute('dungeoncrawler_content.campaign_select_character', [
          'campaign_id' => $campaign_id,
          'character_id' => $record->id,
        ])->toString();
      }
      // Incomplete characters can continue creation.
      elseif ($status === 0 || $step < 8) {
        $continue_url = Url::fromRoute('dungeoncrawler_content.character_step', [
          'step' => $step,
        ], [
          'query' => ['character_id' => (int) $record->id],
        ])->toString();
      }

      // Non-archived characters can be archived from the roster.
      if ($status !== 2) {
        $destination = Url::fromRoute('dungeoncrawler_content.characters', [
          'campaign_id' => $campaign_id,
        ])->toString();
        $archive_url = Url::fromRoute('dungeoncrawler_content.character_archive', [
          'character_id' => (int) $record->id,
        ], [
          'query' => ['destination' => $destination],
        ])->toString();
      }

      // Load portrait from generated images
      $portraits = $this->imageRepository->loadImagesForObject(
        'dc_campaign_characters',
        (string) $record->id,
        NULL,
        'portrait',
        'original'
      );
      $portrait_url = NULL;
      if (!empty($portraits)) {
        $portrait_url = $this->imageRepository->resolveClientUrl($portraits[0]);
      }

      $character_cards[] = [
        'id' => $record->id,
        'uuid' => $record->uuid,
        'name' => $record->name,
        'level' => $record->level,
        'ancestry' => $record->ancestry,
        'class' => $record->class,
        'hp_current' => $hot['hp_current'],
        'hp_max' => $hot['hp_max'],
        'ac' => $hot['armor_class'],
        'status' => $this->getCharacterStatusClass($record, $char),
        'portrait' => $portrait_url,
        'heritage' => $char['ancestry']['heritage'] ?? '',
        'alignment' => $char['personality']['alignment'] ?? '',
        'url' => $view_url->toString(),
        'select_url' => $select_url,
        'step' => $step,
        'continue_url' => $continue_url,
        'archive_url' => $archive_url,
        'created' => date('M j, Y', $record->created),
      ];
    }

    $create_url = Url::fromRoute('dungeoncrawler_content.character_creation_wizard');
    $create_url->setOption('query', ['campaign_id' => $campaign_id]);

    $build = [
      '#theme' => 'character_list',
      '#characters' => $character_cards,
      '#create_url' => $create_url->toString(),
      '#create_campaign_url' => Url::fromRoute('dungeoncrawler_content.campaign_create')->toString(),
      '#campaign_id' => $campaign_id,
      '#campaign_name' => $campaign_name,
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
      '#cache' => [
        'contexts' => ['user', 'url.path'],
        'tags' => ['dc_campaign_characters'],
      ],
    ];

    return $build;
  }

  /**
   * Determine character status class for consistent UI state.
   * 
   * @param object $record
   *   Character database record.
   * @param array $char
   *   Decoded character_data JSON.
   * 
   * @return string
   *   Status class: 'active', 'incomplete', or 'archived'.
   */
  private function getCharacterStatusClass(object $record, array $char): string {
    $status = (int) $record->status;
    $step = (int) ($char['step'] ?? 8);
    
    // Status values:
    // 0 = incomplete/draft
    // 1 = complete/active
    // 2 = archived
    if ($status === 2) {
      return 'archived';
    }
    elseif ($status === 1 && $step >= 8) {
      return 'active';
    }
    else {
      return 'incomplete';
    }
  }

}
