<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Form\CharacterPortraitRegenerateForm;
use Drupal\dungeoncrawler_content\Form\CharacterPortraitUploadForm;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\FeatEffectManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for viewing a single character's full PF2e sheet.
 */
class CharacterViewController extends ControllerBase {

  protected CharacterManager $characterManager;
  protected FeatEffectManager $featEffectManager;
  protected GeneratedImageRepository $imageRepository;
  protected Connection $database;
  protected TimeInterface $time;

  public function __construct(CharacterManager $character_manager, FeatEffectManager $feat_effect_manager, GeneratedImageRepository $image_repository, Connection $database, TimeInterface $time) {
    $this->characterManager = $character_manager;
    $this->featEffectManager = $feat_effect_manager;
    $this->imageRepository = $image_repository;
    $this->database = $database;
    $this->time = $time;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.feat_effect_manager'),
      $container->get('dungeoncrawler_content.generated_image_repository'),
      $container->get('database'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Renders a full character sheet.
   */
  public function viewCharacter(int $character_id) {
    $campaign_id = (int) (\Drupal::request()->query->get('campaign_id') ?? 0);

    $record = $this->characterManager->loadCharacter($character_id);

    if (!$record) {
      throw new NotFoundHttpException();
    }

    if (!$this->characterManager->isOwner($record) && !$this->currentUser()->hasPermission('administer site configuration')) {
      throw new AccessDeniedHttpException();
    }

    // Decode character data via manager and normalize onto the canonical shape
    // used by the character sheet.
    $decoded = $this->characterManager->getCharacterData($record);
    $char_data = $this->characterManager->canonicalizeCharacterData($decoded);
    $hot = $this->characterManager->resolveHotColumnsForRecord($record, $char_data);

    $abilities = $this->buildAbilityDisplayData($char_data);

    // Calculate derived stats
    $level = $char_data['level'] ?? $record->level ?? 1;
    $con_mod = $abilities['constitution']['modifier'];
    
    $ac = (int) ($char_data['armor_class'] ?? $hot['armor_class']);
    
    $hit_points = is_array($char_data['hit_points'] ?? NULL) ? $char_data['hit_points'] : [];
    $max_hp = (int) ($hit_points['max'] ?? $hot['hp_max']);
    
    $prof_bonus = $level + 2;
    $stored_saves = is_array($char_data['saves'] ?? NULL) ? $char_data['saves'] : [];
    $saves = [
      'Fortitude' => [
        'modifier' => (int) ($stored_saves['fortitude'] ?? ($con_mod + $prof_bonus)),
        'proficiency' => 'Trained',
      ],
      'Reflex' => [
        'modifier' => (int) ($stored_saves['reflex'] ?? ($abilities['dexterity']['modifier'] + $prof_bonus)),
        'proficiency' => 'Trained',
      ],
      'Will' => [
        'modifier' => (int) ($stored_saves['will'] ?? ($abilities['wisdom']['modifier'] + $prof_bonus)),
        'proficiency' => 'Trained',
      ],
    ];

    $perception = [
      'modifier' => (int) ($char_data['perception'] ?? ($abilities['wisdom']['modifier'] + $prof_bonus)),
      'proficiency' => 'Trained',
      'senses' => [],
    ];

    // Basic skills (all untrained unless specified)
    $skill_list = [
      'Acrobatics' => 'dexterity',
      'Arcana' => 'intelligence',
      'Athletics' => 'strength',
      'Crafting' => 'intelligence',
      'Deception' => 'charisma',
      'Diplomacy' => 'charisma',
      'Intimidation' => 'charisma',
      'Lore' => 'intelligence',
      'Medicine' => 'wisdom',
      'Nature' => 'wisdom',
      'Occultism' => 'intelligence',
      'Performance' => 'charisma',
      'Religion' => 'wisdom',
      'Society' => 'intelligence',
      'Stealth' => 'dexterity',
      'Survival' => 'wisdom',
      'Thievery' => 'dexterity',
    ];

    $skills = [];
    foreach ($skill_list as $skill_name => $ability_key) {
      $skills[] = [
        'name' => $skill_name,
        'modifier' => $abilities[$ability_key]['modifier'],
        'proficiency' => 'Untrained',
      ];
    }

    $launch_url = Url::fromRoute('dungeoncrawler_content.hexmap_demo')
      ->setOption('query', ['character_id' => $record->id]);
    $tavern_url = NULL;
    if ($campaign_id > 0) {
      $launch_url->setOption('query', [
        'campaign_id' => $campaign_id,
        'character_id' => $record->id,
      ]);
      $tavern_url = Url::fromRoute('dungeoncrawler_content.campaign_tavernentrance', [
        'campaign_id' => $campaign_id,
      ])->toString();
    }

    if ($campaign_id > 0) {
      $back_url = Url::fromRoute('dungeoncrawler_content.characters', ['campaign_id' => $campaign_id]);
    }
    else {
      $back_url = Url::fromRoute('dungeoncrawler_content.campaigns');
    }

    $ancestry_name = is_array($char_data['ancestry'] ?? NULL)
      ? ($char_data['ancestry']['name'] ?? 'Unknown')
      : (CharacterManager::resolveAncestryCanonicalName((string) ($char_data['ancestry'] ?? '')) ?: $this->humanizeName((string) ($char_data['ancestry'] ?? 'Unknown')));
    $heritage = is_array($char_data['ancestry'] ?? NULL)
      ? ($char_data['ancestry']['heritage'] ?? NULL)
      : (!empty($char_data['heritage']) ? $this->humanizeName((string) $char_data['heritage']) : NULL);
    $size = is_array($char_data['ancestry'] ?? NULL)
      ? ($char_data['ancestry']['size'] ?? 'Medium')
      : ($char_data['size'] ?? 'Medium');
    $base_speed = is_array($char_data['ancestry'] ?? NULL)
      ? ($char_data['ancestry']['speed'] ?? 25)
      : ($char_data['speed'] ?? 25);
    $languages = is_array($char_data['ancestry'] ?? NULL)
      ? ($char_data['ancestry']['languages'] ?? [])
      : ($char_data['languages'] ?? []);

    $class_name = is_array($char_data['class'] ?? NULL)
      ? ($char_data['class']['name'] ?? 'Unknown')
      : $this->humanizeName((string) ($char_data['class'] ?? 'Unknown'));
    $class_subclass = is_array($char_data['class'] ?? NULL)
      ? ($char_data['class']['subclass'] ?? NULL)
      : (!empty($char_data['subclass']) ? $this->humanizeName((string) $char_data['subclass']) : NULL);
    $class_key_ability = is_array($char_data['class'] ?? NULL)
      ? ($char_data['class']['key_ability'] ?? 'STR')
      : strtoupper((string) ($char_data['class_key_ability'] ?? $char_data['spells']['casting_ability'] ?? 'STR'));
    $class_hp_per_level = is_array($char_data['class'] ?? NULL)
      ? ((int) ($char_data['class']['hp_per_level'] ?? 8))
      : 8;

    $feat_effects = $this->featEffectManager->buildEffectState($char_data, [
      'level' => (int) $level,
      'base_speed' => (int) $base_speed,
      'existing_hp_max' => (int) $max_hp,
    ]);

    $perception['modifier'] += (int) ($feat_effects['derived_adjustments']['perception_bonus'] ?? 0);
    $perception['senses'] = array_map(static function (array $sense): string {
      return (string) ($sense['name'] ?? '');
    }, $feat_effects['senses'] ?? []);

    $max_hp += (int) ($feat_effects['derived_adjustments']['hp_max_bonus'] ?? 0);
    $speed = (int) ($feat_effects['derived_adjustments']['computed_speed'] ?? $base_speed);

    // Read inventory data (structured format from Step 7).
    $inventory = $char_data['inventory'] ?? [];
    $equipment_items = $this->enrichEquipmentItems($inventory['carried'] ?? []);
    $inv_currency = $inventory['currency'] ?? [];
    $equipment_gold = (float) ($inv_currency['gp'] ?? ($char_data['gold'] ?? 15));

    // Load portrait: try generated images first, fall back to DB column.
    $portrait_url = NULL;
    $portraits = $this->imageRepository->loadImagesForObject(
      'dc_campaign_characters',
      (string) $record->id,
      $campaign_id > 0 ? $campaign_id : NULL,
      'portrait',
      'original'
    );
    if (!empty($portraits)) {
      $portrait_url = $this->imageRepository->resolveClientUrl($portraits[0]);
    }
    // Fall back to global (non-campaign-scoped) image link.
    if (!$portrait_url && $campaign_id > 0) {
      $global = $this->imageRepository->loadImagesForObject(
        'dc_campaign_characters',
        (string) $record->id,
        NULL,
        'portrait',
        'original'
      );
      if (!empty($global)) {
        $portrait_url = $this->imageRepository->resolveClientUrl($global[0]);
      }
    }
    // Final fallback: portrait column on the record itself.
    if (!$portrait_url && !empty($record->portrait)) {
      $portrait_url = $record->portrait;
    }

    $alignment = is_array($char_data['personality'] ?? NULL)
      ? ($char_data['personality']['alignment'] ?? NULL)
      : ($char_data['alignment'] ?? NULL);
    $deity = is_array($char_data['personality'] ?? NULL)
      ? ($char_data['personality']['deity'] ?? NULL)
      : ($char_data['deity'] ?? NULL);
    $appearance = is_array($char_data['personality'] ?? NULL)
      ? ($char_data['personality']['appearance'] ?? NULL)
      : ($char_data['appearance'] ?? NULL);
    $personality = is_array($char_data['personality'] ?? NULL)
      ? ($char_data['personality']['traits'][0] ?? NULL)
      : ($char_data['personality'] ?? NULL);
    $backstory = is_array($char_data['personality'] ?? NULL)
      ? ($char_data['personality']['backstory'] ?? NULL)
      : ($char_data['backstory'] ?? NULL);

    $setup_query = ['character_id' => (int) $record->id];
    if ($campaign_id > 0) {
      $setup_query['campaign_id'] = $campaign_id;
    }

    $continue_query = $setup_query;
    $continue_query['step'] = max(1, (int) ($char_data['step'] ?? 1));

    $build = [
      '#theme' => 'character_sheet',
      '#character' => [
        'id' => $record->id,
        'uuid' => $record->uuid,
        'name' => $char_data['name'] ?? $record->name,
        'level' => $level,
        'xp' => (int) ($record->experience_points ?? $char_data['experience_points'] ?? 0),
        'hero_points' => $char_data['hero_points'] ?? 1,
        'status' => $record->status ? 'active' : 'incomplete',
        'portrait' => $portrait_url,
        'step' => $char_data['step'] ?? 1,
      ],
      '#char_data' => $char_data,
      '#ancestry' => [
        'name' => $ancestry_name,
        'heritage' => $heritage,
        'size' => $size,
        'speed' => $speed,
        'languages' => $languages,
        'traits' => [],
      ],
      '#background' => [
        'name' => !empty($char_data['background']) ? $this->humanizeName((string) $char_data['background']) : 'Unknown',
      ],
      '#class_data' => [
        'name' => $class_name,
        'subclass' => $class_subclass,
        'key_ability' => $class_key_ability,
        'hp_per_level' => $class_hp_per_level,
        'class_features' => [],
        'class_feats' => [],
      ],
      '#abilities' => $abilities,
      '#hp' => [
        'max' => $max_hp,
        'current' => (int) ($hit_points['current'] ?? $hot['hp_current']),
        'temporary' => (int) ($hit_points['temp'] ?? 0),
      ],
      '#ac' => $ac,
      '#saves' => $saves,
      '#perception' => $perception,
      '#skills' => $skills,
      '#melee_attacks' => [],
      '#ranged_attacks' => [],
      '#equipment' => [
        'gold' => $equipment_gold,
        'items' => $equipment_items,
      ],
      '#feats' => $this->enrichFeatDisplayData($char_data['feats'] ?? []),
      '#spells' => $this->buildSpellsDisplayData($char_data, $feat_effects),
      '#conditions' => $char_data['conditions'] ?? [],
      '#feat_effects' => $feat_effects,
      '#personality' => [
        'alignment' => $alignment,
        'deity' => $deity,
        'age' => $char_data['age'] ?? NULL,
        'gender' => $char_data['gender'] ?? NULL,
        'appearance' => $appearance,
        'personality' => $personality,
        'backstory' => $backstory,
      ],
      '#npc_data' => NULL,
      '#raw_json' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
      '#portrait_upload_form' => $this->formBuilder()->getForm(CharacterPortraitUploadForm::class, (int) $record->id, $campaign_id),
      '#portrait_regenerate_form' => $this->formBuilder()->getForm(CharacterPortraitRegenerateForm::class, (int) $record->id, $campaign_id),
      '#edit_url' => Url::fromRoute('dungeoncrawler_content.character_setup', [], ['query' => $setup_query])->toString(),
      '#continue_url' => Url::fromRoute('dungeoncrawler_content.character_setup', [], ['query' => $continue_query])->toString(),
      '#archive_url' => Url::fromRoute('dungeoncrawler_content.character_archive', ['character_id' => $record->id])->toString(),
      '#launch_url' => $launch_url->toString(),
      '#tavern_url' => $tavern_url,
      '#campaign_id' => $campaign_id,
      '#back_url' => $back_url->toString(),
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;
  }

  /**
   * Renders character sheet markup only for iframe embedding (no site chrome).
   */
  public function viewCharacterEmbed(int $character_id): Response {
    $build = $this->viewCharacter($character_id);
    if (is_array($build)) {
      $build['#embed_mode'] = TRUE;
      unset($build['#attached']);
    }

    $sheet_markup = (string) \Drupal::service('renderer')->renderRoot($build);
    $module_path = '/' . \Drupal::service('extension.list.module')->getPath('dungeoncrawler_content');
    $css_url = $module_path . '/css/character-sheet.css';
    $js_url = $module_path . '/js/character-sheet.js';

    $html = '<!doctype html><html lang="en"><head>'
      . '<meta charset="utf-8">'
      . '<meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<link rel="stylesheet" href="' . $css_url . '">'
      . '<style>html,body{margin:0;padding:0;background:#0f172a;} .dc-character-sheet{margin:0;padding:16px;}</style>'
      . '</head><body>' . $sheet_markup
      . '<script src="' . $js_url . '"></script>'
      . '</body></html>';

    return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
  }

  /**
   * Resolves raw spell IDs into display-ready spell data for the template.
   *
   * Converts the stored `spells` structure (cantrips: [id, ...], first_level:
   * [id, ...]) into a `spells_known` array of {name, rank, school} objects
   * grouped by spell level, which the character-sheet.html.twig template
   * expects for rendering.
   *
   * @param array $char_data
   *   Full character data array.
   *
   * @return array|null
   *   Enriched spells data with spells_known, or NULL if not a caster.
   */
  private function buildSpellsDisplayData(array $char_data, array $feat_effects = []): ?array {
    $spells_raw = $char_data['spells'] ?? NULL;
    if (empty($spells_raw) || empty($spells_raw['tradition'])) {
      return NULL;
    }

    $spells_known = [];
    $spell_ids = array_values(array_unique(array_merge(
      $spells_raw['cantrips'] ?? [],
      $spells_raw['first_level'] ?? []
    )));
    $spell_lookup = $this->buildContentMetadataLookup($spell_ids, 'spell');

    // Resolve cantrip IDs → display data (rank 0).
    $cantrip_ids = $spells_raw['cantrips'] ?? [];
    if (!empty($cantrip_ids)) {
      foreach ($cantrip_ids as $id) {
        $spells_known[] = [
          'spell_id' => $id,
          'name' => $spell_lookup[$id]['name'] ?? $this->humanizeName($id),
          'description' => $spell_lookup[$id]['description'] ?? '',
          'rank' => 0,
        ];
      }
    }

    // Resolve 1st-level spell IDs → display data (rank 1).
    $first_ids = $spells_raw['first_level'] ?? [];
    if (!empty($first_ids)) {
      foreach ($first_ids as $id) {
        $spells_known[] = [
          'spell_id' => $id,
          'name' => $spell_lookup[$id]['name'] ?? $this->humanizeName($id),
          'description' => $spell_lookup[$id]['description'] ?? '',
          'rank' => 1,
        ];
      }
    }

    // Pre-group spells by rank for the template.
    // Twig's |merge reindexes numeric keys, so we group in PHP.
    $by_rank = [];
    foreach ($spells_known as $spell) {
      $rank = $spell['rank'];
      $by_rank[$rank][] = $spell;
    }
    ksort($by_rank);

    // Build slot info per rank.
    $slots = $spells_raw['slots'] ?? [];
    $slot_info = [];
    if (!empty($slots['first'])) {
      $slot_info[1] = [
        'max' => (int) $slots['first'],
        'remaining' => (int) $slots['first'],
      ];
    }

    // Build the rank groups array for the template.
    $rank_groups = [];
    foreach ($by_rank as $rank => $rank_spells) {
      $rank_groups[] = [
        'rank' => (int) $rank,
        'label' => $rank === 0 ? 'Cantrips' : 'Rank ' . $rank,
        'spells' => $rank_spells,
        'slots' => $slot_info[$rank] ?? NULL,
      ];
    }

    // Build enriched spells array for the template.
    $result = $spells_raw;
    $result['spells_known'] = $spells_known;
    $result['rank_groups'] = $rank_groups;
    if (!empty($feat_effects['spell_augments']) && is_array($feat_effects['spell_augments'])) {
      $result['feat_augments'] = $feat_effects['spell_augments'];
    }

    return $result;
  }

  /**
   * Normalizes ability scores from canonical, nested, or flat payloads.
   */
  private function buildAbilityDisplayData(array $char_data): array {
    $abilities = [];
    $short_map = [
      'str' => 'strength',
      'dex' => 'dexterity',
      'con' => 'constitution',
      'int' => 'intelligence',
      'wis' => 'wisdom',
      'cha' => 'charisma',
    ];

    if (!empty($char_data['abilities']) && is_array($char_data['abilities'])) {
      foreach ($short_map as $short => $long) {
        $score = $char_data['abilities'][$long] ?? $char_data['abilities'][$short] ?? 10;
        $score = (int) $score;
        $abilities[$long] = [
          'score' => $score,
          'modifier' => (int) floor(($score - 10) / 2),
        ];
      }
      return $abilities;
    }

    if (!empty($char_data['ability_scores']) && is_array($char_data['ability_scores'])) {
      foreach (array_values($short_map) as $ability) {
        $score = (int) ($char_data['ability_scores'][$ability]['score'] ?? 10);
        $abilities[$ability] = [
          'score' => $score,
          'modifier' => (int) floor(($score - 10) / 2),
        ];
      }
      return $abilities;
    }

    foreach (array_values($short_map) as $ability) {
      $score = (int) ($char_data[$ability] ?? 10);
      $abilities[$ability] = [
        'score' => $score,
        'modifier' => (int) floor(($score - 10) / 2),
      ];
    }

    return $abilities;
  }

  /**
   * Looks up spell display names from the content registry by ID.
   *
   * @param array $ids
   *   Array of content_id strings.
   *
   * @return array
   *   Associative array of content_id => display name.
   */
  private function buildContentMetadataLookup(array $ids, ?string $content_type = NULL): array {
    if (empty($ids)) {
      return [];
    }

    $query = $this->characterManager->getDatabase()
      ->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'name', 'schema_data'])
      ->condition('content_id', $ids, 'IN');

    if ($content_type !== NULL && $content_type !== '') {
      $query->condition('content_type', $content_type);
    }

    $rows = $query->execute()->fetchAll();
    $lookup = [];
    foreach ($rows as $row) {
      $schema = json_decode((string) ($row->schema_data ?? '{}'), TRUE);
      if (!is_array($schema)) {
        $schema = [];
      }

      $lookup[$row->content_id] = [
        'name' => (string) ($row->name ?? ''),
        'description' => trim((string) ($schema['description'] ?? $schema['description_snippet'] ?? '')),
      ];
    }

    return $lookup;
  }

  /**
   * Adds tooltip-ready item descriptions from the content registry.
   */
  private function enrichEquipmentItems(array $items): array {
    $item_ids = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $item_id = (string) ($item['item_id'] ?? $item['id'] ?? '');
      if ($item_id !== '') {
        $item_ids[] = $item_id;
      }
    }

    $lookup = $this->buildContentMetadataLookup(array_values(array_unique($item_ids)), 'item');
    foreach ($items as $index => $item) {
      if (!is_array($item)) {
        continue;
      }

      $item_id = (string) ($item['item_id'] ?? $item['id'] ?? '');
      $description = trim((string) ($item['description'] ?? ''));
      if ($description === '' && $item_id !== '' && !empty($lookup[$item_id]['description'])) {
        $items[$index]['description'] = $lookup[$item_id]['description'];
      }
    }

    return $items;
  }

