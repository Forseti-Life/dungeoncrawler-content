<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Dungeon Crawler Content settings.
 */
class DungeonCrawlerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dungeoncrawler_content.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dungeoncrawler_content_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dungeoncrawler_content.settings');

    $form['game_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('⚔️ Dungeon Settings'),
    ];

    $form['game_settings']['max_level'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Adventurer Level'),
      '#default_value' => $config->get('max_level') ?? 100,
      '#min' => 1,
      '#max' => 999,
      '#description' => $this->t('The maximum level an adventurer can reach in the dungeon.'),
    ];

    $form['game_settings']['difficulty_levels'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Dungeon Depth Tiers'),
      '#default_value' => $config->get('difficulty_levels') ?? "Shallow Halls\nTwisting Corridors\nDeep Caverns\nThe Underdark\nThe Abyss",
      '#description' => $this->t('One dungeon depth tier per line. Deeper tiers have stronger AI-generated monsters and better loot.'),
    ];

    $form['game_settings']['rarity_tiers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Item Rarity Tiers'),
      '#default_value' => $config->get('rarity_tiers') ?? "Common\nUncommon\nRare\nEpic\nLegendary",
      '#description' => $this->t('One rarity tier per line, from lowest to highest. Determines loot drop colors and AI generation parameters.'),
    ];

    $form['ai_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('🧠 AI Generation Settings'),
    ];

    $form['ai_settings']['room_persistence'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rooms are permanent after first generation'),
      '#default_value' => $config->get('room_persistence') ?? TRUE,
      '#description' => $this->t('When enabled, AI-generated rooms become permanent world fixtures after first exploration.'),
    ];

    $form['ai_settings']['monster_permadeath'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable monster permadeath for mortal creatures'),
      '#default_value' => $config->get('monster_permadeath') ?? TRUE,
      '#description' => $this->t('When enabled, mortal monsters that are slain stay dead permanently. Respawning creatures are unaffected.'),
    ];

    $form['ai_settings']['encounter_ai_npc_autoplay_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable AI-driven NPC auto-play in encounters'),
      '#default_value' => $config->get('encounter_ai_npc_autoplay_enabled') ?? FALSE,
      '#description' => $this->t('When enabled, non-player turns can use validated AI recommendations and deterministic fallback behavior. Disabled by default.'),
    ];

    $form['ai_settings']['encounter_ai_narration_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Persist AI narration events in encounter timeline'),
      '#default_value' => $config->get('encounter_ai_narration_enabled') ?? FALSE,
      '#description' => $this->t('When enabled, AI narration snippets are logged as encounter timeline events (`ai_narration`) during NPC auto-play.'),
    ];

    $form['ai_settings']['encounter_ai_retry_attempts'] = [
      '#type' => 'number',
      '#title' => $this->t('Encounter AI retry attempts'),
      '#default_value' => $config->get('encounter_ai_retry_attempts') ?? 2,
      '#min' => 1,
      '#max' => 3,
      '#description' => $this->t('Maximum Bedrock invocation attempts per encounter recommendation/narration request before deterministic fallback.'),
    ];

    $form['ai_settings']['encounter_ai_recommendation_max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Encounter AI recommendation max tokens'),
      '#default_value' => $config->get('encounter_ai_recommendation_max_tokens') ?? 800,
      '#min' => 200,
      '#max' => 2000,
      '#description' => $this->t('Token budget passed to Bedrock recommendation calls.'),
    ];

    $form['ai_settings']['encounter_ai_narration_max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Encounter AI narration max tokens'),
      '#default_value' => $config->get('encounter_ai_narration_max_tokens') ?? 500,
      '#min' => 120,
      '#max' => 1200,
      '#description' => $this->t('Token budget passed to Bedrock narration calls.'),
    ];

    $form['ai_settings']['gemini_image_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Gemini image generation live mode'),
      '#default_value' => $config->get('gemini_image_enabled') ?? FALSE,
      '#description' => $this->t('When enabled, dashboard image requests attempt a live Gemini API call when an API key is available.'),
    ];

    $form['ai_settings']['generated_image_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Default image provider'),
      '#options' => [
        'gemini' => $this->t('Gemini'),
        'vertex' => $this->t('Vertex (Vertix)'),
      ],
      '#default_value' => $config->get('generated_image_provider') ?? 'gemini',
      '#description' => $this->t('Default provider used by dashboard image generation when no provider override is selected.'),
    ];

    $form['ai_settings']['gemini_image_model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gemini image model'),
      '#default_value' => $config->get('gemini_image_model') ?? 'gemini-2.0-flash-exp',
      '#maxlength' => 255,
      '#description' => $this->t('Model name used for image generation requests. Example: gemini-2.0-flash-exp.'),
    ];

    $form['ai_settings']['gemini_image_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gemini endpoint template'),
      '#default_value' => $config->get('gemini_image_endpoint') ?? 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
      '#maxlength' => 512,
      '#description' => $this->t('Endpoint template for Gemini requests. Use {model} as placeholder for the selected model.'),
    ];

    $form['ai_settings']['gemini_image_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Gemini request timeout (seconds)'),
      '#default_value' => $config->get('gemini_image_timeout') ?? 30,
      '#min' => 5,
      '#max' => 120,
    ];

    $form['ai_settings']['gemini_image_api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('Gemini API key (optional)'),
      '#description' => $this->t('Prefer environment variable GEMINI_API_KEY. If set here, this value is stored in Drupal configuration.'),
      '#maxlength' => 255,
      '#attributes' => [
        'autocomplete' => 'new-password',
      ],
    ];

    $form['ai_settings']['gemini_system_context_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Gemini system context prompt'),
      '#default_value' => $config->get('gemini_system_context_prompt') ?? '',
      '#rows' => 10,
      '#description' => $this->t('System prompt automatically wrapped around user input for Gemini requests from the Gemini interface.'),
    ];

    $form['ai_settings']['vertex_image_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Vertex image generation live mode'),
      '#default_value' => $config->get('vertex_image_enabled') ?? FALSE,
      '#description' => $this->t('When enabled, dashboard image requests can use Vertex live API calls when configured credentials are available.'),
    ];

    $form['ai_settings']['vertex_image_project_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vertex project ID'),
      '#default_value' => $config->get('vertex_image_project_id') ?? '',
      '#maxlength' => 255,
    ];

    $form['ai_settings']['vertex_image_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vertex location'),
      '#default_value' => $config->get('vertex_image_location') ?? 'us-central1',
      '#maxlength' => 64,
    ];

    $form['ai_settings']['vertex_image_model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vertex image model'),
      '#default_value' => $config->get('vertex_image_model') ?? 'imagen-3.0-generate-002',
      '#maxlength' => 255,
      '#description' => $this->t('Model name used for Vertex image requests. Example: imagen-3.0-generate-002.'),
    ];

    $form['ai_settings']['vertex_image_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vertex endpoint template'),
      '#default_value' => $config->get('vertex_image_endpoint') ?? 'https://{location}-aiplatform.googleapis.com/v1/projects/{project_id}/locations/{location}/publishers/google/models/{model}:predict',
      '#maxlength' => 512,
      '#description' => $this->t('Endpoint template. Supports placeholders: {project_id}, {location}, {model}.'),
    ];

    $form['ai_settings']['vertex_image_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Vertex request timeout (seconds)'),
      '#default_value' => $config->get('vertex_image_timeout') ?? 30,
      '#min' => 5,
      '#max' => 120,
    ];

    $form['ai_settings']['vertex_image_api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('Vertex API key (optional)'),
      '#description' => $this->t('Prefer environment variable VERTEX_API_KEY. If set here, this value is stored in Drupal configuration.'),
      '#maxlength' => 255,
      '#attributes' => [
        'autocomplete' => 'new-password',
      ],
    ];

    $form['display_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('🗺️ Display Settings'),
    ];

    $form['display_settings']['items_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Items per page'),
      '#default_value' => $config->get('items_per_page') ?? 12,
      '#min' => 4,
      '#max' => 100,
      '#description' => $this->t('Number of dungeon rooms, items, or creatures to display per page in listings.'),
    ];

    $form['display_settings']['show_game_stats'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show adventure statistics on content pages'),
      '#default_value' => $config->get('show_game_stats') ?? TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dungeoncrawler_content.settings')
      ->set('max_level', $form_state->getValue('max_level'))
      ->set('difficulty_levels', $form_state->getValue('difficulty_levels'))
      ->set('rarity_tiers', $form_state->getValue('rarity_tiers'))
      ->set('room_persistence', $form_state->getValue('room_persistence'))
      ->set('monster_permadeath', $form_state->getValue('monster_permadeath'))
      ->set('encounter_ai_npc_autoplay_enabled', $form_state->getValue('encounter_ai_npc_autoplay_enabled'))
      ->set('encounter_ai_narration_enabled', $form_state->getValue('encounter_ai_narration_enabled'))
      ->set('encounter_ai_retry_attempts', (int) $form_state->getValue('encounter_ai_retry_attempts'))
      ->set('encounter_ai_recommendation_max_tokens', (int) $form_state->getValue('encounter_ai_recommendation_max_tokens'))
      ->set('encounter_ai_narration_max_tokens', (int) $form_state->getValue('encounter_ai_narration_max_tokens'))
      ->set('generated_image_provider', (string) $form_state->getValue('generated_image_provider'))
      ->set('gemini_image_enabled', $form_state->getValue('gemini_image_enabled'))
      ->set('gemini_image_model', trim((string) $form_state->getValue('gemini_image_model')))
      ->set('gemini_image_endpoint', trim((string) $form_state->getValue('gemini_image_endpoint')))
      ->set('gemini_image_timeout', (int) $form_state->getValue('gemini_image_timeout'))
      ->set('gemini_system_context_prompt', trim((string) $form_state->getValue('gemini_system_context_prompt')))
      ->set('vertex_image_enabled', $form_state->getValue('vertex_image_enabled'))
      ->set('vertex_image_project_id', trim((string) $form_state->getValue('vertex_image_project_id')))
      ->set('vertex_image_location', trim((string) $form_state->getValue('vertex_image_location')))
      ->set('vertex_image_model', trim((string) $form_state->getValue('vertex_image_model')))
      ->set('vertex_image_endpoint', trim((string) $form_state->getValue('vertex_image_endpoint')))
      ->set('vertex_image_timeout', (int) $form_state->getValue('vertex_image_timeout'))
      ->set('items_per_page', $form_state->getValue('items_per_page'))
      ->set('show_game_stats', $form_state->getValue('show_game_stats'))
      ->save();

    $submitted_key = trim((string) $form_state->getValue('gemini_image_api_key'));
    if ($submitted_key !== '') {
      $this->config('dungeoncrawler_content.settings')
        ->set('gemini_image_api_key', $submitted_key)
        ->save();
    }

    $submitted_vertex_key = trim((string) $form_state->getValue('vertex_image_api_key'));
    if ($submitted_vertex_key !== '') {
      $this->config('dungeoncrawler_content.settings')
        ->set('vertex_image_api_key', $submitted_vertex_key)
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

}
