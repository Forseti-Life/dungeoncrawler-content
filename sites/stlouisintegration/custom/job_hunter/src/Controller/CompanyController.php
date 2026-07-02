<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for company and job requirement management.
 */
class CompanyController extends ControllerBase {

  /**
   * List all companies.
   */
  public function listCompanies() {
    $database = \Drupal::database();
    
    // Render navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    // Get all companies
    $query = $database->select('job_hunter_companies', 'c')
      ->fields('c')
      ->orderBy('name', 'ASC');
    $companies = $query->execute()->fetchAll();
    
    // Build table
    $header = [
      $this->t('Company'),
      $this->t('Industry'),
      $this->t('Location'),
      $this->t('Active'),
      $this->t('Jobs'),
      $this->t('Actions'),
    ];
    
    $rows = [];
    foreach ($companies as $company) {
      // Count jobs for this company
      $job_count = $database->select('job_hunter_job_requirements', 'j')
        ->condition('company_id', $company->id)
        ->countQuery()
        ->execute()
        ->fetchField();
      
      $rows[] = [
        $company->name,
        $company->industry ?: $this->t('N/A'),
        $company->location ?: $this->t('N/A'),
        $company->active ? $this->t('Yes') : $this->t('No'),
        $job_count,
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('job_hunter.company_edit', ['company_id' => $company->id]),
              ],
              'add_job' => [
                'title' => $this->t('Add Job'),
                'url' => Url::fromRoute('job_hunter.job_paste'),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('job_hunter.company_delete', ['company_id' => $company->id]),
                'attributes' => [
                  'onclick' => 'return confirm("Are you sure you want to delete this company and all its jobs?");',
                ],
              ],
            ],
          ],
        ],
      ];
    }
    
    $content = [
      'header' => [
        '#markup' => '<h2>' . $this->t('Companies') . '</h2>',
      ],
      'add_button' => [
        '#type' => 'link',
        '#title' => $this->t('Add Company'),
        '#url' => Url::fromRoute('job_hunter.company_add'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No companies found. Click "Add Company" to add your first target company.'),
        '#attributes' => ['class' => ['companies-table']],
      ],
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
    ];
    
    return $build;
  }

  /**
   * Delete a company.
   */
  public function deleteCompany($company_id) {
    $database = \Drupal::database();
    
    // Delete all jobs for this company first
    $database->delete('job_hunter_job_requirements')
      ->condition('company_id', $company_id)
      ->execute();
    
    // Delete the company
    $database->delete('job_hunter_companies')
      ->condition('id', $company_id)
      ->execute();
    
    $this->messenger()->addMessage($this->t('Company and all associated jobs have been deleted.'));
    
    return new RedirectResponse(Url::fromRoute('job_hunter.companies_list')->toString());
  }

  /**
   * List all job requirements.
   */
  public function listJobs() {
    $database = \Drupal::database();
    $current_user_id = \Drupal::currentUser()->id();
    
    // Render navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    // Get all jobs with company names and tailoring status
    $query = $database->select('job_hunter_job_requirements', 'j')
      ->fields('j');
    $query->leftJoin('job_hunter_companies', 'c', 'j.company_id = c.id');
    $query->addField('c', 'name', 'company_name');
    // Join tailored resumes for current user
    $query->leftJoin('job_hunter_tailored_resumes', 'tr', 'j.id = tr.job_id AND tr.uid = :uid', [':uid' => $current_user_id]);
    $query->addField('tr', 'tailoring_status');
    $query->addField('tr', 'tailored_resume_json');
    $query->addField('tr', 'pdf_path');
    $query->orderBy('c.name', 'ASC');
    $query->orderBy('j.job_title', 'ASC');
    $jobs = $query->execute()->fetchAll();
    
    // Build table
    $header = [
      $this->t('Job Title'),
      $this->t('Company'),
      $this->t('Status'),
      $this->t('AI Parsed'),
      $this->t('Tailored'),
      $this->t('Actions'),
    ];
    
    $rows = [];
    foreach ($jobs as $job) {
      // Parse extracted JSON for better title display
      $extracted = $job->extracted_json ? json_decode($job->extracted_json, TRUE) : NULL;
      $job_title = $extracted['position']['title'] ?? $job->job_title ?: 'Job #' . $job->id;
      $company_name = $extracted['company']['name'] ?? $job->company_name ?: 'Unknown';
      
      // Determine AI parsing status
      $has_raw_text = !empty($job->raw_posting_text);
      $has_extracted = !empty($job->extracted_json);
      $ai_status = $job->ai_extraction_status ?? 'pending';
      
      if ($has_extracted) {
        $ai_badge = '<span class="badge badge--success" title="AI parsing complete">✅ Parsed</span>';
      } elseif ($ai_status === 'processing' || $ai_status === 'queued') {
        $ai_badge = '<span class="badge badge--warning" title="AI parsing in progress">⏳ Processing</span>';
      } elseif ($ai_status === 'failed') {
        $ai_badge = '<span class="badge badge--error" title="AI parsing failed">❌ Failed</span>';
      } elseif ($has_raw_text) {
        $ai_badge = '<span class="badge badge--info" title="Has raw text, needs AI parsing">📝 Needs Parsing</span>';
      } else {
        $ai_badge = '<span class="badge badge--neutral" title="No content yet">⚪ No Content</span>';
      }
      
      // Determine tailoring status
      $tailoring_status = $job->tailoring_status ?? NULL;
      $has_tailored_json = !empty($job->tailored_resume_json);
      $has_pdf = !empty($job->pdf_path);
      
      if ($tailoring_status === 'completed' && $has_tailored_json) {
        if ($has_pdf) {
          $tailor_badge = '<span class="badge badge--success" title="Tailored with PDF ready">✅ PDF Ready</span>';
        } else {
          $tailor_badge = '<span class="badge badge--success" title="Resume tailored, generate PDF">✅ Tailored</span>';
        }
      } elseif ($tailoring_status === 'processing' || $tailoring_status === 'queued') {
        $tailor_badge = '<span class="badge badge--warning" title="Tailoring in progress">⏳ Processing</span>';
      } elseif ($tailoring_status === 'failed') {
        $tailor_badge = '<span class="badge badge--error" title="Tailoring failed">❌ Failed</span>';
      } else {
        $tailor_badge = '<span class="badge badge--neutral" title="Not yet tailored">⚪ Not Tailored</span>';
      }
      
      // Build action links
      $tailor_link = [
        '#type' => 'link',
        '#title' => $tailoring_status === 'completed' ? $this->t('View/Edit') : $this->t('Tailor'),
        '#url' => Url::fromRoute('job_hunter.tailor_resume', ['job' => $job->id]),
        '#attributes' => ['class' => ['button', 'button--small', $tailoring_status === 'completed' ? 'button--secondary' : 'button--primary']],
      ];
      
      $rows[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => $job_title,
            '#url' => Url::fromRoute('job_hunter.job_view', ['job_id' => $job->id]),
          ],
        ],
        $company_name,
        ucfirst($job->status ?: 'active'),
        ['data' => ['#markup' => $ai_badge]],
        ['data' => ['#markup' => $tailor_badge]],
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'tailor' => [
                'title' => $tailoring_status === 'completed' ? $this->t('View Tailored') : $this->t('Tailor Resume'),
                'url' => Url::fromRoute('job_hunter.tailor_resume', ['job' => $job->id]),
              ],
              'view' => [
                'title' => $this->t('View Job'),
                'url' => Url::fromRoute('job_hunter.job_view', ['job_id' => $job->id]),
              ],
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('job_hunter.job_edit', ['job_id' => $job->id]),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('job_hunter.job_delete', ['job_id' => $job->id]),
                'attributes' => [
                  'onclick' => 'return confirm("Are you sure you want to delete this job?");',
                ],
              ],
            ],
          ],
        ],
      ];
    }
    
    $content = [
      'header' => [
        '#markup' => '<h2>' . $this->t('Job Requirements') . '</h2>',
      ],
      'add_button' => [
        '#type' => 'link',
        '#title' => $this->t('Add Job Posting'),
        '#url' => Url::fromRoute('job_hunter.job_paste'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No job requirements found. Click "Add Job Requirement" to add your first job.'),
        '#attributes' => ['class' => ['jobs-table']],
      ],
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
    ];
    
    return $build;
  }

  /**
   * Delete a job requirement.
   */
  public function deleteJob($job_id) {
    $database = \Drupal::database();
    
    // Delete the job
    $database->delete('job_hunter_job_requirements')
      ->condition('id', $job_id)
      ->execute();
    
    $this->messenger()->addMessage($this->t('Job requirement has been deleted.'));
    
    return new RedirectResponse(Url::fromRoute('job_hunter.jobs_list')->toString());
  }

  /**
   * View a job requirement with all extracted data.
   */
  public function viewJob($job_id) {
    $database = \Drupal::database();
    
    // Render navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    // Load the job
    $job = $database->select('job_hunter_job_requirements', 'j')
      ->fields('j')
      ->condition('id', $job_id)
      ->execute()
      ->fetchObject();
    
    if (!$job) {
      $this->messenger()->addError($this->t('Job not found.'));
      return new RedirectResponse(Url::fromRoute('job_hunter.jobs_list')->toString());
    }
    
    // Parse JSON data
    $extracted = $job->extracted_json ? json_decode($job->extracted_json, TRUE) : NULL;
    $skills = $job->skills_required_json ? json_decode($job->skills_required_json, TRUE) : NULL;
    $keywords = $job->keywords_json ? json_decode($job->keywords_json, TRUE) : NULL;
    $duplicates = !empty($job->potential_duplicates_json) ? json_decode($job->potential_duplicates_json, TRUE) : [];
    
    // Build the content
    $content = [];
    
    // Show duplicate warning if found
    if (!empty($duplicates)) {
      $exact_match = array_filter($duplicates, fn($d) => $d['is_exact_match'] ?? FALSE);
      
      if (!empty($exact_match)) {
        $match = reset($exact_match);
        $content['duplicate_exact'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['messages', 'messages--error', 'duplicate-warning']],
          '#markup' => '<strong>⚠️ Exact Duplicate Found!</strong><br>' .
            'This job appears to be identical to: <a href="' . 
            Url::fromRoute('job_hunter.job_view', ['job_id' => $match['job_id']])->toString() . 
            '"><strong>' . htmlspecialchars($match['job_title']) . '</strong> at ' . 
            htmlspecialchars($match['company']) . ' (Job #' . $match['job_id'] . ')</a>',
        ];
      }
      else {
        $links = [];
        foreach ($duplicates as $dup) {
          $links[] = '<a href="' . 
            Url::fromRoute('job_hunter.job_view', ['job_id' => $dup['job_id']])->toString() . 
            '">' . htmlspecialchars($dup['job_title']) . ' at ' . 
            htmlspecialchars($dup['company']) . ' (' . $dup['similarity_score'] . '% match)</a>';
        }
        $content['duplicate_warning'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['messages', 'messages--warning', 'duplicate-warning']],
          '#markup' => '<strong>📋 Potential Duplicates Found</strong><br>' .
            'This job may be similar to:<br><ul><li>' . implode('</li><li>', $links) . '</li></ul>',
        ];
      }
    }
    
    // Header with edit link
    $content['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['job-view-header']],
      'title' => [
        '#markup' => '<h2>' . ($extracted['position']['title'] ?? 'Job Requisition #' . $job_id) . '</h2>',
      ],
      'company' => [
        '#markup' => $extracted ? '<p class="job-company"><strong>' . ($extracted['company']['name'] ?? '') . '</strong> — ' . ($extracted['company']['industry'] ?? '') . '</p>' : '',
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['job-actions']],
        'edit' => [
          '#type' => 'link',
          '#title' => $this->t('Edit'),
          '#url' => Url::fromRoute('job_hunter.job_edit', ['job_id' => $job_id]),
          '#attributes' => ['class' => ['button']],
        ],
        'tailor' => [
          '#type' => 'link',
          '#title' => $this->t('Tailor My Resume'),
          '#url' => Url::fromRoute('job_hunter.tailor_resume', ['job' => $job_id]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
      ],
    ];
    
    // Extracted Job Data section
    if ($extracted) {
      $content['extracted'] = [
        '#type' => 'details',
        '#title' => $this->t('Job Details'),
        '#open' => TRUE,
        '#attributes' => ['class' => ['job-section']],
      ];
      
      // Position info
      if (!empty($extracted['position'])) {
        $pos = $extracted['position'];
        $content['extracted']['position'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['job-subsection']],
          '#markup' => '<h3>Position</h3>' .
            '<dl class="job-details">' .
            '<dt>Title</dt><dd>' . ($pos['title'] ?? 'N/A') . '</dd>' .
            '<dt>Level</dt><dd>' . ($pos['level'] ?? 'N/A') . '</dd>' .
            '<dt>Department</dt><dd>' . ($pos['department'] ?? 'N/A') . '</dd>' .
            '<dt>Reports To</dt><dd>' . ($pos['reports_to'] ?? 'N/A') . '</dd>' .
            '<dt>Team Size</dt><dd>' . ($pos['team_size'] ?? 'N/A') . '</dd>' .
            '<dt>Remote</dt><dd>' . (($pos['is_remote'] ?? FALSE) ? 'Yes' : 'No') . '</dd>' .
            '<dt>Location</dt><dd>' . ($pos['location_requirements'] ?? 'N/A') . '</dd>' .
            '</dl>',
        ];
      }
      
      // Compensation
      if (!empty($extracted['compensation'])) {
        $comp = $extracted['compensation'];
        $salary = '';
        if (!empty($comp['salary_min']) && !empty($comp['salary_max'])) {
          $salary = '$' . number_format($comp['salary_min']) . ' - $' . number_format($comp['salary_max']);
        }
        $content['extracted']['compensation'] = [
          '#type' => 'container',
          '#markup' => '<h3>Compensation</h3>' .
            '<dl class="job-details">' .
            '<dt>Salary Range</dt><dd>' . ($salary ?: 'N/A') . '</dd>' .
            '<dt>Bonus</dt><dd>' . ($comp['bonus_structure'] ?? 'N/A') . '</dd>' .
            '<dt>Equity</dt><dd>' . (($comp['equity'] ?? FALSE) ? 'Yes' : 'No') . '</dd>' .
            '</dl>',
        ];
        if (!empty($comp['benefits_highlights'])) {
          $content['extracted']['benefits'] = [
            '#markup' => '<p><strong>Benefits:</strong> ' . implode(', ', $comp['benefits_highlights']) . '</p>',
          ];
        }
      }
      
      // Requirements
      if (!empty($extracted['requirements'])) {
        $req = $extracted['requirements'];
        $content['extracted']['requirements'] = [
          '#type' => 'container',
          '#markup' => '<h3>Requirements</h3>' .
            '<dl class="job-details">' .
            '<dt>Experience</dt><dd>' . ($req['years_experience_min'] ?? '?') . '+ years (preferred: ' . ($req['years_experience_preferred'] ?? '?') . ')</dd>' .
            '<dt>Education</dt><dd>' . ($req['education_required'] ?? 'N/A') . '</dd>' .
            '<dt>Preferred</dt><dd>' . ($req['education_preferred'] ?? 'N/A') . '</dd>' .
            '</dl>',
        ];
      }
      
      // Role type
      if (!empty($extracted['role_type'])) {
        $role = $extracted['role_type'];
        $content['extracted']['role_type'] = [
          '#type' => 'container',
          '#markup' => '<h3>Role Type</h3>' .
            '<dl class="job-details">' .
            '<dt>Player-Coach</dt><dd>' . (($role['is_player_coach'] ?? FALSE) ? 'Yes (' . ($role['hands_on_percentage'] ?? 0) . '% hands-on)' : 'No') . '</dd>' .
            '<dt>Management Scope</dt><dd>' . ($role['management_scope'] ?? 'N/A') . '</dd>' .
            '<dt>Strategic Scope</dt><dd>' . ($role['strategic_scope'] ?? 'N/A') . '</dd>' .
            '</dl>',
        ];
      }
      
      // Key responsibilities
      if (!empty($extracted['key_responsibilities'])) {
        $content['extracted']['responsibilities'] = [
          '#type' => 'container',
          '#markup' => '<h3>Key Responsibilities</h3><ul><li>' . implode('</li><li>', $extracted['key_responsibilities']) . '</li></ul>',
        ];
      }
      
      // Company culture
      if (!empty($extracted['company']['culture_keywords'])) {
        $content['extracted']['culture'] = [
          '#markup' => '<p><strong>Culture:</strong> ' . implode(', ', $extracted['company']['culture_keywords']) . '</p>',
        ];
      }
    }
    
    // Skills section
    if ($skills) {
      $content['skills'] = [
        '#type' => 'details',
        '#title' => $this->t('Required Skills'),
        '#open' => TRUE,
        '#attributes' => ['class' => ['job-section']],
      ];
      
      // Must-have skills
      if (!empty($skills['must_have'])) {
        $must_items = [];
        foreach ($skills['must_have'] as $skill) {
          $must_items[] = '<strong>' . $skill['skill'] . '</strong>' . 
            (!empty($skill['years']) ? ' (' . $skill['years'] . '+ years)' : '') .
            (!empty($skill['context']) ? ' — ' . $skill['context'] : '');
        }
        $content['skills']['must_have'] = [
          '#markup' => '<h3>Must Have</h3><ul><li>' . implode('</li><li>', $must_items) . '</li></ul>',
        ];
      }
      
      // Nice-to-have skills
      if (!empty($skills['nice_to_have'])) {
        $nice_items = [];
        foreach ($skills['nice_to_have'] as $skill) {
          $nice_items[] = '<strong>' . $skill['skill'] . '</strong>' .
            (!empty($skill['context']) ? ' — ' . $skill['context'] : '');
        }
        $content['skills']['nice_to_have'] = [
          '#markup' => '<h3>Nice to Have</h3><ul><li>' . implode('</li><li>', $nice_items) . '</li></ul>',
        ];
      }
      
      // Tech stack
      if (!empty($skills['tech_stack'])) {
        $stack = $skills['tech_stack'];
        $stack_html = '<h3>Tech Stack</h3><dl class="job-details">';
        if (!empty($stack['languages'])) {
          $stack_html .= '<dt>Languages</dt><dd>' . implode(', ', $stack['languages']) . '</dd>';
        }
        if (!empty($stack['frameworks'])) {
          $stack_html .= '<dt>Frameworks</dt><dd>' . implode(', ', $stack['frameworks']) . '</dd>';
        }
        if (!empty($stack['databases'])) {
          $stack_html .= '<dt>Databases</dt><dd>' . implode(', ', $stack['databases']) . '</dd>';
        }
        if (!empty($stack['cloud'])) {
          $stack_html .= '<dt>Cloud</dt><dd>' . implode(', ', $stack['cloud']) . '</dd>';
        }
        if (!empty($stack['tools'])) {
          $stack_html .= '<dt>Tools</dt><dd>' . implode(', ', $stack['tools']) . '</dd>';
        }
        $stack_html .= '</dl>';
        $content['skills']['tech_stack'] = [
          '#markup' => $stack_html,
        ];
      }
    }
    
    // Keywords section
    if ($keywords) {
      $content['keywords'] = [
        '#type' => 'details',
        '#title' => $this->t('Keywords for Resume Tailoring'),
        '#open' => FALSE,
        '#attributes' => ['class' => ['job-section']],
      ];
      
      if (!empty($keywords['high_frequency'])) {
        $content['keywords']['high_freq'] = [
          '#markup' => '<p><strong>High Frequency:</strong> ' . implode(', ', $keywords['high_frequency']) . '</p>',
        ];
      }
      if (!empty($keywords['action_verbs'])) {
        $content['keywords']['verbs'] = [
          '#markup' => '<p><strong>Action Verbs:</strong> ' . implode(', ', $keywords['action_verbs']) . '</p>',
        ];
      }
      if (!empty($keywords['key_phrases'])) {
        $content['keywords']['phrases'] = [
          '#markup' => '<p><strong>Key Phrases:</strong> ' . implode(', ', $keywords['key_phrases']) . '</p>',
        ];
      }
      if (!empty($keywords['domain_terms'])) {
        $content['keywords']['domain'] = [
          '#markup' => '<p><strong>Domain Terms:</strong> ' . implode(', ', $keywords['domain_terms']) . '</p>',
        ];
      }
    }
    
    // Raw posting (collapsed)
    if ($job->raw_posting_text) {
      $content['raw'] = [
        '#type' => 'details',
        '#title' => $this->t('Original Posting Text'),
        '#open' => FALSE,
        'text' => [
          '#markup' => '<pre style="white-space: pre-wrap; font-size: 12px; background: #f5f5f5; padding: 15px; border-radius: 4px;">' . htmlspecialchars($job->raw_posting_text) . '</pre>',
        ],
      ];
    }
    
    // Add some basic styling
    $content['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          .job-details { display: grid; grid-template-columns: 150px 1fr; gap: 8px; margin: 10px 0; }
          .job-details dt { font-weight: 600; color: #555; }
          .job-details dd { margin: 0; }
          .job-section { margin-bottom: 15px; }
          .job-subsection { margin-bottom: 20px; }
          .job-view-header { margin-bottom: 20px; }
          .job-company { color: #666; font-size: 1.1em; margin-top: -10px; }
        ',
      ],
      'job_view_styles',
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
    ];
    
    return $build;
  }

}
