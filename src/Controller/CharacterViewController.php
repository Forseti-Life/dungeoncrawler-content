<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Form\CharacterPortraitRegenerateForm;
use Drupal\dungeoncrawler_content\Form\CharacterPortraitUploadForm;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\FeatLibraryService;
use Drupal\dungeoncrawler_content\Service\FeatEffectManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\NpcPsychologyService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
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
  protected CharacterStateService $characterStateService;
  protected FeatEffectManager $featEffectManager;
  protected FeatLibraryService $featLibrary;
  protected RelationshipManagerService $relationshipManager;
  protected ?NpcPsychologyService $npcPsychologyService;
  protected GeneratedImageRepository $imageRepository;
  protected Connection $database;
  protected TimeInterface $time;

  public function __construct(CharacterManager $character_manager, CharacterStateService $character_state_service, FeatEffectManager $feat_effect_manager, FeatLibraryService $feat_library, RelationshipManagerService $relationship_manager, ?NpcPsychologyService $npc_psychology_service, GeneratedImageRepository $image_repository, Connection $database, TimeInterface $time) {
    $this->characterManager = $character_manager;
    $this->characterStateService = $character_state_service;
    $this->featEffectManager = $feat_effect_manager;
    $this->featLibrary = $feat_library;
    $this->relationshipManager = $relationship_manager;
    $this->npcPsychologyService = $npc_psychology_service;
    $this->imageRepository = $image_repository;
    $this->database = $database;
    $this->time = $time;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.character_state_service'),
      $container->get('dungeoncrawler_content.feat_effect_manager'),
      $container->get('dungeoncrawler_content.feat_library'),
      $container->get('dungeoncrawler_content.relationship_manager'),
      $container->get('dungeoncrawler_content.npc_psychology_service'),
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

    $is_admin = $this->currentUser()->hasPermission('administer site configuration');
    $is_owner = $this->characterManager->isOwner($record);
    $is_campaign_owner = $campaign_id > 0 ? $this->isCampaignOwner($campaign_id) : FALSE;
    if (!$is_owner && !$is_admin && !$is_campaign_owner) {
      throw new AccessDeniedHttpException();
    }

    // Decode character data via manager and normalize onto the canonical shape
    // used by the character sheet.
    $decoded = $this->characterManager->getCharacterData($record);
    $char_data = $this->characterManager->canonicalizeCharacterData($decoded);
    $hot = $this->characterManager->resolveHotColumnsForRecord($record, $char_data);

    $state = $this->characterStateService->getState(
      (string) $record->id,
      $campaign_id > 0 ? $campaign_id : NULL,
      !empty($record->instance_id) ? (string) $record->instance_id : NULL
    );
    $state_basic_info = is_array($state['basicInfo'] ?? NULL) ? $state['basicInfo'] : [];
    $state_resources = is_array($state['resources'] ?? NULL) ? $state['resources'] : [];
    $state_defenses = is_array($state['defenses'] ?? NULL) ? $state['defenses'] : [];
    $state_skills = is_array($state['skills'] ?? NULL) ? $state['skills'] : [];
    $state_features = is_array($state['features'] ?? NULL) ? $state['features'] : [];
    $state_traits = is_array($state['traits'] ?? NULL) ? $state['traits'] : [];
    $state_conditions = is_array($state['conditions'] ?? NULL) ? $state['conditions'] : [];
    $state_descriptors = is_array($state['descriptors'] ?? NULL) ? $state['descriptors'] : [];

    $abilities = $this->buildAbilityDisplayData($char_data);
    if (is_array($state['abilities'] ?? NULL) && $state['abilities'] !== []) {
      $abilities = $this->buildAbilityDisplayData(['abilities' => $state['abilities']]);
    }

    // Calculate derived stats
    $level = $state_basic_info['level'] ?? $char_data['level'] ?? $record->level ?? 1;
    $con_mod = $abilities['constitution']['modifier'];
    
    $ac = (int) ($char_data['armor_class'] ?? $hot['armor_class']);
    
    $hit_points = is_array($char_data['hit_points'] ?? NULL) ? $char_data['hit_points'] : [];
    $max_hp = (int) ($hit_points['max'] ?? $hot['hp_max']);
    
    $prof_bonus = $level + 2;
    $stored_saves = is_array($state['saves'] ?? NULL)
      ? $state['saves']
      : (is_array($char_data['saves'] ?? NULL) ? $char_data['saves'] : []);
    $fortitude = is_array($stored_saves['fortitude'] ?? NULL) ? $stored_saves['fortitude'] : ['modifier' => (int) ($stored_saves['fortitude'] ?? ($con_mod + $prof_bonus))];
    $reflex = is_array($stored_saves['reflex'] ?? NULL) ? $stored_saves['reflex'] : ['modifier' => (int) ($stored_saves['reflex'] ?? ($abilities['dexterity']['modifier'] + $prof_bonus))];
    $will = is_array($stored_saves['will'] ?? NULL) ? $stored_saves['will'] : ['modifier' => (int) ($stored_saves['will'] ?? ($abilities['wisdom']['modifier'] + $prof_bonus))];
    $saves = [
      'Fortitude' => [
        'modifier' => (int) ($fortitude['modifier'] ?? ($con_mod + $prof_bonus)),
        'proficiency' => (string) ($fortitude['proficiency'] ?? 'Trained'),
      ],
      'Reflex' => [
        'modifier' => (int) ($reflex['modifier'] ?? ($abilities['dexterity']['modifier'] + $prof_bonus)),
        'proficiency' => (string) ($reflex['proficiency'] ?? 'Trained'),
      ],
      'Will' => [
        'modifier' => (int) ($will['modifier'] ?? ($abilities['wisdom']['modifier'] + $prof_bonus)),
        'proficiency' => (string) ($will['proficiency'] ?? 'Trained'),
      ],
    ];

    $perception = [
      'modifier' => (int) ($state['perception'] ?? $char_data['perception'] ?? ($abilities['wisdom']['modifier'] + $prof_bonus)),
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

    $skills = $this->buildSkillsDisplayData($state_skills, $abilities, $skill_list);

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

    $condition_effects = $this->extractConditionStatEffects(is_array($char_data['conditions'] ?? NULL) ? $char_data['conditions'] : []);

    $feat_effects = $this->featEffectManager->buildEffectState($char_data, [
      'level' => (int) $level,
      'base_speed' => (int) $base_speed,
      'existing_hp_max' => (int) $max_hp,
    ]);

    $base_perception_modifier = (int) $perception['modifier'];
    $perception_bonus = (int) ($feat_effects['derived_adjustments']['perception_bonus'] ?? 0);
    $initiative_bonus = (int) ($feat_effects['derived_adjustments']['initiative_bonus'] ?? 0);
    $perception['modifier'] += $perception_bonus;
    $perception['senses'] = array_map(static function (array $sense): string {
      return (string) ($sense['name'] ?? '');
    }, $feat_effects['senses'] ?? []);

    $base_max_hp = $max_hp;
    $max_hp += (int) ($feat_effects['derived_adjustments']['hp_max_bonus'] ?? 0);
    $ac_base = $ac;
    $ac += (int) ($condition_effects['armor_class']['total'] ?? 0);
    $feat_speed = (int) ($feat_effects['derived_adjustments']['computed_speed'] ?? $base_speed);
    $speed = max(0, $feat_speed + (int) ($condition_effects['speed']['total'] ?? 0));
    $sheet_effect_summary = $this->buildSheetEffectSummary($char_data, $feat_effects, [
      'condition_effects' => $condition_effects,
      'hp_max_base' => $base_max_hp,
      'hp_max_final' => $max_hp,
      'ac_base' => (int) $ac_base,
      'ac_final' => (int) $ac,
      'speed_base' => (int) $base_speed,
      'speed_feat_final' => (int) $feat_speed,
      'speed_final' => (int) $speed,
      'perception_base' => $base_perception_modifier,
      'perception_final' => (int) $perception['modifier'],
      'initiative_base' => $base_perception_modifier,
      'initiative_final' => (int) ($perception['modifier'] + $initiative_bonus),
      'initiative_bonus_total' => $perception_bonus + $initiative_bonus,
    ]);

    // Read inventory data (structured format from Step 7).
    $inventory = $char_data['inventory'] ?? [];
    $equipment_items = $this->enrichEquipmentItems($inventory['carried'] ?? []);
    $inv_currency = CharacterManager::normalizeCurrencyDenominations(
      is_array($inventory['currency'] ?? NULL) ? $inventory['currency'] : [],
      isset($char_data['gold']) ? (float) $char_data['gold'] : 15.0
    );
    $equipment_gold = CharacterManager::currencyDenominationsToGoldValue($inv_currency);

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

    $alignment = (string) ($state_basic_info['alignment'] ?? $char_data['alignment'] ?? '');
    $deity = (string) ($state_basic_info['deity'] ?? $char_data['deity'] ?? '');
    $appearance = (string) ($state_basic_info['appearance'] ?? $state_descriptors['appearance'] ?? $char_data['appearance'] ?? '');
    $personality_text = (string) ($state_basic_info['personality'] ?? $state_descriptors['personality'] ?? $char_data['personality'] ?? '');
    $backstory = (string) ($state_basic_info['backstory'] ?? $char_data['backstory'] ?? '');
    $attitude = (string) ($state_descriptors['attitude'] ?? '');
    $motivations = (string) ($state_descriptors['motivations'] ?? '');
    $goals = $this->normalizePersonalityGoals($state['goals'] ?? ($char_data['goals'] ?? []));

    $type = (string) ($state['type'] ?? $record->type ?? 'pc');
    $relationship_source_type = $type === 'pc' ? 'campaign_character' : 'campaign_npc';
    $relationship_source_id = trim((string) ($state['instanceId'] ?? $record->instance_id ?? ''));
    $relationships_outgoing = [];
    $relationships_incoming = [];
    if ($campaign_id > 0 && $relationship_source_id !== '' && $this->relationshipManager->isRelationshipStorageReady()) {
      $relationships_outgoing = $this->relationshipManager->listEntityRelationships($campaign_id, $relationship_source_type, $relationship_source_id);
      $relationships_incoming = $this->relationshipManager->listIncomingEntityRelationships($campaign_id, $relationship_source_type, $relationship_source_id);
    }

    if ($campaign_id > 0 && $type !== 'pc' && $relationship_source_id !== '' && $this->npcPsychologyService) {
      $profile = $this->npcPsychologyService->loadProfile($campaign_id, $relationship_source_id);
      if (is_array($profile)) {
        $attitude = (string) ($profile['attitude'] ?? $attitude);
        $motivations = (string) ($profile['motivations'] ?? $motivations);
        $sheet = is_array($profile['character_sheet'] ?? NULL) ? $profile['character_sheet'] : [];
        if ($appearance === '') {
          $appearance = (string) ($sheet['appearance'] ?? '');
        }
        if ($personality_text === '') {
          $personality_text = (string) ($sheet['personality'] ?? '');
        }
        if ($backstory === '') {
          $backstory = (string) ($sheet['backstory'] ?? '');
        }
        if ($goals === []) {
          $goals = $this->normalizePersonalityGoals($sheet['goals'] ?? []);
        }
      }
    }

    $setup_query = ['character_id' => (int) $record->id];
    if ($campaign_id > 0) {
      $setup_query['campaign_id'] = $campaign_id;
    }

    $continue_query = $setup_query;
    $continue_query['step'] = max(1, (int) ($char_data['step'] ?? 1));

    $state_attacks = is_array($state['attacks'] ?? NULL) ? $state['attacks'] : [];
    $melee_attacks = is_array($state_attacks['melee'] ?? NULL)
      ? $state_attacks['melee']
      : (is_array($char_data['attacks']['melee'] ?? NULL) ? $char_data['attacks']['melee'] : []);
    $ranged_attacks = is_array($state_attacks['ranged'] ?? NULL)
      ? $state_attacks['ranged']
      : (is_array($char_data['attacks']['ranged'] ?? NULL) ? $char_data['attacks']['ranged'] : []);

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
        'speed' => (int) $base_speed,
        'languages' => $languages,
        'traits' => $state_traits,
      ],
      '#background' => [
        'name' => !empty($char_data['background']) ? $this->humanizeName((string) $char_data['background']) : 'Unknown',
      ],
      '#class_data' => [
        'name' => $class_name,
        'subclass' => $class_subclass,
        'key_ability' => $class_key_ability,
        'hp_per_level' => $class_hp_per_level,
        'class_features' => is_array($state_features['classFeatures'] ?? NULL) ? $state_features['classFeatures'] : [],
        'class_feats' => is_array($state_features['feats'] ?? NULL) ? $state_features['feats'] : [],
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
      '#melee_attacks' => $melee_attacks,
      '#ranged_attacks' => $ranged_attacks,
      '#equipment' => [
        'gold' => $equipment_gold,
        'items' => $equipment_items,
      ],
      '#followers' => $this->buildFollowerDisplayData($char_data),
      '#feats' => $this->enrichFeatDisplayData($char_data['feats'] ?? []),
      '#spells' => $this->buildSpellsDisplayData($char_data, $feat_effects),
      '#conditions' => $state_conditions !== [] ? $state_conditions : ($char_data['conditions'] ?? []),
      '#feat_effects' => $feat_effects,
      '#sheet_effect_summary' => $sheet_effect_summary,
      '#personality' => [
        'alignment' => $alignment,
        'deity' => $deity,
        'age' => $state_basic_info['age'] ?? $char_data['age'] ?? NULL,
        'gender' => $char_data['gender'] ?? NULL,
        'appearance' => $appearance,
        'personality' => $personality_text,
        'backstory' => $backstory,
        'attitude' => $attitude,
        'motivations' => $motivations,
        'goals' => $goals,
        'traits' => is_array($char_data['personality']['traits'] ?? NULL) ? $char_data['personality']['traits'] : [],
        'catchphrases' => is_array($char_data['personality']['catchphrases'] ?? NULL) ? $char_data['personality']['catchphrases'] : [],
      ],
      '#npc_data' => $type === 'pc' ? NULL : (is_array($state['npcDefinition'] ?? NULL) ? $state['npcDefinition'] : NULL),
      '#relationships' => [
        'outgoing' => $relationships_outgoing,
        'incoming' => $relationships_incoming,
      ],
      '#actor_identity' => [
        'type' => $type,
        'role' => (string) ($record->role ?? ''),
        'lifecycle_state' => (string) ($record->lifecycle_state ?? ''),
        'campaign_id' => (int) ($record->campaign_id ?? 0),
        'source_character_id' => (int) ($record->source_character_id ?? 0),
        'instance_id' => (string) ($state['instanceId'] ?? $record->instance_id ?? ''),
      ],
      '#actor_state' => [
        'basic_info' => $state_basic_info,
        'resources' => $state_resources,
        'defenses' => $state_defenses,
        'skills' => $state_skills,
        'features' => $state_features,
        'descriptors' => $state_descriptors,
      ],
      '#raw_json' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
      '#state_json' => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
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
   * Build a sheet-facing summary of applied and conditional effects.
   */
  private function buildSheetEffectSummary(array $char_data, array $feat_effects, array $context): array {
    $condition_effects = is_array($context['condition_effects'] ?? NULL)
      ? $context['condition_effects']
      : $this->extractConditionStatEffects(is_array($char_data['conditions'] ?? NULL) ? $char_data['conditions'] : []);
    $applied_feat_label = $this->resolveAppliedFeatLabel($feat_effects);
    $adjustments = is_array($feat_effects['derived_adjustments'] ?? NULL) ? $feat_effects['derived_adjustments'] : [];
    $hp_max_base = $this->requireContextInt($context, 'hp_max_base');
    $hp_max_final = $this->requireContextInt($context, 'hp_max_final');
    $speed_base = $this->requireContextInt($context, 'speed_base');
    $speed_feat_final = $this->requireContextInt($context, 'speed_feat_final');
    $speed_final = $this->requireContextInt($context, 'speed_final');
    $perception_base = $this->requireContextInt($context, 'perception_base');
    $perception_final = $this->requireContextInt($context, 'perception_final');
    $ac_base = $this->requireContextInt($context, 'ac_base');
    $ac_final = $this->requireContextInt($context, 'ac_final');
    $initiative_base = $this->requireContextInt($context, 'initiative_base');
    $initiative_final = $this->requireContextInt($context, 'initiative_final');
    $initiative_bonus_total = $this->requireContextInt($context, 'initiative_bonus_total');

    $hp_contributions = [];
    $hp_bonus = (int) ($adjustments['hp_max_bonus'] ?? 0);
    if ($hp_bonus !== 0) {
      $hp_contributions[] = [
        'source' => 'feat',
        'label' => $applied_feat_label,
        'value' => $hp_bonus,
      ];
    }

    $speed_contributions = [];
    $feat_speed_delta = $speed_feat_final - $speed_base;
    if ($feat_speed_delta !== 0) {
      $speed_contributions[] = [
        'source' => 'feat',
        'label' => $applied_feat_label,
        'value' => $feat_speed_delta,
      ];
    }
    foreach ($condition_effects['speed']['contributions'] ?? [] as $contribution) {
      $speed_contributions[] = $contribution;
    }
    $speed_raw_final = $speed_feat_final + (int) ($condition_effects['speed']['total'] ?? 0);
    $speed_clamp_adjustment = $speed_final - $speed_raw_final;
    if ($speed_clamp_adjustment !== 0) {
      $speed_contributions[] = [
        'source' => 'system',
        'label' => 'Minimum speed 0 ft',
        'value' => $speed_clamp_adjustment,
      ];
    }

    $perception_contributions = [];
    $perception_bonus = (int) ($adjustments['perception_bonus'] ?? 0);
    if ($perception_bonus !== 0) {
      $perception_contributions[] = [
        'source' => 'feat',
        'label' => $applied_feat_label,
        'value' => $perception_bonus,
      ];
    }

    $initiative_contributions = [];
    if ($initiative_bonus_total !== 0) {
      $initiative_contributions[] = [
        'source' => 'feat',
        'label' => $applied_feat_label,
        'value' => $initiative_bonus_total,
      ];
    }

    $speed_summary = $this->buildStatSummary(
      $speed_base,
      $speed_final,
      $speed_contributions,
      'ft'
    );
    $speed_summary['clamped'] = $speed_clamp_adjustment !== 0;
    $speed_summary['raw_final'] = $speed_raw_final;

    return [
      'stats' => [
        'hp_max' => $this->buildStatSummary(
          $hp_max_base,
          $hp_max_final,
          $hp_contributions
        ),
        'speed' => $speed_summary,
        'perception' => $this->buildStatSummary(
          $perception_base,
          $perception_final,
          $perception_contributions
        ),
        'armor_class' => $this->buildStatSummary(
          $ac_base,
          $ac_final,
          is_array($condition_effects['armor_class']['contributions'] ?? NULL) ? $condition_effects['armor_class']['contributions'] : []
        ),
        'initiative' => $this->buildStatSummary(
          $initiative_base,
          $initiative_final,
          $initiative_contributions
        ),
      ],
      'conditional' => $this->buildConditionalEffectSummary($feat_effects),
    ];
  }

  /**
   * Reads a required integer context value for sheet effect summaries.
   */
  private function requireContextInt(array $context, string $key): int {
    if (!array_key_exists($key, $context)) {
      throw new \InvalidArgumentException(sprintf('Missing required sheet effect context key: %s', $key));
    }

    return (int) $context[$key];
  }

  /**
   * Build a normalized stat-summary payload for Twig.
   */
  private function buildStatSummary(int $base, int $final, array $contributions, string $unit = ''): array {
    $normalized_contributions = [];
    foreach ($contributions as $contribution) {
      if (!is_array($contribution)) {
        continue;
      }
      $value = (int) ($contribution['value'] ?? 0);
      if ($value === 0) {
        continue;
      }
      $normalized_contributions[] = [
        'source' => (string) ($contribution['source'] ?? 'effect'),
        'label' => trim((string) ($contribution['label'] ?? 'Effect')),
        'value' => $value,
        'formatted_value' => ($value > 0 ? '+' : '') . $value . ($unit !== '' ? ' ' . $unit : ''),
      ];
    }

    $delta = $final - $base;
    $direction = $delta > 0 ? 'positive' : ($delta < 0 ? 'negative' : 'neutral');

    return [
      'base' => $base,
      'final' => $final,
      'modified' => $delta !== 0 || $normalized_contributions !== [],
      'direction' => $direction,
      'delta' => $delta,
      'formatted_delta' => ($delta > 0 ? '+' : '') . $delta . ($unit !== '' && $delta !== 0 ? ' ' . $unit : ''),
      'contributions' => $normalized_contributions,
      'unit' => $unit,
    ];
  }

  /**
   * Build a compact display list for conditional-only effects.
   */
  private function buildConditionalEffectSummary(array $feat_effects): array {
    $conditional = is_array($feat_effects['conditional_modifiers'] ?? NULL) ? $feat_effects['conditional_modifiers'] : [];
    $items = [];

    foreach ($conditional['saving_throws'] ?? [] as $modifier) {
      if (!is_array($modifier)) {
        continue;
      }
      $items[] = [
        'label' => sprintf(
          '%s%s %s saves',
          ((int) ($modifier['bonus'] ?? 0)) > 0 ? '+' : '',
          (int) ($modifier['bonus'] ?? 0),
          (string) ($modifier['save'] ?? 'saving throw')
        ),
        'context' => (string) ($modifier['context'] ?? ''),
      ];
    }

    foreach ($conditional['skills'] ?? [] as $modifier) {
      if (!is_array($modifier)) {
        continue;
      }
      $items[] = [
        'label' => sprintf(
          '%s%s %s',
          ((int) ($modifier['bonus'] ?? 0)) > 0 ? '+' : '',
          (int) ($modifier['bonus'] ?? 0),
          (string) ($modifier['skill'] ?? 'skill')
        ),
        'context' => (string) ($modifier['context'] ?? ''),
      ];
    }

    foreach ($conditional['movement'] ?? [] as $modifier) {
      if (!is_array($modifier)) {
        continue;
      }
      $items[] = [
        'label' => ucfirst(str_replace('_', ' ', (string) ($modifier['rule'] ?? 'movement modifier'))),
        'context' => (string) ($modifier['context'] ?? ''),
      ];
    }

    foreach ($conditional['outcome_upgrades'] ?? [] as $modifier) {
      if (!is_array($modifier)) {
        continue;
      }
      $items[] = [
        'label' => sprintf(
          '%s: %s -> %s',
          (string) ($modifier['target'] ?? 'outcome'),
          str_replace('_', ' ', (string) ($modifier['from'] ?? '')),
          str_replace('_', ' ', (string) ($modifier['to'] ?? ''))
        ),
        'context' => (string) ($modifier['context'] ?? ''),
      ];
    }

    return $items;
  }

  /**
   * Extract supported condition-driven stat effects for sheet rendering.
   */
  private function extractConditionStatEffects(array $conditions): array {
    $summary = [
      'armor_class' => [
        'total' => 0,
        'contributions' => [],
      ],
      'speed' => [
        'total' => 0,
        'contributions' => [],
      ],
    ];

    foreach ($conditions as $condition) {
      if (!is_array($condition) && !is_string($condition)) {
        continue;
      }

      $name = is_array($condition)
        ? (string) ($condition['name'] ?? $condition['condition_type'] ?? $condition['id'] ?? 'Condition')
        : (string) $condition;
      $raw_code = is_array($condition)
        ? (string) ($condition['condition_type'] ?? $condition['id'] ?? $condition['name'] ?? '')
        : (string) $condition;
      $code = strtolower(str_replace([' ', '-'], '_', trim($raw_code)));
      $value = (int) (is_array($condition) ? ($condition['value'] ?? 0) : 0);
      if ($value === 0 && preg_match('/_(\d+)$/', $code, $matches) === 1) {
        $value = (int) $matches[1];
      }

      switch ($code) {
        case 'flat_footed':
          $summary['armor_class']['total'] -= 2;
          $summary['armor_class']['contributions'][] = [
            'source' => 'condition',
            'label' => $name,
            'value' => -2,
          ];
          break;

        case 'frightened':
          $penalty = max(1, $value);
          $summary['armor_class']['total'] -= $penalty;
          $summary['armor_class']['contributions'][] = [
            'source' => 'condition',
            'label' => $penalty > 1 ? $name . ' ' . $penalty : $name,
            'value' => -$penalty,
          ];
          break;

        default:
          if (str_starts_with($code, 'speed_penalty_')) {
            $penalty = max(0, $value);
            if ($penalty > 0) {
              $summary['speed']['total'] -= $penalty;
              $summary['speed']['contributions'][] = [
                'source' => 'condition',
                'label' => $name,
                'value' => -$penalty,
              ];
            }
          }
          break;
      }
    }

    return $summary;
  }

  /**
   * Resolve a human-readable feat label for summary rows.
   */
  private function resolveAppliedFeatLabel(array $feat_effects): string {
    $applied = array_values(array_filter(array_map('strval', is_array($feat_effects['applied_feats'] ?? NULL) ? $feat_effects['applied_feats'] : [])));
    if ($applied === []) {
      return 'Feat effects';
    }
    if (count($applied) === 1) {
      return $this->humanizeName($applied[0]);
    }
    return 'Feat effects';
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
   * Normalize mixed skill payload shapes into template-ready rows.
   *
   * @param array<int,mixed> $state_skills
   * @param array<string,array<string,int>> $abilities
   * @param array<string,string> $skill_list
   *
   * @return array<int,array{name:string,modifier:int,proficiency:string}>
   */
  private function buildSkillsDisplayData(array $state_skills, array $abilities, array $skill_list): array {
    $skills = [];
    if ($state_skills !== []) {
      foreach ($state_skills as $key => $entry) {
        if (is_array($entry)) {
          $name = (string) ($entry['name'] ?? $entry['skill'] ?? $key);
          $modifier = (int) ($entry['modifier'] ?? $entry['bonus'] ?? 0);
          $proficiency = (string) ($entry['proficiency'] ?? $entry['rank'] ?? 'Untrained');
          $skills[] = [
            'name' => $this->humanizeName($name),
            'modifier' => $modifier,
            'proficiency' => ucfirst(strtolower($proficiency)),
          ];
          continue;
        }
        if (is_numeric($entry) || is_string($entry)) {
          $skills[] = [
            'name' => $this->humanizeName((string) $key),
            'modifier' => (int) $entry,
            'proficiency' => 'Trained',
          ];
        }
      }
      if ($skills !== []) {
        return $skills;
      }
    }

    foreach ($skill_list as $skill_name => $ability_key) {
      $skills[] = [
        'name' => $skill_name,
        'modifier' => (int) ($abilities[$ability_key]['modifier'] ?? 0),
        'proficiency' => 'Untrained',
      ];
    }

    return $skills;
  }

  /**
   * Normalize personality goals from string/list/object shapes.
   *
   * @return array<int,string>|array<string,string>
   */
  private function normalizePersonalityGoals(mixed $goals): array {
    if (is_array($goals)) {
      $is_list = array_keys($goals) === range(0, count($goals) - 1);
      if ($is_list) {
        return array_values(array_filter(array_map(static fn($v) => is_scalar($v) ? trim((string) $v) : '', $goals), static fn($v) => $v !== ''));
      }
      $normalized = [];
      foreach ($goals as $key => $value) {
        if (is_scalar($value)) {
          $text = trim((string) $value);
          if ($text !== '') {
            $normalized[(string) $key] = $text;
          }
        }
      }
      return $normalized;
    }

    if (is_string($goals) && trim($goals) !== '') {
      $parts = preg_split('/[;\n\r]+/', $goals) ?: [];
      return array_values(array_filter(array_map('trim', $parts), static fn($v) => $v !== ''));
    }

    return [];
  }

  /**
   * Build familiar and companion display data for the character sheet.
   */
  private function buildFollowerDisplayData(array $char_data): array {
    $followers = [];
    $class_id = strtolower(trim((string) ($char_data['class'] ?? '')));
    $class_feat = strtolower(trim((string) ($char_data['class_feat'] ?? '')));
    $subclass = strtolower(trim((string) ($char_data['subclass'] ?? '')));
    $arcane_thesis = strtolower(trim((string) ($char_data['arcane_thesis'] ?? '')));
    $feat_selections = is_array($char_data['feat_selections'] ?? NULL) ? $char_data['feat_selections'] : [];

    $familiar = is_array($char_data['familiar'] ?? NULL) ? $char_data['familiar'] : [];
    $familiar_source = $this->resolveFollowerFamiliarSource($class_id, $class_feat, $subclass, $arcane_thesis);
    if ($familiar !== []) {
      $familiar_type = (string) ($familiar['familiar_type'] ?? 'standard');
      $followers[] = [
        'kind' => 'Familiar',
        'status' => 'configured',
        'name' => trim((string) ($familiar['name'] ?? '')) !== ''
          ? (string) $familiar['name']
          : $this->humanizeName($familiar_type === 'standard' ? 'standard familiar' : $familiar_type),
        'details' => array_values(array_filter([
          $familiar_type !== 'standard' ? 'Form: ' . $this->humanizeName($familiar_type) : 'Form: Standard familiar',
          'Abilities: ' . count($familiar['abilities'] ?? []),
          isset($familiar['max_hp']) ? 'HP: ' . (int) ($familiar['hp'] ?? 0) . '/' . (int) $familiar['max_hp'] : '',
          !empty($familiar['is_witch_required']) ? 'Class-bound familiar' : '',
        ])),
      ];
    }
    elseif ($familiar_source !== NULL) {
      $followers[] = [
        'kind' => 'Familiar',
        'status' => 'pending',
        'name' => 'Pending familiar',
        'details' => [
          'Granted by: ' . $this->humanizeName($familiar_source),
          'Familiar choices have not been saved yet. Revisit Step 4 to configure this follower.',
        ],
      ];
    }

    foreach (['animal-companion', 'animal-companion-druid'] as $source_id) {
      $selection = is_array($feat_selections[$source_id] ?? NULL) ? $feat_selections[$source_id] : [];
      $species_id = strtolower(trim((string) ($selection['selected_companion_species'] ?? $selection['species_id'] ?? '')));
      if ($species_id === '') {
        continue;
      }
      $followers[] = [
        'kind' => 'Animal Companion',
        'status' => 'configured',
        'name' => trim((string) ($selection['name'] ?? '')) !== ''
          ? (string) $selection['name']
          : $this->humanizeName($species_id),
        'details' => array_values(array_filter([
          'Species: ' . $this->humanizeName($species_id),
          'Source: ' . $this->humanizeName($source_id),
        ])),
      ];
    }

    return $followers;
  }

  /**
   * Resolve whether the current character build should have a familiar.
   */
  private function resolveFollowerFamiliarSource(string $class_id, string $class_feat, string $subclass, string $arcane_thesis): ?string {
    if (in_array($class_feat, ['familiar', 'familiar-druid', 'familiar-sorcerer', 'alchemical-familiar', 'leshy-familiar-druid'], TRUE)) {
      return $class_feat;
    }
    if ($class_id === 'druid' && $subclass === 'leaf') {
      return 'leshy-familiar-druid';
    }
    if ($class_id === 'wizard' && $arcane_thesis === 'improved-familiar-attunement') {
      return 'improved-familiar-attunement';
    }
    if ($class_id === 'witch') {
      return 'familiar-witch-class';
    }

    return NULL;
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
      if ($description === '' && $feat_id !== '') {
        $canonical = $lookup[$feat_id] ?? NULL;
        if (is_array($canonical)) {
          $resolved_description = trim((string) ($canonical['description'] ?? $canonical['benefit'] ?? ''));
          if ($resolved_description !== '') {
            $feats[$index]['description'] = $resolved_description;
          }
        }
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
    return $this->featLibrary->getFeatLookup();
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
   * Returns whether current user owns the campaign.
   */
  private function isCampaignOwner(int $campaign_id): bool {
    if ($campaign_id <= 0) {
      return FALSE;
    }
    $campaign_uid = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['uid'])
      ->condition('id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $campaign_uid !== FALSE && (int) $campaign_uid === (int) $this->currentUser()->id();
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
