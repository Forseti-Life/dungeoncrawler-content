<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Button form to close dead-value PRs and optionally linked issues.
 */
class DeadValueCloseForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_tester_dead_value_close_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $prNumber = 0, int $issueNumber = 0): array {
    $form['pr_number'] = [
      '#type' => 'hidden',
      '#value' => $prNumber,
    ];

    $form['issue_number'] = [
      '#type' => 'hidden',
      '#value' => $issueNumber,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $issueNumber > 0
        ? $this->t('Close dead PR + issue')
        : $this->t('Close dead PR'),
      '#attributes' => ['class' => ['button', 'button--small']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addWarning($this->t('Dead-value GitHub close actions are disabled in local tracker mode. Use the import page for GitHub operations.'));
    $form_state->setRedirect('dungeoncrawler_tester.issue_pr_report');
  }

}