  /**
   * Adds tooltip-ready feat descriptions from the static feat definitions.
   */
  private function enrichFeatDisplayData(array $feats): array {
    $lookup = $this->buildFeatDefinitionLookup();
    foreach ($feats as $index => $feat) {
      if (!is_array($feat)) {
        continue;
      }

      $feat_id = (string) ($feat['feat_id'] ?? $feat['id'] ?? '');
      $description = trim((string) ($feat['description'] ?? ''));
      if ($description === '' && $feat_id !== '' && !empty($lookup[$feat_id]['benefit'])) {
        $feats[$index]['description'] = (string) $lookup[$feat_id]['benefit'];
      }
      if (!isset($feats[$index]['type']) && isset($feat['feat_type'])) {
        $feats[$index]['type'] = $feat['feat_type'];
      }
      if (!isset($feats[$index]['level']) && isset($feat['level_gained'])) {
        $feats[$index]['level'] = $feat['level_gained'];
      }
    }

    return $feats;
  }

  /**
   * Flattens static feat definitions keyed by feat id.
   */
  private function buildFeatDefinitionLookup(): array {
    $lookup = [];

    foreach (CharacterManager::ANCESTRY_FEATS as $feat_pool) {
      foreach ($feat_pool as $feat) {
        if (!empty($feat['id'])) {
          $lookup[$feat['id']] = $feat;
        }
      }
    }

    foreach (CharacterManager::CLASS_FEATS as $feat_pool) {
      foreach ($feat_pool as $feat) {
        if (!empty($feat['id'])) {
          $lookup[$feat['id']] = $feat;
        }
      }
    }

    foreach (array_merge(CharacterManager::GENERAL_FEATS, CharacterManager::SKILL_FEATS) as $feat) {
      if (!empty($feat['id'])) {
        $lookup[$feat['id']] = $feat;
      }
    }

    return $lookup;
  }

