<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Schema-driven multi-step character creation.
 */
class CharacterCreationStepController extends ControllerBase {

  protected CharacterManager $characterManager;
  protected SchemaLoader $schemaLoader;
  protected CsrfTokenGenerator $csrfToken;
  protected Connection $database;
  protected CharacterPortraitGenerationService $portraitGenerator;

  public function __construct(CharacterManager $character_manager, SchemaLoader $schema_loader, CsrfTokenGenerator $csrf_token, Connection $database, CharacterPortraitGenerationService $portrait_generator) {
    $this->characterManager = $character_manager;
    $this->schemaLoader = $schema_loader;
    $this->csrfToken = $csrf_token;
    $this->database = $database;
    $this->portraitGenerator = $portrait_generator;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.schema_loader'),
      $container->get('csrf_token'),
      $container->get('database'),
      $container->get('dungeoncrawler_content.character_portrait_generator'),
    );
  }

  /**
   * Start or resume character creation.
   */
  public function start(Request $request) {
    // Check for existing draft
    $character_id = $request->query->get('character_id');
    $campaign_id = $request->query->get('campaign_id');
    
    if ($character_id) {
      // Load existing draft
      $character = $this->characterManager->loadCharacter($character_id);
      $is_admin = $this->currentUser()->hasPermission('administer dungeoncrawler content');
      if ($character && ($character->uid == $this->currentUser()->id() || $is_admin)) {
        $data = json_decode($character->character_data, TRUE);
        $step = (int) ($data['step'] ?? 1);
        $url = Url::fromRoute('dungeoncrawler_content.character_step', [
          'step' => $step,
        ]);
        $query = ['character_id' => $character_id];
        if ($campaign_id) {
          $query['campaign_id'] = $campaign_id;
        }
        $url->setOption('query', $query);
        return new RedirectResponse($url->toString());
      }
    }

    // Enforce single-draft limit: check if user already has an active draft.
    $existing_draft = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id'])
      ->condition('uid', (int) $this->currentUser()->id())
      ->condition('status', 0)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($existing_draft) {
      $this->messenger()->addError($this->t('You already have an active draft character. Please complete or delete it before starting a new one.'));
      $query = ['character_id' => $existing_draft];
      if ($campaign_id) {
        $query['campaign_id'] = $campaign_id;
      }
      return new RedirectResponse(Url::fromRoute('dungeoncrawler_content.character_step', ['step' => 1])->setOption('query', $query)->toString());
    }

    // Start new character at step 1
    $url = Url::fromRoute('dungeoncrawler_content.character_step', ['step' => 1]);
    if ($campaign_id) {
      $url->setOption('query', ['campaign_id' => $campaign_id]);
    }
    return new RedirectResponse($url->toString());
  }

  /**
   * Display a specific character creation step.
   */
  public function step(int $step, Request $request) {
    $character_id = $request->query->get('character_id');
    $campaign_id = $request->query->get('campaign_id');

    if ($step < 1 || $step > 8) {
      $this->messenger()->addError($this->t('Invalid step number.'));
      return new RedirectResponse($this->buildCharactersUrl($campaign_id));
    }

    if ($character_id) {
      $character = $this->characterManager->loadCharacter((int) $character_id);
      $is_admin = $this->currentUser()->hasPermission('administer dungeoncrawler content');
      if (!$character || ((int) $character->uid !== (int) $this->currentUser()->id() && !$is_admin)) {
        $this->messenger()->addError($this->t('Access denied.'));
        return new RedirectResponse($this->buildCharactersUrl($campaign_id));
      }

      $character_data = json_decode((string) $character->character_data, TRUE);
      $saved_step = (int) (($character_data['step'] ?? 1));
      $saved_step = max(1, min(8, $saved_step));

      if ($step > $saved_step) {
        $query = ['character_id' => $character_id];
        if ($campaign_id) {
          $query['campaign_id'] = $campaign_id;
        }

        $this->messenger()->addWarning($this->t('Please complete the previous step before continuing.'));
        return new RedirectResponse(Url::fromRoute('dungeoncrawler_content.character_step', ['step' => $saved_step])->setOption('query', $query)->toString());
      }
    }
    elseif ($step > 1) {
      $query = [];
      if ($campaign_id) {
        $query['campaign_id'] = $campaign_id;
      }

      $this->messenger()->addWarning($this->t('Start character creation at step 1.'));
      return new RedirectResponse(Url::fromRoute('dungeoncrawler_content.character_step', ['step' => 1])->setOption('query', $query)->toString());
    }
    
    // Return the form
    return $this->formBuilder()->getForm(
      'Drupal\dungeoncrawler_content\Form\CharacterCreationStepForm',
      $step,
      $character_id,
      $campaign_id
    );
  }

  /**
   * Save step data and return JSON response for AJAX.
   * Requires CSRF token for security.
   */
  public function saveStep(int $step, Request $request) {
    // Validate CSRF token
    $token = $request->headers->get('X-CSRF-Token');
    if (!$token
      || (!$this->csrfToken->validate($token, CsrfRequestHeaderAccessCheck::TOKEN_KEY)
        && !$this->csrfToken->validate($token, 'rest'))
    ) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Invalid or missing CSRF token.'),
      ], 403);
    }

    $character_id = $request->request->get('character_id') ?: $request->query->get('character_id');
    $campaign_id = $request->request->get('campaign_id') ?: $request->query->get('campaign_id');
    $data = $request->request->all();
    
    // Load existing character
    $character = $character_id ? $this->characterManager->loadCharacter($character_id) : NULL;
    
    if ($character && $character->uid != $this->currentUser()->id()
      && !$this->currentUser()->hasPermission('administer dungeoncrawler content')) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Access denied.'),
      ], 403);
    }

    // Merge with existing data
    $character_data = $character ? json_decode($character->character_data, TRUE) : $this->getDefaultCharacterData();

    $validation_errors = $this->validateStepRequirements($step, $data, $character_data);
    if (!empty($validation_errors)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Please complete all required fields for this step.'),
        'errors' => $validation_errors,
      ], 422);
    }
    
    // Update with step data
    $result = $this->updateStepData($character_data, $step, $data);
    
    // If validation failed, return the error response
    if ($result instanceof JsonResponse) {
      return $result;
    }
    
    $character_data = $result;
    $next_step = $this->getNextStep($step);
    $character_data['step'] = $next_step; // Advance to next step

    $hit_points = is_array($character_data['hit_points'] ?? NULL) ? $character_data['hit_points'] : [];
    $abilities = is_array($character_data['abilities'] ?? NULL) ? $character_data['abilities'] : [];
    $dex = (int) ($abilities['dex'] ?? 10);

    // Resolve campaign_id for persistence.
    $resolved_campaign_id = ($campaign_id !== NULL && $campaign_id !== '') ? (int) $campaign_id : 0;

    // Save to database
    if ($character) {
      $this->characterManager->updateCharacter($character_id, [
        'campaign_id' => $resolved_campaign_id,
        'name' => $character_data['name'] ?: 'Unnamed Character',
        'ancestry' => $character_data['ancestry'] ?? '',
        'class' => $character_data['class'] ?? '',
        'level' => (int) ($character_data['level'] ?? 1),
        'hp_current' => (int) ($hit_points['current'] ?? 0),
        'hp_max' => (int) ($hit_points['max'] ?? 0),
        'armor_class' => (int) (10 + floor(($dex - 10) / 2)),
        'experience_points' => (int) ($character_data['experience_points'] ?? 0),
        'position_q' => (int) ($character_data['position']['q'] ?? 0),
        'position_r' => (int) ($character_data['position']['r'] ?? 0),
        'last_room_id' => (string) ($character_data['position']['room_id'] ?? ''),
        'character_data' => json_encode($character_data, JSON_PRETTY_PRINT),
      ]);
    } else {
      $character_id = $this->createDraft($character_data, $campaign_id);
    }

    // Return JSON response with redirect URL
    if ($step >= 8) {
      // Mark as complete
      $this->characterManager->updateCharacter($character_id, ['status' => 1]);
      $portrait_result = $this->portraitGenerator->generatePortrait(
        $character_data,
        (int) $character_id,
        (int) $this->currentUser()->id(),
        $campaign_id !== NULL && $campaign_id !== '' ? (int) $campaign_id : NULL,
        [
          'generate' => $data['portrait_generate'] ?? NULL,
          'user_prompt' => $data['portrait_prompt'] ?? '',
        ]
      );
      $image_summary = $this->buildPortraitSummary($portrait_result);
      $view_url = Url::fromRoute('dungeoncrawler_content.character_view', [
        'character_id' => $character_id,
      ]);
      if ($campaign_id !== NULL && $campaign_id !== '') {
        $view_url->setOption('query', ['campaign_id' => $campaign_id]);
      }

      return new JsonResponse([
        'success' => TRUE,
        'message' => $this->t('Character created successfully!'),
        'image_generation' => $image_summary,
        'redirect' => $view_url->toString(),
      ]);
    }

    $next_query = ['character_id' => $character_id];
    if ($campaign_id !== NULL && $campaign_id !== '') {
      $next_query['campaign_id'] = $campaign_id;
    }

    return new JsonResponse([
      'success' => TRUE,
      'redirect' => Url::fromRoute('dungeoncrawler_content.character_step', [
        'step' => $next_step,
      ])->setOption('query', $next_query)->toString(),
    ]);
  }

  /**
   * Summarizes portrait generation results for responses.
   */
  private function buildPortraitSummary(array $result): array {
    $summary = [
      'attempted' => (bool) ($result['attempted'] ?? FALSE),
    ];

    if (!empty($result['provider'])) {
      $summary['provider'] = (string) $result['provider'];
    }
    if (!empty($result['reason'])) {
      $summary['reason'] = (string) $result['reason'];
    }
    if (!empty($result['provider_status']) && is_array($result['provider_status'])) {
      $summary['provider_status'] = [
        'enabled' => (bool) ($result['provider_status']['enabled'] ?? FALSE),
        'has_api_key' => (bool) ($result['provider_status']['has_api_key'] ?? FALSE),
      ];
    }

    if (!empty($result['storage']) && is_array($result['storage'])) {
      $summary['stored'] = (bool) ($result['storage']['stored'] ?? FALSE);
      if (!empty($result['storage']['reason'])) {
        $summary['storage_reason'] = (string) $result['storage']['reason'];
      }
      if (!empty($result['storage']['image_uuid'])) {
        $summary['image_uuid'] = (string) $result['storage']['image_uuid'];
      }
    }

    return $summary;
  }

  /**
   * Gets the next step in the flow.
   */
  private function getNextStep(int $step): int {
    return min(8, $step + 1);
  }

  /**
   * Load existing draft or create new one.
   */
  private function loadOrCreateDraft($character_id) {
    if ($character_id) {
      $character = $this->characterManager->loadCharacter($character_id);
      if ($character && $character->uid == $this->currentUser()->id()) {
        return [
          'id' => $character->id,
          'data' => json_decode($character->character_data, TRUE),
        ];
      }
    }

    return [
      'id' => NULL,
      'data' => $this->getDefaultCharacterData(),
    ];
  }

  /**
   * Builds a redirect URL to the campaign characters list or campaigns page.
   */
  private function buildCharactersUrl(int|string|null $campaign_id): string {
    if ($campaign_id !== NULL && $campaign_id !== '' && (int) $campaign_id > 0) {
      return Url::fromRoute('dungeoncrawler_content.characters', [
        'campaign_id' => (int) $campaign_id,
      ])->toString();
    }
    return Url::fromRoute('dungeoncrawler_content.campaigns')->toString();
  }

  /**
   * Create new draft character.
   */
  private function createDraft(array $character_data, int|string|null $campaign_id = NULL) {
    $db = \Drupal::database();
    $now = \Drupal::time()->getRequestTime();
    $instance_id = \Drupal::service('uuid')->generate();
    $resolved_campaign_id = ($campaign_id !== NULL && $campaign_id !== '') ? (int) $campaign_id : 0;

    return $db->insert('dc_campaign_characters')
      ->fields([
        'uuid' => $instance_id,
        'campaign_id' => $resolved_campaign_id,
        'character_id' => 0,
        'instance_id' => $instance_id,
        'uid' => (int) $this->currentUser()->id(),
        'name' => $character_data['name'] ?: 'Unnamed Character',
        'level' => 1,
        'ancestry' => $character_data['ancestry'] ?? '',
        'class' => $character_data['class'] ?? '',
        'hp_current' => 0,
        'hp_max' => 0,
        'armor_class' => 10,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'character_data' => json_encode($character_data, JSON_PRETTY_PRINT),
        'status' => 0, // Draft
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();
  }

  /**
   * Update character data with step-specific fields.
   */
  private function updateStepData(array $character_data, int $step, array $form_data) {
    // Simple mapping of form fields to character data
    $field_mappings = [
      1 => ['name', 'concept'],
      2 => ['ancestry', 'heritage', 'ancestry_feat', 'ancestry_boosts'],
      3 => ['background', 'background_boosts'],
      4 => ['class', 'class_key_ability', 'class_feat', 'subclass'],
      5 => ['free_boosts'],
      6 => ['alignment', 'deity', 'age', 'gender', 'general_feat', 'trained_skills'],
      // Step 7 handled separately below (equipment needs catalog resolution).
      8 => ['appearance', 'personality', 'backstory', 'portrait_generate', 'portrait_prompt'],
    ];

    // Non-blocking flow: do not reject partial step payloads.
    // Keep mapping permissive so users can progress without required fields.

    // Map form data to character data
    if (isset($field_mappings[$step])) {
      foreach ($field_mappings[$step] as $field) {
        if (isset($form_data[$field])) {
          $character_data[$field] = $form_data[$field];
        }
      }
    }

    // Step 4: Store class proficiencies and 1st-level class features; build spellcasting for casters.
    if ($step === 4) {
      $selected_class = $character_data['class'] ?? '';

      // Store class proficiencies from CLASSES constant.
      if (!empty($selected_class) && isset(CharacterManager::CLASSES[$selected_class]['proficiencies'])) {
        $character_data['class_proficiencies'] = CharacterManager::CLASSES[$selected_class]['proficiencies'];
      }

      // Store 1st-level class features from CLASS_ADVANCEMENT.
      if (!empty($selected_class) && isset(CharacterManager::CLASS_ADVANCEMENT[$selected_class][1]['auto_features'])) {
        $character_data['class_features'] = CharacterManager::CLASS_ADVANCEMENT[$selected_class][1]['auto_features'];
      }
      else {
        $character_data['class_features'] = [];
      }
      $tradition = $this->characterManager->resolveClassTradition($selected_class, $character_data);

      if ($tradition) {
        // Clean cantrips: accept array of IDs or checkbox-format.
        $raw_cantrips = $form_data['cantrips'] ?? [];
        $cantrip_ids = is_array($raw_cantrips)
          ? array_values(array_filter($raw_cantrips, static fn($v) => $v !== 0 && $v !== '' && $v !== NULL && $v !== FALSE))
          : [];

        $raw_spells = $form_data['spells_first'] ?? [];
        $spell_ids = is_array($raw_spells)
          ? array_values(array_filter($raw_spells, static fn($v) => $v !== 0 && $v !== '' && $v !== NULL && $v !== FALSE))
          : [];

        $spell_slots = CharacterManager::CASTER_SPELL_SLOTS[$selected_class] ?? [];

        $ability_map = [
          'wizard' => 'intelligence', 'witch' => 'intelligence',
          'cleric' => 'wisdom', 'druid' => 'wisdom',
          'bard' => 'charisma', 'sorcerer' => 'charisma', 'oracle' => 'charisma',
        ];

        $character_data['cantrips'] = $cantrip_ids;
        $character_data['spells_first'] = $spell_ids;
        $character_data['spells'] = [
          'tradition' => $tradition,
          'casting_ability' => $ability_map[strtolower($selected_class)] ?? 'charisma',
          'cantrips' => $cantrip_ids,
          'first_level' => $spell_ids,
          'slots' => [
            'cantrips' => $spell_slots['cantrips'] ?? 5,
            'first' => $spell_slots['first'] ?? 2,
          ],
        ];

        if ($selected_class === 'wizard') {
          $character_data['spells']['spellbook_size'] = $spell_slots['spellbook'] ?? 10;
        }
      }
    }

    // Step 2: Apply ancestry stats to character data.
    if ($step === 2) {
      $ancestry_id = $character_data['ancestry'] ?? '';
      if (!empty($ancestry_id)) {
        $canonical = CharacterManager::resolveAncestryCanonicalName($ancestry_id);
        $ancestry_data = $canonical ? (CharacterManager::ANCESTRIES[$canonical] ?? []) : [];
        if (!empty($ancestry_data)) {
          // Store ancestry stats.
          $character_data['ancestry_hp'] = (int) ($ancestry_data['hp'] ?? 0);
          $character_data['ancestry_speed'] = (int) ($ancestry_data['speed'] ?? 25);
          $character_data['ancestry_size'] = $ancestry_data['size'] ?? 'Medium';

          // Ensure abilities array exists.
          if (empty($character_data['abilities']) || !is_array($character_data['abilities'])) {
            $character_data['abilities'] = ['str' => 10, 'dex' => 10, 'con' => 10, 'int' => 10, 'wis' => 10, 'cha' => 10];
          }
          $ability_map = ['Strength' => 'str', 'Dexterity' => 'dex', 'Constitution' => 'con', 'Intelligence' => 'int', 'Wisdom' => 'wis', 'Charisma' => 'cha'];

          // Reverse previous ancestry mods if ancestry was re-selected.
          $prev_ancestry_id = $character_data['_prev_ancestry'] ?? '';
          if (!empty($prev_ancestry_id) && $prev_ancestry_id !== $ancestry_id) {
            $prev_canonical = CharacterManager::resolveAncestryCanonicalName($prev_ancestry_id);
            $prev_data = $prev_canonical ? (CharacterManager::ANCESTRIES[$prev_canonical] ?? []) : [];
            foreach ($prev_data['boosts'] ?? [] as $boost) {
              if ($boost !== 'Free') {
                $key = $ability_map[$boost] ?? '';
                if ($key) { $character_data['abilities'][$key] = max(0, ($character_data['abilities'][$key] ?? 10) - 2); }
              }
            }
            if (!empty($prev_data['flaw'])) {
              $key = $ability_map[$prev_data['flaw']] ?? '';
              if ($key) { $character_data['abilities'][$key] = ($character_data['abilities'][$key] ?? 10) + 2; }
            }
            // Also reverse previous free boost selections.
            foreach ($character_data['_prev_ancestry_free_boosts'] ?? [] as $free_boost) {
              $key = $ability_map[ucfirst(strtolower($free_boost))] ?? $ability_map[$free_boost] ?? '';
              if (!$key) {
                // Try direct short-key lookup.
                $key = $free_boost;
                if (!isset($character_data['abilities'][$key])) { continue; }
              }
              if ($key && isset($character_data['abilities'][$key])) {
                $character_data['abilities'][$key] = max(0, $character_data['abilities'][$key] - 2);
              }
            }
          }

          // Apply new ancestry boosts.
          $free_boost_selections = $this->normalizeSelectionList($form_data['ancestry_boosts'] ?? []);
          $free_idx = 0;
          foreach ($ancestry_data['boosts'] ?? [] as $boost) {
            if ($boost === 'Free') {
              $chosen = $free_boost_selections[$free_idx] ?? '';
              if (!empty($chosen)) {
                // chosen may be short-form (str) or long-form (Strength).
                $key = $ability_map[$chosen] ?? (array_key_exists($chosen, $character_data['abilities']) ? $chosen : '');
                if ($key) { $character_data['abilities'][$key] = ($character_data['abilities'][$key] ?? 10) + 2; }
              }
              $free_idx++;
            } else {
              $key = $ability_map[$boost] ?? '';
              if ($key) { $character_data['abilities'][$key] = ($character_data['abilities'][$key] ?? 10) + 2; }
            }
          }

          // Apply ancestry flaw.
          if (!empty($ancestry_data['flaw'])) {
            $key = $ability_map[$ancestry_data['flaw']] ?? '';
            if ($key) { $character_data['abilities'][$key] = max(0, ($character_data['abilities'][$key] ?? 10) - 2); }
          }

          // Track for re-selection reversal.
          $character_data['_prev_ancestry'] = $ancestry_id;
          $character_data['_prev_ancestry_free_boosts'] = $free_boost_selections;

          // Grant heritage abilities: resolve granted_abilities for the selected
          // heritage (e.g., ancient-blooded grants call-on-ancient-blood).
          // Re-apply on each step-2 save to handle re-selection; always rebuild
          // from scratch to avoid stale grants from a previous selection.
          $selected_heritage = $character_data['heritage'] ?? '';
          if (!empty($selected_heritage)) {
            $heritage_abilities = CharacterManager::getHeritageGrantedAbilities($canonical, $selected_heritage);
          }
          else {
            $heritage_abilities = [];
          }
          // Preserve existing non-heritage granted abilities (e.g. from class/background).
          $existing = is_array($character_data['granted_abilities'] ?? NULL) ? $character_data['granted_abilities'] : [];
          // Strip any previously-granted heritage abilities (identified by presence in HERITAGE_REACTIONS).
          $heritage_reaction_ids = array_keys(CharacterManager::HERITAGE_REACTIONS);
          $non_heritage = array_values(array_filter($existing, static fn($id) => !in_array($id, $heritage_reaction_ids, TRUE)));
          $character_data['granted_abilities'] = array_values(array_unique(array_merge($non_heritage, $heritage_abilities)));
        }
      }
    }

    // Step 6: Clean trained_skills and build feats summary.
    if ($step === 6) {
      if (isset($form_data['trained_skills']) && is_array($form_data['trained_skills'])) {
        $character_data['trained_skills'] = array_values(
          array_filter($form_data['trained_skills'], static fn($v) => $v !== 0 && $v !== '' && $v !== NULL)
        );
      }

      // Build feats array from all sources.
      $character_data['feats'] = $this->buildFeatsArrayFromData($character_data);
    }

    // Step 7: resolve selected checkbox IDs into full item objects from the
    // equipment catalog, matching the logic in CharacterCreationStepForm.
    if ($step === 7) {
      $catalog = $this->getEquipmentCatalog();
      $catalog_by_id = [];
      foreach ($catalog as $items) {
        foreach ($items as $item) {
          $catalog_by_id[$item['id']] = $item;
        }
      }

      // Collect selected IDs from the three checkbox groups.
      $selected_ids = [];
      foreach (['weapons', 'armor', 'gear'] as $group) {
        $raw = $form_data[$group] ?? [];
        if (is_array($raw)) {
          foreach (array_filter($raw) as $id) {
            $selected_ids[] = $id;
          }
        }
      }

      $selected_items = [];
      $total_cost = 0.0;
      foreach ($selected_ids as $item_id) {
        if (isset($catalog_by_id[$item_id])) {
          $selected_items[] = $catalog_by_id[$item_id];
          $total_cost += (float) $catalog_by_id[$item_id]['cost'];
        }
      }

      $remaining_gp = max(0, round(15 - $total_cost, 2));
      $character_data['gold'] = $remaining_gp;

      // Build proper inventory structure.
      $carried = [];
      foreach ($selected_items as $item) {
        $carried[] = [
          'id' => $item['id'],
          'name' => $item['name'],
          'type' => $item['type'],
          'bulk' => $item['bulk'] ?? 'L',
          'quantity' => 1,
          'traits' => $item['traits'] ?? [],
        ];
      }

      $total_bulk = 0;
      foreach ($carried as $ci) {
        $bulk_val = $ci['bulk'] ?? 'L';
        if ($bulk_val === 'L' || $bulk_val === 'light') {
          $total_bulk += 0.1;
        }
        elseif (is_numeric($bulk_val)) {
          $total_bulk += (float) $bulk_val * ($ci['quantity'] ?? 1);
        }
      }
      $total_bulk = round($total_bulk, 1);

      $character_data['inventory'] = [
        'worn' => ['weapons' => [], 'armor' => NULL, 'accessories' => []],
        'carried' => $carried,
        'currency' => ['cp' => 0, 'sp' => 0, 'gp' => $remaining_gp, 'pp' => 0],
        'totalBulk' => $total_bulk,
        'encumbrance' => 'unencumbered',
      ];
    }

    return $character_data;
  }

  /**
   * Validate required fields for a specific character creation step.
   */
  private function validateStepRequirements(int $step, array $submitted, array $existing): array {
    $merged = $existing;

    foreach ($submitted as $key => $value) {
      if (in_array($key, ['form_build_id', 'form_token', 'form_id', 'op'], TRUE)) {
        continue;
      }

      if (in_array($key, ['background_boosts', 'free_boosts'], TRUE)) {
        $merged[$key] = $this->normalizeSelectionList($value);
      }
      else {
        $merged[$key] = $value;
      }
    }

    $errors = [];

    $requireNonEmpty = static function ($value): bool {
      return is_string($value) ? trim($value) !== '' : !empty($value);
    };

    switch ($step) {
      case 1:
        if (!$requireNonEmpty($merged['name'] ?? '')) {
          $errors['name'] = 'Character name is required.';
        }
        break;

      case 2:
        $ancestry_val = trim($merged['ancestry'] ?? '');
        if (!$requireNonEmpty($ancestry_val)) {
          $errors['ancestry'] = 'Ancestry selection is required.';
        }
        else {
          // Validate ancestry is known.
          $canonical = CharacterManager::resolveAncestryCanonicalName($ancestry_val);
          if ($canonical === '') {
            $errors['ancestry'] = 'Invalid ancestry: ' . $ancestry_val . '.';
          }
          else {
            // Heritage ancestry-gate validation: if a heritage is selected,
            // confirm it belongs to the submitted ancestry.
            $heritage_val = trim($merged['heritage'] ?? '');
            if ($heritage_val !== '' && !CharacterManager::isValidHeritageForAncestry($canonical, $heritage_val)) {
              $errors['heritage'] = 'The selected heritage (' . $heritage_val . ') is not valid for the ' . $canonical . ' ancestry.';
            }

            $boost_config = CharacterManager::getAncestryBoostConfig($ancestry_val, $heritage_val);
            $free_count = (int) ($boost_config['free_boosts'] ?? 0);
            $chosen = $this->normalizeSelectionList($merged['ancestry_boosts'] ?? []);
            $fixed_boosts = array_values(array_filter(array_map(static function ($boost) {
              $boost = strtolower(trim((string) $boost));
              $map = [
                'strength' => 'str',
                'dexterity' => 'dex',
                'constitution' => 'con',
                'intelligence' => 'int',
                'wisdom' => 'wis',
                'charisma' => 'cha',
                'str' => 'str',
                'dex' => 'dex',
                'con' => 'con',
                'int' => 'int',
                'wis' => 'wis',
                'cha' => 'cha',
              ];
              return $map[$boost] ?? NULL;
            }, $boost_config['fixed_boosts'] ?? [])));

            if ($free_count > 0) {
              if (count($chosen) !== $free_count) {
                $errors['ancestry_boosts'] = 'Select exactly ' . $free_count . ' free ancestry boost' . ($free_count === 1 ? '' : 's') . '.';
              }
              elseif (count($chosen) !== count(array_unique($chosen))) {
                $errors['ancestry_boosts'] = 'Ability boost selections must be unique.';
              }
              else {
                foreach ($chosen as $boost_choice) {
                  $boost_short = strtolower(trim((string) $boost_choice));
                  if (in_array($boost_short, $fixed_boosts, TRUE)) {
                    $errors['ancestry_boosts'] = 'Cannot apply a free ancestry boost to an ability that already receives an ancestry boost.';
                    break;
                  }
                }
              }
            }
          }
        }
        break;

      case 3:
        if (!$requireNonEmpty($merged['background'] ?? '')) {
          $errors['background'] = 'Background is required.';
        }
        else {
          $bg_id = $merged['background'];
          $bg_data = CharacterManager::BACKGROUNDS[$bg_id] ?? NULL;
          if ($bg_data === NULL) {
            $errors['background'] = 'Invalid background selection.';
          }
          else {
            $background_boosts = $this->normalizeSelectionList($merged['background_boosts'] ?? []);
            if (isset($bg_data['fixed_boost'])) {
              // New model: 1 free boost required (fixed is auto-applied).
              if (count($background_boosts) !== 1) {
                $errors['background_boosts'] = 'Select exactly 1 free ability boost for your background.';
              }
              elseif (strtolower(trim($background_boosts[0])) === strtolower(trim($bg_data['fixed_boost']))) {
                $errors['background_boosts'] = 'Cannot apply two boosts to the same ability score from a single background.';
              }
            }
            else {
              // Legacy model: 2 free boosts.
              if (count($background_boosts) !== 2) {
                $errors['background_boosts'] = 'Select exactly 2 background boosts.';
              }
              elseif (count(array_unique($background_boosts)) !== 2) {
                $errors['background_boosts'] = 'Cannot apply two boosts to the same ability score from a single background.';
              }
            }
          }
        }
        break;

      case 4:
        if (!$requireNonEmpty($merged['class'] ?? '')) {
          $errors['class'] = 'Class is required.';
        }
        else {
          $selected_class_id = $merged['class'];
          if (!isset(CharacterManager::CLASSES[$selected_class_id])) {
            $errors['class'] = 'Invalid class: ' . $selected_class_id . '.';
          }
          else {
            $class_data = CharacterManager::CLASSES[$selected_class_id];
            $ka_raw = $class_data['key_ability'] ?? '';
            $ka_opts = array_map('trim', explode(' or ', strtolower($ka_raw)));
            if (count($ka_opts) > 1 && empty($merged['class_key_ability'])) {
              $errors['class_key_ability'] = 'You must choose a key ability for this class.';
            }
          }
        }
        break;

      case 5:
        $free_boosts = $this->normalizeSelectionList($merged['free_boosts'] ?? []);
        if (count($free_boosts) !== 4) {
          $errors['free_boosts'] = 'Select exactly 4 free boosts.';
        }
        elseif (count(array_unique($free_boosts)) !== 4) {
          $errors['free_boosts'] = 'Free boosts must be unique.';
        }
        break;

      case 6:
        if (!$requireNonEmpty($merged['alignment'] ?? '')) {
          $errors['alignment'] = 'Alignment selection is required.';
        }
        break;
    }

    return $errors;
  }

  /**
   * Normalize boost selection payloads from array or JSON string values.
   */
  private function normalizeSelectionList($value): array {
    if (is_string($value)) {
      $decoded = json_decode($value, TRUE);
      if (is_array($decoded)) {
        $value = $decoded;
      }
      elseif (trim($value) === '') {
        $value = [];
      }
      else {
        $value = [$value];
      }
    }

    if (!is_array($value)) {
      return [];
    }

    return array_values(array_filter(array_map(static function ($item) {
      return is_string($item) ? trim($item) : $item;
    }, $value), static function ($item) {
      return $item !== NULL && $item !== '';
    }));
  }

  /**
   * Prepare options data for a specific step from CharacterManager constants.
   */
  private function prepareOptionsForStep(int $step): array {
    switch ($step) {
      case 2:
        return [
          'ancestries' => $this->prepareAncestries(),
          'heritages' => CharacterManager::HERITAGES,
        ];

      case 3:
        return [
          'backgrounds' => CharacterManager::BACKGROUNDS,
        ];

      case 4:
        return [
          'classes' => array_values(CharacterManager::CLASSES),
        ];

      case 6:
        return [
          'alignments' => $this->getAlignments(),
        ];

      case 7:
        return [
          'equipment' => $this->getEquipmentCatalog(),
        ];

      default:
        return [];
    }
  }

  /**
   * Get default character data structure.
   */
  private function getDefaultCharacterData() {
    return [
      'step' => 1,
      'name' => '',
      'concept' => '',
      'ancestry' => '',
      'ancestry_hp' => 0,
      'ancestry_speed' => 25,
      'ancestry_size' => '',
      'ancestry_boosts' => [],
      'heritage' => '',
      'background' => '',
      'background_boosts' => [],
      'class' => '',
      'free_boosts' => [],
      'abilities' => [
        'str' => 10, 'dex' => 10, 'con' => 10,
        'int' => 10, 'wis' => 10, 'cha' => 10,
      ],
      'alignment' => '',
      'deity' => '',
      'age' => '',
      'gender' => '',
      'inventory' => [],
      'gold' => 15,
      'appearance' => '',
      'personality' => '',
      'backstory' => '',
      'portrait_generate' => 1,
      'portrait_prompt' => '',
    ];
  }

  /**
   * Prepare ancestry data.
   */
  private function prepareAncestries() {
    $ancestries = [];
    foreach (CharacterManager::ANCESTRIES as $name => $data) {
      $ancestries[] = [
        'id' => strtolower(str_replace(' ', '-', $name)),
        'name' => $name,
        'hp' => $data['hp'],
        'size' => $data['size'],
        'speed' => $data['speed'],
        'boosts' => $data['boosts'],
        'flaw' => $data['flaw'] ?? '',
        'vision' => $data['vision'],
      ];
    }
    return $ancestries;
  }

  /**
   * Get alignment options.
   */
  private function getAlignments() {
    return [
      ['id' => 'LG', 'name' => 'Lawful Good', 'description' => 'Acts with compassion and honor within the bounds of law and order.'],
      ['id' => 'NG', 'name' => 'Neutral Good', 'description' => 'Does good without bias toward or against order.'],
      ['id' => 'CG', 'name' => 'Chaotic Good', 'description' => 'Acts with freedom and kindness.'],
      ['id' => 'LN', 'name' => 'Lawful Neutral', 'description' => 'Values tradition and order above morality.'],
      ['id' => 'N', 'name' => 'Neutral', 'description' => 'Seeks balance or personal interest.'],
      ['id' => 'CN', 'name' => 'Chaotic Neutral', 'description' => 'Follows individual freedom.'],
      ['id' => 'LE', 'name' => 'Lawful Evil', 'description' => 'Uses order as a tool for exploitation.'],
      ['id' => 'NE', 'name' => 'Neutral Evil', 'description' => 'Acts selfishly with no regard for others.'],
      ['id' => 'CE', 'name' => 'Chaotic Evil', 'description' => 'Driven by greed and hatred.'],
    ];
  }

  /**
   * Get equipment catalog.
   */
  private function getEquipmentCatalog() {
    $template_catalog = $this->buildEquipmentCatalogFromTemplates();
    if (!empty($template_catalog['weapons']) || !empty($template_catalog['armor']) || !empty($template_catalog['gear'])) {
      return $template_catalog;
    }

    return [
      'weapons' => [
        ['id' => 'longsword', 'name' => 'Longsword', 'cost' => 1, 'damage' => '1d8 S', 'bulk' => 1, 'hands' => 1],
        ['id' => 'shortsword', 'name' => 'Shortsword', 'cost' => 0.9, 'damage' => '1d6 P', 'bulk' => 'L', 'hands' => 1],
        ['id' => 'dagger', 'name' => 'Dagger', 'cost' => 0.2, 'damage' => '1d4 P', 'bulk' => 'L', 'hands' => 1],
        ['id' => 'staff', 'name' => 'Staff', 'cost' => 0, 'damage' => '1d4 B', 'bulk' => 1, 'hands' => 1],
      ],
      'armor' => [
        ['id' => 'leather', 'name' => 'Leather Armor', 'cost' => 2, 'ac' => '+1', 'bulk' => 1],
        ['id' => 'chain_shirt', 'name' => 'Chain Shirt', 'cost' => 5, 'ac' => '+2', 'bulk' => 1],
      ],
      'gear' => [
        ['id' => 'backpack', 'name' => 'Backpack', 'cost' => 0.1, 'bulk' => 'L'],
        ['id' => 'bedroll', 'name' => 'Bedroll', 'cost' => 0.1, 'bulk' => 'L'],
        ['id' => 'rope', 'name' => 'Rope (50ft)', 'cost' => 0.5, 'bulk' => 'L'],
      ],
    ];
  }

  /**
   * Builds step-7 equipment catalog from template item tables.
   */
  private function buildEquipmentCatalogFromTemplates(): array {
    $catalog = [
      'weapons' => [],
      'armor' => [],
      'gear' => [],
    ];

    if (!$this->database->schema()->tableExists('dungeoncrawler_content_registry')) {
      return $catalog;
    }

    // Query curated starting equipment directly from the content registry.
    $query = $this->database->select('dungeoncrawler_content_registry', 'r');
    $query->fields('r', ['content_id', 'name', 'tags', 'schema_data']);
    $query->condition('r.content_type', 'item');
    $query->condition('r.source_file', 'items/%', 'LIKE');

    $result = $query->execute();

    foreach ($result as $row) {
      $item_id = (string) ($row->content_id ?? '');
      if ($item_id === '') {
        continue;
      }

      $schema_data = json_decode((string) ($row->schema_data ?? '{}'), TRUE);
      if (!is_array($schema_data)) {
        $schema_data = [];
      }

      // Calculate cost in gp from the nested price object.
      $price = $schema_data['price'] ?? [];
      $cost_gp = (float) ($price['gp'] ?? 0)
        + (float) ($price['sp'] ?? 0) / 10
        + (float) ($price['cp'] ?? 0) / 100
        + (float) ($price['pp'] ?? 0) * 10;

      if ($cost_gp == 0 && isset($schema_data['price_gp'])) {
        $cost_gp = (float) $schema_data['price_gp'];
      }

      if ($cost_gp > 15) {
        continue;
      }

      $tags = $this->normalizeTags((string) ($row->tags ?? ''));
      $item_type = (string) ($schema_data['item_type'] ?? 'adventuring_gear');
      $category = $this->mapTemplateItemCategory($item_type, $tags);

      $name = (string) ($row->name ?? '');
      if ($name === '') {
        $name = ucwords(str_replace(['_', '-'], ' ', $item_id));
      }

      $item = [
        'id' => $item_id,
        'name' => $name,
        'type' => $item_type,
        'cost' => round($cost_gp, 2),
        'bulk' => $schema_data['bulk'] ?? 'L',
        'traits' => $schema_data['traits'] ?? $tags,
      ];

      if ($category === 'weapons') {
        $ws = $schema_data['weapon_stats'] ?? [];
        $dmg = $ws['damage'] ?? [];
        $dice = ($dmg['dice_count'] ?? 1) . ($dmg['die_size'] ?? '');
        $dmg_type = $dmg['damage_type'] ?? '';
        $dmg_abbrev = $dmg_type ? strtoupper($dmg_type[0]) : '';
        $item['damage'] = trim($dice . ' ' . $dmg_abbrev);
        $item['hands'] = (int) ($schema_data['hands'] ?? 1);
      }
      elseif ($category === 'armor') {
        $as = $schema_data['armor_stats'] ?? [];
        $ac_bonus = $as['ac_bonus'] ?? NULL;
        if ($ac_bonus !== NULL) {
          $item['ac'] = '+' . (int) $ac_bonus;
          if (($as['category'] ?? '') === 'shield') {
            $item['ac'] .= ' circumstance';
          }
        }
        else {
          $item['ac'] = (string) ($schema_data['ac'] ?? '');
        }
      }

      $catalog[$category][$item_id] = $item;
    }

    foreach (['weapons', 'armor', 'gear'] as $category) {
      uasort($catalog[$category], static function (array $a, array $b): int {
        return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
      });
      $catalog[$category] = array_values($catalog[$category]);
    }

    return $catalog;
  }

  /**
   * Normalizes stored registry tags into a plain string list.
   */
  private function normalizeTags(string $raw_tags): array {
    $decoded = json_decode($raw_tags, TRUE);
    if (is_array($decoded)) {
      return array_values(array_filter(array_map(static fn($tag): string => (string) $tag, $decoded)));
    }

    return [];
  }

  /**
   * Maps template item metadata to step-7 equipment categories.
   */
  private function mapTemplateItemCategory(string $item_type, array $tags): string {
    $normalized_type = strtolower($item_type);
    $normalized_tags = array_map('strtolower', $tags);

    if ($normalized_type === 'weapon' || in_array('weapon', $normalized_tags, TRUE)) {
      return 'weapons';
    }

    if ($normalized_type === 'armor' || in_array('armor', $normalized_tags, TRUE) || in_array('shield', $normalized_tags, TRUE)) {
      return 'armor';
    }

    return 'gear';
  }

  /**
   * Builds a consolidated feats array from character data.
   *
   * @param array $character_data
   *   Character data with feat selections.
   *
   * @return array
   *   Flat array of feat entries.
   */
  private function buildFeatsArrayFromData(array $character_data): array {
    $feats = [];

    // Auto-granted ancestry feats (e.g. Halfling Luck, Keen Eyes).
    $ancestry_name = ucfirst($character_data['ancestry'] ?? '');
    $ancestry_stats = CharacterManager::ANCESTRIES[$ancestry_name] ?? [];
    $auto_grant_ids = $ancestry_stats['special']['auto_grant_feats'] ?? [];
    foreach ($auto_grant_ids as $auto_id) {
      // Look up display name from ANCESTRY_FEATS if available; fall back to ID.
      $display_name = ucwords(str_replace('-', ' ', $auto_id));
      $ancestry_feats_all = CharacterManager::ANCESTRY_FEATS[$ancestry_name] ?? [];
      foreach ($ancestry_feats_all as $f) {
        if ($f['id'] === $auto_id) {
          $display_name = $f['name'];
          break;
        }
      }
      $feats[] = ['type' => 'ancestry', 'id' => $auto_id, 'name' => $display_name, 'level' => 1, 'auto_granted' => TRUE];
    }

    // Ancestry feat.
    if (!empty($character_data['ancestry_feat'])) {
      $ancestry_name = ucfirst($character_data['ancestry'] ?? '');
      $ancestry_feats = CharacterManager::ANCESTRY_FEATS[$ancestry_name] ?? [];
      foreach ($ancestry_feats as $f) {
        if ($f['id'] === $character_data['ancestry_feat']) {
          $feats[] = ['type' => 'ancestry', 'id' => $f['id'], 'name' => $f['name'], 'level' => 1];
          break;
        }
      }
    }

    // Class feat.
    if (!empty($character_data['class_feat'])) {
      $class_feats = CharacterManager::CLASS_FEATS[$character_data['class'] ?? ''] ?? [];
      foreach ($class_feats as $f) {
        if ($f['id'] === $character_data['class_feat']) {
          $feats[] = ['type' => 'class', 'id' => $f['id'], 'name' => $f['name'], 'level' => 1];
          break;
        }
      }
    }

    // General feat.
    if (!empty($character_data['general_feat'])) {
      foreach (CharacterManager::GENERAL_FEATS as $f) {
        if ($f['id'] === $character_data['general_feat']) {
          $feats[] = ['type' => 'general', 'id' => $f['id'], 'name' => $f['name'], 'level' => 1];
          break;
        }
      }
    }

    // Background skill feat.
    if (!empty($character_data['background_skill_feat'])) {
      $feats[] = [
        'type' => 'skill',
        'id' => strtolower(str_replace(' ', '-', $character_data['background_skill_feat'])),
        'name' => $character_data['background_skill_feat'],
        'level' => 1,
        'source' => 'background',
      ];
    }

    return $feats;
  }

}
