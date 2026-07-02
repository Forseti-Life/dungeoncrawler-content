<?php

namespace Drupal\job_application_automation\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for AI-powered resume tailoring using AWS Bedrock Claude.
 * 
 * Consolidated from resume_tailoring module into job_application_automation
 * to provide unified resume customization functionality.
 */
class ResumeTailoringService {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new ResumeTailoringService object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('job_application_automation');
  }

  /**
   * Generates a tailored resume using AWS Bedrock Claude.
   *
   * @param string $resume_text
   *   The original resume text content.
   * @param string $job_title
   *   The job title to tailor for.
   * @param string $company
   *   The company name.
   * @param string $job_description
   *   The complete job description.
   *
   * @return string
   *   The AI-generated tailored resume text or error message.
   */
  public function generateTailoredResume($resume_text, $job_title, $company, $job_description) {
    try {
      // Use the same AWS SDK approach as ai_conversation and news_extractor modules.
      $sdk = new \Aws\Sdk([
        'region' => 'us-west-2',
        'version' => 'latest',
      ]);
      
      $bedrock = $sdk->createBedrockRuntime();

      // Build the prompt for resume tailoring.
      $prompt = $this->buildResumePrompt($resume_text, $job_title, $company, $job_description);

      $response = $bedrock->invokeModel([
        'modelId' => 'anthropic.claude-3-5-sonnet-20240620-v1:0',
        'body' => json_encode([
          'anthropic_version' => 'bedrock-2023-05-31',
          'max_tokens' => 4000,
          'messages' => [
            [
              'role' => 'user',
              'content' => $prompt
            ]
          ]
        ])
      ]);

      $result = json_decode($response['body']->getContents(), true);
      
      if (isset($result['content'][0]['text'])) {
        $ai_response = $result['content'][0]['text'];
        
        $this->logger->info('Generated tailored resume for @job at @company', [
          '@job' => $job_title,
          '@company' => $company,
        ]);
        
        return $ai_response;
      }
      
      $this->logger->error('Unexpected API response format: @response', ['@response' => print_r($result, TRUE)]);
      throw new \Exception('Unexpected API response format');
      
    } catch (\Exception $e) {
      $this->logger->error('Error generating tailored resume: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      // Return a fallback message instead of failing completely.
      return "Error generating tailored resume. Please try again later.";
    }
  }

  /**
   * Build the AI prompt for resume tailoring.
   *
   * @param string $resume_text
   *   The original resume text.
   * @param string $job_title
   *   The job title.
   * @param string $company
   *   The company name.
   * @param string $job_description
   *   The job description.
   *
   * @return string
   *   The formatted prompt for the AI service.
   */
  private function buildResumePrompt($resume_text, $job_title, $company, $job_description) {
    return "You are an expert resume writer. Please tailor the following resume for the specific job opening below. 

Focus on:
1. Highlighting relevant skills and experience that match the job requirements
2. Using keywords from the job description
3. Emphasizing accomplishments that align with the role
4. Maintaining the original resume structure and format
5. Keeping the same personal information and contact details

Job Title: {$job_title}
Company: {$company}

Job Description:
{$job_description}

Original Resume:
{$resume_text}

Please provide a tailored version of this resume that would be compelling for this specific position:";
  }
}
