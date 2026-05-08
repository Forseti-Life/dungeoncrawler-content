<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dedicated image generation interface.
 */
class ImageGenerationInterfaceForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Integration service.
   */
  protected ImageGenerationIntegrationService $integrationService;

  /**
   * Generated image repository.
   */
  protected GeneratedImageRepository $generatedImageRepository;

  /**
   * Constructs an ImageGenerationInterfaceForm.
   */
  public function __construct(ImageGenerationIntegrationService $integration_service, GeneratedImageRepository $generated_image_repository) {
    $this->integrationService = $integration_service;
    $this->generatedImageRepository = $generated_image_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.image_generation_integration'),
      $container->get('dungeoncrawler_content.generated_image_repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_content_image_generation_interface_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $integration_status = $this->integrationService->getIntegrationStatus();
    $default_provider = (string) ($integration_status['default_provider'] ?? 'vertex');

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Enter a prompt to generate an image using the configured provider (@provider).', [
        '@provider' => strtoupper($default_provider),
      ]) . '</p>',
    ];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#required' => TRUE,
      '#rows' => 4,
      '#description' => $this->t('Describe the image you want generated.'),
    ];

    $form['style'] = [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#options' => [
        'fantasy' => $this->t('Fantasy concept art'),
        'realistic' => $this->t('Realistic'),
        'pixel' => $this->t('Pixel art'),
        'illustration' => $this->t('Illustration'),
      ],
      '#default_value' => 'fantasy',
    ];

    $form['aspect_ratio'] = [
      '#type' => 'select',
      '#title' => $this->t('Aspect ratio'),
      '#options' => [
        '1:1' => '1:1',
        '16:9' => '16:9',
        '9:16' => '9:16',
        '4:3' => '4:3',
      ],
      '#default_value' => '1:1',
    ];

    $form['negative_prompt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Negative prompt (optional)'),
      '#maxlength' => 500,
    ];

    $form['campaign_context'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Campaign context (optional)'),
      '#maxlength' => 255,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Image'),
      '#button_type' => 'primary',
    ];

    $result = $form_state->get('image_generation_interface_result');
    if (is_array($result)) {
      $form['result'] = [
        '#type' => 'details',
        '#title' => $this->t('Latest generation result'),
        '#open' => TRUE,
        'request' => [
          '#markup' => '<p><strong>' . $this->t('Request ID') . ':</strong> ' . Html::escape((string) ($result['request_id'] ?? '')) . '</p>',
        ],
        'provider' => [
          '#markup' => '<p><strong>' . $this->t('Provider') . ':</strong> ' . Html::escape(strtoupper((string) ($result['provider'] ?? 'unknown'))) . '</p>',
        ],
        'mode' => [
          '#markup' => '<p><strong>' . $this->t('Mode') . ':</strong> ' . Html::escape((string) ($result['mode'] ?? '')) . '</p>',
        ],
      ];

      if (!empty($result['output']['text']) && is_string($result['output']['text'])) {
        $form['result']['text'] = [
          '#markup' => '<p><strong>' . $this->t('Model text response') . ':</strong> ' . Html::escape((string) $result['output']['text']) . '</p>',
        ];
      }

      $image_data_uri = isset($result['output']['image_data_uri']) && is_string($result['output']['image_data_uri']) ? $result['output']['image_data_uri'] : '';
      if ($image_data_uri !== '' && $this->isSafeDataUriImage($image_data_uri)) {
        $form['result']['image_preview'] = [
          '#type' => 'html_tag',
          '#tag' => 'img',
          '#attributes' => [
            'src' => $image_data_uri,
            'alt' => $this->t('Generated image'),
            'style' => 'max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px; margin-top: 8px;',
          ],
        ];
      }

      if (!empty($result['output']['image_url']) && is_string($result['output']['image_url'])) {
        $form['result']['image_url'] = [
          '#markup' => '<p><strong>' . $this->t('Image URL') . ':</strong> ' . Html::escape((string) $result['output']['image_url']) . '</p>',
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $prompt = trim((string) $form_state->getValue('prompt'));
    if (mb_strlen($prompt) < 12) {
      $form_state->setErrorByName('prompt', $this->t('Prompt must be at least 12 characters for usable generation output.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $user_prompt = trim((string) $form_state->getValue('prompt'));

    $payload = [
      'prompt' => $user_prompt,
      'style' => $form_state->getValue('style'),
      'aspect_ratio' => $form_state->getValue('aspect_ratio'),
      'negative_prompt' => $form_state->getValue('negative_prompt'),
      'campaign_context' => $form_state->getValue('campaign_context'),
      'requested_by_uid' => (int) $this->currentUser()->id(),
    ];

    $result = $this->integrationService->generateImage($payload, NULL);

    $result['storage'] = $this->generatedImageRepository->persistGeneratedImage($result, [
      'owner_uid' => (int) $this->currentUser()->id(),
      'scope_type' => 'template',
      'variant' => 'original',
      'slot' => 'portrait',
      'visibility' => 'owner',
      'is_primary' => 1,
    ]);

    $form_state->set('image_generation_interface_result', $result);
    $form_state->setRebuild(TRUE);

    if (!empty($result['success'])) {
      $this->messenger()->addStatus($this->t('@provider request @request_id completed in @mode mode.', [
        '@provider' => strtoupper((string) ($result['provider'] ?? 'image')),
        '@request_id' => (string) ($result['request_id'] ?? 'unknown'),
        '@mode' => (string) ($result['mode'] ?? 'unknown'),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('@provider request failed: @message', [
        '@provider' => strtoupper((string) ($result['provider'] ?? 'image')),
        '@message' => (string) ($result['message'] ?? 'Unknown error'),
      ]));
    }
  }

  /**
   * Validate that a data URI points to an image payload.
   */
  private function isSafeDataUriImage(string $uri): bool {
    if (strlen($uri) > 8 * 1024 * 1024) {
      return FALSE;
    }

    return (bool) preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,[A-Za-z0-9+\/=\r\n]+$/', $uri);
  }

}
