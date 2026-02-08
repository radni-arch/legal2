<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Grounding — citation coverage enforcement for document approval
    |--------------------------------------------------------------------------
    */
    'grounding' => [
        'min_citation_coverage' => env('LEGAL_ARTILLERY_MIN_CITATION_COVERAGE', 0.5),
        'min_quality_score' => env('LEGAL_ARTILLERY_MIN_QUALITY_SCORE', 5.0),
        'require_legal_basis' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Podnositelj (sender) — zajednicki za sve dopise
    |--------------------------------------------------------------------------
    */
    'sender' => [
        'name' => env('LEGAL_SENDER_NAME', 'Andrija Glavas'),
        'oib' => env('LEGAL_SENDER_OIB', ''),
        'address' => env('LEGAL_SENDER_ADDRESS', 'Primorska ul. 5, 31000 Osijek'),
        'email' => env('LEGAL_SENDER_EMAIL', ''),
        'phone' => env('LEGAL_SENDER_PHONE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Case context — podatci o predmetu Pp Prz-74/2025 + K-DO-731/2025
    |--------------------------------------------------------------------------
    */
    'case_context' => [
        // Prekrsajni predmet (naredba za pretragu)
        'case_number' => 'Pp Prz-74/2025',
        'criminal_case_number' => 'K-DO-731/2025',
        'search_date' => '2025-06-09',
        'archive_date' => '2025-07-10',
        'address_searched' => 'Primorska 5, 31000 Osijek',
        'warrant_reference' => 'Pp Prz-74/2025-2',
        'police_request_klasa' => 'NK-214-05/25-01/1155',
        'police_request_urbroj' => '511-07-11-25-2',
        'legal_basis_warrant' => 'PZ cl.159 st.1 toc.1 u vezi ZKP cl.240',
        'suspected_offense' => 'cl.54 st.3 Zakon o suzbijanju zlouporabe droga',
        'judge' => 'Dunja Bertok',
        'denial_date' => '2025-09-03',
        'county_court_response_date' => '2025-09-17',

        // Zaplijenjeni predmeti (za izdvajanje dokaza)
        'seized_items' => [
            ['item' => 'marihuana', 'quantity' => 'cca 20 g'],
            ['item' => 'hasis', 'quantity' => '10 g'],
            ['item' => 'MDMA', 'quantity' => '0,2 g'],
            ['item' => 'halucinogene gljive', 'quantity' => '6 g'],
            ['item' => 'puska (vojni karabin)', 'quantity' => '1 kom', 'note' => 'zadruzna ostavstina'],
        ],

        // Proceduralne nepravilnosti pretrage
        'procedural_violations' => [
            'timestamp_discrepancy' => [
                'official_start' => '11:00',
                'official_end' => '12:45',
                'actual_start' => '10:25',
                'actual_end' => '13:40',
                'evidence' => 'Poruke s suprugom u 10:22, foto meta-podaci',
            ],
            'k9_before_witnesses' => 'Policijski pas i vodic usli u stan prije pozivanja svjedoka, dok je okrivljenik bio vani',
            'no_voluntary_surrender' => 'Okrivljenik nije pozvan da dobrovoljno preda trazene predmete (ZKP cl.243 st.5)',
            'mystery_package' => [
                'description' => 'Policija spominjala paket adresiran na okrivljenika, dostavljen na Slavonska 8 (bivsa adresa)',
                'address' => 'Slavonska 8, Osijek',
                'note' => 'U naredbi nema spomena paketa. Kaznena prijava KP-DO-731/2025 govori o paketima s materijom nalik na drogu',
            ],
            'denied_file_access' => [
                'description' => 'Prekrsajni sud uskratio uvid u spis Pp Prz-74/2025 pozivajuci se na tajnost izvida',
                'no_formal_ruling' => true,
                'days_without_ruling' => '30+',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document profiles — svaki profil = jedan tip dopisa
    |--------------------------------------------------------------------------
    */
    'profiles' => [

        // 1. Predsjednik suda
        'predsjednik_suda' => [
            'name' => 'Zahtjev predsjedniku suda za uvid u spis',
            'recipient' => [
                'title' => 'Predsjednica Opcinskog suda u Osijeku',
                'institution' => 'Opcinski sud u Osijeku',
                'institution_type' => 'opcinski_sud',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'PZ cl.150 st.1 — opravdani interes',
                'PZ cl.150 st.4 — predsjednik suda odlucuje za zavrsen postupak',
                'Ustav cl.18 — pravo na zalbu',
                'Ustav cl.34 — nepovredivost doma',
            ],
            'tone' => 'formal_assertive',
            'structure' => ['heading', 'case_reference', 'identification', 'facts_chronology', 'legal_arguments', 'requests', 'legal_remedy_demand', 'signature'],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Zahtjev za uvid u spis {case_number} — PZ cl.150 st.4',
            'requires_attachments' => false,
            'metadata' => ['priority' => 'immediate', 'forum' => 'Opcinski sud Osijek'],
        ],

        // 2. DORH
        'dorh_production' => [
            'name' => 'Zahtjev DORH-u za pribavljanje spisa',
            'recipient' => [
                'title' => 'Opcinsko drzavno odvjetnistvo u Osijeku',
                'institution' => 'Drzavno odvjetnistvo',
                'institution_type' => 'dorh',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'ZKP cl.9 st.2 — duznost prikupljanja i oslobadajucih dokaza',
                'ZKP cl.184 — prava obrane na uvid',
                'ZKP cl.342 — bitna povreda postupka',
            ],
            'tone' => 'formal_assertive',
            'structure' => ['heading', 'case_reference', 'identification', 'connection_criminal_misdemeanor', 'legal_arguments', 'disclosure_demand', 'consequences_warning', 'signature'],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Zahtjev za pribavljanje prekrsajnog spisa {case_number}',
            'requires_attachments' => true,
            'metadata' => ['priority' => 'immediate', 'forum' => 'DORH Osijek'],
        ],

        // 3. Kazneni sud motion
        'kazneni_sud_motion' => [
            'name' => 'Prijedlog kaznenom sudu za pribavljanje spisa',
            'recipient' => [
                'title' => 'Opcinski kazneni sud u Osijeku',
                'institution' => 'Opcinski sud u Osijeku — kazneni odjel',
                'institution_type' => 'opcinski_sud',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'ZKP cl.183 — pravo obrane na razgledavanje spisa',
                'ZKP cl.184 st.5 — uvid u hitne radnje',
                'ZKP cl.10 — nezakoniti dokazi',
            ],
            'tone' => 'formal_assertive',
            'structure' => ['heading', 'case_reference', 'identification', 'motion_context', 'evidence_connection', 'legal_arguments', 'specific_requests', 'signature'],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Prijedlog za pribavljanje spisa {case_number}',
            'requires_attachments' => false,
            'metadata' => ['priority' => 'before_optuznica', 'forum' => 'Kazneni sud Osijek'],
        ],

        // 4. Ombudsman
        'ombudsman' => [
            'name' => 'Prituzba Puckom pravobranitelju',
            'recipient' => [
                'title' => 'Pucki pravobranitelj — Podrucni ured Osijek',
                'institution' => 'Ured Puckog pravobranitelja',
                'institution_type' => 'pucki_pravobranitelj',
                'address' => 'Hrvatske Republike 19/I, 31000 Osijek',
                'email' => null,
                'phone' => '+385 31 628 054',
            ],
            'legal_basis' => [
                'Zakon o puckom pravobranitelju cl.22',
                'Ustav cl.93 — pucki pravobranitelj',
                'Ocita zloupotreba ovlasti — odbijanje formalnog rjesenja',
            ],
            'tone' => 'formal_narrative',
            'structure' => ['heading', 'identification', 'narrative_chronology', 'rights_violations', 'specific_complaint', 'requested_action', 'signature', 'attachments_list'],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Prituzba — uskrata uvida u spis {case_number} bez formalnog rjesenja',
            'requires_attachments' => true,
            'metadata' => ['priority' => 'immediate', 'forum' => 'Pucki pravobranitelj'],
        ],

        // 5. Ministarstvo pravosudja
        'ministarstvo_pravosudja' => [
            'name' => 'Prituzba Ministarstvu pravosudja i uprave',
            'recipient' => [
                'title' => 'Ministarstvo pravosudja i uprave — Pravosudna inspekcija',
                'institution' => 'Ministarstvo pravosudja i uprave',
                'institution_type' => 'ministarstvo_pravosudja',
                'address' => 'Ulica grada Vukovara 49, 10000 Zagreb',
                'email' => null,
            ],
            'legal_basis' => [
                'Zakon o sudovima cl.72 st.6 — upravni nadzor',
                'Ustav cl.18 — pravo na zalbu',
            ],
            'tone' => 'formal_administrative',
            'structure' => ['heading', 'identification', 'subject_complaint', 'facts_chronology', 'administrative_irregularities', 'requested_measures', 'signature', 'attachments_list'],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Prituzba na rad Opcinskog suda u Osijeku — spis {case_number}',
            'requires_attachments' => true,
            'metadata' => ['priority' => 'immediate', 'forum' => 'Ministarstvo pravosudja'],
        ],

        // 6. Ustavni sud
        'ustavni_sud' => [
            'name' => 'Ustavna tuzba — cl.62 iznimka',
            'recipient' => [
                'title' => 'Ustavni sud Republike Hrvatske',
                'institution' => 'Ustavni sud RH',
                'institution_type' => 'ustavni_sud',
                'address' => 'Trg svetog Marka 4, 10000 Zagreb',
                'email' => null,
            ],
            'legal_basis' => [
                'Ustavni zakon cl.62 — grubo vrijedjanje ustavnih prava',
                'Ustav cl.18 — pravo na zalbu',
                'Ustav cl.19 — sudska kontrola zakonitosti',
                'Ustav cl.29 — pravicno sudjenje',
                'Ustav cl.34 — nepovredivost doma',
            ],
            'tone' => 'formal_constitutional',
            'structure' => ['heading_constitutional', 'identification', 'challenged_acts', 'constitutional_provisions_violated', 'factual_background', 'constitutional_arguments', 'article_62_justification', 'proposed_measures', 'signature', 'attachments_list'],
            'docx_template' => 'legal-constitutional',
            'email_subject_template' => 'Ustavna tuzba — {case_number} — cl.62 Ustavnog zakona',
            'requires_attachments' => true,
            'metadata' => ['priority' => 'within_30_days', 'forum' => 'Ustavni sud RH', 'deadline_note' => '30 dana od zadnje odluke ili odmah pod cl.62'],
        ],

        // 7. Izdvajanje dokaza — "Teski Gustav"
        'izdvajanje_dokaza' => [
            'name' => 'Prijedlog za izdvajanje nezakonito pribavljenih dokaza',
            'recipient' => [
                'title' => 'Zupanijski sud u Osijeku',
                'institution' => 'Zupanijski sud u Osijeku',
                'institution_type' => 'zupanijski_sud',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                // Osnova za izdvajanje (ZKP)
                'ZKP cl.9 — nacelo zakonitosti dokaza',
                'ZKP cl.10 st.2 toc.2 — povreda prava obrane',
                'ZKP cl.10 st.2 toc.3 — bitna povreda postupka',
                'ZKP cl.183 — pravo obrane na razgledavanje spisa',
                'ZKP cl.184 st.5 — uvid u hitne radnje nakon 30 dana',
                'ZKP cl.243 — uvjeti za pretragu doma',
                'ZKP cl.244 — svjedoci pri pretrazi',
                'ZKP cl.245 — postupak pretrazivanja',
                'ZKP cl.246 st.1 — nezakonit dokaz zbog bitne povrede',
                'ZKP cl.247 — izdvajanje nezakonitog dokaza iz spisa',
                // Ustav
                'Ustav cl.29 st.2 — pravo na upoznavanje s dokazima',
                'Ustav cl.34 — nepovredivost doma',
                // ZSZD
                'ZSZD cl.54 st.3 — prekrsajne odredbe za posjedovanje',
                // PZ
                'PZ cl.150 st.3 — pravo uvida u zavrseni postupak',
                'PZ cl.152 — prava stranke nakon arhiviranja',
                // EKLJP
                'EKLJP cl.8 — pravo na postovanje privatnog i obiteljskog zivota i doma',
            ],
            'tone' => 'formal_aggressive',
            'structure' => [
                'heading',
                'case_reference',
                'identification',
                'introductory_paragraph_zkp_247',
                'facts_chronology',
                'ground_1_warrant_deficiency',
                'ground_2_illegal_search_execution',
                'ground_3_denied_defense_rights',
                'constitutional_dimension',
                'echr_dimension',
                'evidence_list_for_exclusion',
                'specific_request',
                'signature',
                'attachments_list',
            ],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Prijedlog za izdvajanje nezakonito pribavljenih dokaza — {criminal_case_number}',
            'requires_attachments' => true,
            'metadata' => [
                'priority' => 'at_optuzno_vijece',
                'forum' => 'Zupanijski sud Osijek',
                'codename' => 'teski_gustav',
            ],
        ],

        // 8. ECHR Application
        'echr_application' => [
            'name' => 'ECHR Application',
            'recipient' => [
                'title' => 'European Court of Human Rights',
                'institution' => 'ECHR / ESLJP',
                'institution_type' => 'echr',
                'address' => 'Council of Europe, 67075 Strasbourg Cedex, France',
                'email' => null,
            ],
            'legal_basis' => [
                'ECHR Article 6 — Right to a fair trial',
                'ECHR Article 8 — Right to respect for private and family life, home',
                'ECHR Article 13 — Right to an effective remedy',
                'ECHR Article 34 — Individual applications',
            ],
            'tone' => 'formal_international',
            'structure' => ['echr_header', 'applicant_details', 'respondent_state', 'statement_of_facts', 'domestic_proceedings', 'alleged_violations', 'article_6_arguments', 'article_8_arguments', 'article_13_arguments', 'exhaustion_of_remedies', 'timeliness', 'relief_sought', 'declaration', 'signature', 'annexes'],
            'docx_template' => 'echr-application',
            'email_subject_template' => null,
            'requires_attachments' => true,
            'metadata' => ['priority' => 'after_domestic_exhaustion', 'forum' => 'ECHR Strasbourg', 'deadline_note' => '4 mjeseca od zadnje domace odluke', 'language' => 'en'],
        ],

        /*
        |--------------------------------------------------------------------------
        | Scenario profiles — Pp Prz-74/2025 escalation barrage
        |--------------------------------------------------------------------------
        */

        // 1. Uvid u spis — inicijalni zahtjev
        'uvid_spis_initial' => [
            'name' => 'Zahtjev za uvid u spis (inicijalni)',
            'recipient' => [
                'title' => 'Predsjednica Opcinskog suda u Osijeku',
                'institution' => 'Opcinski sud u Osijeku',
                'institution_type' => 'opcinski_sud',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'PZ cl.150 st.1 — opravdani interes za uvid u spis',
                'PZ cl.150 st.4 — predsjednik suda odlucuje u zavrsenom postupku',
                'Ustav cl.18 — pravo na zalbu',
                'Ustav cl.29 — pravo na obranu i razgledavanje spisa',
            ],
            'tone' => 'formal_assertive',
            'structure' => ['heading', 'case_reference', 'identification', 'request_access', 'facts_chronology', 'legal_arguments', 'requests', 'signature'],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Zahtjev za uvid u spis {case_number} — inicijalni',
            'requires_attachments' => false,
            'metadata' => ['priority' => 'initial', 'forum' => 'Opcinski sud Osijek', 'scenario_step' => 1],
        ],

        // 2. Pozurnica
        'pozurnica' => [
            'name' => 'Pozurnica za uvid u spis',
            'recipient' => [
                'title' => 'Predsjednica Opcinskog suda u Osijeku',
                'institution' => 'Opcinski sud u Osijeku',
                'institution_type' => 'opcinski_sud',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'PZ cl.150 st.4 — predsjednik suda odlucuje u zavrsenom postupku',
                'ZUP cl.48 — zahtjev stranke i duznost postupanja',
                'Ustav cl.18 — pravo na zalbu',
            ],
            'tone' => 'formal_assertive',
            'structure' => ['heading', 'case_reference', 'identification', 'reference_prior_request', 'delay_notice', 'requests', 'signature'],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Pozurnica — uvid u spis {case_number}',
            'requires_attachments' => true,
            'metadata' => ['priority' => 'follow_up', 'forum' => 'Opcinski sud Osijek', 'scenario_step' => 2],
        ],

        // 3. Zurna predstavka predsjednici
        'zurna_predstavka_predsjednici' => [
            'name' => 'Zurna predstavka predsjednici suda',
            'recipient' => [
                'title' => 'Predsjednica Opcinskog suda u Osijeku',
                'institution' => 'Opcinski sud u Osijeku',
                'institution_type' => 'opcinski_sud',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'PZ cl.150 st.4 — nadleznost predsjednice suda',
                'Ustav cl.18 — pravo na zalbu i pravni lijek',
                'Ustav cl.29 — pravo na obranu',
            ],
            'tone' => 'formal_assertive',
            'structure' => ['heading', 'case_reference', 'identification', 'prior_steps_summary', 'urgency_basis', 'requests', 'signature', 'attachments_list'],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Zurna predstavka — uvid u spis {case_number}',
            'requires_attachments' => true,
            'metadata' => ['priority' => 'urgent', 'forum' => 'Opcinski sud Osijek', 'scenario_step' => 3],
        ],

        // 4. Ponovljeni zahtjev
        'ponovljeni_zahtjev' => [
            'name' => 'Ponovljeni zahtjev za uvid u spis',
            'recipient' => [
                'title' => 'Predsjednica Opcinskog suda u Osijeku',
                'institution' => 'Opcinski sud u Osijeku',
                'institution_type' => 'opcinski_sud',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'PZ cl.150 st.4 — ovlast predsjednika suda u zavrsenom postupku',
                'Ustav cl.18 — pravo na zalbu',
                'Ustav cl.29 — pravo na obranu i razgledavanje spisa',
            ],
            'tone' => 'formal_assertive',
            'structure' => ['heading', 'case_reference', 'identification', 'prior_steps_summary', 'requests', 'signature', 'attachments_list'],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Ponovljeni zahtjev — uvid u spis {case_number}',
            'requires_attachments' => true,
            'metadata' => ['priority' => 'follow_up', 'forum' => 'Opcinski sud Osijek', 'scenario_step' => 4],
        ],

        // 5. Podsjetnik / dopuna
        'podsjetnik_dopuna' => [
            'name' => 'Podsjetnik i dopuna zahtjeva (rjesenje)',
            'recipient' => [
                'title' => 'Predsjednica Opcinskog suda u Osijeku',
                'institution' => 'Opcinski sud u Osijeku',
                'institution_type' => 'opcinski_sud',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'PZ cl.150 st.4 — odluka predsjednice u zavrsenom postupku',
                'ZUP cl.98 — obveza donosenja rjesenja',
                'Ustav cl.18 — pravo na zalbu i pravnu pouku',
            ],
            'tone' => 'formal_administrative',
            'structure' => ['heading', 'case_reference', 'identification', 'prior_steps_summary', 'formal_decision_request', 'requests', 'signature', 'attachments_list'],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Podsjetnik i dopuna — zahtjev za rjesenje {case_number}',
            'requires_attachments' => true,
            'metadata' => ['priority' => 'urgent', 'forum' => 'Opcinski sud Osijek', 'scenario_step' => 5],
        ],

        // 6. Zahtjev za formalno rjesenje
        'zahtjev_rjesenje' => [
            'name' => 'Zahtjev za donosenje formalnog rjesenja',
            'recipient' => [
                'title' => 'Predsjednica Opcinskog suda u Osijeku',
                'institution' => 'Opcinski sud u Osijeku',
                'institution_type' => 'opcinski_sud',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'ZUP cl.98 — obvezno rjesenje uz pravnu pouku',
                'Ustav cl.18 — pravo na pravni lijek',
                'PZ cl.150 st.4 — predsjednik suda odlucuje',
            ],
            'tone' => 'formal_administrative',
            'structure' => ['heading', 'case_reference', 'identification', 'prior_steps_summary', 'legal_arguments', 'formal_decision_request', 'signature', 'attachments_list'],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Zahtjev za formalno rjesenje — {case_number}',
            'requires_attachments' => true,
            'metadata' => ['priority' => 'urgent', 'forum' => 'Opcinski sud Osijek', 'scenario_step' => 6],
        ],

        // 7. Upravni nadzor
        'upravni_nadzor' => [
            'name' => 'Zahtjev za upravni nadzor',
            'recipient' => [
                'title' => 'Predsjednik Zupanijskog suda u Osijeku',
                'institution' => 'Zupanijski sud u Osijeku',
                'institution_type' => 'zupanijski_sud',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'Zakon o sudovima cl.72 st.6 — upravni nadzor',
                'Ustav cl.18 — pravo na zalbu',
                'Ustav cl.19 — sudska kontrola zakonitosti',
            ],
            'tone' => 'formal_administrative',
            'structure' => ['heading', 'case_reference', 'identification', 'facts_chronology', 'administrative_irregularities', 'requested_measures', 'signature', 'attachments_list'],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Zahtjev za upravni nadzor — spis {case_number}',
            'requires_attachments' => true,
            'metadata' => ['priority' => 'escalation', 'forum' => 'Zupanijski sud Osijek', 'scenario_step' => 7],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scenario profile sets — brza selekcija profila po scenariju
    |--------------------------------------------------------------------------
    */
    'profile_sets' => [
        'pp_prz_74_2025' => [
            'predsjednik_suda',
            'dorh_production',
            'kazneni_sud_motion',
            'ombudsman',
            'ministarstvo_pravosudja',
            'ustavni_sud',
            'izdvajanje_dokaza',
            'echr_application',
        ],
        'pp_prz_74_2025_scenario' => [
            'uvid_spis_initial',
            'pozurnica',
            'zurna_predstavka_predsjednici',
            'ponovljeni_zahtjev',
            'podsjetnik_dopuna',
            'zahtjev_rjesenje',
            'upravni_nadzor',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | E-komunikacija — mapiranje profila na tip dokumenta
    |--------------------------------------------------------------------------
    */
    'document_type_map' => [
        'predsjednik_suda' => 'ZAHTJEV',
        'dorh_production' => 'ZAHTJEV_DORH',
        'kazneni_sud_motion' => 'PRIJEDLOG',
        'izdvajanje_dokaza' => 'PRIJEDLOG_IZDVAJANJE',
        'ustavni_sud' => 'USTAVNA_TUZBA',
        'ombudsman' => 'PRITUZBA',
        'ministarstvo_pravosudja' => 'PRITUZBA_NADZOR',
        'echr_application' => 'ECHR_APPLICATION',
        'uvid_spis_initial' => 'ZAHTJEV',
        'pozurnica' => 'ZAHTJEV',
        'zurna_predstavka_predsjednici' => 'ZAHTJEV',
        'ponovljeni_zahtjev' => 'ZAHTJEV',
        'podsjetnik_dopuna' => 'ZAHTJEV',
        'zahtjev_rjesenje' => 'ZAHTJEV',
        'upravni_nadzor' => 'PRITUZBA_NADZOR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Escalation profiles — explicit confirmation required before generation
    |--------------------------------------------------------------------------
    */
    'escalation_profiles' => [
        'ombudsman',
        'ministarstvo_pravosudja',
        'ustavni_sud',
        'echr_application',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tone definitions
    |--------------------------------------------------------------------------
    */
    'tones' => [
        'formal_assertive' => [
            'system_instruction' => 'Pisi formalno, jasno i asertivno. Citiraj pravne odredbe precizno. Koristi imperative ("zahtijevam", "trazim", "pozivam se na"). Izbjegavaj nepotrebnu ljubaznost — budi direktan ali profesionalan.',
            'language' => 'hr',
        ],
        'formal_narrative' => [
            'system_instruction' => 'Pisi formalno ali narativno. Kronoloski izlozi cinjenice. Naglasi ljudsku dimenziju — prava pojedinca nasuprot institucijama. Budi precizan ali i empatican.',
            'language' => 'hr',
        ],
        'formal_administrative' => [
            'system_instruction' => 'Pisi suhoparno i administrativno. Citiraj propise i irregularnosti. Fokusiraj se na sistemske nedostatke u radu suda. Ton: neutralan, cinjenicni, birokratski precizan.',
            'language' => 'hr',
        ],
        'formal_constitutional' => [
            'system_instruction' => 'Pisi uzvyseno i ustavnopravno. Naglasi fundamentalna prava i ustavne garancije. Pozivaj se na ustavnosudsku praksu. Ton: ozbiljan, principijelan, drzavnicki.',
            'language' => 'hr',
        ],
        'formal_aggressive' => [
            'system_instruction' => 'Pisi ostro i argumentirano. Napadaj pravne pogreske suprotne strane. Koristi jaku pravnu argumentaciju. Ne budi nepristojan, ali budi neumoljivno precizan i nemilosrdan u analizi.',
            'language' => 'hr',
        ],
        'formal_international' => [
            'system_instruction' => 'Write in formal international legal English. Follow ECHR application structure. Reference ECHR case law precisely. Maintain measured, objective tone appropriate for international tribunal.',
            'language' => 'en',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gmail slanje
    |--------------------------------------------------------------------------
    */
    'gmail' => [
        'enabled' => env('LEGAL_GMAIL_ENABLED', false),
        'credentials_path' => env('LEGAL_GMAIL_CREDENTIALS', storage_path('app/google/credentials.json')),
        'token_path' => env('LEGAL_GMAIL_TOKEN', storage_path('app/google/token.json')),
        'from_name' => env('LEGAL_SENDER_NAME', 'Andrija Glavas'),
        'from_email' => env('LEGAL_SENDER_EMAIL', ''),
        'cc' => env('LEGAL_GMAIL_CC', null),
        'save_sent' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generiranje dokumenata
    |--------------------------------------------------------------------------
    */
    'generation' => [
        'provider' => env('LEGAL_LLM_PROVIDER', 'anthropic'),
        'model' => env('LEGAL_LLM_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => env('LEGAL_LLM_MAX_TOKENS', 8192),
        'recursion_depth' => env('LEGAL_RECURSION_DEPTH', 3),
        'output_dir' => storage_path('app/legal-artillery/generated'),
        'archive_sent' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost control — token budget and spending limits per run
    |--------------------------------------------------------------------------
    */
    'cost' => [
        'token_budget_per_run' => env('LEGAL_ARTILLERY_TOKEN_BUDGET', 100000),
        'max_cost_per_run_usd' => env('LEGAL_ARTILLERY_MAX_COST', 5.00),
        'warn_at_percentage' => 80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention — archive and cleanup policy for document generation runs
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'archive_days' => env('LEGAL_ARTILLERY_ARCHIVE_DAYS', 365),
        'cleanup_failed_days' => env('LEGAL_ARTILLERY_CLEANUP_FAILED_DAYS', 30),
        'archive_path' => storage_path('app/legal-artillery/archive'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Template configuration — court-specific document templates
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'directory' => resource_path('legal-artillery/templates'),
        'court_mapping' => [
            'opcinski_sud' => 'general/legal-formal',
            'zupanijski_sud' => 'general/legal-formal',
            'vrhovni_sud' => 'general/legal-formal',
            'ustavni_sud' => 'constitutional/legal-constitutional',
            'echr' => 'echr/echr-application',
            'dorh' => 'general/legal-formal',
            'pucki_pravobranitelj' => 'general/legal-formal',
            'ministarstvo_pravosudja' => 'general/legal-formal',
        ],
        'default' => 'general/legal-formal',
    ],

];
