<?php

namespace Drupal\resume_tailoring\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\resume_tailoring\ResumeTailoringManager;

/**
 * Configuration form for Resume Tailoring settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The resume tailoring manager.
   *
   * @var \Drupal\resume_tailoring\ResumeTailoringManager
   */
  protected $resumeTailoringManager;

  /**
   * Constructs a SettingsForm object.
   *
   * @param \Drupal\resume_tailoring\ResumeTailoringManager $resume_tailoring_manager
   *   The resume tailoring manager.
   */
  public function __construct(ResumeTailoringManager $resume_tailoring_manager) {
    $this->resumeTailoringManager = $resume_tailoring_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('resume_tailoring.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'resume_tailoring_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['resume_tailoring.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('resume_tailoring.settings');

    $form['ai_integration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('AI Integration'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#description' => $this->t('Resume tailoring uses the ai_conversation module for AWS Bedrock integration. Configure AWS credentials in the AI Conversation module settings.'),
    ];

    $form['ai_integration']['ai_service_status'] = [
      '#markup' => '<p>' . $this->t('AWS Bedrock AI services are provided by the <strong>ai_conversation</strong> module. Ensure that module is enabled and configured with valid AWS credentials.') . '</p>',
    ];

    $form['ai_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('AI Model Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['ai_settings']['ai_model'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Model'),
      '#description' => $this->t('The Bedrock AI model to use for resume tailoring.'),
      '#options' => [
        'anthropic.claude-3-5-sonnet-20240620-v1:0' => $this->t('Claude 3.5 Sonnet (Recommended)'),
        'anthropic.claude-3-haiku-20240307-v1:0' => $this->t('Claude 3 Haiku (Faster)'),
        'anthropic.claude-3-opus-20240229-v1:0' => $this->t('Claude 3 Opus (Most Capable)'),
      ],
      '#default_value' => $config->get('ai_model') ?: 'anthropic.claude-3-5-sonnet-20240620-v1:0',
      '#required' => TRUE,
    ];

    $form['ai_settings']['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Tokens'),
      '#description' => $this->t('Maximum number of tokens to use for tailored resume generation.'),
      '#default_value' => $config->get('max_tokens') ?: 4000,
      '#min' => 1000,
      '#max' => 8000,
      '#required' => TRUE,
    ];

    $form['tailoring_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Resume Tailoring Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['tailoring_settings']['auto_generate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-generate tailored resumes'),
      '#description' => $this->t('Automatically generate tailored resumes when job postings are created or updated.'),
      '#default_value' => $config->get('auto_generate') ?? TRUE,
    ];

    $form['tailoring_settings']['preserve_contact_info'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preserve contact information'),
      '#description' => $this->t('Ensure contact information is preserved exactly in tailored resumes.'),
      '#default_value' => $config->get('preserve_contact_info') ?? TRUE,
    ];

    $form['tailoring_settings']['emphasize_keywords'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Emphasize job posting keywords'),
      '#description' => $this->t('Automatically incorporate relevant keywords from job postings into tailored resumes.'),
      '#default_value' => $config->get('emphasize_keywords') ?? TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('resume_tailoring.settings');
    
    // Save AI settings.
    $config->set('ai_model', $form_state->getValue('ai_model'));
    $config->set('max_tokens', $form_state->getValue('max_tokens'));
    
    // Save tailoring settings.
    $config->set('auto_generate', $form_state->getValue('auto_generate'));
    $config->set('preserve_contact_info', $form_state->getValue('preserve_contact_info'));
    $config->set('emphasize_keywords', $form_state->getValue('emphasize_keywords'));
    
    $config->save();
    
    $this->messenger()->addMessage($this->t('Resume Tailoring settings saved successfully.'));
    
    parent::submitForm($form, $form_state);
  }

}