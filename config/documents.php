<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Document Type Definitions
    |--------------------------------------------------------------------------
    |
    | Define all supported legal document types with their Croatian names,
    | required context, structure, and legal frameworks.
    |
    */

    'types' => [
        'suppression_motion' => [
            'display_name' => 'Prijedlog za isključenje dokaza',
            'category' => 'court_motion',
            'template' => 'suppression_motion_template',
            'required_context' => ['case_facts', 'evidence', 'legal_grounds'],
            'legal_framework' => ['ZKP', 'Ustav RH Članak 29, 34, 36'],
            'default_structure' => [
                'introduction',
                'factual_background',
                'legal_grounds',
                'constitutional_violations',
                'procedural_violations',
                'conclusion',
                'signature',
            ],
            'max_length' => 5000,
            'confidential' => false,
        ],

        'dismissal_motion' => [
            'display_name' => 'Zahtjev za odbacivanje optužnice',
            'category' => 'court_motion',
            'template' => 'dismissal_motion_template',
            'required_context' => ['case_facts', 'charges'],
            'legal_framework' => ['ZKP Članak 284', 'Ustav RH'],
            'default_structure' => [
                'introduction',
                'grounds_for_dismissal',
                'legal_analysis',
                'conclusion',
                'signature',
            ],
            'max_length' => 4000,
            'confidential' => false,
        ],

        'appeal_brief' => [
            'display_name' => 'Žalba',
            'category' => 'court_motion',
            'template' => 'appeal_template',
            'required_context' => ['verdict', 'appeal_grounds', 'trial_record'],
            'legal_framework' => ['ZKP Članak 469-485'],
            'default_structure' => [
                'introduction',
                'procedural_history',
                'grounds_for_appeal',
                'legal_analysis',
                'requested_relief',
                'conclusion',
                'signature',
            ],
            'max_length' => 10000,
            'confidential' => false,
        ],

        'client_letter' => [
            'display_name' => 'Pismo klijentu',
            'category' => 'client_communication',
            'template' => 'client_letter_template',
            'required_context' => ['case_update', 'next_steps'],
            'legal_framework' => null,
            'default_structure' => [
                'greeting',
                'case_update',
                'legal_analysis',
                'next_steps',
                'contact_information',
                'closing',
            ],
            'max_length' => 2000,
            'confidential' => true,
        ],

        'case_summary' => [
            'display_name' => 'Sažetak predmeta',
            'category' => 'client_communication',
            'template' => 'case_summary_template',
            'required_context' => ['case_facts', 'charges', 'strategy'],
            'legal_framework' => null,
            'default_structure' => [
                'overview',
                'charges',
                'key_facts',
                'defense_theory',
                'timeline',
                'risks_opportunities',
            ],
            'max_length' => 3000,
            'confidential' => true,
        ],

        'legal_opinion' => [
            'display_name' => 'Pravno mišljenje',
            'category' => 'client_communication',
            'template' => 'legal_opinion_template',
            'required_context' => ['legal_question', 'relevant_facts'],
            'legal_framework' => ['applicable_laws'],
            'default_structure' => [
                'question_presented',
                'short_answer',
                'facts',
                'legal_analysis',
                'conclusion',
            ],
            'max_length' => 4000,
            'confidential' => true,
        ],

        'case_strategy' => [
            'display_name' => 'Obrambena strategija',
            'category' => 'internal',
            'template' => 'strategy_template',
            'required_context' => ['case_facts', 'charges', 'evidence'],
            'legal_framework' => null,
            'default_structure' => [
                'case_overview',
                'strengths_weaknesses',
                'defense_theory',
                'evidence_strategy',
                'witness_strategy',
                'motion_plan',
                'trial_strategy',
                'risks_opportunities',
            ],
            'max_length' => 5000,
            'confidential' => true,
        ],

        'evidence_analysis' => [
            'display_name' => 'Analiza dokaza',
            'category' => 'internal',
            'template' => 'evidence_analysis_template',
            'required_context' => ['evidence'],
            'legal_framework' => ['ZKP Članak 331', 'Ustav RH'],
            'default_structure' => [
                'evidence_inventory',
                'admissibility_analysis',
                'credibility_assessment',
                'suppression_opportunities',
                'recontextualization_opportunities',
                'recommendations',
            ],
            'max_length' => 4000,
            'confidential' => true,
        ],

        'witness_preparation' => [
            'display_name' => 'Priprema svjedoka',
            'category' => 'internal',
            'template' => 'witness_prep_template',
            'required_context' => ['witness_info', 'testimony_topics'],
            'legal_framework' => null,
            'default_structure' => [
                'witness_overview',
                'key_testimony_points',
                'anticipated_questions',
                'preparation_strategy',
                'credibility_concerns',
                'dos_and_donts',
            ],
            'max_length' => 3000,
            'confidential' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring Weights
    |--------------------------------------------------------------------------
    |
    | Weights for multi-dimensional document quality scoring.
    | Must sum to 1.0 (100%)
    |
    */

    'scoring_weights' => [
        'legal_rigor' => 0.40,         // 40% - Croatian law citations, procedural correctness
        'persuasiveness' => 0.25,       // 25% - Argument strength, rhetoric
        'clarity' => 0.20,              // 20% - Readability, organization
        'evidence_integration' => 0.10, // 10% - Evidence weaving
        'formatting' => 0.05,           // 5% - Croatian legal format
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation Constraints
    |--------------------------------------------------------------------------
    */

    'max_iterations' => 10,
    'convergence_threshold' => 5.0, // Stop if improvement < 5%
    'min_acceptable_score' => 70.0,
];
