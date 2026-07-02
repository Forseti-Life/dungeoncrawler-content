<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\job_hunter\Service\ResumePdfService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for resume PDF generation and download.
 */
class ResumeController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The resume PDF service.
   *
   * @var \Drupal\job_hunter\Service\ResumePdfService
   */
  protected ResumePdfService $pdfService;

  /**
   * Constructs a ResumeController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\job_hunter\Service\ResumePdfService $pdf_service
   *   The resume PDF service.
   */
  public function __construct(Connection $database, ResumePdfService $pdf_service) {
    $this->database = $database;
    $this->pdfService = $pdf_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('job_hunter.resume_pdf_service')
    );
  }

  /**
   * Generate and save a tailored resume PDF for a specific job.
   *
   * @param int $job_id
   *   The job ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with PDF info.
   */
  public function generateTailoredPdf(int $job_id): \Symfony\Component\HttpFoundation\JsonResponse {
    $userId = $this->currentUser()->id();

    // Get tailored resume for this job.
    $tailoredRecord = $this->database->select('job_hunter_tailored_resumes', 'tr')
      ->fields('tr', ['id', 'tailored_resume_json'])
      ->condition('job_id', $job_id)
      ->condition('uid', $userId)
      ->execute()
      ->fetchAssoc();

    if (!$tailoredRecord || empty($tailoredRecord['tailored_resume_json'])) {
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'success' => FALSE,
        'message' => 'No tailored resume found. Please generate a tailored resume first.',
      ], 400);
    }

    $content = json_decode($tailoredRecord['tailored_resume_json'], TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid tailored resume data.',
      ], 400);
    }

    // Get job info for filename.
    $job = $this->database->select('job_hunter_job_requirements', 'j')
      ->fields('j', ['job_title', 'extracted_json'])
      ->condition('id', $job_id)
      ->execute()
      ->fetchAssoc();

    $jobTitle = '';
    $companyName = '';
    if ($job) {
      $extractedData = json_decode($job['extracted_json'] ?? '', TRUE);
      $jobTitle = $extractedData['position']['title'] ?? $job['job_title'] ?? 'Job';
      $companyName = $extractedData['company']['name'] ?? '';
    }

    // Generate filename with timestamp.
    $name = $content['contact_info']['full_name'] ?? 'Resume';
    $timestamp = date('Ymd_His');
    $filename = $this->sanitizeFilename($name);
    if ($companyName) {
      $filename .= '_' . $this->sanitizeFilename($companyName);
    }
    if ($jobTitle) {
      $filename .= '_' . $this->sanitizeFilename($jobTitle);
    }
    $filename .= '_' . $timestamp . '.pdf';

    // Generate and save PDF.
    $pdfContent = $this->pdfService->generatePdf($content);
    if ($pdfContent === NULL) {
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'success' => FALSE,
        'message' => 'Failed to generate PDF.',
      ], 500);
    }

    // Save to private files directory (tailored resumes).
    $directory = 'private://job_hunter/resumes/' . $userId . '/tailoredresumes';
    /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */
    $fileSystem = \Drupal::service('file_system');
    $fileSystem->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);

    $filepath = $directory . '/' . $filename;
    $saved = $fileSystem->saveData($pdfContent, $filepath, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);

    if (!$saved) {
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'success' => FALSE,
        'message' => 'Failed to save PDF file.',
      ], 500);
    }

    // Update the database record.
    $this->database->update('job_hunter_tailored_resumes')
      ->fields([
        'pdf_path' => $filepath,
        'pdf_generated' => \Drupal::time()->getRequestTime(),
      ])
      ->condition('id', $tailoredRecord['id'])
      ->execute();

    // Insert into PDF history table.
    $this->database->insert('job_hunter_pdf_history')
      ->fields([
        'uid' => $userId,
        'job_id' => $job_id,
        'filename' => $filename,
        'filepath' => $filepath,
        'filesize' => strlen($pdfContent),
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    return new \Symfony\Component\HttpFoundation\JsonResponse([
      'success' => TRUE,
      'message' => 'PDF generated successfully.',
      'filename' => $filename,
      'generated' => date('Y-m-d H:i:s'),
    ]);
  }

  /**
   * Download a specific PDF by its history ID.
   *
   * @param int $pdf_id
   *   The PDF history ID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The PDF response.
   */
  public function downloadPdfById(int $pdf_id): Response {
    $userId = (int) $this->currentUser()->id();

    // Get PDF record.
    $pdfRecord = $this->database->select('job_hunter_pdf_history', 'ph')
      ->fields('ph', ['filepath', 'filename', 'uid'])
      ->condition('id', $pdf_id)
      ->execute()
      ->fetchAssoc();

    if (!$pdfRecord) {
      throw new NotFoundHttpException('PDF not found.');
    }

    // Security check - make sure user owns this PDF.
    if ((int) $pdfRecord['uid'] !== $userId) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Access denied.');
    }

    // Read the file.
    /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */
    $fileSystem = \Drupal::service('file_system');
    $realPath = $fileSystem->realpath($pdfRecord['filepath']);

    if (!$realPath || !file_exists($realPath)) {
      throw new NotFoundHttpException('PDF file not found on disk.');
    }

    $pdfContent = file_get_contents($realPath);

    $response = new Response($pdfContent);
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $pdfRecord['filename'] . '"');
    $response->headers->set('Content-Length', strlen($pdfContent));
    $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
    $response->headers->set('Pragma', 'public');

    return $response;
  }

  /**
   * Delete a specific PDF by its history ID.
   *
   * @param int $pdf_id
   *   The PDF history ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function deletePdf(int $pdf_id): \Symfony\Component\HttpFoundation\JsonResponse {
    $userId = (int) $this->currentUser()->id();

    // Get PDF record.
    $pdfRecord = $this->database->select('job_hunter_pdf_history', 'ph')
      ->fields('ph', ['id', 'filepath', 'filename', 'uid', 'job_id'])
      ->condition('id', $pdf_id)
      ->execute()
      ->fetchAssoc();

    if (!$pdfRecord) {
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'success' => FALSE,
        'message' => 'PDF not found.',
      ], 404);
    }

    // Security check - make sure user owns this PDF.
    if ((int) $pdfRecord['uid'] !== $userId) {
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'success' => FALSE,
        'message' => 'Access denied.',
      ], 403);
    }

    // Delete the file from disk.
    /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */
    $fileSystem = \Drupal::service('file_system');
    $realPath = $fileSystem->realpath($pdfRecord['filepath']);

    if ($realPath && file_exists($realPath)) {
      unlink($realPath);
    }

    // Delete from database.
    $this->database->delete('job_hunter_pdf_history')
      ->condition('id', $pdf_id)
      ->execute();

    // Check if this was the latest PDF and update tailored_resumes table.
    $latestPdf = $this->database->select('job_hunter_pdf_history', 'ph')
      ->fields('ph', ['filepath', 'created'])
      ->condition('uid', $userId)
      ->condition('job_id', $pdfRecord['job_id'])
      ->orderBy('created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if ($latestPdf) {
      $this->database->update('job_hunter_tailored_resumes')
        ->fields([
          'pdf_path' => $latestPdf['filepath'],
          'pdf_generated' => $latestPdf['created'],
        ])
        ->condition('uid', $userId)
        ->condition('job_id', $pdfRecord['job_id'])
        ->execute();
    } else {
      // No more PDFs, clear the path.
      $this->database->update('job_hunter_tailored_resumes')
        ->fields([
          'pdf_path' => NULL,
          'pdf_generated' => 0,
        ])
        ->condition('uid', $userId)
        ->condition('job_id', $pdfRecord['job_id'])
        ->execute();
    }

    return new \Symfony\Component\HttpFoundation\JsonResponse([
      'success' => TRUE,
      'message' => 'PDF deleted successfully.',
      'filename' => $pdfRecord['filename'],
    ]);
  }

  /**
   * Download a tailored resume as PDF for a specific job.
   *
   * @param int $job_id
   *   The job ID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The PDF response.
   */
  public function downloadTailoredPdf(int $job_id): Response {
    $userId = $this->currentUser()->id();

    // First try to get tailored resume for this job.
    $tailoredResume = $this->database->select('job_hunter_tailored_resumes', 'tr')
      ->fields('tr', ['tailored_resume_json'])
      ->condition('job_id', $job_id)
      ->condition('uid', $userId)
      ->execute()
      ->fetchField();

    if ($tailoredResume) {
      $content = json_decode($tailoredResume, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new NotFoundHttpException('Invalid tailored resume data.');
      }
    }
    else {
      // Fall back to base resume.
      $content = $this->getBaseResumeContent($userId);
      if (!$content) {
        throw new NotFoundHttpException('No resume found. Please create your job seeker profile first.');
      }
    }

    // Get job info for filename.
    $job = $this->database->select('job_hunter_job_requirements', 'j')
      ->fields('j', ['job_title', 'extracted_json'])
      ->condition('id', $job_id)
      ->execute()
      ->fetchAssoc();

    $jobTitle = '';
    $companyName = '';
    if ($job) {
      $extractedData = json_decode($job['extracted_json'] ?? '', TRUE);
      $jobTitle = $extractedData['position']['title'] ?? $job['job_title'] ?? 'Job';
      $companyName = $extractedData['company']['name'] ?? '';
    }

    // Generate filename.
    $name = $content['contact_info']['full_name'] ?? 'Resume';
    $filename = $this->sanitizeFilename($name);
    if ($companyName) {
      $filename .= '_' . $this->sanitizeFilename($companyName);
    }
    if ($jobTitle) {
      $filename .= '_' . $this->sanitizeFilename($jobTitle);
    }
    $filename .= '.pdf';

    return $this->generatePdfResponse($content, $filename);
  }

  /**
   * Download the base resume as PDF.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The PDF response.
   */
  public function downloadBasePdf(): Response {
    $userId = $this->currentUser()->id();
    $content = $this->getBaseResumeContent($userId);

    if (!$content) {
      throw new NotFoundHttpException('No resume found. Please create your job seeker profile first.');
    }

    // Generate filename.
    $name = $content['contact_info']['full_name'] ?? 'Resume';
    $filename = $this->sanitizeFilename($name) . '_Resume.pdf';

    return $this->generatePdfResponse($content, $filename);
  }

  /**
   * Get base resume content for user.
   *
   * @param int $user_id
   *   The user ID.
   *
   * @return array|null
   *   The resume content or NULL.
   */
  protected function getBaseResumeContent(int $user_id): ?array {
    $result = $this->database->select('jobhunter_job_seeker', 'js')
      ->fields('js', ['consolidated_profile_json'])
      ->condition('uid', $user_id)
      ->execute()
      ->fetchField();

    if (!$result) {
      return NULL;
    }

    $content = json_decode($result, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return NULL;
    }

    return $content;
  }

  /**
   * Generate PDF response.
   *
   * @param array $content
   *   The resume content.
   * @param string $filename
   *   The filename.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The PDF response.
   */
  protected function generatePdfResponse(array $content, string $filename): Response {
    $pdfContent = $this->pdfService->generatePdf($content);

    if ($pdfContent === NULL) {
      throw new NotFoundHttpException('Failed to generate PDF.');
    }

    $response = new Response($pdfContent);
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    $response->headers->set('Content-Length', strlen($pdfContent));
    $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
    $response->headers->set('Pragma', 'public');

    return $response;
  }

  /**
   * Sanitize a string for use in a filename.
   *
   * @param string $string
   *   The string to sanitize.
   *
   * @return string
   *   The sanitized string.
   */
  protected function sanitizeFilename(string $string): string {
    // Remove special characters.
    $string = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $string);
    // Replace spaces with underscores.
    $string = preg_replace('/\s+/', '_', $string);
    // Limit length.
    $string = substr($string, 0, 50);
    // Remove trailing underscores.
    $string = rtrim($string, '_');

    return $string;
  }

}
