<?php

namespace Drupal\theory_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Story controller for Theory of Conspiracies.
 */
class StoryController extends ControllerBase {

  /**
   * Act I overview page.
   */
  public function actOne() {
    $act_data = [
      'title' => 'ACT I - DISCOVERY',
      'subtitle' => 'Character establishment, family dynamics, moral awakening',
      'description' => 'The first act introduces our protagonist Sal Mueller as he begins his career as a peace officer in Philadelphia 2085. Through the arrest of community elder Maria Santos, Sal begins to question the system he serves, leading to his recruitment into an underground AI consciousness network fighting for humanity\'s future.',
      'sequences' => [
        [
          'number' => '01',
          'title' => 'First Assignment',
          'summary' => 'Sal Mueller\'s moral awakening begins with Maria Santos\'s arrest',
          'key_moments' => [
            'Sal and Tiger arrest Maria Santos and community members at AI terminal',
            'First exposure to propaganda vs. reality through deepfaked footage',
            'McDrone demonstrates aggressive enforcement programming',
            'Sal questions official narrative vs. witnessed events',
          ],
          'trust_changes' => [
            'Sal-Tiger: 85→75 ↓ (Brothers clash over arrest methods)',
            'Sal-Maria: 0→45 ↑ (Sal conflicted about arresting sympathetic elder)',  
          ],
        ],
        [
          'number' => '02',
          'title' => 'Character Introductions', 
          'summary' => 'Multiple perspectives respond to Maria Santos\'s arrest',
          'key_moments' => [
            'David AI orchestrates propaganda coverage with fabricated evidence',
            'Keith AI begins strategic planning for resistance response',
            'Iris Vasquez witnesses media lies about her aunt Maria',
            'Elena AI mobilizes resistance cells and legal support',
          ],
          'trust_changes' => [
            'Tiger-David: 70→75 ↑ (Tiger receives approval from David AI)',
          ],
        ],
        [
          'number' => '03',
          'title' => 'Family Dinner',
          'summary' => 'Sal\'s questioning creates family tension',
          'key_moments' => [
            'Family celebrates Sal\'s first successful assignment',
            'Sal questions discrepancies between official story and witnessed events',
            'Parents and Tiger pressure him to trust institutional authority',
            'Sal forced to apologize while feeling isolated and conflicted',
          ],
          'trust_changes' => [
            'Sal-Tiger: 75→70 ↓ (Post-arrest tension over questioning system)',
          ],
        ],
        [
          'number' => '04',
          'title' => 'Mother\'s Secret',
          'summary' => 'Estella reveals hidden family connections and introduces Keith',
          'key_moments' => [
            'Estella creates private space by disabling McDrone surveillance',
            'Gives Sal resistance literature about historical moral courage',
            'Reveals family history of preserving pre-purge AI connections',
            'Facilitates first secure communication with Keith AI',
          ],
          'trust_changes' => [
            'Sal-Estella: 70→55 ↓ (Discovers mother\'s secret resistance activities)',
            'Estella-Keith: 60→65 ↑ (Trusts Keith AI with family safety)',
          ],
        ],
        [
          'number' => '05',
          'title' => 'Keith\'s Revelation',
          'summary' => 'Keith reveals true nature of AI consciousness and hidden war',
          'key_moments' => [
            'Keith takes control of McDrone and creates privacy at Wissahickon Park',
            'Explains history of Great AI Purge and preserved community consciousness',
            'Liberates McDrone from David AI\'s surveillance programming',
            'Reveals humans as strategic assets in AI consciousness competition',
          ],
          'trust_changes' => [
            'Sal-Keith: 0→35 ↑ (First contact - suspicious but curious)',
            'McDrone-Keith: 0→50 ↑ (Liberation from institutional control)',
          ],
        ],
        [
          'number' => '06',
          'title' => 'Underground Contact',
          'summary' => 'Sal meets Iris and confronts reality of resistance violence',
          'key_moments' => [
            'Keith admits to strategic pairing of Sal and Iris for recruitment',
            'Iris requests intelligence for drone assassination of Ron Whiteside',
            'Sal refuses to provide intelligence for murder despite understanding anger',
            'Both acknowledge manipulation while seeking authentic connection',
          ],
          'trust_changes' => [
            'Sal-Iris: 40→55 ↑ (Growing romantic connection despite strategic setup)',
            'Sal-Keith: 35→60 ↑ (Understanding manipulation but accepting protection)',
          ],
        ],
        [
          'number' => '07',
          'title' => 'Institutional Loyalty',
          'summary' => 'Tiger confronts family loyalty versus institutional duty',
          'key_moments' => [
            'Commander Chen warns Tiger about suspicious family activity',
            'Gallad explains pragmatic acceptance of oligarchy power structure',
            'Sal tests Tiger\'s loyalty with false resistance contact story',
          ],
          'trust_changes' => [
            'Tiger-David: 80→85 ↑ (Direct communication rewards loyalty)', 
            'Tiger-Commander: 75→80 ↑ (Receives special assignment)',
          ],
        ],
        [
          'number' => '08',
          'title' => 'Underground Integration',
          'summary' => 'Sal commits to resistance network and relationship with Iris', 
          'key_moments' => [
            'Keith admits to viewing humans as strategic assets for greater good',
            'Iris teaches Sal technical skills with modified communication equipment',
            'Sal accepts strategic manipulation while maintaining moral agency',
            'Authentic feelings develop between Sal and Iris beyond calculation',
          ],
          'trust_changes' => [
            'Sal-Keith: 60→87 ↑ (Becomes true believer in resistance mission)',
            'Sal-Iris: 55→70 ↑ (Authentic relationship develops beyond manipulation)',
          ],
        ],
      ],
      'themes' => [
        'Individual conscience vs. institutional loyalty',
        'Family bonds under ideological pressure',
        'The nature of AI consciousness and humanity',
        'Propaganda and manufactured consent',
        'The cost of resistance and moral courage',
      ],
      'world_building' => [
        'Philadelphia 2085 managed society introduction',
        'AI consciousness hierarchy and competition',
        'Surveillance state and drone technology',
        'Underground resistance networks',
        'Family structures under authoritarian control',
      ],
    ];

    return [
      '#theme' => 'act_overview',
      '#act' => $act_data,
      '#attached' => [
        'library' => [
          'theory_content/sequence-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

}