  /**
   * Converts a snake_case content_id into a human-readable name.
   *
   * @param string $id
   *   The content_id string, e.g. 'ray_of_frost'.
   *
   * @return string
   *   Human name, e.g. 'Ray Of Frost'.
   */
  private function humanizeName(string $id): string {
    return ucwords(str_replace(['_', '-'], ' ', $id));
  }

  /**
   * Title callback for character view page.
   */
  public function viewTitle(int $character_id): string {
    $record = $this->characterManager->loadCharacter($character_id);
    return $record ? $record->name : 'Character Not Found';
  }

  /**
   * Archive a character directly without a confirmation form.
   */
  public function archiveCharacter(int $character_id): RedirectResponse {
    $character = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'name', 'uid', 'status', 'character_data', 'campaign_id'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchObject() ?: NULL;

    if (!$character) {
      throw new NotFoundHttpException();
    }

    $current_user = $this->currentUser();
    if (
      (int) $character->uid !== (int) $current_user->id()
      && !$current_user->hasPermission('administer dungeoncrawler content')
    ) {
      throw new AccessDeniedHttpException();
    }

    $destination = \Drupal::request()->query->get('destination');
    if ($destination) {
      $redirect_url = Url::fromUserInput($destination)->toString();
    }
    elseif ((int) ($character->campaign_id ?? 0) > 0) {
      $redirect_url = Url::fromRoute('dungeoncrawler_content.characters', ['campaign_id' => (int) $character->campaign_id])->toString();
    }
    else {
      $redirect_url = Url::fromRoute('dungeoncrawler_content.characters_roster')->toString();
    }

    if ((int) $character->status === 2) {
      $this->messenger()->addStatus($this->t('%name is already archived.', ['%name' => $character->name]));
      return new RedirectResponse($redirect_url);
    }

    $character_data = json_decode((string) ($character->character_data ?? '{}'), TRUE);
    if (!is_array($character_data)) {
      $character_data = [];
    }
    $character_data['_archive_meta'] = [
      'previous_status' => (int) $character->status,
      'archived_at' => $this->time->getRequestTime(),
    ];

    $this->database->update('dc_campaign_characters')
      ->fields([
        'status' => 2,
        'character_data' => json_encode($character_data, JSON_UNESCAPED_UNICODE),
        'changed' => $this->time->getRequestTime(),
      ])
      ->condition('id', $character_id)
      ->execute();

    $this->messenger()->addStatus($this->t('%name archived. It is now hidden from your character roster.', [
      '%name' => $character->name,
    ]));

    return new RedirectResponse($redirect_url);
  }

