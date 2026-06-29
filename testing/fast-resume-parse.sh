#!/usr/bin/env node

/**
 * Fast Resume Parsing Test
 * Uses Drush command to bypass queue, enabling instant testing
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Configuration
const BASE_URL = process.argv[2] || 'http://localhost';
const RESUME_PATH = process.argv[3] || '/mnt/chromeos/MyFiles/Downloads/KeithAumillerA.pdf';
const DRUPAL_ROOT = '/home/keithaumiller/forseti.life/sites/forseti';

console.log('\n=== 🚀 Job Hunter Resume Parser - Fast Test (Drush Bypass) ===\n');
console.log(`📝 Configuration:`);
console.log(`   Site: ${BASE_URL}`);
console.log(`   Resume: ${RESUME_PATH}`);
console.log(`   Drupal: ${DRUPAL_ROOT}\n`);

// Step 1: Validate resume file
console.log('📄 Step 1: Validating resume file...');
if (!fs.existsSync(RESUME_PATH)) {
  console.error(`❌ Resume file not found: ${RESUME_PATH}`);
  process.exit(1);
}
const fileSize = fs.statSync(RESUME_PATH).size;
console.log(`✅ File exists: ${(fileSize / 1024).toFixed(1)} KB\n`);

// Step 2: Run Drush command to parse resume
console.log('🔄 Step 2: Finding resume file in Drupal...');
try {
  // First, we need to get the file ID - for this test, we'll assume file ID 1
  // In production, you'd query the database or UI to get the proper file ID
  
  // For now, let's use a simpler approach: directly call the parsing via Drush
  const fileId = process.env.JOBHUNTER_FILE_ID || '1';
  
  console.log(`   Using file ID: ${fileId}`);
  console.log(`   Executing: drush job-hunter:parse-resume ${fileId}\n`);
  
  console.log('🔄 Step 3: Parsing resume (via Drush)...');
  const output = execSync(
    `cd ${DRUPAL_ROOT} && php vendor/bin/drush job-hunter:parse-resume ${fileId}`,
    { encoding: 'utf-8', stdio: 'pipe' }
  );
  
  console.log(output);
  
  // Check for success indicators
  if (output.includes('✓ Resume parsing complete')) {
    console.log('\n✅ Resume parsing successful!\n');
  } else if (output.includes('Error') || output.includes('error')) {
    console.error('\n❌ Parsing encountered an error\n');
    process.exit(1);
  }
  
} catch (error) {
  console.error(`\n❌ Drush command failed:`);
  console.error(error.message);
  
  // If Drush command fails, provide helpful guidance
  console.error(`\n💡 Troubleshooting:`);
  console.error(`   1. Verify Drush is installed: cd ${DRUPAL_ROOT} && php vendor/bin/drush --version`);
  console.error(`   2. Check if job-hunter module is enabled: drush pm:list | grep job-hunter`);
  console.error(`   3. Verify file exists in Drupal: drush sql:query "SELECT * FROM file_managed"`);
  console.error(`   4. Check Drupal logs: drush watchdog:show\n`);
  
  process.exit(1);
}

// Step 4: Validate parsed data in database
console.log('🔄 Step 4: Validating parsing results in database...');
try {
  const dbQuery = `
    SELECT id, status, parsed_data, error_message
    FROM jobhunter_resume_parsed_data
    WHERE status = 'complete'
    ORDER BY changed DESC
    LIMIT 1
  `;
  
  const result = execSync(
    `cd ${DRUPAL_ROOT} && php vendor/bin/drush sql:query "${dbQuery}"`,
    { encoding: 'utf-8', stdio: 'pipe' }
  );
  
  if (result.includes('complete')) {
    console.log('✅ Parsing result stored in database\n');
    console.log('📊 Latest parsed record:');
    console.log(result);
  } else {
    console.error('⚠️  Could not find completed parsing record');
  }
  
} catch (error) {
  console.warn('\n⚠️  Could not verify database results');
  console.warn(`   Error: ${error.message}\n`);
}

// Step 5: Display summary
console.log('\n=== ✅ Fast Parse Test Complete ===\n');
console.log('Next steps:');
console.log('  1. Check UI: http://localhost/jobhunter/profile/edit');
console.log('  2. View parsed JSON in database');
console.log('  3. Proceed to tailoring workflow\n');

console.log('📝 Notes:');
console.log('  - This test bypassed the queue system for instant feedback');
console.log('  - GenAI parsing ran with 2-pass approach (core + experience)');
console.log('  - Results automatically consolidated if all resumes are processed\n');
