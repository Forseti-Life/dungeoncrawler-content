<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Upload form for replacing a character portrait.
 */
class CharacterPortraitUploadForm extends FormBase {

  public function __construct(
    protected CharacterManager $characterManager,
    protected GeneratedImageRepository $imageRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.generated_image_repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_character_portrait_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $character_id = 0, int $campaign_id = 0): array {
    $record = $this->characterManager->loadCharacter($character_id);
    if (!$record) {
      throw new NotFoundHttpException();
    }

    if (!$this->characterManager->isOwner($record) && !$this->currentUser()->hasPermission('administer site configuration')) {
      throw new AccessDeniedHttpException();
    }

    $form_state->set('character_id', $character_id);
    $form_state->set('campaign_id', $campaign_id);
    $form['#attributes']['class'][] = 'dc-sheet__portrait-upload-form';
    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['portrait_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Portrait image'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'accept' => '.png,.jpg,.jpeg,.webp,.gif',
      ],
      '#description' => $this->t('PNG, JPG, WEBP, or GIF. Max 8 MB.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['dc-btn', 'dc-btn-primary', 'dc-btn-sm']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $character_id = (int) $form_state->get('character_id');
    $campaign_id = (int) $form_state->get('campaign_id');

    $validators = [
      'file_validate_extensions' => ['png jpg jpeg webp gif'],
      'file_validate_size' => [8 * 1024 * 1024],
    ];
    $files = file_save_upload('portrait_file', $validators, 'public://generated-images/uploads/portraits', 0);
    $file = is_array($files) ? reset($files) : $files;
    if (!$file) {
      $this->messenger()->addError($this->t('Upload failed: no portrait file was received.'));
      return;
    }

    $file->setPermanent();
    $file->save();

    $storage = $this->imageRepository->persistUploadedImage($file, [
      'owner_uid' => (int) $this->currentUser()->id(),
      'scope_type' => 'campaign',
      'campaign_id' => $campaign_id > 0 ? $campaign_id : NULL,
      'table_name' => 'dc_campaign_characters',
      'object_id' => (string) $character_id,
      'slot' => 'portrait',
      'variant' => 'original',
      'visibility' => 'owner',
      'is_primary' => 1,
    ]);

    if (!empty($storage['stored'])) {
      $this->imageRepository->archiveObjectImages(
        'dc_campaign_characters',
        (string) $character_id,
        $campaign_id > 0 ? $campaign_id : NULL,
        'portrait',
        'original',
        !empty($storage['image_id']) ? (int) $storage['image_id'] : NULL
      );
      $this->messenger()->addStatus($this->t('Portrait uploaded successfully.'));
    }
    else {
      $reason = (string) ($storage['reason'] ?? 'upload_failed');
      $this->messenger()->addError($this->t('Portrait upload failed (@reason).', ['@reason' => $reason]));
    }

    $form_state->setRedirect('dungeoncrawler_content.character_view', ['character_id' => $character_id], $campaign_id > 0 ? ['query' => ['campaign_id' => $campaign_id]] : []);
  }

}
