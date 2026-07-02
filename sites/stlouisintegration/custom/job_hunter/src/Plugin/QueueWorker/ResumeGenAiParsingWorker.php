<?php

namespace Drupal\job_hunter\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resume GenAI parsing queue worker.
 *
 * Processes resume parsing via AWS Bedrock in the background.
 *
 * @QueueWorker(
 *   id = "job_hunter_genai_parsing",
 *   title = @Translation("Resume GenAI Parsing"),
 *   cron = {"time" = 120}
 * )
 */
class ResumeGenAiParsingWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
    $resume_id = $data['resume_id'];
    $file_id = $data['file_id'];
    $extracted_text = $data['extracted_text'];
    $filename = $data['filename'];

    $logger = \Drupal::logger('job_hunter');
    $logger->info('🔄 Queue: Starting GenAI parsing for @filename (user @uid)', [
      '@filename' => $filename,
      '@uid' => $uid,
    ]);

    try {
      // Update status to processing
      $connection = \Drupal::database();
      $connection->update('jobhunter_resume_parsed_data')
        ->fields(['status' => 'processing', 'changed' => \Drupal::time()->getRequestTime()])
        ->condition('resume_file_id', $file_id)
        ->condition('uid', $uid)
        ->execute();

      // Call GenAI parsing
      $parsed_data = $this->parseResumeProdMode($extracted_text, $filename);

      // Store successful result
      $connection->update('jobhunter_resume_parsed_data')
        ->fields([
          'parsed_data' => json_encode($parsed_data),
          'status' => 'complete',
          'error_message' => NULL,
          'changed' => \Drupal::time()->getRequestTime(),
        ])
        ->condition('resume_file_id', $file_id)
        ->condition('uid', $uid)
        ->execute();

      $logger->info('✅ Queue: GenAI parsing complete for @filename', ['@filename' => $filename]);

      // Check if all queued items are complete before consolidating
      $pending_count = $connection->select('jobhunter_resume_parsed_data', 'rpd')
        ->condition('uid', $uid)
        ->condition('status', ['queued', 'processing'], 'IN')
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($pending_count == 0) {
        // All files complete - consolidate all parsed data
        $logger->info('🔄 Queue: All files complete for user @uid, running consolidation', ['@uid' => $uid]);
        $this->consolidateAllParsedData($uid);
      } else {
        $logger->info('⏳ Queue: @count files still pending for user @uid, deferring consolidation', [
          '@count' => $pending_count,
          '@uid' => $uid,
        ]);
      }

    } catch (\Exception $e) {
      $logger->error('❌ Queue: GenAI parsing failed for @filename: @error', [
        '@filename' => $filename,
        '@error' => $e->getMessage(),
      ]);

      // Store error status
      $connection = \Drupal::database();
      $connection->update('jobhunter_resume_parsed_data')
        ->fields([
          'status' => 'error',
          'error_message' => $e->getMessage(),
          'changed' => \Drupal::time()->getRequestTime(),
        ])
        ->condition('resume_file_id', $file_id)
        ->condition('uid', $uid)
        ->execute();

      // Re-throw to mark queue item as failed
      throw $e;
    }
  }

  /**
   * Parse resume using GenAI (chunked approach).
   */
  private function parseResumeProdMode($extracted_text, $filename) {
    $logger = \Drupal::logger('job_hunter');

    // Get AWS configuration
    $config = $this->configFactory->get('ai_conversation.settings');
    $aws_access_key = $config->get('aws_access_key_id') ?: getenv('AWS_ACCESS_KEY_ID');
    $aws_secret_key = $config->get('aws_secret_access_key') ?: getenv('AWS_SECRET_ACCESS_KEY');
    $aws_region = $config->get('aws_region') ?: getenv('AWS_DEFAULT_REGION') ?: 'us-east-1';

    $sdk_config = [
      'region' => $aws_region,
      'version' => 'latest',
    ];

    if (!empty($aws_access_key) && !empty($aws_secret_key)) {
      $sdk_config['credentials'] = [
        'key' => $aws_access_key,
        'secret' => $aws_secret_key,
      ];
    }

    $sdk = new \Aws\Sdk($sdk_config);
    $bedrock = $sdk->createBedrockRuntime();
    $model = $config->get('aws_model') ?: 'anthropic.claude-3-5-sonnet-20240620-v1:0';

    // CALL 1: Parse core profile
    $logger->info('📄 Queue Call 1/2: Parsing core profile sections');
    $core_prompt = $this->buildCoreProfilePrompt($extracted_text, $filename);
    $core_data = $this->callBedrockAndParse($bedrock, $model, $core_prompt, 'core');

    if (!$core_data) {
      throw new \Exception('Failed to parse core profile sections');
    }

    // CALL 2: Parse professional experience
    $logger->info('💼 Queue Call 2/2: Parsing professional experience');
    $experience_prompt = $this->buildProfessionalExperiencePrompt($extracted_text, $filename);
    $experience_data = $this->callBedrockAndParse($bedrock, $model, $experience_prompt, 'experience');

    if (!$experience_data) {
      throw new \Exception('Failed to parse professional experience');
    }

    // Merge results
    $merged_data = $core_data;
    $merged_data['professional_experience'] = $experience_data['professional_experience'] ?? [];

    return $merged_data;
  }

  /**
   * Call Bedrock and parse JSON response.
   */
  private function callBedrockAndParse($bedrock, $model, $prompt, $chunk_name) {
    $logger = \Drupal::logger('job_hunter');

    $result = $bedrock->invokeModel([
      'modelId' => $model,
      'contentType' => 'application/json',
      'body' => json_encode([
        'anthropic_version' => 'bedrock-2023-05-31',
        'max_tokens' => 8000,
        'messages' => [
          ['role' => 'user', 'content' => $prompt],
        ],
      ]),
    ]);

    $response_body = json_decode($result->get('body')->getContents(), TRUE);
    $response_text = $response_body['content'][0]['text'] ?? '';

    $logger->info('🔍 Queue @chunk response: @len chars', [
      '@chunk' => $chunk_name,
      '@len' => strlen($response_text),
    ]);

    $json_text = $this->extractJsonFromResponse($response_text);

    if ($json_text) {
      $parsed_data = json_decode($json_text, TRUE);
      if (json_last_error() === JSON_ERROR_NONE && is_array($parsed_data)) {
        return $parsed_data;
      }
    }

    return NULL;
  }

  /**
   * Extract JSON from response.
   */
  private function extractJsonFromResponse($response_text) {
    // Try markdown code fence
    if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/s', $response_text, $matches)) {
      return trim($matches[1]);
    }

    // Find balanced JSON using brace counting
    $start_pos = strpos($response_text, '{');
    if ($start_pos === FALSE) {
      return NULL;
    }

    $depth = 0;
    $in_string = FALSE;
    $escape_next = FALSE;
    $len = strlen($response_text);

    for ($i = $start_pos; $i < $len; $i++) {
      $char = $response_text[$i];

      if ($escape_next) {
        $escape_next = FALSE;
        continue;
      }
      if ($char === '\\' && $in_string) {
        $escape_next = TRUE;
        continue;
      }
      if ($char === '"') {
        $in_string = !$in_string;
        continue;
      }
      if ($in_string) {
        continue;
      }
      if ($char === '{') {
        $depth++;
      }
      elseif ($char === '}') {
        $depth--;
        if ($depth === 0) {
          return substr($response_text, $start_pos, $i - $start_pos + 1);
        }
      }
    }

    return NULL;
  }

  /**
   * Consolidate ALL parsed resume data for a user.
   *
   * This is called only when all queued items are complete,
   * ensuring we have all data before building the consolidated profile.
   */
  private function consolidateAllParsedData($uid) {
    try {
      $connection = \Drupal::database();
      $logger = \Drupal::logger('job_hunter');

      // Get all completed parsed data for this user
      $results = $connection->select('jobhunter_resume_parsed_data', 'rpd')
        ->fields('rpd', ['parsed_data', 'resume_file_id'])
        ->condition('uid', $uid)
        ->condition('status', 'complete')
        ->orderBy('created', 'ASC')  // Oldest first, newest overwrites
        ->execute()
        ->fetchAll();

      if (empty($results)) {
        $logger->warning('Queue: No completed parsed data found for user @uid', ['@uid' => $uid]);
        return;
      }

      $consolidated = [];
      $professional_experiences = [];
      $source_files = [];

      foreach ($results as $row) {
        $parsed_data = json_decode($row->parsed_data, TRUE);
        if (!$parsed_data || !is_array($parsed_data)) {
          continue;
        }

        // Track source filenames for status display
        if (!empty($parsed_data['extraction_metadata']['source_filename'])) {
          $source_files[] = $parsed_data['extraction_metadata']['source_filename'];
        } else {
          // Fallback: get filename from file entity
          $file = \Drupal\file\Entity\File::load($row->resume_file_id);
          if ($file) {
            $source_files[] = $file->getFilename();
          }
        }

        // Collect professional experiences from all resumes
        if (!empty($parsed_data['professional_experience'])) {
          $professional_experiences = array_merge(
            $professional_experiences,
            $parsed_data['professional_experience']
          );
          unset($parsed_data['professional_experience']);
        }

        // Merge other sections (newer overwrites older for same keys)
        $consolidated = array_merge($consolidated, $parsed_data);
      }

      // De-duplicate professional experiences by company+title+start_date
      $unique_experiences = [];
      $seen_keys = [];
      foreach ($professional_experiences as $exp) {
        $key = ($exp['company'] ?? '') . '|' . ($exp['title'] ?? '') . '|' . ($exp['start_date'] ?? '');
        if (!isset($seen_keys[$key])) {
          $seen_keys[$key] = TRUE;
          $unique_experiences[] = $exp;
        }
      }

      // Sort by start_date descending (most recent first)
      usort($unique_experiences, function($a, $b) {
        return ($b['start_date'] ?? '') <=> ($a['start_date'] ?? '');
      });

      $consolidated['professional_experience'] = $unique_experiences;
      $consolidated['last_updated'] = date('c');
      $consolidated['resume_count'] = count($results);
      
      // Build extraction_metadata with source_files for status tracking
      $consolidated['extraction_metadata'] = [
        'source_files' => array_unique($source_files),
        'consolidated_at' => date('c'),
        'resume_count' => count($results),
      ];

      // Save consolidated profile
      $connection->update('jobhunter_job_seeker')
        ->fields(['consolidated_profile_json' => json_encode($consolidated)])
        ->condition('uid', $uid)
        ->execute();

      $logger->info('✅ Queue: Consolidated @count resumes for user @uid (@exp experiences)', [
        '@count' => count($results),
        '@uid' => $uid,
        '@exp' => count($unique_experiences),
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Queue: Failed to consolidate all parsed data: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * Build core profile prompt.
   */
  private function buildCoreProfilePrompt($extracted_text, $filename) {
    $timestamp = date('c');
    $char_count = strlen($extracted_text);

    return <<<PROMPT
You are a professional resume parser. Extract the CORE PROFILE sections from this resume into JSON.

IMPORTANT: Do NOT include professional_experience in this response. That will be extracted separately.

REQUIREMENTS:
1. Preserve ALL information - do not summarize
2. Use YYYY-MM format for dates
3. Use null for missing optional fields
4. Return ONLY valid JSON with no markdown or explanation

JSON SCHEMA:
{
  "schema_version": "1.0",
  "extraction_metadata": {
    "source_filename": "{$filename}",
    "extracted_at": "{$timestamp}",
    "character_count": {$char_count}
  },
  "contact_info": {
    "full_name": "First Last",
    "credentials": ["MBA", "PMP"],
    "headline": "Professional title/tagline",
    "location": {"city": "City", "state": "ST"},
    "phone": "(xxx) xxx-xxxx",
    "email": "email@example.com",
    "websites": [{"type": "linkedin|github|personal", "url": "https://..."}]
  },
  "executive_profile": {
    "summary": "Full executive summary text",
    "industry_focus": ["industry1", "industry2"],
    "key_metrics": [{"metric": "name", "value": "XXM+", "context": "explanation"}]
  },
  "strategic_differentiators": [
    {"title": "Title", "description": "Description"}
  ],
  "consulting_practice": {
    "company": "Company Name",
    "title": "Title",
    "start_date": "YYYY-MM",
    "end_date": null,
    "description": "Description",
    "notable_engagements": [{"client": "Client", "role": "Role", "description": "Desc"}]
  },
  "early_career": {
    "period": "YYYY-YYYY",
    "summary": "Summary text",
    "positions": [{"company": "Company", "duration": "X years", "focus": "Role desc"}]
  },
  "education": [
    {"institution": "University", "degree": "Degree Name", "abbreviation": "MBA", "field": "Field", "end_date": "YYYY-MM"}
  ],
  "technical_expertise": {
    "categories": [{"name": "Category", "skills": ["skill1", "skill2"]}]
  },
  "leadership_philosophy": {
    "statement": "Philosophy text",
    "key_themes": ["theme1", "theme2"]
  },
  "demonstration_projects": [
    {"name": "Project", "url": "https://...", "technologies": ["tech1"], "description": "Desc"}
  ]
}

RESUME TEXT:
---
{$extracted_text}
---

Return the JSON object with all core profile sections. Do NOT include professional_experience.
PROMPT;
  }

  /**
   * Build professional experience prompt.
   */
  private function buildProfessionalExperiencePrompt($extracted_text, $filename) {
    return <<<PROMPT
You are a professional resume parser. Extract ONLY the professional work experience from this resume.

REQUIREMENTS:
1. Preserve ALL job details and achievements - do not summarize
2. Extract metrics (dollar amounts, percentages, team sizes) into metrics arrays
3. Identify technologies mentioned in each achievement
4. Extract searchable keywords from each achievement
5. Use YYYY-MM format for dates
6. Return ONLY valid JSON with no markdown or explanation

JSON SCHEMA:
{
  "professional_experience": [
    {
      "company": "Company Name",
      "title": "Job Title",
      "employment_type": "direct|consulting",
      "via_company": null,
      "start_date": "YYYY-MM",
      "end_date": "YYYY-MM or null if current",
      "location": "City, ST",
      "company_context": "Brief company description if provided",
      "responsibility_categories": [
        {
          "category": "Category Name",
          "achievements": [
            {
              "text": "Full bullet point text",
              "metrics": ["\$3.2M revenue", "30% improvement"],
              "technologies": ["Python", "AWS"],
              "keywords": ["AI strategy", "data governance"]
            }
          ]
        }
      ]
    }
  ]
}

RESUME TEXT:
---
{$extracted_text}
---

Return the JSON object with professional_experience array containing ALL jobs and achievements.
PROMPT;
  }

}
