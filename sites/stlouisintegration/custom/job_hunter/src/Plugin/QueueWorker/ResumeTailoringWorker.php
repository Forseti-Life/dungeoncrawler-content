<?php

namespace Drupal\job_hunter\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resume Tailoring GenAI queue worker.
 *
 * Processes resume tailoring via AWS Bedrock in the background.
 *
 * @QueueWorker(
 *   id = "job_hunter_resume_tailoring",
 *   title = @Translation("Resume Tailoring GenAI"),
 *   cron = {"time" = 180}
 * )
 */
class ResumeTailoringWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $uid = $data['uid'];
    $job_id = $data['job_id'];
    $profile_json = $data['profile_json'];
    $job_data = $data['job_data'];

    $logger = \Drupal::logger('job_hunter');
    $logger->info('🔄 Queue: Starting GenAI resume tailoring for user @uid, job @job_id', [
      '@uid' => $uid,
      '@job_id' => $job_id,
    ]);

    $connection = \Drupal::database();

    try {
      // Update status to processing
      $this->updateTailoringStatus($connection, $uid, $job_id, 'processing');

      // Parse job data
      $extracted = !empty($job_data['extracted_json']) ? json_decode($job_data['extracted_json'], TRUE) : [];
      $skills = !empty($job_data['skills_required_json']) ? json_decode($job_data['skills_required_json'], TRUE) : [];
      $keywords = !empty($job_data['keywords_json']) ? json_decode($job_data['keywords_json'], TRUE) : [];

      // Build the GenAI request payload
      $genai_payload = [
        'action' => 'generate_tailored_resume',
        'job_requisition' => [
          'id' => (int) $job_id,
          'extracted_json' => $extracted,
          'skills_required_json' => $skills,
          'keywords_json' => $keywords,
          'raw_posting_text' => $job_data['raw_posting_text'] ?? '',
        ],
        'user_resume' => [
          'consolidated_profile_json' => $profile_json,
        ],
      ];

      // Call AWS Bedrock
      $tailored_result = $this->callGenAiTailoringService($genai_payload);

      if (!$tailored_result || !isset($tailored_result['tailored_resume_json'])) {
        throw new \Exception('Failed to generate tailored resume from AI service');
      }

      // Save the tailored resume
      $now = time();
      $existing = $connection->select('job_hunter_tailored_resumes', 'tr')
        ->fields('tr', ['id'])
        ->condition('uid', $uid)
        ->condition('job_id', $job_id)
        ->execute()
        ->fetchField();

      if ($existing) {
        $connection->update('job_hunter_tailored_resumes')
          ->fields([
            'tailored_resume_json' => json_encode($tailored_result['tailored_resume_json']),
            'tailoring_status' => 'completed',
            'updated' => $now,
          ])
          ->condition('id', $existing)
          ->execute();
      }
      else {
        $connection->insert('job_hunter_tailored_resumes')
          ->fields([
            'uid' => $uid,
            'job_id' => $job_id,
            'tailored_resume_json' => json_encode($tailored_result['tailored_resume_json']),
            'tailoring_status' => 'completed',
            'created' => $now,
            'updated' => $now,
          ])
          ->execute();
      }

      $job_title = $extracted['position']['title'] ?? $extracted['job_title'] ?? 'Unknown Position';
      $logger->info('✅ Queue: Successfully tailored resume for user @uid, job "@title"', [
        '@uid' => $uid,
        '@title' => $job_title,
      ]);

    }
    catch (\Exception $e) {
      $logger->error('❌ Queue: Failed to tailor resume for user @uid, job @job_id: @error', [
        '@uid' => $uid,
        '@job_id' => $job_id,
        '@error' => $e->getMessage(),
      ]);

      // Update status to failed
      $this->updateTailoringStatus($connection, $uid, $job_id, 'failed');

      throw $e;
    }
  }

  /**
   * Update or create tailoring status record.
   */
  private function updateTailoringStatus($connection, $uid, $job_id, $status) {
    $now = time();
    $existing = $connection->select('job_hunter_tailored_resumes', 'tr')
      ->fields('tr', ['id'])
      ->condition('uid', $uid)
      ->condition('job_id', $job_id)
      ->execute()
      ->fetchField();

    if ($existing) {
      $connection->update('job_hunter_tailored_resumes')
        ->fields([
          'tailoring_status' => $status,
          'updated' => $now,
        ])
        ->condition('id', $existing)
        ->execute();
    }
    else {
      $connection->insert('job_hunter_tailored_resumes')
        ->fields([
          'uid' => $uid,
          'job_id' => $job_id,
          'tailoring_status' => $status,
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }
  }

  /**
   * Call AWS Bedrock for resume tailoring.
   */
  private function callGenAiTailoringService(array $payload) {
    try {
      $sdk = new \Aws\Sdk([
        'region' => 'us-west-2',
        'version' => 'latest',
      ]);

      $bedrock = $sdk->createBedrockRuntime();
      $prompt = $this->buildTailoredResumePrompt($payload);

      \Drupal::logger('job_hunter')->info('Queue: Calling AWS Bedrock Claude for resume tailoring');

      $response = $bedrock->invokeModel([
        'modelId' => 'anthropic.claude-3-5-sonnet-20240620-v1:0',
        'body' => json_encode([
          'anthropic_version' => 'bedrock-2023-05-31',
          'max_tokens' => 20000,
          'messages' => [
            [
              'role' => 'user',
              'content' => $prompt,
            ],
          ],
        ]),
      ]);

      $result = json_decode($response['body']->getContents(), TRUE);

      if (isset($result['content'][0]['text'])) {
        $ai_response = $result['content'][0]['text'];
        
        // Debug: Log first 500 chars of response
        \Drupal::logger('job_hunter')->info('Queue: AI response preview: @preview', [
          '@preview' => substr($ai_response, 0, 500),
        ]);
        
        $json_str = $this->extractJsonFromResponse($ai_response);

        if ($json_str) {
          $tailored_resume = json_decode($json_str, TRUE);

          if (json_last_error() === JSON_ERROR_NONE && $tailored_resume) {
            \Drupal::logger('job_hunter')->info('Queue: Successfully generated tailored resume JSON');

            return [
              'tailored_resume_json' => $tailored_resume,
              'tailoring_guidance' => $tailored_resume['tailoring_metadata']['guidance'] ?? NULL,
            ];
          }
          
          // Log JSON parse error
          \Drupal::logger('job_hunter')->error('Queue: JSON parse error: @error', [
            '@error' => json_last_error_msg(),
          ]);
        }
        else {
          \Drupal::logger('job_hunter')->error('Queue: extractJsonFromResponse returned null. Response length: @len', [
            '@len' => strlen($ai_response),
          ]);
        }

        \Drupal::logger('job_hunter')->error('Queue: Failed to parse tailored resume JSON from AI response');
        return NULL;
      }

      \Drupal::logger('job_hunter')->error('Queue: Unexpected API response format from Bedrock');
      return NULL;

    }
    catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Queue: GenAI API call failed: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Build the prompt for generating a tailored resume JSON.
   */
  private function buildTailoredResumePrompt(array $payload) {
    $job = $payload['job_requisition'] ?? [];
    $resume = $payload['user_resume']['consolidated_profile_json'] ?? [];

    $job_title = $job['extracted_json']['position']['title'] ?? $job['extracted_json']['job_title'] ?? 'the position';
    $company_name = $job['extracted_json']['company']['name'] ?? $job['extracted_json']['company_name'] ?? 'the company';
    $job_skills = json_encode($job['skills_required_json'] ?? [], JSON_PRETTY_PRINT);
    $job_keywords = json_encode($job['keywords_json'] ?? [], JSON_PRETTY_PRINT);
    $job_description = $job['raw_posting_text'] ?? '';
    $resume_json = json_encode($resume, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $job_id = $job['id'] ?? 0;

    return <<<PROMPT
You are an expert resume tailoring AI. Your task is to create a tailored version of the candidate's resume optimized for a specific job posting.

## Job Information
**Position:** {$job_title}
**Company:** {$company_name}

**Required Skills:**
{$job_skills}

**Key Keywords:**
{$job_keywords}

**Job Description:**
{$job_description}

## Candidate's Current Resume (JSON)
{$resume_json}

## Your Task

Generate a TAILORED version of the candidate's resume as a JSON object. The output must:

1. **Match the RESUME_JSON_SCHEMA.md structure** exactly with these sections:
   - `schema_version`: "1.0"
   - `tailoring_metadata`: Object with job_id, job_title, company, tailored_at timestamp, and guidance array
   - `contact_info`: Keep unchanged from original
   - `executive_profile`: Rewrite summary to emphasize relevant experience for this role
   - `strategic_differentiators`: Prioritize/reword to match job requirements
   - `professional_experience`: Reorder achievements, emphasize relevant technologies/metrics
   - `consulting_practice`: Include if relevant to role
   - `early_career`: Include if relevant
   - `education`: Keep unchanged
   - `technical_expertise`: Reorder categories to prioritize job-relevant skills
   - `leadership_philosophy`: Tailor if relevant
   - `demonstration_projects`: Include if relevant

2. **Tailoring Guidelines:**
   - Incorporate keywords from the job posting naturally
   - Prioritize achievements that match required skills
   - Quantified metrics should be preserved and highlighted when relevant
   - Technologies mentioned in job posting should be emphasized
   - Maintain professional tone and factual accuracy
   - DO NOT fabricate information - only reorganize and emphasize existing content

3. **Add tailoring_metadata section:**
   ```json
   "tailoring_metadata": {
     "job_id": {$job_id},
     "job_title": "{$job_title}",
     "company": "{$company_name}",
     "tailored_at": "ISO-8601 timestamp",
     "match_score": 0-100,
     "guidance": [
       "Key suggestion 1",
       "Key suggestion 2"
     ],
     "emphasized_skills": ["skill1", "skill2"],
     "emphasized_achievements": ["achievement summary 1"]
   }
   ```

## Output Format

Return ONLY valid JSON. No markdown code blocks, no explanatory text. The JSON should be parseable directly.
Start your response with { and end with }.

PROMPT;
  }

  /**
   * Extract JSON from AI response that may contain markdown or text.
   */
  private function extractJsonFromResponse($response) {
    // Try direct parse first
    $decoded = json_decode($response, TRUE);
    if (json_last_error() === JSON_ERROR_NONE) {
      return $response;
    }

    // Try extracting from markdown code block
    if (preg_match('/```(?:json)?\s*(\{[\s\S]*\})\s*```/', $response, $matches)) {
      return trim($matches[1]);
    }

    // Try finding JSON object in response
    if (preg_match('/(\{[\s\S]*\})/', $response, $matches)) {
      $decoded = json_decode($matches[1], TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $matches[1];
      }
    }

    return NULL;
  }

}
