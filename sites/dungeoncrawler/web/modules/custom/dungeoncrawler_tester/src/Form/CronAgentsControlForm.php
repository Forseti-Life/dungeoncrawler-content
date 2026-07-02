<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Quick dashboard control for tester cron agents.
 */
class CronAgentsControlForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_tester_cron_agents_control_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $enabled = (bool) ($this->config('dungeoncrawler_tester.settings')->get('cron_agents_enabled') ?? TRUE);

    $form['cron_agents_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable tester cron agents'),
      '#description' => $this->t('Controls cron-driven issue sync and auto-enqueue. Manual queue actions still work when disabled.'),
      '#default_value' => $enabled,
    ];

    $form['status'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => ['class' => ['text-muted-light']],
      '#value' => $enabled
        ? $this->t('Current status: enabled')
        : $this->t('Current status: paused'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save cron agent setting'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $enabled = (bool) $form_state->getValue('cron_agents_enabled');
    $this->configFactory()->getEditable('dungeoncrawler_tester.settings')
      ->set('cron_agents_enabled', $enabled)
      ->save();

    $this->messenger()->addStatus($enabled
      ? $this->t('Tester cron agents enabled.')
      : $this->t('Tester cron agents paused.'));
  }

}
