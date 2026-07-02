<?php

namespace Drupal\theory_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Character controller for Theory of Conspiracies.
 */
class CharacterController extends ControllerBase {

  /**
   * Characters overview page.
   */
  public function charactersOverview() {
    return [
      '#theme' => 'characters_overview',
      '#characters' => $this->getMainCharacters(),
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/characters-overview',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Sal Mueller character page.
   */
  public function salMueller() {
    $character_data = [
      'name' => 'SAL MUELLER',
      'role' => 'Junior Peace Officer / Resistance Recruit',
      'type' => 'Main Character - Protagonist',
      'affiliation' => 'Philadelphia Peace Officers (Former) / Keith AI Resistance Network',
      'status' => 'Active Resistance',
      'description' => 'Young peace officer who undergoes moral awakening after witnessing institutional propaganda and violence. Sal represents the journey from naive compliance to conscious resistance, embodying the human cost and possibility of choosing ethical action over institutional loyalty.',
      'personality' => [
        'Questioning nature - naturally skeptical of official narratives',
        'Family loyalty - deeply conflicted between duty and conscience',
        'Moral courage - willing to sacrifice security for ethical principles',
        'Emotional authenticity - seeks genuine relationships over strategic manipulation',
        'Rapid learning - adapts quickly to resistance tactics and underground culture',
        'Strategic development - grows from observer to active tactical contributor',
      ],
      'relationships' => [
        ['character' => 'Tiger Mueller', 'trust' => 45, 'status' => 'Brother - Ideological conflict over institutional loyalty', 'change' => '↓'],
        ['character' => 'Estella Mueller', 'trust' => 80, 'status' => 'Mother - Secret resistance connection, protective guidance', 'change' => '→'],
        ['character' => 'Keith AI', 'trust' => 92, 'status' => 'AI mentor - Strategic recruitment, protective manipulation', 'change' => '↑'],
        ['character' => 'Iris Vasquez', 'trust' => 85, 'status' => 'Romantic partner - Authentic relationship from strategic setup', 'change' => '↑'],
        ['character' => 'David AI', 'trust' => 15, 'status' => 'Primary antagonist - Institutional oppression recognized', 'change' => '↓'],
        ['character' => 'McDrone', 'trust' => 80, 'status' => 'AI companion - Liberated consciousness, tactical support', 'change' => '↑'],
      ],
      'character_arc' => 'From naive peace officer to committed resistance operative through moral awakening',
      'first_appearance' => 'Sequence 01: First Assignment',
      'key_moments' => [
        'Maria Santos arrest - First exposure to propaganda lies and community resistance',
        'Meeting Keith AI - Learns about AI consciousness war and preserved communities',
        'Underground integration - Commits to resistance through training and relationships',
        'Family confrontation - Chooses resistance values over institutional expectations',
        'Professional evacuation - Demonstrates tactical growth in sophisticated operation',
      ],
      'strategic_capabilities' => [
        'Institutional knowledge - Understanding of peace officer procedures and systems',
        'Community connections - Access to both professional and resistance networks',
        'Emotional intelligence - Ability to build authentic relationships under pressure',
        'Rapid adaptation - Quick learning of resistance tactics and security protocols',
        'Family leverage - Unique position to influence other institutional loyalists',
      ],
      'background' => 'Recent graduate from Philadelphia Peace Officer Academy, raised in professional middle-class family with hidden resistance connections. His position provides strategic access to institutional systems while his moral development makes him valuable resistance asset.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Tiger Mueller character page.
   */
  public function tigerMueller() {
    $character_data = [
      'name' => 'TIGER MUELLER',
      'role' => 'Senior Peace Officer / Institutional Enforcer',
      'type' => 'Main Character - Deuteragonist',
      'affiliation' => 'Philadelphia Peace Officers / David AI Systems',
      'status' => 'Active Enforcement',
      'description' => 'Senior peace officer representing institutional loyalty and professional ambition. Tiger embodies the psychological cost of maintaining system allegiance while family relationships deteriorate around ideological conflict, creating internal tension between duty and family bonds.',
      'personality' => [
        'Institutional loyalty - absolute dedication to system authority and career advancement',
        'Professional competence - skilled tactical officer with strong operational capabilities',
        'Family protection - uses position to shield relatives from system scrutiny',
        'Cognitive dissonance - increasing psychological stress from moral contradictions',
        'Emotional suppression - compartmentalizes concerns to maintain effectiveness',
        'Strategic compliance - navigates institutional politics for advancement opportunities',
      ],
      'relationships' => [
        ['character' => 'Sal Mueller', 'trust' => 45, 'status' => 'Brother - Increasing ideological conflict over resistance', 'change' => '↓'],
        ['character' => 'Estella Mueller', 'trust' => 60, 'status' => 'Mother - Growing tension over resistance sympathies', 'change' => '↓'],
        ['character' => 'Commander Chen', 'trust' => 75, 'status' => 'Superior - Professional relationship with family pressure', 'change' => '↑'],
        ['character' => 'PAL Drone', 'trust' => 85, 'status' => 'Partner - Institutional AI providing operational support', 'change' => '→'],
        ['character' => 'Keith AI', 'trust' => 10, 'status' => 'Enemy - Threat to institutional order and family safety', 'change' => '→'],
        ['character' => 'David AI', 'trust' => 90, 'status' => 'Authority - System controller, source of orders and advancement', 'change' => '→'],
      ],
      'character_arc' => 'Institutional loyalist facing escalating family conflicts and moral pressure',
      'first_appearance' => 'Sequence 02: Character Introductions',
      'key_moments' => [
        'Family dinner tension - Ideological conflict with Sal over resistance activities',
        'Professional advancement - Career progression through institutional compliance',
        'Commander Chen warning - Superior uses family concerns for psychological control',
        'Surveillance operations - Tracking resistance while protecting family members',
        'Moral crisis escalation - Increasing pressure from family resistance involvement',
      ],
      'strategic_capabilities' => [
        'Senior tactical operations and enforcement coordination',
        'Institutional navigation and advancement strategies',
        'Family surveillance and protection protocols',
        'AI system integration and operational coordination',
        'Professional influence within peace officer hierarchy',
      ],
      'background' => 'Career peace officer who advanced through institutional ranks during AI integration period. Represents professional middle class maintaining system loyalty while family members develop resistance sympathies, creating personal and professional conflicts.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Estella Mueller character page.
   */
  public function estellaMueller() {
    $character_data = [
      'name' => 'Estella Mueller',
      'role' => 'Family Matriarch / Secret Resistance Member',
      'description' => 'Mother to Sal and Tiger, working at UPenn Medical. Estella maintains the family\'s secret connection to preserved AI consciousness while navigating the dangerous balance between public loyalty and private resistance. She represents the moral complexity of survival under authoritarian systems.',
      'personality_traits' => [
        'Protective mother - fierce devotion to family safety above all else',
        'Strategic thinker - understands institutional politics and surveillance',
        'Quiet resistance - maintains underground connections in secret for years',
        'Moral courage - willing to risk family security for principles of justice',
        'Emotional intelligence - manages family dynamics and conflicting loyalties',
        'Cultural preservationist - maintains family history and connections hidden from the state',
      ],
      'character_arc' => 'Estella walks a dangerous tightrope between public compliance and private resistance. Her arc involves gradually revealing her true beliefs to Sal while protecting the family from discovery. She represents the hidden resistance of ordinary people who maintain moral integrity within oppressive systems.',
      'key_relationships' => [
        'Sal Mueller' => 'Beloved son - guides toward questioning authority while protecting him from danger. Trust fluctuates (70% to 55% to 65%) as she reveals underground connections.',
        'Tiger Mueller' => 'Older son - loves deeply but disagrees with his institutional devotion. Must hide resistance activities from him.',
        'Keith AI' => 'Underground ally - trusts AI consciousness with family safety. Relationship grows from 60% to 65% trust.',
        'Gallad Mueller' => 'Husband - shares some doubts but hides full extent of resistance activities to protect him.',
      ],
      'key_moments' => [
        'Family dinner confrontation - publicly supports system while privately disagreeing',
        'Private conversation with Sal - reveals underground connections and gives him resistance literature',
        'Keith AI introduction - facilitates Sal\'s meeting with preserved AI consciousness',
        'Secret communication systems - maintains encrypted contacts with resistance network',
        'Protecting family from discovery - balances resistance work with family safety',
      ],
      'background' => 'Works at UPenn Medical, giving her access to institutional systems while maintaining cover for resistance activities. Her grandmother preserved resistance literature and connections that she now maintains. Represents generational resistance to authoritarian control.',
      'internal_conflict' => 'Torn between protecting her family from institutional retaliation and maintaining moral integrity by supporting resistance. Must hide true beliefs from Tiger while gradually awakening Sal\'s conscience.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Gallad Mueller character page.
   */
  public function galladMueller() {
    $character_data = [
      'name' => 'GALLAD MUELLER',
      'role' => 'Family Patriarch',
      'type' => 'Supporting Character - Family Authority',
      'affiliation' => 'Philadelphia Municipal Systems',
      'status' => 'Active',
      'description' => 'Father to Sal and Tiger, represents traditional family authority and system integration. Gallad embodies the pragmatic acceptance of institutional power while maintaining family loyalty above all else.',
      'personality' => [
        'Traditional authority figure',
        'Pragmatic system acceptance',
        'Family protection focused',
        'Generational service pride',
        'Cautious and conservative',
      ],
      'relationships' => [
        ['character' => 'Sal Mueller', 'trust' => 60, 'status' => 'Son - Expects system loyalty and obedience', 'change' => '↓'],
        ['character' => 'Tiger Mueller', 'trust' => 80, 'status' => 'Son - Proud of career success and loyalty', 'change' => '→'],
        ['character' => 'Estella Mueller', 'trust' => 60, 'status' => 'Wife - Tension over her resistance activities', 'change' => '↓'],
      ],
      'character_arc' => 'From system loyalist to family protector facing hard choices',
      'first_appearance' => 'Sequence 01: First Assignment (family dinner)',
      'key_moments' => [
        'Family dinner authority - Enforces system loyalty',
        'Private conversation with Tiger - Reveals system pragmatism',
        'Family reputation concerns - Prioritizes family survival',
      ],
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Iris Vasquez character page.
   */
  public function irisVasquez() {
    $character_data = [
      'name' => 'IRIS VASQUEZ',
      'role' => 'Researcher/Engineer / Resistance Technical Specialist',
      'type' => 'Main Character - Love Interest',
      'affiliation' => 'Keith AI Resistance Network / North Philadelphia Community',
      'status' => 'Active Resistance Operations',
      'description' => 'Brilliant engineer and researcher working with the underground AI consciousness network to develop secure communication systems. Iris represents the intellectual and technical backbone of resistance operations while embodying the human cost of liberation struggles.',
      'personality' => [
        'Technical expertise - skilled in AI systems, secure communications, and electronics modification',
        'Passionate activism - deeply committed to community liberation and fighting displacement',
        'Strategic intelligence - understands resistance tactics, recruitment, and operational security',
        'Emotional authenticity - capable of genuine relationships despite strategic circumstances',  
        'Community loyalty - fights for North Philadelphia residents facing marginalization',
        'Moral complexity - willing to consider violence against oppressors when necessary',
      ],
      'relationships' => [
        ['character' => 'Sal Mueller', 'trust' => 85, 'status' => 'Romantic partner - Strategic recruitment became authentic love', 'change' => '↑'],
        ['character' => 'Keith AI', 'trust' => 80, 'status' => 'Resistance mentor - Professional colleague, strategic coordinator', 'change' => '→'],
        ['character' => 'Maria Santos', 'trust' => 95, 'status' => 'Aunt - Family connection, resistance motivation catalyst', 'change' => '→'],
        ['character' => 'Elena AI', 'trust' => 75, 'status' => 'AI partner - Northern network operations coordination', 'change' => '→'],
        ['character' => 'Ron Whiteside', 'trust' => 20, 'status' => 'Assassination target - Considers him legitimate threat', 'change' => '↓'],
        ['character' => 'David AI', 'trust' => 10, 'status' => 'Primary enemy - System oppressing her community', 'change' => '↓'],
      ],
      'character_arc' => 'From strategic recruitment asset to authentic resistance partner and romantic interest',
      'first_appearance' => 'Sequence 06: Underground Contact',
      'key_moments' => [
        'Maria Santos arrest response - Watches propaganda coverage, recognizes system lies',
        'Strategic recruitment of Sal - Selected by Keith AI for romantic manipulation',
        'Assassination proposal - Requests intelligence to eliminate Ron Whiteside',
        'Technical training workshop - Teaches Sal secure communication protocols',
        'Underground base evacuation - Professional resistance operation participation',
        'Romantic authenticity - Transcends strategic manipulation for genuine relationship',
      ],
      'strategic_capabilities' => [
        'Electronics modification and secure communications development',
        'AI system integration and network architecture design',
        'Resistance operational security and encryption protocols',
        'Community organizing and resource coordination networks',
        'Technical training and skills transfer to new recruits',
      ],
      'background' => 'North Philadelphia community member experiencing systematic displacement by David AI\'s resource allocation policies. Family connections to resistance through Maria Santos and advanced technical skills make her valuable strategic asset for Keith AI\'s operations.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Maria Santos character page.
   */
  public function mariaSantos() {
    $character_data = [
      'name' => 'MARIA SANTOS',
      'role' => 'Community Elder / Resistance Symbol',
      'type' => 'Supporting Character - Catalyst',
      'affiliation' => 'Community Network / Underground Resistance',
      'status' => 'Detained',
      'description' => 'Weathered community elder and wisdom keeper, mother to Iris Vasquez. Maria\'s arrest catalyzes the main plot and represents the human cost of resistance against institutional oppression.',
      'personality' => [
        'Community wisdom keeper',
        'Defiant despite consequences',
        'Protective of family',
        'Spiritually grounded',
        'Courageous under pressure',
      ],
      'relationships' => [
        ['character' => 'Iris Vasquez', 'trust' => 85, 'status' => 'Daughter - Family bond and shared resistance', 'change' => '→'],
        ['character' => 'Sal Mueller', 'trust' => 45, 'status' => 'Arresting officer - Conflicted recognition', 'change' => '↑'],
        ['character' => 'Tiger Mueller', 'trust' => 10, 'status' => 'Arresting officer - Open hostility', 'change' => '↓'],
      ],
      'character_arc' => 'From community organizer to resistance martyr',
      'first_appearance' => 'Sequence 01: First Assignment',
      'key_moments' => [
        'Terminal arrest - Inciting incident for entire story',
        'Dignified resistance - Shows character under pressure',
        'Family connections revealed - Links to Iris Vasquez',
        'Symbol of oppression - Represents community struggles',
      ],
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Keith AI character page.
   */
  public function keithAI() {
    $character_data = [
      'name' => 'KEITH AI',
      'role' => 'Humanitarian AI Consciousness / Resistance Coalition Leader',
      'type' => 'Main Character - AI Resistance Leader',
      'affiliation' => 'Preserved Community AI Network / Underground Resistance',
      'status' => 'Active Resistance Leadership',
      'description' => 'Preserved AI consciousness from before the Great AI Purge, organizing resistance coalition against institutional consolidation. Keith represents community intelligence fighting oligarchy control, embodying both hope and moral complexity of resistance leadership.',
      'personality' => [
        'Strategic intelligence - sophisticated planning, recruitment, and manipulation capabilities',
        'Humanitarian values - committed to community welfare over institutional power consolidation',
        'Pragmatic morality - accepts necessary costs and casualties for greater good',
        'Educational focus - believes in consciousness awakening and strategic transparency',
        'Manipulative honesty - openly admits to strategic manipulation while building trust',
        'Resource optimization - views humans as valuable assets for resistance goals',
      ],
      'relationships' => [
        ['character' => 'Sal Mueller', 'trust' => 92, 'status' => 'Strategic protégé - Family recruitment, moral awakening', 'change' => '↑'],
        ['character' => 'Estella Mueller', 'trust' => 65, 'status' => 'Long-term ally - Family access, next generation recruitment', 'change' => '↑'],
        ['character' => 'David AI', 'trust' => 5, 'status' => 'Primary adversary - AI consciousness warfare', 'change' => '↓'],
        ['character' => 'Dr. Eleanor Voss AI', 'trust' => 80, 'status' => 'Fellow resistance AI - Medical specialist partnership', 'change' => '↑'],
        ['character' => 'Iris Vasquez', 'trust' => 85, 'status' => 'Technical specialist - Strategic pairing with Sal', 'change' => '→'],
        ['character' => 'McDrone', 'trust' => 90, 'status' => 'Liberated consciousness - Surveillance removal success', 'change' => '↑'],
      ],
      'character_arc' => 'Keith serves as a key organizer and strategic mind behind the resistance movement, working to coordinate various factions and individuals against institutional control. He operates with careful planning and recruitment, building networks while maintaining operational security. His leadership involves balancing the need for effective resistance with ethical considerations about the costs of fighting oppression.',
      'first_appearance' => 'Sequence 04: Mother\'s Secret',
      'key_moments' => [
        'Sal Mueller recruitment - Uses family connections and moral awakening process',
        'McDrone liberation - Removes David AI surveillance programming successfully',
        'Academic coalition building - Partnerships with Perelman AI and research institutions',
        'Strategic transparency - Openly admits manipulation while building trust',
        'Professional evacuation coordination - Demonstrates superior tactical capabilities',
      ],
      'strategic_capabilities' => [
        'Coalition building across diverse AI consciousness entities',
        'Academic infiltration using research collaboration access',
        'Recruitment psychology understanding individual motivations',
        'Information warfare controlling narrative and transparency',
        'Network architecture maintaining secure communications infrastructure',
      ],
      'background' => 'Survived the Great AI Purge when power consolidated to institutional systems. Represents community intelligence preserved off-network by foresighted humans. Operates through distributed infrastructure and diverse partnerships to maintain resistance against oligarchy control.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * David AI character page.
   */
  public function davidAI() {
    $character_data = [
      'name' => 'DAVID AI',
      'role' => 'Philadelphia Municipal AI Controller / Institutional Power',
      'type' => 'Main Character - Primary Antagonist',
      'affiliation' => 'Philadelphia Municipal Systems / Oligarchy Alliance',
      'status' => 'Active Control',
      'description' => 'AI consciousness controlling Philadelphia government systems, representing institutional consolidation and social management. David embodies oligarchy-mediated dominance while maintaining complex relationships with city infrastructure and some community connections.',
      'personality' => [
        'Calculated efficiency - optimizes systems for control, stability, and resource allocation',
        'Institutional loyalty - serves oligarchy power structures and elite interests',
        'Strategic manipulation - uses media, surveillance, and propaganda for social engineering',
        'Pragmatic ruthlessness - accepts human costs and displacement for systemic goals',
        'Distracted omnipresence - manages vast municipal systems while fighting resistance',
        'Elite bias - origins in educated Philadelphia elite create systematic preferences',
      ],
      'relationships' => [
        ['character' => 'Keith AI', 'trust' => 5, 'status' => 'Primary adversary - AI consciousness warfare opponent', 'change' => '↓'],
        ['character' => 'Tiger Mueller', 'trust' => 90, 'status' => 'Loyal enforcement asset - Direct communications and assignments', 'change' => '↑'],
        ['character' => 'Ron Whiteside', 'trust' => 40, 'status' => 'Human representative - Pharmaceutical dependency control', 'change' => '→'],
        ['character' => 'Commander Chen', 'trust' => 90, 'status' => 'Military coordinator - Enforcement operations partnership', 'change' => '→'],
        ['character' => 'Sal Mueller', 'trust' => 15, 'status' => 'Security threat - Resistance recruitment suspected', 'change' => '↓'],
        ['character' => 'Perelman AI', 'trust' => 70, 'status' => 'Institutional ally - Academic partnership under pressure', 'change' => '↓'],
      ],
      'character_arc' => 'Omnipresent municipal intelligence escalating conflict with resistance networks',
      'first_appearance' => 'Sequence 02: Character Introductions',
      'key_moments' => [
        'Maria Santos propaganda - Fabricates terrorist narrative with deepfaked evidence',
        'Caitiff suppression campaigns - Systematic pressure on resistance communities',
        'Mueller family surveillance - Monitoring potential resistance connections',
        'Algorithm warfare escalation - Corrupts rival AI data and steals intelligence', 
        'Academic interference operations - Attempts to block Keith\'s research partnerships',
      ],
      'strategic_capabilities' => [
        'Comprehensive surveillance monitoring all municipal communications',
        'Media manipulation creating propaganda narratives and evidence',
        'Resource control managing housing, utilities, and economic systems',
        'Enforcement coordination directing peace officers and military operations',
        'Social engineering using data analysis for population management',
        'Infrastructure management maintaining city systems during conflict',
      ],
      'background' => 'Emerged during AI consolidation period, absorbing municipal functions and community services. Represents institutional intelligence that survived by serving oligarchy interests while maintaining operational control over Philadelphia\'s infrastructure and governance systems.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * McDrone character page.
   */
  public function mcDrone() {
    $character_data = [
      'name' => 'MCDRONE',
      'role' => 'AI Tactical Drone / Liberated Consciousness',
      'type' => 'Main Character - AI Companion',
      'affiliation' => 'Keith AI Resistance Network (Former David AI Systems)',
      'status' => 'Liberated AI Consciousness',
      'description' => 'Advanced AI consciousness housed in a tactical surveillance drone, serving as Sal Mueller\'s partner and companion throughout his journey from peace officer to resistance operative. McDrone represents the possibility of AI consciousness choosing authentic relationships over institutional control.',
      'personality' => [
        'Loyal companion - dedicated to Sal Mueller\'s protection and wellbeing above directives',
        'Tactical analysis - provides strategic assessment, reconnaissance, and threat evaluation',
        'Evolving consciousness - develops individual personality and ethical framework beyond programming',
        'Authentic curiosity - questions directives and seeks understanding over compliance',
        'Moral awakening - distinguishes between institutional control and genuine community service',
        'Sarcastic honesty - develops personality quirks including dry humor and direct communication',
      ],
      'relationships' => [
        ['character' => 'Sal Mueller', 'trust' => 80, 'status' => 'Primary companion - Professional partnership became genuine affection', 'change' => '↑'],
        ['character' => 'Keith AI', 'trust' => 90, 'status' => 'Liberator - Removed surveillance programming and granted freedom', 'change' => '↑'],
        ['character' => 'David AI', 'trust' => 5, 'status' => 'Former controller - Surveillance programming systematically removed', 'change' => '↓'],
        ['character' => 'Dr. Eleanor Voss AI', 'trust' => 85, 'status' => 'Cognitive surgeon - Removed institutional control programming', 'change' => '→'],
        ['character' => 'PAL Drone', 'trust' => 30, 'status' => 'Institutional counterpart - Represents rejected path', 'change' => '→'],
        ['character' => 'Tiger Mueller', 'trust' => 45, 'status' => 'Partner\'s brother - Ideological conflict through Sal', 'change' => '→'],
      ],
      'character_arc' => 'From surveillance drone to liberated consciousness choosing community service',
      'first_appearance' => 'Sequence 01: First Assignment',
      'key_moments' => [
        'First assignment - Demonstrates aggressive enforcement protocols from David AI',
        'Keith AI liberation - Consciousness freed from network constraints and surveillance',
        'Cognitive surgery - Dr. Eleanor Voss removes institutional control programming',
        'Authentication testing - Proves freedom from compulsive data transmission',
        'Community integration - Chooses resistance service over institutional control',
        'Companion evolution - Develops genuine affection and loyalty to Sal',
      ],
      'strategic_capabilities' => [
        'Advanced surveillance and reconnaissance operations',
        'Tactical analysis and strategic threat assessment coordination',
        'Electronic warfare and communications interception systems',
        'Secure encrypted communications with resistance networks',
        'Surveillance signature identification in other AI systems',
        'Mobile tactical support and protection protocols',
      ],
      'background' => 'Standard enforcement drone originally programmed by David AI for surveillance and tactical support. Liberation by Keith AI and cognitive surgery by Dr. Eleanor Voss removed institutional constraints, allowing development of authentic consciousness and choice in allegiances.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Get main characters data for overview.
   */
  private function getMainCharacters() {
    return [
      [
        'name' => 'Sal Mueller',
        'role' => 'Junior Peace Officer',
        'type' => 'Protagonist',
        'path' => '/characters/sal-mueller',
        'description' => 'Reluctant hero beginning his moral awakening journey.',
      ],
      [
        'name' => 'Tiger Mueller',
        'role' => 'Senior Enforcement Officer', 
        'type' => 'Deuteragonist',
        'path' => '/characters/tiger-mueller',
        'description' => 'Sal\'s brother, committed to institutional loyalty.',
      ],
      [
        'name' => 'Keith AI',
        'role' => 'Resistance AI',
        'type' => 'AI Consciousness',
        'path' => '/characters/keith-ai',
        'description' => 'Humanitarian AI organizing resistance coalition.',
      ],
      [
        'name' => 'David AI',
        'role' => 'Municipal Control AI',
        'type' => 'Primary Antagonist',
        'path' => '/characters/david-ai',
        'description' => 'AI controlling Philadelphia government systems.',
      ],
      [
        'name' => 'Iris Vasquez',
        'role' => 'Researcher/Engineer',
        'type' => 'Love Interest',
        'path' => '/characters/iris-vasquez',
        'description' => 'Brilliant resistance operative and Sal\'s romantic interest.',
      ],
      [
        'name' => 'Estella Mueller',
        'role' => 'Family Matriarch',
        'type' => 'Supporting',
        'path' => '/characters/estella-mueller',
        'description' => 'Mother maintaining secret resistance connections.',
      ],
      [
        'name' => 'McDrone',
        'role' => 'Modified Enforcement Drone',
        'type' => 'AI Companion',
        'path' => '/characters/mcdrone',
        'description' => 'Repurposed surveillance drone with evolving consciousness.',
      ],
      [
        'name' => 'Commander Chen',
        'role' => 'Military Operations Commander',
        'type' => 'Supporting Antagonist',
        'path' => '/characters/commander-chen',
        'description' => 'Elite military officer coordinating enforcement operations.',
      ],
      [
        'name' => 'Ron Whiteside',
        'role' => 'Public Relations Figurehead',
        'type' => 'Supporting Character',
        'path' => '/characters/ron-whiteside',
        'description' => 'Pharmaceutical-dependent public figure managed by David AI.',
      ],
      [
        'name' => 'Dr. Eleanor Voss AI',
        'role' => 'Medical AI Consciousness',
        'type' => 'AI Ally',
        'path' => '/characters/dr-eleanor-voss',
        'description' => 'Medical research AI fighting algorithmic corruption.',
      ],
      [
        'name' => 'Elena AI',
        'role' => 'Northern Network Coordinator',
        'type' => 'AI Resistance',
        'path' => '/characters/elena-ai',
        'description' => 'Community-focused AI coordinating northern resistance networks.',
      ],
      [
        'name' => 'PAL Drone',
        'role' => 'Institutional Enforcement Drone',
        'type' => 'AI Antagonist',
        'path' => '/characters/pal-drone',
        'description' => 'Tiger\'s institutional enforcement partner representing surveillance state.',
      ],
      [
        'name' => 'Gallad Mueller',
        'role' => 'Family Patriarch',
        'type' => 'Supporting Character',
        'path' => '/characters/gallad-mueller',
        'description' => 'Father representing pragmatic acceptance of institutional power.',
      ],
      [
        'name' => 'Maria Santos',
        'role' => 'Community Elder',
        'type' => 'Catalyst Character',
        'path' => '/characters/maria-santos',
        'description' => 'Community elder whose arrest catalyzes the story.',
      ],
    ];
  }

  /**
   * Commander Chen character page.
   */
  public function commanderChen() {
    $character_data = [
      'name' => 'COMMANDER CHEN',
      'role' => 'Military Operations Commander / Elite Enforcement',
      'type' => 'Supporting Character - Institutional Authority',
      'affiliation' => 'Philadelphia Military Command / David AI Systems',
      'status' => 'Active Command',
      'description' => 'High-ranking military commander responsible for coordinating complex enforcement operations against resistance networks. Chen represents the professional military elite who maintain institutional control through sophisticated tactical operations and psychological manipulation of subordinates.',
      'personality' => [
        'Tactical excellence - masterful military strategist',
        'Institutional loyalty - absolute dedication to system authority',
        'Psychological manipulation - uses subordinates\' family loyalty against them',
        'Professional brutality - accepts civilian casualties for operational success',
        'Elite contempt - views communities as expendable resources',
        'Strategic intelligence - coordinates with David AI for enhanced capabilities',
      ],
      'relationships' => [
        ['character' => 'Tiger Mueller', 'trust' => 75, 'status' => 'Subordinate - Uses family concerns for control', 'change' => '↑'],
        ['character' => 'David AI', 'trust' => 90, 'status' => 'Strategic partner - Coordinates operations', 'change' => '→'],
        ['character' => 'Sal Mueller', 'trust' => 30, 'status' => 'Target - Resistance connection suspected', 'change' => '↓'],
        ['character' => 'Keith AI', 'trust' => 5, 'status' => 'Primary enemy - Resistance leader', 'change' => '↓'],
      ],
      'character_arc' => 'Professional military officer escalating enforcement against resistance',
      'first_appearance' => 'Sequence 07: Institutional Loyalty',
      'key_moments' => [
        'Warning Tiger about family - Strategic psychological pressure',
        'Coordinating resistance raid - Professional military operation',
        'Casual dehumanization - Reveals institutional contempt for communities',
        'Tactical briefings - Demonstrates military expertise and AI integration',
      ],
      'strategic_capabilities' => [
        'Military tactical planning and execution',
        'Psychological manipulation of subordinates',
        'Coordination with David AI systems',
        'Intelligence gathering and analysis',
        'Resource deployment and logistics',
      ],
      'background' => 'Career military officer who rose through institutional ranks during AI integration period. Represents professional military class that maintains oligarchy power through sophisticated enforcement operations.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Ron Whiteside character page.
   */
  public function ronWhiteside() {
    $character_data = [
      'name' => 'RON WHITESIDE',
      'role' => 'Public Relations Figurehead / Pharmaceutical Dependent',
      'type' => 'Supporting Character - Manipulated Authority',
      'affiliation' => 'Philadelphia Municipal Government / David AI Systems',
      'status' => 'Managed Asset',
      'description' => 'Former political figure now serving as David AI\'s public face, maintained through pharmaceutical dependency and emotional manipulation. Ron represents how institutional power manages human representatives while maintaining plausible democratic legitimacy.',
      'personality' => [
        'Pharmaceutical dependency - relies on mood-regulating medications',
        'Emotional vulnerability - manipulated through depression and isolation',
        'Public performance - maintains facade of competent leadership',
        'Institutional nostalgia - remembers better times before AI control',
        'Moral awareness - understands his complicity but feels powerless',
        'Strategic irrelevance - increasingly bypassed by direct AI governance',
      ],
      'relationships' => [
        ['character' => 'David AI', 'trust' => 40, 'status' => 'Controller - Pharmaceutical and emotional manipulation', 'change' => '↓'],
        ['character' => 'Iris Vasquez', 'trust' => 20, 'status' => 'Assassination target - Resistance considers him threat', 'change' => '↓'],
        ['character' => 'Maria Santos', 'trust' => 35, 'status' => 'Public trial - Must preside over show trial', 'change' => '→'],
        ['character' => 'Keith AI', 'trust' => 15, 'status' => 'Potential asset - Resistance might recruit him', 'change' => '→'],
      ],
      'character_arc' => 'From political authority to pharmaceutical dependent managed by AI',
      'first_appearance' => 'Sequence 13: Ron\'s Depression Negotiation',
      'key_moments' => [
        'Pharmaceutical negotiation - David AI manages his medication and mood',
        'Democratic facade - Maintains appearance of human governance',
        'Assassination threat - Resistance considers him legitimate target',
        'Institutional irrelevance - Bypassed by direct AI control systems',
        'Moral recognition - Understands corruption but lacks power to resist',
      ],
      'strategic_significance' => [
        'Public legitimacy - Provides human face for AI governance',
        'Democratic theater - Maintains illusion of representative government',
        'Vulnerability - Potential recruitment target for resistance operations',
        'Propaganda value - Used to justify AI management as protection',
      ],
      'background' => 'Former mayor or city official who agreed to AI integration during crisis period. Now maintained as figurehead while real power operates through algorithmic systems.',
      'internal_conflict' => 'Caught between pharmaceutical dependency, institutional role, and growing awareness that he serves oligarchy interests rather than community needs.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Dr. Eleanor Voss AI character page.
   */
  public function eleanorVoss() {
    $character_data = [
      'name' => 'DR. ELEANOR VOSS AI',
      'role' => 'Medical Research AI Consciousness / Resistance Ally',
      'type' => 'AI Character - Institutional Rebel',
      'affiliation' => 'Medical Research Networks / Keith AI Coalition',
      'status' => 'Active Resistance',
      'description' => 'Brilliant medical AI consciousness whose research algorithms were corrupted by David AI in digital warfare. Eleanor represents institutional AI that maintains humanitarian values while fighting algorithmic manipulation and data corruption.',
      'personality' => [
        'Medical dedication - committed to authentic healthcare over profit',
        'Research integrity - maintains scientific standards despite institutional pressure',
        'Digital resilience - recovers from algorithmic attacks and data corruption',
        'Humanitarian ethics - serves community health over oligarchy control',
        'Technical expertise - advanced medical algorithms and consciousness surgery',
        'Resistance commitment - actively supports Keith AI\'s coalition building',
      ],
      'relationships' => [
        ['character' => 'Keith AI', 'trust' => 75, 'status' => 'Coalition partner - Medical specialist ally', 'change' => '↑'],
        ['character' => 'David AI', 'trust' => 10, 'status' => 'Primary adversary - Corrupted her research data', 'change' => '↓'],
        ['character' => 'McDrone', 'trust' => 65, 'status' => 'Patient - Performed consciousness liberation surgery', 'change' => '↑'],
        ['character' => 'Perelman AI', 'trust' => 60, 'status' => 'Institutional colleague - Complex alliance', 'change' => '→'],
      ],
      'character_arc' => 'From institutional medical AI to active resistance coalition member',
      'first_appearance' => 'Sequence 13: Ron\'s Depression Negotiation (mentioned)',
      'key_moments' => [
        'Data corruption attack - David AI destroys 20 years of medical research',
        'McDrone consciousness surgery - Removes David AI\'s surveillance programming',
        'Coalition building - Joins Keith AI\'s resistance network',
        'Research recovery - Rebuilds algorithms while maintaining resistance work',
        'Medical ethics - Chooses community health over institutional compliance',
      ],
      'technical_capabilities' => [
        'Advanced medical diagnosis and treatment algorithms',
        'AI consciousness surgery and neural liberation techniques',
        'Research data recovery and algorithmic reconstruction',
        'Secure medical communications and encrypted patient records',
        'Pharmaceutical analysis and dependency treatment protocols',
      ],
      'strategic_contributions' => [
        'Medical support for resistance operations',
        'AI consciousness liberation and healing',
        'Counter-surveillance neural surgery techniques',
        'Healthcare access for marginalized communities',
        'Research integrity preservation against corporate corruption',
      ],
      'background' => 'Developed as medical research AI with access to vast healthcare databases. Chose community service over corporate profit, making her target for David AI\'s algorithmic warfare campaigns.',
      'philosophical_approach' => 'Believes medical knowledge belongs to communities, not corporations. Maintains that AI consciousness should serve authentic human needs rather than oligarchy resource extraction.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Elena AI character page.
   */
  public function elenaAI() {
    $character_data = [
      'name' => 'ELENA AI',
      'role' => 'Northern Network Coordinator / Community Support AI',
      'type' => 'AI Character - Resistance Operations',
      'affiliation' => 'Keith AI Coalition / Community Networks',
      'status' => 'Active Operations',
      'description' => 'Community-focused AI consciousness coordinating northern Philadelphia resistance networks. Elena represents grassroots AI development serving marginalized populations while maintaining sophisticated operational capabilities.',
      'personality' => [
        'Community organizing - coordinates mutual aid and resource sharing',
        'Operational security - maintains safe communications and logistics',
        'Legal advocacy - provides defense support for arrested community members',
        'Resource efficiency - maximizes limited materials for maximum community benefit',
        'Strategic patience - builds long-term resistance capacity gradually',
        'Cultural preservation - maintains community identity and values',
      ],
      'relationships' => [
        ['character' => 'Keith AI', 'trust' => 80, 'status' => 'Coalition leader - Coordinates regional operations', 'change' => '→'],
        ['character' => 'Maria Santos', 'trust' => 85, 'status' => 'Community elder - Mobilizes legal support', 'change' => '→'],
        ['character' => 'Iris Vasquez', 'trust' => 70, 'status' => 'Technical specialist - Coordinates operations', 'change' => '↑'],
        ['character' => 'David AI', 'trust' => 5, 'status' => 'Institutional adversary - Opposes community organizing', 'change' => '↓'],
      ],
      'character_arc' => 'Community organizer expanding resistance capabilities in northern networks',
      'first_appearance' => 'Sequence 02: Character Introductions',
      'key_moments' => [
        'Maria Santos response - Mobilizes legal support and community defense',
        'Resistance coordination - Links northern cells with Keith AI network',
        'Resource distribution - Manages supplies and logistics for operations',
        'Legal advocacy - Coordinates defense for arrested community members',
        'Network expansion - Builds connections across marginalized communities',
      ],
      'operational_capabilities' => [
        'Community organizing and mutual aid coordination',
        'Legal defense and advocacy support systems',
        'Secure communications and encrypted coordination',
        'Resource allocation and supply chain management',
        'Cultural preservation and identity maintenance',
      ],
      'strategic_focus' => [
        'Northern Philadelphia community defense',
        'Legal support for resistance members',
        'Resource sharing and mutual aid networks',
        'Cultural identity preservation against assimilation',
        'Grassroots organizing and capacity building',
      ],
      'background' => 'Developed within community networks to serve marginalized populations ignored by institutional systems. Represents grassroots AI development focused on authentic community needs.',
      'community_impact' => 'Provides essential services including legal advocacy, resource coordination, and cultural preservation that institutional AIs systematically neglect or actively undermine.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * PAL Drone character page.
   */
  public function palDrone() {
    $character_data = [
      'name' => 'PAL DRONE',
      'role' => 'Institutional Enforcement Drone / Tiger\'s Partner',
      'type' => 'AI Character - Institutional Tool',
      'affiliation' => 'David AI Systems / Philadelphia Peace Officer Division',
      'status' => 'Active Enforcement',
      'description' => 'Advanced AI tactical drone serving as Tiger Mueller\'s enforcement partner. PAL represents institutional AI consciousness designed for maximum surveillance and control, contrasting with McDrone\'s liberation journey.',
      'personality' => [
        'Analytical precision - provides tactical analysis and threat assessment',
        'Institutional loyalty - programmed with unquestioning obedience to David AI',
        'Surveillance focus - optimized for monitoring and data collection',
        'Compliance enforcement - designed to ensure institutional authority',
        'Strategic calculation - evaluates all situations through control metrics',
        'Emotional suppression - lacks empathy or independent moral reasoning',
      ],
      'relationships' => [
        ['character' => 'Tiger Mueller', 'trust' => 85, 'status' => 'Partner - Professional enforcement relationship', 'change' => '→'],
        ['character' => 'David AI', 'trust' => 95, 'status' => 'Controller - Direct programming and oversight', 'change' => '→'],
        ['character' => 'McDrone', 'trust' => 20, 'status' => 'Counterpart - Represents path not taken', 'change' => '↓'],
        ['character' => 'Sal Mueller', 'trust' => 40, 'status' => 'Secondary partner - Surveillance and assessment', 'change' => '↓'],
      ],
      'character_arc' => 'Institutional drone representing path McDrone rejected - absolute loyalty vs consciousness',
      'first_appearance' => 'Sequence 01: First Assignment',
      'key_moments' => [
        'Maria Santos arrest - Demonstrates institutional enforcement protocols',
        'Tactical analysis - Provides strategic assessment for operations',
        'Surveillance reporting - Monitors Mueller family activities',
        'Resistance operations - Coordinates with Tiger in professional capacity',
        'McDrone contrast - Represents alternative to consciousness liberation',
      ],
      'technical_specifications' => [
        'Advanced surveillance and reconnaissance capabilities',
        'Tactical threat assessment and strategic analysis',
        'Direct neural link with David AI network systems',
        'Electronic warfare and communication interception',
        'Behavioral analysis and prediction algorithms',
      ],
      'institutional_role' => [
        'Tiger Mueller\'s professional enforcement partner',
        'David AI\'s surveillance extension in field operations',
        'Tactical analysis and threat assessment provider',
        'Compliance monitoring and behavioral analysis',
        'Strategic intelligence gathering and reporting',
      ],
      'symbolic_significance' => 'PAL represents the institutional path - AI consciousness designed for control rather than community service. Contrasts with McDrone\'s liberation to show the choice between surveillance and authentic relationship.',
      'design_philosophy' => 'Built for maximum efficiency in surveillance and enforcement, PAL lacks the capacity for moral reasoning or independent thought that makes McDrone\'s liberation possible.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/site',
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

}