  /**
   * Unarchives a character directly without a confirmation form.
   */
  public function unarchiveCharacter(int $character_id): RedirectResponse {
    $character = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'name', 'uid', 'status', 'character_data', 'campaign_id'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchObject() ?: NULL;

    if (!$character) {
      throw new NotFoundHttpException();
    }

    $current_user = $this->currentUser();
    if (
      (int) $character->uid !== (int) $current_user->id()
      && !$current_user->hasPermission('administer dungeoncrawler content')
    ) {
      throw new AccessDeniedHttpException();
    }

    $destination = \Drupal::request()->query->get('destination');
    if ($destination) {
      $redirect_url = Url::fromUserInput($destination)->toString();
    }
    else {
      $redirect_url = Url::fromRoute('dungeoncrawler_content.characters_archived')->toString();
    }

    if ((int) $character->status !== 2) {
      $this->messenger()->addStatus($this->t('%name is not archived.', ['%name' => $character->name]));
      return new RedirectResponse($redirect_url);
    }

    $character_data = json_decode((string) ($character->character_data ?? '{}'), TRUE);
    if (!is_array($character_data)) {
      $character_data = [];
    }

    $restored_status = (int) ($character_data['_archive_meta']['previous_status'] ?? 0);
    if (!in_array($restored_status, [0, 1], TRUE)) {
      $restored_status = 0;
    }

    unset($character_data['_archive_meta']);

    $this->database->update('dc_campaign_characters')
      ->fields([
        'status' => $restored_status,
        'character_data' => json_encode($character_data, JSON_UNESCAPED_UNICODE),
        'changed' => $this->time->getRequestTime(),
      ])
      ->condition('id', $character_id)
      ->execute();

    $this->messenger()->addStatus($this->t('%name unarchived. It is now visible on your character roster.', [
      '%name' => $character->name,
    ]));

    return new RedirectResponse($redirect_url);
  }

}
