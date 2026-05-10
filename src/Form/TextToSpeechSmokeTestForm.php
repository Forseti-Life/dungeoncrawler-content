<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dungeoncrawler_content\Service\TextToSpeechIntegrationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin-only smoke test form for optional Forseti TTS integration.
 */
class TextToSpeechSmokeTestForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Bridge service.
   */
  protected TextToSpeechIntegrationService $ttsIntegration;

  /**
   * Constructs the form.
   */
  public function __construct(TextToSpeechIntegrationService $tts_integration) {
    $this->ttsIntegration = $tts_integration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.text_to_speech_integration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_content_text_to_speech_smoke_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $status = $this->ttsIntegration->getIntegrationStatus();
    $provider_status = is_array($status['provider_status'] ?? NULL) ? $status['provider_status'] : [];
    $available = !empty($status['available']);

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('This smoke test proves Dungeoncrawler can detect and invoke the standalone Forseti TTS module without embedding Google-specific logic in the main app.') . '</p>',
    ];

    $form['availability'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'margin: 0 0 1rem 0;',
      ],
    ];
    $form['availability']['message'] = [
      '#markup' => '<p><strong>' . $this->t('Integration status') . ':</strong> ' . Html::escape((string) ($status['message'] ?? '')) . '</p>',
    ];

    if (!$available) {
      $form['availability']['help'] = [
        '#markup' => '<p>' . $this->t('Enable the external `forseti_tts` module in this Drupal installation, then return here to run a live synthesis request.') . '</p>',
      ];
    }

    $form['status'] = [
      '#type' => 'details',
      '#title' => $this->t('Provider status'),
      '#open' => TRUE,
    ];
    $form['status']['service_id'] = [
      '#markup' => '<p><strong>' . $this->t('Expected service') . ':</strong> ' . Html::escape((string) ($status['service_id'] ?? 'forseti_tts.google_text_to_speech')) . '</p>',
    ];
    $form['status']['provider_status'] = [
      '#markup' => '<pre>' . Html::escape(print_r($provider_status, TRUE)) . '</pre>',
    ];

    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text to synthesize'),
      '#default_value' => 'Dungeon Crawler text to speech smoke test.',
      '#required' => TRUE,
      '#rows' => 4,
    ];

    $form['language_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language code'),
      '#default_value' => (string) ($provider_status['default_language_code'] ?? 'en-US'),
    ];

    $form['voice_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Voice name'),
      '#default_value' => (string) ($provider_status['default_voice_name'] ?? ''),
      '#description' => $this->t('Optional provider voice override.'),
    ];

    $form['audio_encoding'] = [
      '#type' => 'select',
      '#title' => $this->t('Audio encoding'),
      '#default_value' => (string) ($provider_status['default_audio_encoding'] ?? 'MP3'),
      '#options' => [
        'MP3' => 'MP3',
        'OGG_OPUS' => 'OGG_OPUS',
        'LINEAR16' => 'LINEAR16',
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate audio'),
      '#button_type' => 'primary',
      '#disabled' => !$available,
    ];

    $result = $form_state->get('tts_smoke_test_result');
    if (is_array($result)) {
      $form['result'] = [
        '#type' => 'details',
        '#title' => $this->t('Latest result'),
        '#open' => TRUE,
      ];

      if (!empty($result['message'])) {
        $form['result']['message'] = [
          '#markup' => '<p><strong>' . $this->t('Message') . ':</strong> ' . Html::escape((string) $result['message']) . '</p>',
        ];
      }

      if (!empty($result['voice']) && is_array($result['voice'])) {
        $voice_name = trim((string) ($result['voice']['name'] ?? ''));
        $voice_summary = trim((string) ($result['voice']['language_code'] ?? ''));
        if ($voice_name !== '') {
          $voice_summary .= ' / ' . $voice_name;
        }
        $form['result']['voice'] = [
          '#markup' => '<p><strong>' . $this->t('Voice') . ':</strong> ' . Html::escape($voice_summary) . '</p>',
        ];
      }

      if (!empty($result['storage']) && is_array($result['storage'])) {
        $storage = $result['storage'];
        $form['result']['storage_uri'] = [
          '#markup' => '<p><strong>' . $this->t('Stored URI') . ':</strong> ' . Html::escape((string) ($storage['uri'] ?? '')) . '</p>',
        ];
        if (!empty($storage['realpath'])) {
          $form['result']['storage_realpath'] = [
            '#markup' => '<p><strong>' . $this->t('Stored path') . ':</strong> ' . Html::escape((string) $storage['realpath']) . '</p>',
          ];
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $text = trim((string) $form_state->getValue('text'));
    if (mb_strlen($text) < 8) {
      $form_state->setErrorByName('text', $this->t('Text must be at least 8 characters for a meaningful smoke test.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $result = $this->ttsIntegration->synthesizeSpeech((string) $form_state->getValue('text'), [
      'language_code' => (string) $form_state->getValue('language_code'),
      'voice_name' => (string) $form_state->getValue('voice_name'),
      'audio_encoding' => (string) $form_state->getValue('audio_encoding'),
    ]);

    if (empty($result['success'])) {
      $this->messenger()->addError((string) ($result['message'] ?? $this->t('Text-to-speech generation failed.')));
      $form_state->set('tts_smoke_test_result', $result);
      $form_state->setRebuild(TRUE);
      return;
    }

    $storage = $this->ttsIntegration->storeAudioResult($result);
    $result['storage'] = $storage;
    $form_state->set('tts_smoke_test_result', $result);
    $form_state->setRebuild(TRUE);

    if (!empty($storage['success'])) {
      $this->messenger()->addStatus($this->t('Audio generated successfully and stored at @uri.', [
        '@uri' => (string) ($storage['uri'] ?? ''),
      ]));
      return;
    }

    $this->messenger()->addWarning($this->t('Audio generated, but storage failed: @message', [
      '@message' => (string) ($storage['message'] ?? 'Unknown storage error'),
    ]));
  }

}
