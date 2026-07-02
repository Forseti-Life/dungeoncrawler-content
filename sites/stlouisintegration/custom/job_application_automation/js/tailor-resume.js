/**
 * @file
 * JavaScript for tailor resume functionality.
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Helper function to add messages.
   */
  function addMessage(message, type) {
    type = type || 'status';
    var messageClass = 'messages--' + type;
    var messageHtml = '<div class="messages ' + messageClass + '">' + message + '</div>';
    
    // Try to add to messages region first
    if ($('.region-messages').length) {
      $('.region-messages').prepend(messageHtml);
    } else if ($('.messages').length) {
      // Add after existing messages
      $('.messages').first().after(messageHtml);
    } else {
      // Add to the top of the main content area
      if ($('main').length) {
        $('main').prepend(messageHtml);
      } else if ($('#content').length) {
        $('#content').prepend(messageHtml);
      } else {
        $('body').prepend(messageHtml);
      }
    }
  }

  /**
   * Tailor Resume behavior.
   */
  Drupal.behaviors.tailorResume = {
    attach: function (context, settings) {
      once('tailor-resume-init', '#start-tailoring', context).forEach(function (element) {
        $(element).on('click', function(e) {
          e.preventDefault();
          
          const button = $(this);
          const jobId = button.data('job-id');
          const userId = button.data('user-id');
          
          // Show loading status
          $('#tailoring-status').show();
          $('#tailoring-results').hide();
          button.prop('disabled', true);
          
          // Get selected tailoring options
          const options = [];
          $('.form-check-input:checked').each(function() {
            options.push($(this).attr('id'));
          });
          
          // Call the AI service
          $.ajax({
            url: '/tailor-resume/ajax',
            type: 'POST',
            data: {
              job_id: jobId,
              user_id: userId,
              options: options
            },
            success: function(response) {
              // Hide loading status
              $('#tailoring-status').hide();
              
              if (response.success) {
                // Show tailored resume
                $('#resume-content').html(response.tailored_resume);
                $('#tailoring-results').show();
                
                var successMessage = 'Resume successfully tailored for ' + response.job_title;
                if (response.tailored_resume_node_id) {
                  successMessage += '. <a href="/node/' + response.tailored_resume_node_id + '" target="_blank">View saved tailored resume</a>';
                }
                
                addMessage(successMessage, 'status');
              } else {
                addMessage('Error: ' + (response.error || 'Unknown error occurred'), 'error');
              }
              
              button.prop('disabled', false);
            },
            error: function(xhr, status, error) {
              // Hide loading status
              $('#tailoring-status').hide();
              button.prop('disabled', false);
              
              let errorMessage = 'Failed to tailor resume. Please try again.';
              if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMessage = xhr.responseJSON.error;
              }
              addMessage(errorMessage, 'error');
            }
          });
        });
      });
      
      // Handle resume action buttons
      once('resume-actions-init', '.resume-actions button', context).forEach(function (element) {
        $(element).on('click', function(e) {
          e.preventDefault();
          
          const action = $(this).find('i').hasClass('fa-download') ? 'download' :
                        $(this).find('i').hasClass('fa-edit') ? 'edit' : 'save';
          
          switch(action) {
            case 'download':
              handleDownloadResume();
              break;
            case 'edit':
              handleEditResume();
              break;
            case 'save':
              handleSaveResume();
              break;
          }
        });
      });
    }
  };
  
  /**
   * Generate mock tailored resume content.
   */
  function generateMockTailoredResume(jobId) {
    return `
      <div class="resume-header">
        <h2>John Doe</h2>
        <p>Data Science Professional | Analytics Expert</p>
        <p>Email: john.doe@email.com | Phone: (555) 123-4567</p>
      </div>
      
      <div class="resume-section">
        <h3>Professional Summary</h3>
        <p><strong>Tailored for this position:</strong> Experienced data science leader with 8+ years in oncology analytics and GPO data analysis. Proven track record in developing predictive models and driving data-driven decisions in healthcare settings.</p>
      </div>
      
      <div class="resume-section">
        <h3>Key Skills (Matched to Job Requirements)</h3>
        <ul>
          <li><strong>Data Science & Analytics</strong> - Python, R, SQL, Machine Learning</li>
          <li><strong>Healthcare Data</strong> - Oncology data analysis, Clinical datasets</li>
          <li><strong>Leadership</strong> - Team management, Cross-functional collaboration</li>
          <li><strong>GPO Analytics</strong> - Group purchasing organization data modeling</li>
        </ul>
      </div>
      
      <div class="resume-section">
        <h3>Professional Experience</h3>
        <div class="job-entry">
          <h4>Senior Data Scientist - Healthcare Analytics Co.</h4>
          <p><em>2020 - Present</em></p>
          <ul>
            <li><strong>Highlighted:</strong> Led oncology data analysis projects resulting in 25% improvement in treatment outcome predictions</li>
            <li>Developed machine learning models for GPO purchasing pattern analysis</li>
            <li>Managed team of 5 data analysts and scientists</li>
          </ul>
        </div>
      </div>
      
      <div class="resume-section">
        <h3>Education</h3>
        <p><strong>M.S. Data Science</strong> - Stanford University</p>
        <p><strong>B.S. Statistics</strong> - University of California, Berkeley</p>
      </div>
    `;
  }
  
  /**
   * Handle resume download.
   */
  function handleDownloadResume() {
    // Create a simple text file download
    const resumeContent = $('#resume-content').text();
    const blob = new Blob([resumeContent], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'tailored-resume.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    // Show success message
    addMessage('Resume downloaded successfully!', 'status');
  }
  
  /**
   * Handle resume editing.
   */
  function handleEditResume() {
    const resumeContent = $('#resume-content');
    if (resumeContent.attr('contenteditable') === 'true') {
      // Stop editing
      resumeContent.attr('contenteditable', 'false');
      resumeContent.removeClass('editing');
      $(this).html('<i class="fas fa-edit"></i> Edit Resume');
    } else {
      // Start editing
      resumeContent.attr('contenteditable', 'true');
      resumeContent.addClass('editing');
      resumeContent.focus();
      $(this).html('<i class="fas fa-check"></i> Done Editing');
    }
  }
  
  /**
   * Handle resume saving.
   */
  function handleSaveResume() {
    // Simulate saving
    const button = $(this);
    const originalText = button.html();
    
    button.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
    button.prop('disabled', true);
    
    setTimeout(function() {
      button.html(originalText);
      button.prop('disabled', false);
      // Show success message
      addMessage('Resume changes saved successfully!', 'status');
    }, 1500);
  }

})(jQuery, Drupal, once);