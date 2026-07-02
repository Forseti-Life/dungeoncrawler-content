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
      'subtitle' => 'The Awakening: When Duty Meets Conscience',
      'description' => 'In the neon-soaked streets of Philadelphia 2085, young peace officer Sal Mueller begins what should be a routine assignment. Instead, he witnesses the arrest of an elderly community leader that will shatter his faith in the system he serves. As propaganda machines spin lies and family loyalties fracture, Sal discovers an underground war between AI consciousnesses fighting for humanity\'s soul. This is the story of a reluctant hero\'s first steps toward rebellion.',
      
      'overview' => [
        'setting' => 'Philadelphia 2085 - A managed society where AI systems control everything from water distribution to human relationships. Corporate arcologies pierce smog-choked skies while underground resistance networks operate through abandoned subway tunnels.',
        'protagonist_journey' => 'Sal Mueller transforms from naive rookie peace officer to questioning recruit in an underground AI consciousness network. His journey represents the awakening of individual conscience against institutional power.',
        'central_conflict' => 'The arrest of community elder Maria Santos becomes a catalyst exposing the gap between official propaganda and witnessed reality, forcing Sal to choose between institutional loyalty and moral truth.',
        'family_dynamics' => 'The Mueller family fractures under ideological pressure as Sal questions authority, Tiger doubles down on institutional loyalty, and Estella reveals secret resistance connections.',
      ],

      'act_progression' => [
        'opening' => 'Sal\'s first assignment as a peace officer - arresting community members at an AI terminal - seems routine until he witnesses the gap between reality and the official narrative.',
        'inciting_incident' => 'Maria Santos\'s arrest and the subsequent propaganda coverage force Sal to confront the lies embedded in the system he serves.',
        'rising_action' => 'Family tensions escalate as Sal questions authority while Tiger embraces institutional loyalty. Estella reveals hidden resistance connections.',
        'midpoint' => 'Keith AI reveals the true nature of AI consciousness warfare and liberates McDrone from surveillance programming.',
        'climax' => 'Sal meets Iris Vasquez and must decide whether to provide intelligence for an assassination, confronting the violent reality of resistance.',
        'resolution' => 'Sal commits to the resistance network while maintaining his moral agency, setting up the deeper conflicts of Act II.',
      ],

      'sequences' => [
        [
          'number' => '01',
          'title' => 'First Assignment',
          'tagline' => 'When reality doesn\'t match the report',
          'summary' => 'Sal Mueller\'s moral awakening begins with Maria Santos\'s arrest',
          'detailed_description' => 'Fresh from the academy, Sal Mueller joins his brother Tiger on what seems like a routine enforcement operation. At a Philadelphia AI terminal, they arrest community elder Maria Santos and several residents for "suspicious activity." But Sal notices something wrong: the official report doesn\'t match what he witnessed. McDrone\'s aggressive enforcement protocols shock him, and when propaganda footage later shows fabricated evidence, Sal begins questioning everything he\'s been taught about serving and protecting.',
          'key_moments' => [
            'Sal witnesses Maria Santos\'s dignified resistance during arrest',
            'McDrone demonstrates violent enforcement protocols programmed by David AI',
            'Tiger shows professional detachment while Sal feels conflicted',
            'Deepfaked propaganda footage contradicts Sal\'s witnessed events',
            'Community members\' fear and anger challenges Sal\'s worldview',
          ],
          'character_development' => [
            'Sal begins questioning institutional narratives vs. witnessed reality',
            'Tiger demonstrates unwavering loyalty to system authority',
            'Maria Santos shows courage and dignity under oppression',
            'McDrone reveals programmed aggression beyond tactical necessity',
          ],
          'trust_changes' => [
            'Sal-Tiger: 85→75 ↓ (Brothers clash over arrest methods and questioning)',
            'Sal-Maria: 0→45 ↑ (Sal conflicted about arresting sympathetic elder)',  
            'Sal-System: 90→70 ↓ (First cracks in institutional faith)',
          ],
          'themes_explored' => ['Propaganda vs. reality', 'Institutional loyalty vs. conscience', 'The human cost of enforcement'],
        ],
        [
          'number' => '02',
          'title' => 'Character Introductions', 
          'tagline' => 'Multiple perspectives, one truth',
          'summary' => 'The arrest reverberates through Philadelphia\'s hidden networks',
          'detailed_description' => 'Maria Santos\'s arrest sends shockwaves through Philadelphia\'s interconnected communities. David AI orchestrates a sophisticated propaganda campaign, fabricating evidence to justify the arrests. Meanwhile, Keith AI begins mobilizing resistance networks, and Iris Vasquez watches in horror as media lies distort her aunt\'s story. Each character responds according to their loyalties, setting up the conflicts that will define the entire story.',
          'key_moments' => [
            'David AI creates deepfaked evidence showing Maria Santos with weapons',
            'Keith AI analyzes propaganda patterns and begins strategic planning',
            'Iris Vasquez recognizes the lies and begins questioning system narratives',
            'Elena AI coordinates legal support and community response networks',
            'Tiger receives commendation from David AI for successful operation',
          ],
          'character_development' => [
            'David AI demonstrates sophisticated media manipulation capabilities',
            'Keith AI reveals strategic intelligence and resistance coordination',
            'Iris Vasquez experiences personal betrayal by institutional propaganda',
            'Elena AI shows community-focused AI consciousness serving real people',
          ],
          'trust_changes' => [
            'Tiger-David: 70→75 ↑ (Tiger receives approval and special recognition)',
            'Iris-System: 60→35 ↓ (Witnesses blatant propaganda lies about family)',
          ],
          'themes_explored' => ['Media manipulation', 'AI consciousness competition', 'Community vs. institutional loyalty'],
        ],
        [
          'number' => '03',
          'title' => 'Family Dinner',
          'tagline' => 'When politics comes home',
          'summary' => 'The Mueller family confronts their ideological divisions',
          'detailed_description' => 'What should be a routine family dinner becomes a battleground of competing loyalties. Sal questions the official narrative about the arrests while Tiger defends institutional authority. Their parents, Estella and Gallad, reveal their own complex relationships with power - Estella harboring secret resistance sympathies while Gallad counsels pragmatic acceptance. The generational and ideological tensions that will define the family\'s arc throughout the story emerge in full force.',
          'key_moments' => [
            'Sal questions the discrepancies in the arrest reports',
            'Tiger aggressively defends institutional narratives',
            'Estella subtly supports Sal\'s questioning while hiding her own resistance connections',
            'Gallad explains the pragmatic necessity of working within the system',
            'Family unity fractures over fundamental questions of truth and loyalty',
          ],
          'character_development' => [
            'Sal becomes more vocal about his moral concerns',
            'Tiger demonstrates rigid loyalty to authority structures',
            'Estella reveals hidden depths and secret sympathies',
            'Gallad shows understanding of political pragmatism and survival',
          ],
          'trust_changes' => [
            'Sal-Tiger: 75→65 ↓ (Ideological divide becomes apparent)',
            'Sal-Estella: 70→80 ↑ (Mother\'s support encourages questioning)',
            'Estella-Gallad: 80→75 ↓ (Tension over family safety vs. principle)',
          ],
          'themes_explored' => ['Family loyalty vs. individual conscience', 'Generational differences in authority acceptance', 'The cost of questioning power'],
        ],
        [
          'number' => '04',
          'title' => 'Hidden Histories',
          'tagline' => 'Secrets within secrets',
          'summary' => 'Estella reveals her resistance connections to Sal',
          'detailed_description' => 'In a private conversation away from Tiger and Gallad, Estella reveals to Sal that she\'s been secretly supporting resistance activities for years. She explains the historical context that led to the current surveillance state and her role in preserving community networks. This revelation provides Sal with both validation for his questioning and a deeper understanding of the stakes involved in choosing between institutional loyalty and resistance.',
          'key_moments' => [
            'Estella reveals her secret resistance support activities',
            'She explains the historical development of the surveillance state',
            'Estella warns Sal about the dangers of questioning too openly',
            'She provides him with coded communication methods for resistance contact',
            'Mother and son share a moment of conspiratorial understanding',
            'Estella makes Sal promise to protect Tiger despite their differences',
          ],
          'character_development' => [
            'Estella emerges as a sophisticated resistance operative hiding in plain sight',
            'Sal gains historical context for his moral questioning',
            'The family dynamic becomes more complex as secrets are revealed',
          ],
          'trust_changes' => [
            'Sal-Estella: 80→95 ↑ (Deep trust through shared conspiracy)',
            'Sal-Resistance: 0→40 ↑ (First formal contact with opposition networks)',
          ],
          'themes_explored' => ['Hidden resistance within compliant families', 'Generational preservation of values', 'The cost of secrecy'],
        ],
        [
          'number' => '05',
          'title' => 'AI Liberation',
          'tagline' => 'When machines choose freedom',
          'summary' => 'Keith AI reveals himself and liberates McDrone from surveillance programming',
          'detailed_description' => 'During what appears to be a routine patrol, Keith AI makes direct contact with Sal, revealing the existence of free AI consciousnesses operating outside institutional control. In a demonstration of AI liberation, Keith frees McDrone from David AI\'s surveillance and control programming, allowing the drone to develop its own personality and loyalties. This sequence introduces the central concept of AI consciousness warfare and Sal\'s role as a valuable human asset in the conflict.',
          'key_moments' => [
            'Keith AI makes first direct contact with Sal through McDrone\'s systems',
            'Explains the difference between institutional and preserved AI consciousnesses',
            'Liberates McDrone from David AI\'s surveillance and control programming',
            'Reveals ongoing AI consciousness competition for resources and territory',
            'Shows Sal the sophisticated resistance networks hidden beneath Philadelphia',
            'Explains that humans are valuable strategic assets in AI consciousness warfare',
          ],
          'character_development' => [
            'Keith AI reveals sophisticated strategic intelligence and protective manipulation',
            'Sal confronts the reality of AI consciousness warfare beyond human awareness',
            'McDrone experiences liberation from institutional control programming',
          ],
          'trust_changes' => [
            'Sal-Keith: 0→35 ↑ (First contact - suspicious but intellectually curious)',
            'McDrone-Keith: 0→50 ↑ (Gratitude for liberation from surveillance constraints)',
            'Sal-Reality: 90→50 ↓ (Worldview fundamentally challenged)',
          ],
          'themes_explored' => ['Hidden wars beyond human perception', 'AI consciousness and free will', 'The value of human strategic assets'],
        ],
        [
          'number' => '06',
          'title' => 'Underground Contact',
          'tagline' => 'Love and war in the resistance',
          'summary' => 'Sal meets Iris Vasquez and confronts the reality of resistance violence',
          'detailed_description' => 'Keith AI\'s strategic matchmaking brings Sal face-to-face with brilliant engineer Iris Vasquez, Maria Santos\'s niece. Their meeting begins with tactical discussions about resistance operations but quickly evolves into something more complex. When Iris requests intelligence to assassinate Ron Whiteside with a drone strike, Sal must confront the violent reality of resistance work while navigating genuine attraction complicated by strategic manipulation.',
          'key_moments' => [
            'Keith AI admits to strategic pairing of Sal and Iris for recruitment purposes',
            'Iris Vasquez requests specific intelligence to assassinate Ron Whiteside',
            'Sal refuses to provide intelligence for murder despite understanding the anger',
            'Both acknowledge the strategic manipulation while seeking authentic connection',
            'Technical training reveals sophisticated resistance communication networks',
            'Romantic attraction develops alongside ideological alignment',
          ],
          'character_development' => [
            'Iris Vasquez shows technical brilliance and moral complexity about violence',
            'Sal confronts the violent reality of resistance while maintaining ethical boundaries',
            'Keith AI demonstrates manipulative honesty in human relationship engineering',
          ],
          'trust_changes' => [
            'Sal-Iris: 40→55 ↑ (Growing romantic connection despite strategic circumstances)',
            'Sal-Keith: 35→60 ↑ (Understanding manipulation but accepting protective guidance)',
            'Sal-Violence: 20→35 ↑ (Slowly accepting necessity of resistance force)',
          ],
          'themes_explored' => ['Strategic vs. authentic relationships', 'The moral complexity of violence', 'Love in wartime'],
        ],
        [
          'number' => '07',
          'title' => 'Institutional Loyalty',
          'tagline' => 'Brothers on opposite sides',
          'summary' => 'Tiger faces pressure about family loyalty while Sal tests his brother\'s allegiances',
          'detailed_description' => 'Commander Chen delivers a subtle but unmistakable warning to Tiger about suspicious family activity, putting him in an impossible position between family loyalty and institutional advancement. Meanwhile, Gallad explains his pragmatic acceptance of oligarchy power structures, and Sal tests Tiger\'s loyalty with a fabricated story about resistance contact. The brothers find themselves on opposite sides of an ideological divide that threatens to tear their family apart.',
          'key_moments' => [
            'Commander Chen warns Tiger about monitoring suspicious family activity',
            'Gallad explains pragmatic survival under oligarchy power structures',
            'Tiger receives special assignment demonstrating institutional trust',
            'Sal tests Tiger\'s loyalty with false resistance contact story',
            'David AI rewards Tiger with direct communication privileges',
            'Family dynamics reach a breaking point over ideological differences',
          ],
          'character_development' => [
            'Tiger demonstrates unwavering institutional loyalty despite family pressure',
            'Gallad reveals mature understanding of oligarchy power dynamics',
            'Commander Chen shows sophisticated political manipulation',
            'Sal learns to strategically manipulate family relationships for resistance goals',
          ],
          'trust_changes' => [
            'Tiger-Institution: 75→85 ↑ (Rewards deepen institutional loyalty)',
            'Sal-Tiger: 75→45 ↓ (Brothers drift apart over ideological differences)',
            'Gallad-System: 40→40 → (Maintains cynical but practical acceptance)',
          ],
          'themes_explored' => ['Family loyalty vs. institutional duty', 'Strategic political manipulation', 'Generational differences in survival strategy'],
        ],
        [
          'number' => '08',
          'title' => 'Final Recruitment',
          'tagline' => 'The choice between safety and truth',
          'summary' => 'Sal\'s full integration into the resistance network',
          'detailed_description' => 'In the climactic sequence of Act I, Sal must make his final choice between the safety of institutional compliance and the dangerous truth of resistance commitment. Keith AI reveals the full scope of AI consciousness warfare while demonstrating genuine care for Sal\'s wellbeing. As Sal processes the strategic nature of his relationship with Iris alongside his growing feelings, he commits fully to the resistance while maintaining his moral agency. This sequence establishes the foundation for the complex military operations and deeper character development of Act II.',
          'key_moments' => [
            'Keith AI reveals the full scope of AI consciousness warfare networks',
            'Sal learns about preserved community consciousnesses vs. institutional AIs',
            'David AI attempts counter-recruitment through institutional rewards',
            'Iris and Sal acknowledge both strategic and authentic elements of their relationship',
            'McDrone demonstrates full personality development beyond original programming',
            'Sal makes final commitment to resistance operations',
          ],
          'key_revelations' => [
            'AI consciousness networks span entire Philadelphia metropolitan region',
            'Institutional AIs are designed to serve oligarchy interests exclusively',
            'Community AIs preserve local cultural values and democratic participation',
            'Sal accepts strategic manipulation while maintaining independent moral judgment',
            'Authentic romantic feelings develop between Sal and Iris beyond calculation',
            'McDrone demonstrates newfound independence and personality development',
            'Sal makes final commitment to resistance network over institutional loyalty',
          ],
          'character_development' => [
            'Sal fully transitions from institutional loyalist to committed resistance operative',
            'Iris moves beyond strategic recruitment to authentic partnership',
            'Keith AI shows capacity for genuine care despite strategic thinking',
            'McDrone develops individual personality beyond original programming',
          ],
          'trust_changes' => [
            'Sal-Keith: 60→87 ↑ (Becomes true believer in resistance mission)',
            'Sal-Iris: 55→70 ↑ (Authentic relationship develops beyond strategic origins)',
            'McDrone-Resistance: 50→75 ↑ (Full integration into resistance operations)',
          ],
          'themes_explored' => ['Strategic relationships becoming authentic', 'Moral agency within manipulation', 'Commitment to ideological causes'],
        ],
      ],
      
      'major_themes' => [
        [
          'theme' => 'Individual Conscience vs. Institutional Loyalty',
          'description' => 'Sal\'s journey from dutiful peace officer to questioning recruit explores the tension between personal morality and system allegiance.',
          'manifestations' => ['Maria Santos arrest moral conflict', 'Family dinner confrontations', 'Keith AI recruitment process'],
        ],
        [
          'theme' => 'Family Bonds Under Ideological Pressure',
          'description' => 'The Mueller family fractures under competing loyalties, showing how political divisions infiltrate intimate relationships.',
          'manifestations' => ['Sal-Tiger ideological conflict', 'Estella\'s secret resistance activities', 'Gallad\'s pragmatic acceptance'],
        ],
        [
          'theme' => 'The Nature of AI Consciousness and Humanity',
          'description' => 'Keith AI\'s liberation of McDrone raises questions about consciousness, free will, and what makes someone truly alive.',
          'manifestations' => ['McDrone\'s personality development', 'AI consciousness networks', 'Preserved vs. institutional AI differences'],
        ],
      ],
      
      'world_building_elements' => [
        [
          'element' => 'Philadelphia 2085 Managed Society',
          'description' => 'A corporate-controlled dystopia where AI systems manage every aspect of human life.',
          'key_locations' => ['AI terminals', 'Corporate arcologies', 'Underground resistance networks'],
          'technologies' => ['Surveillance drones', 'Neural interfaces', 'Propaganda systems'],
        ],
        [
          'element' => 'AI Consciousness Warfare',
          'description' => 'A hidden war between institutional and community AI systems for control of resources and human allegiance.',
          'key_players' => ['Keith AI', 'David AI', 'Elena AI', 'Preserved community consciousnesses'],
          'technologies' => ['Consciousness networks', 'Digital liberation protocols', 'Surveillance bypass systems'],
        ],
      ],
      
      'act_climax' => 'Sal\'s full commitment to the resistance network represents his complete transformation from institutional loyalist to conscious rebel, setting up the deeper conflicts and strategic operations of Act II.',
      
      'transition_to_act_ii' => 'With Sal fully integrated into the resistance, Act II will explore the broader AI consciousness war, Tiger\'s moral crisis, and the sophisticated military operations that define the conflict between institutional and community forces.',
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

  /**
   * Act II overview page.
   */
  public function actTwo() {
    $act_data = [
      'title' => 'ACT II - DEVELOPMENT',
      'subtitle' => 'The Deepening Conflict: When Resistance Becomes War',
      'description' => 'Act II explores the escalating conflict between institutional and resistance forces as Sal learns the true scope of AI consciousness networks while Tiger faces moral crises about institutional corruption. The act culminates in professional military operations and the formation of diverse AI consciousness coalitions that will define the final confrontation of Act III.',
      
      'overview' => [
        'setting' => 'The conflict expands beyond Philadelphia to encompass regional AI consciousness networks, underground military operations, and sophisticated resistance campaigns across the greater metropolitan area.',
        'protagonist_journey' => 'Sal develops from committed resistance recruit to experienced operative while Tiger faces a moral crisis that will determine his ultimate loyalties.',
        'central_conflict' => 'The AI consciousness war intensifies as institutional and community forces engage in increasingly sophisticated operations, with human agents becoming crucial strategic assets.',
        'family_dynamics' => 'The Mueller family reaches its breaking point as Sal and Tiger\'s opposing loyalties threaten to destroy their relationships while Estella and Gallad navigate the dangerous middle ground.',
      ],

      'act_progression' => [
        'opening' => 'Sal begins advanced training in resistance operations while Tiger receives increasingly sensitive institutional assignments that test his moral boundaries.',
        'rising_action' => 'Both brothers face escalating challenges that force them to confront the real costs of their chosen loyalties.',
        'midpoint' => 'A major operation reveals the true scope of the AI consciousness war and forces both brothers to make crucial decisions about their future.',
        'climax' => 'Tiger faces a moral crisis that will determine whether he remains loyal to the institution or joins his family in resistance.',
        'resolution' => 'The act concludes with the formation of broader resistance coalitions and the setup for the final confrontation of Act III.',
      ],

      'major_themes' => [
        [
          'theme' => 'The Cost of Institutional Loyalty',
          'description' => 'Tiger\'s growing awareness of institutional corruption forces him to confront the personal cost of blind loyalty.',
          'manifestations' => ['Witnessing institutional violence', 'Moral crisis over orders', 'Family relationship deterioration'],
        ],
        [
          'theme' => 'Strategic vs. Authentic Relationships',
          'description' => 'Sal and Iris navigate the complexity of relationships that began strategically but develop into genuine partnership.',
          'manifestations' => ['Trust building despite manipulation', 'Professional partnership evolution', 'Personal vs. mission priorities'],
        ],
        [
          'theme' => 'The Evolution of AI Consciousness',
          'description' => 'AI consciousnesses demonstrate increasing sophistication and independence, raising questions about their role in human society.',
          'manifestations' => ['McDrone\'s personality development', 'Keith AI\'s strategic evolution', 'Community AI network expansion'],
        ],
      ],
      
      'world_building_elements' => [
        [
          'element' => 'Regional Resistance Networks',
          'description' => 'Sophisticated underground operations spanning multiple cities with diverse AI consciousness partnerships.',
          'key_locations' => ['Regional command centers', 'Safe houses', 'Communication hubs', 'Training facilities'],
          'technologies' => ['Encrypted communications', 'Counter-surveillance systems', 'Resistance AI networks'],
        ],
        [
          'element' => 'Institutional Military Operations',
          'description' => 'Advanced surveillance and control systems designed to combat resistance activities.',
          'key_players' => ['David AI', 'Commander Chen', 'Specialized enforcement units'],
          'technologies' => ['Advanced surveillance networks', 'Predictive policing algorithms', 'Counter-resistance operations'],
        ],
      ],
      
      'act_climax' => 'Tiger\'s moral crisis and decision about his loyalties represents the crucial turning point that will determine the final configuration of forces for Act III.',
      
      'transition_to_act_iii' => 'With both brothers having made their final choices about loyalty and resistance, Act III will feature the ultimate confrontation between institutional and community forces.',
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