<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Core\State\StateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for Dungeon Crawler tester.
 */
class TesterSettingsForm extends ConfigFormBase {

  /**
   * State key for sensitive GitHub token storage.
   */
  private const TOKEN_STATE_KEY = 'dungeoncrawler_tester.github_token';

  /**
   * State API for non-exported secret storage.
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_tester_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['dungeoncrawler_tester.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('dungeoncrawler_tester.settings');
    $stored_token = trim((string) $this->state->get(self::TOKEN_STATE_KEY, ''));
    $legacy_config_token = trim((string) ($config->get('github_token') ?: ''));
    $has_token = $stored_token !== '' || $legacy_config_token !== '';

    $form['help'] = [
      '#type' => 'details',
      '#title' => $this->t('How to get a GitHub token'),
      '#open' => TRUE,
      '#description' => $this->t('Create a token at <a href=":tokens">GitHub settings → Tokens</a>. A classic token only needs the <code>public_repo</code> scope for issue creation; fine-grained tokens need repo issue access. Default repo: <code>keithaumiller/forseti.life</code> (override below).', [':tokens' => 'https://github.com/settings/tokens']),
    ];

    $form['github_repo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GitHub repository'),
      '#description' => $this->t('Format: owner/repo. Used for auto-created issues on failed tester stages. If left empty, the tester will fall back to ai_conversation settings or the TESTER_GITHUB_REPO environment variable.'),
      '#default_value' => $config->get('github_repo') ?: '',
      '#required' => FALSE,
    ];

    $form['github_token'] = [
      '#type' => 'password',
      '#title' => $this->t('GitHub token'),
      '#description' => $has_token
        ? $this->t('Token is stored in private state (not config export). Enter a new token to replace, or check "Clear stored token" to remove. If empty, the tester will fall back to ai_conversation settings or the TESTER_GITHUB_TOKEN environment variable.')
        : $this->t('Enter a token with permission to create issues in the configured repository. Saved tokens are stored in private state (not config export). If left empty, the tester will fall back to ai_conversation settings or the TESTER_GITHUB_TOKEN environment variable.'),
      '#default_value' => '',
      '#attributes' => ['autocomplete' => 'new-password'],
    ];

    $form['clear_github_token'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clear stored token'),
      '#description' => $this->t('If checked, the stored token will be removed when saving. Leave unchecked to keep the current token unless a new one is provided above.'),
      '#default_value' => FALSE,
    ];

    $form['copilot_assignment_max_open'] = [
      '#type' => 'number',
      '#title' => $this->t('Copilot assignment max open issues'),
      '#description' => $this->t('Maximum number of open issues already assigned to Copilot before new auto-assignment is skipped. Set to 0 to disable this cap. Default: 0 (disabled).'),
      '#default_value' => (int) ($config->get('copilot_assignment_max_open') ?? 0),
      '#min' => 0,
      '#step' => 1,
      '#required' => FALSE,
    ];

    $form['cron_agents_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable tester cron agents'),
      '#description' => $this->t('When unchecked, tester cron automation is paused (issue sync + stage auto-enqueue). Manual dashboard/queue actions remain available.'),
      '#default_value' => (bool) ($config->get('cron_agents_enabled') ?? TRUE),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $repo = trim((string) $form_state->getValue('github_repo'));
    if ($repo !== '' && !str_contains($repo, '/')) {
      $form_state->setErrorByName('github_repo', $this->t('Repository must be in the format owner/repo.'));
    }

    $max_open = $form_state->getValue('copilot_assignment_max_open');
    if ($max_open !== NULL && $max_open !== '' && ((int) $max_open) < 0) {
      $form_state->setErrorByName('copilot_assignment_max_open', $this->t('Max open issues must be 0 or greater.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('dungeoncrawler_tester.settings');

    $repo = trim((string) $form_state->getValue('github_repo'));
    $config->set('github_repo', $repo);

    $clear_token = (bool) $form_state->getValue('clear_github_token');
    $new_token = trim((string) $form_state->getValue('github_token'));

    if ($clear_token) {
      $this->state->delete(self::TOKEN_STATE_KEY);
    }
    elseif ($new_token !== '') {
      $this->state->set(self::TOKEN_STATE_KEY, $new_token);
    }

    // Ensure token is not persisted in exported config.
    $config->set('github_token', '');

    $max_open = (int) $form_state->getValue('copilot_assignment_max_open');
    $config->set('copilot_assignment_max_open', max(0, $max_open));

    $config->set('cron_agents_enabled', (bool) $form_state->getValue('cron_agents_enabled'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
