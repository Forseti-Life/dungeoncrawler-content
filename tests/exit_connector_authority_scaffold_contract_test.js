/*
 * Contract test: connector lifecycle should have a central authority entrypoint.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const authoritySource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'ExitConnectorAuthorityService.php'),
    'utf8'
  );
  const navigationRuntimeSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'NavigationRuntimeService.php'),
    'utf8'
  );
  const campaignInitSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'CampaignInitializationService.php'),
    'utf8'
  );
  const storylineManagerSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'StorylineManagerService.php'),
    'utf8'
  );
  const servicesSource = fs.readFileSync(
    path.join(__dirname, '..', 'dungeoncrawler_content.services.yml'),
    'utf8'
  );

  assert(
    authoritySource.includes('class ExitConnectorAuthorityService')
      && authoritySource.includes('saveCanonicalConnector(array $data): string')
      && authoritySource.includes('saveCampaignConnector(int $campaign_id, array $data): string'),
    'ExitConnectorAuthorityService should define a central connector lifecycle entrypoint',
  );

  assert(
    servicesSource.includes('dungeoncrawler_content.exit_connector_authority:'),
    'service container should register ExitConnectorAuthorityService',
  );

  assert(
    navigationRuntimeSource.includes('ExitConnectorAuthorityService')
      && navigationRuntimeSource.includes("dungeoncrawler_content.exit_connector_authority"),
    'NavigationRuntimeService should resolve connector lifecycle through ExitConnectorAuthorityService',
  );

  assert(
    campaignInitSource.includes('ExitConnectorAuthorityService'),
    'CampaignInitializationService should depend on ExitConnectorAuthorityService instead of raw connector storage access',
  );

  assert(
    storylineManagerSource.includes('ExitConnectorAuthorityService')
      && storylineManagerSource.includes("dungeoncrawler_content.exit_connector_authority"),
    'StorylineManagerService should resolve connector lifecycle through ExitConnectorAuthorityService',
  );

  console.log('OK exit connector authority scaffold contract');
})();
