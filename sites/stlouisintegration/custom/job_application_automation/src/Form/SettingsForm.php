<?php

namespace Drupal\job_application_automation\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Job Application Automation module settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['job_application_automation.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'job_application_automation_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('job_application_automation.settings');

    $form['resume_tailoring'] = [
      '#type' => 'details',
      '#title' => $this->t('Resume Tailoring Settings'),
      '#open' => TRUE,
    ];

    // Load the entity if we have an ID stored
    $default_resume = NULL;
    $resume_node_id = $config->get('original_resume_node_id');
    if ($resume_node_id && is_numeric($resume_node_id)) {
      $resume_node = \Drupal\node\Entity\Node::load($resume_node_id);
      if ($resume_node && $resume_node->access('view')) {
        $default_resume = $resume_node;
      }
    }

    $form['resume_tailoring']['original_resume_node_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Original Resume Node'),
      '#description' => $this->t('Select the resume node that contains the master resume content. This will be used as the base for all tailored resumes. Leave empty to search for a node titled "Original Resume".'),
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['resume'],
      ],
      '#default_value' => $default_resume,
    ];

    $form['resume_tailoring']['enable_automatic_tailoring'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Automatic Resume Tailoring'),
      '#description' => $this->t('When enabled, a tailored resume will be automatically generated when a new job posting is created.'),
      '#default_value' => $config->get('enable_automatic_tailoring') ?? TRUE,
    ];

    $form['ai_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('AI Service Configuration'),
      '#open' => FALSE,
    ];

    $form['ai_settings']['ai_service_region'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AWS Region'),
      '#description' => $this->t('The AWS region for Bedrock service (e.g., us-west-2).'),
      '#default_value' => $config->get('ai_service_region') ?? 'us-west-2',
      '#required' => TRUE,
    ];

    $form['ai_settings']['ai_model_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AI Model ID'),
      '#description' => $this->t('The AWS Bedrock model ID to use for resume tailoring.'),
      '#default_value' => $config->get('ai_model_id') ?? 'anthropic.claude-3-5-sonnet-20240620-v1:0',
      '#required' => TRUE,
    ];

    $form['ai_settings']['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Tokens'),
      '#description' => $this->t('Maximum number of tokens for AI generation.'),
      '#default_value' => $config->get('max_tokens') ?? 4000,
      '#required' => TRUE,
      '#min' => 1000,
      '#max' => 10000,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('job_application_automation.settings')
      ->set('original_resume_node_id', $form_state->getValue('original_resume_node_id'))
      ->set('enable_automatic_tailoring', $form_state->getValue('enable_automatic_tailoring'))
      ->set('ai_service_region', $form_state->getValue('ai_service_region'))
      ->set('ai_model_id', $form_state->getValue('ai_model_id'))
      ->set('max_tokens', $form_state->getValue('max_tokens'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
