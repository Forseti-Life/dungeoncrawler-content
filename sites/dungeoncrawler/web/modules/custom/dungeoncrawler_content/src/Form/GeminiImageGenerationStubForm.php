<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dashboard form for image generation integration stubbing.
 */
class GeminiImageGenerationStubForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Integration service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService
   */
  protected ImageGenerationIntegrationService $integrationService;

  /**
   * Generated image repository.
   *
   * @var \Drupal\dungeoncrawler_content\Service\GeneratedImageRepository
   */
  protected GeneratedImageRepository $generatedImageRepository;

  /**
   * Constructs a GeminiImageGenerationStubForm.
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
    return 'dungeoncrawler_content_gemini_image_generation_stub_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Image prompt'),
      '#description' => $this->t('Describe the scene to generate. This is stored only in stub logs for now.'),
      '#required' => TRUE,
      '#rows' => 3,
    ];

    $integration_status = $this->integrationService->getIntegrationStatus();
    $default_provider = (string) ($integration_status['default_provider'] ?? 'gemini');

    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Provider'),
      '#options' => [
        'gemini' => $this->t('Gemini'),
        'vertex' => $this->t('Vertex (Vertix)'),
      ],
      '#default_value' => $default_provider,
      '#description' => $this->t('Choose the provider for this request. Default is configured in admin settings.'),
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
      '#description' => $this->t('Example: campaign_id=42, dungeon=obsidian-catacombs, room=R07'),
      '#maxlength' => 255,
    ];

    $form['link_context'] = [
      '#type' => 'details',
      '#title' => $this->t('Object link context (optional)'),
      '#open' => FALSE,
    ];

    $form['link_context']['campaign_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Campaign ID'),
      '#min' => 1,
      '#description' => $this->t('Required when linking campaign-scoped assets.'),
    ];

    $form['link_context']['table_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Table name'),
      '#maxlength' => 128,
      '#description' => $this->t('Example: dc_campaign_characters, dc_campaign_rooms, dungeoncrawler_content_dungeons'),
    ];

    $form['link_context']['object_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Object ID'),
      '#maxlength' => 128,
      '#description' => $this->t('Object identifier in the selected table (numeric IDs allowed).'),
    ];

    $form['link_context']['slot'] = [
      '#type' => 'select',
      '#title' => $this->t('Image slot'),
      '#options' => [
        'portrait' => $this->t('Portrait'),
        'token' => $this->t('Token'),
        'card' => $this->t('Card art'),
        'splash' => $this->t('Splash'),
        'background' => $this->t('Background'),
      ],
      '#default_value' => 'portrait',
    ];

    $form['link_context']['visibility'] = [
      '#type' => 'select',
      '#title' => $this->t('Visibility'),
      '#options' => [
        'owner' => $this->t('Owner only'),
        'campaign_party' => $this->t('Campaign party'),
        'public' => $this->t('Public'),
      ],
      '#default_value' => 'owner',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Queue Image Request'),
      '#button_type' => 'primary',
    ];

    $result = $form_state->get('image_generation_result');
    if (is_array($result)) {
      $form['result'] = [
        '#type' => 'details',
        '#title' => $this->t('Latest stub response'),
        '#open' => TRUE,
        'request' => [
          '#markup' => '<p><strong>' . $this->t('Request ID') . ':</strong> ' . Html::escape((string) $result['request_id']) . '</p>',
        ],
        'mode' => [
          '#markup' => '<p><strong>' . $this->t('Mode') . ':</strong> ' . Html::escape((string) $result['mode']) . '</p>',
        ],
        'status' => [
          '#markup' => '<p><strong>' . $this->t('Status') . ':</strong> ' . Html::escape((string) $result['status']) . '</p>',
        ],
      ];

      if (!empty($result['output']['text']) && is_string($result['output']['text'])) {
        $form['result']['text_output'] = [
          '#markup' => '<p><strong>' . $this->t('Model text response') . ':</strong> ' . Html::escape($result['output']['text']) . '</p>',
        ];
      }

      $image_data_uri = isset($result['output']['image_data_uri']) && is_string($result['output']['image_data_uri']) ? $result['output']['image_data_uri'] : '';
      if ($image_data_uri !== '' && $this->isSafeDataUriImage($image_data_uri)) {
        $form['result']['image_preview'] = [
          '#type' => 'html_tag',
          '#tag' => 'img',
          '#attributes' => [
            'src' => $image_data_uri,
            'alt' => $this->t('Gemini generated preview image'),
            'style' => 'max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px; margin-top: 8px;',
          ],
        ];
      }

      if (!empty($result['output']['image_url']) && is_string($result['output']['image_url'])) {
        $form['result']['image_url'] = [
          '#markup' => '<p><strong>' . $this->t('Image URL') . ':</strong> ' . Html::escape($result['output']['image_url']) . '</p>',
        ];
      }

      if (!empty($result['storage']) && is_array($result['storage'])) {
        $storage = $result['storage'];
        $form['result']['storage_status'] = [
          '#markup' => '<p><strong>' . $this->t('Storage persisted') . ':</strong> ' . (!empty($storage['stored']) ? 'yes' : 'no') . '</p>',
        ];
        if (!empty($storage['image_uuid'])) {
          $form['result']['storage_uuid'] = [
            '#markup' => '<p><strong>' . $this->t('Stored image UUID') . ':</strong> ' . Html::escape((string) $storage['image_uuid']) . '</p>',
          ];
        }
        if (!empty($storage['url'])) {
          $form['result']['storage_url'] = [
            '#markup' => '<p><strong>' . $this->t('Resolved client URL') . ':</strong> ' . Html::escape((string) $storage['url']) . '</p>',
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
    $prompt = trim((string) $form_state->getValue('prompt'));
    if (mb_strlen($prompt) < 12) {
      $form_state->setErrorByName('prompt', $this->t('Prompt must be at least 12 characters for a usable generation request.'));
    }

    $table_name = trim((string) $form_state->getValue('table_name'));
    $object_id = trim((string) $form_state->getValue('object_id'));
    if (($table_name === '') !== ($object_id === '')) {
      $form_state->setErrorByName('table_name', $this->t('Table name and object ID must be provided together when linking image context.'));
    }

    if ($table_name !== '' && !preg_match('/^(dc_|dungeoncrawler_content_)[A-Za-z0-9_]+$/', $table_name)) {
      $form_state->setErrorByName('table_name', $this->t('Table name must start with dc_ or dungeoncrawler_content_.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $payload = [
      'prompt' => $form_state->getValue('prompt'),
      'style' => $form_state->getValue('style'),
      'aspect_ratio' => $form_state->getValue('aspect_ratio'),
      'negative_prompt' => $form_state->getValue('negative_prompt'),
      'campaign_context' => $form_state->getValue('campaign_context'),
      'requested_by_uid' => (int) $this->currentUser()->id(),
    ];

    $provider = (string) $form_state->getValue('provider');

    $result = $this->integrationService->generateImage($payload, $provider);

    $link_context = [
      'campaign_id' => $form_state->getValue('campaign_id'),
      'table_name' => trim((string) $form_state->getValue('table_name')),
      'object_id' => trim((string) $form_state->getValue('object_id')),
      'slot' => $form_state->getValue('slot'),
      'variant' => 'original',
      'visibility' => $form_state->getValue('visibility'),
      'scope_type' => $form_state->getValue('campaign_id') ? 'campaign' : 'template',
      'is_primary' => 1,
      'owner_uid' => (int) $this->currentUser()->id(),
    ];

    $result['storage'] = $this->generatedImageRepository->persistGeneratedImage($result, $link_context);
    $form_state->set('image_generation_result', $result);
    $form_state->setRebuild(TRUE);

    if (!empty($result['success'])) {
      $this->messenger()->addStatus($this->t('@provider request @request_id completed in @mode mode.', [
        '@provider' => strtoupper((string) ($result['provider'] ?? $provider)),
        '@request_id' => $result['request_id'],
        '@mode' => (string) $result['mode'],
      ]));

      if (!empty($result['storage']['stored'])) {
        $this->messenger()->addStatus($this->t('Generated image persisted as @image_uuid.', [
          '@image_uuid' => (string) ($result['storage']['image_uuid'] ?? ''),
        ]));
      }
      elseif (!empty($result['storage']['reason'])) {
        $this->messenger()->addWarning($this->t('Image was generated but not persisted (@reason).', [
          '@reason' => (string) $result['storage']['reason'],
        ]));
      }
    }
    else {
      $this->messenger()->addError($this->t('@provider request @request_id failed: @message', [
        '@provider' => strtoupper((string) ($result['provider'] ?? $provider)),
        '@request_id' => $result['request_id'] ?? 'unknown',
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
