<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Admin form to toggle /dungeoncrawler/testing/thetest status.
 */
class TheTestToggleForm extends FormBase {

  public function __construct(private StateInterface $state) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('state'));
  }

  public function getFormId(): string {
    return 'dungeoncrawler_tester_thetest_toggle';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $status = $this->state->get('dungeoncrawler_tester.thetest_status', 'pass');

    $form['current'] = [
      '#type' => 'item',
      '#title' => $this->t('Current state'),
      '#markup' => $status === 'fail' ? $this->t('TEST:FAIL') : $this->t('TEST:PASS'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'pass' => [
        '#type' => 'submit',
        '#value' => $this->t('Set to PASS'),
        '#submit' => ['::setPass'],
      ],
      'fail' => [
        '#type' => 'submit',
        '#value' => $this->t('Set to FAIL'),
        '#submit' => ['::setFail'],
      ],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Not used; individual handlers handle state changes.
  }

  public function setPass(array &$form, FormStateInterface $form_state): void {
    $this->state->set('dungeoncrawler_tester.thetest_status', 'pass');
    $this->messenger()->addStatus($this->t('Set to PASS.'));
  }

  public function setFail(array &$form, FormStateInterface $form_state): void {
    $this->state->set('dungeoncrawler_tester.thetest_status', 'fail');
    $this->messenger()->addStatus($this->t('Set to FAIL.'));
  }

}
