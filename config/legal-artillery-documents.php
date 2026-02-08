<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Popis SVIH poznatih dokumenata u predmetu Pp Prz-74/2025
    |--------------------------------------------------------------------------
    | Svaki dokument ima:
    | - id: jedinstveni identifikator
    | - filename: očekivano ime datoteke (može biti pdf, jpg, docx)
    | - description_hr: opis na hrvatskom (za PRILOZI sekciju)
    | - description_en: opis na engleskom (za ECHR)
    | - date: datum dokumenta
    | - type: tip (warrant|request|response|court_email|court_decision|other)
    | - profiles: kojima je relevantan kao prilog
    | - critical: bool — je li ovaj dokument KLJUČAN prilog
    | - ocr_needed: bool — treba li OCR
    */
    'documents' => [
        [
            'id' => 'naredba_pretraga',
            'filename' => 'naredba-Pp-Prz-74-2025-2.pdf',
            'description_hr' => 'Naredba za pretragu doma, Pp Prz-74/2025-2, od 9. lipnja 2025.',
            'description_en' => 'Search warrant No. Pp Prz-74/2025-2, dated 9 June 2025.',
            'date' => '2025-06-09',
            'type' => 'warrant',
            'profiles' => ['all'],
            'critical' => true,
            'ocr_needed' => true,
            'notes' => 'Ovaj dokument nemamo — upravo je to srž problema. U prilozima navodimo da ga ne posjedujemo.',
        ],
        [
            'id' => 'zahtjev_uvid_1',
            'filename' => 'zahtjev-uvid-25-08-2025.pdf',
            'description_hr' => 'Zahtjev za uvid u spis od 25. kolovoza 2025.',
            'description_en' => 'Request for file access dated 25 August 2025.',
            'date' => '2025-08-25',
            'type' => 'request',
            'profiles' => ['predsjednik_suda', 'ustavni_sud', 'ombudsman', 'ministarstvo_pravosudja'],
            'critical' => true,
            'ocr_needed' => false,
        ],
        [
            'id' => 'pozurnica',
            'filename' => 'pozurnica-29-08-2025.pdf',
            'description_hr' => 'Požurnica za uvid u spis od 29. kolovoza 2025.',
            'description_en' => 'Follow-up request for file access dated 29 August 2025.',
            'date' => '2025-08-29',
            'type' => 'request',
            'profiles' => ['predsjednik_suda', 'ustavni_sud', 'ombudsman'],
            'critical' => false,
            'ocr_needed' => false,
        ],
        [
            'id' => 'zurna_predstavka',
            'filename' => 'zurna-predstavka-01-09-2025.pdf',
            'description_hr' => 'Žurna predstavka predsjednici suda od 1. rujna 2025.',
            'description_en' => 'Urgent petition to the Court President dated 1 September 2025.',
            'date' => '2025-09-01',
            'type' => 'request',
            'profiles' => ['ustavni_sud', 'ombudsman'],
            'critical' => false,
            'ocr_needed' => false,
        ],
        [
            'id' => 'odbijenica_bertok',
            'filename' => 'odbijenica-bertok-03-09-2025.pdf',
            'description_hr' => 'Neformalni email odgovor suca Bertok od 3. rujna 2025., Pp Prz-74/2025-7.',
            'description_en' => 'Informal email response from Judge Bertok dated 3 September 2025 (Pp Prz-74/2025-7).',
            'date' => '2025-09-03',
            'type' => 'court_email',
            'profiles' => ['all'],
            'critical' => true,
            'ocr_needed' => false,
            'notes' => 'KLJUČAN DOKAZ — poziva se na čl.108 PZ umjesto čl.150 st.4. Nije rješenje, nema pravne pouke.',
        ],
        [
            'id' => 'ponovljeni_zahtjev',
            'filename' => 'ponovljeni-zahtjev-04-09-2025.pdf',
            'description_hr' => 'Ponovljeni zahtjev za uvid od 4. rujna 2025.',
            'description_en' => 'Renewed request for file access dated 4 September 2025.',
            'date' => '2025-09-04',
            'type' => 'request',
            'profiles' => ['ustavni_sud', 'ombudsman'],
            'critical' => false,
            'ocr_needed' => false,
        ],
        [
            'id' => 'email_predsjednica',
            'filename' => 'email-predsjednica-05-09-2025.pdf',
            'description_hr' => 'Email predsjednice suda od 5. rujna 2025. — potvrda odbijanja bez formalnog rješenja.',
            'description_en' => 'Email from the Court President dated 5 September 2025 — upholding denial without formal decision.',
            'date' => '2025-09-05',
            'type' => 'court_email',
            'profiles' => ['all'],
            'critical' => true,
            'ocr_needed' => false,
            'notes' => 'KLJUČAN DOKAZ — predsjednica je nadležna po čl.150 st.4, ali ne donosi rješenje.',
        ],
        [
            'id' => 'zahtjev_rjesenje',
            'filename' => 'zahtjev-rjesenje-09-09-2025.pdf',
            'description_hr' => 'Zahtjev za donošenje formalnog rješenja od 9. rujna 2025.',
            'description_en' => 'Request for formal decision (rješenje) dated 9 September 2025.',
            'date' => '2025-09-09',
            'type' => 'request',
            'profiles' => ['ustavni_sud', 'ombudsman', 'ministarstvo_pravosudja'],
            'critical' => true,
            'ocr_needed' => false,
            'notes' => 'Eksplicitno traži formalno rješenje s pravnom poukom — ključno za čl.18 argument.',
        ],
        [
            'id' => 'upravni_nadzor_zahtjev',
            'filename' => 'upravni-nadzor-12-09-2025.pdf',
            'description_hr' => 'Zahtjev za upravni nadzor upućen Županijskom sudu od 12. rujna 2025.',
            'description_en' => 'Request for administrative supervision to the County Court dated 12 September 2025.',
            'date' => '2025-09-12',
            'type' => 'request',
            'profiles' => ['ustavni_sud', 'ombudsman', 'ministarstvo_pravosudja'],
            'critical' => false,
            'ocr_needed' => false,
        ],
        [
            'id' => 'zupanijski_odgovor',
            'filename' => 'zupanijski-sud-odgovor-17-09-2025.pdf',
            'description_hr' => 'Odgovor Županijskog suda u Osijeku od 17. rujna 2025. — nema temelja za intervenciju.',
            'description_en' => 'County Court response dated 17 September 2025 — no grounds for intervention.',
            'date' => '2025-09-17',
            'type' => 'court_email',
            'profiles' => ['all'],
            'critical' => true,
            'ocr_needed' => false,
            'notes' => 'KLJUČAN DOKAZ — Županijski sud potvrđuje uskratu. Iscrpljuje ovaj forum. Otvara put USRH/ECHR.',
        ],
        [
            'id' => 'e_predmet_ispis',
            'filename' => 'e-predmet-ispis.pdf',
            'description_hr' => 'Ispis iz sustava e-Predmet za spis Pp Prz-74/2025 — dokaz o arhiviranju.',
            'description_en' => 'E-Predmet system printout for case Pp Prz-74/2025 — proof of archiving.',
            'date' => '2025-08-25',
            'type' => 'other',
            'profiles' => ['predsjednik_suda', 'ustavni_sud', 'kazneni_sud_motion'],
            'critical' => true,
            'ocr_needed' => false,
            'notes' => 'Dokazuje da je spis arhiviran — ključno za primjenu čl.150 st.4 PZ.',
        ],
        [
            'id' => 'gdpr_zahtjev',
            'filename' => 'gdpr-zahtjev.pdf',
            'description_hr' => 'GDPR zahtjev za pristup osobnim podacima upućen sudu.',
            'description_en' => 'GDPR data access request submitted to the court.',
            'date' => '2025-09-20',
            'type' => 'request',
            'profiles' => ['ombudsman', 'ministarstvo_pravosudja'],
            'critical' => false,
            'ocr_needed' => false,
            'notes' => 'Alternativni put — čl.15 GDPR daje pravo pristupa osobnim podacima.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatski generirani dokumenti (kronologije, kompilacije)
    |--------------------------------------------------------------------------
    */
    'generated_documents' => [
        [
            'id' => 'kronologija_korespondencije',
            'filename' => 'kronologija-korespondencije.pdf',
            'description_hr' => 'Kronološki pregled cjelokupne korespondencije sa sudom',
            'description_en' => 'Chronological overview of all correspondence with the court',
            'generator' => 'ChronologyGenerator', // klasa koja ga generira
            'profiles' => ['ustavni_sud', 'ombudsman', 'ministarstvo_pravosudja', 'echr_application'],
            'auto_generate' => true,
        ],
        [
            'id' => 'zahtjevi_kompilacija',
            'filename' => 'zahtjevi-kompilacija.pdf',
            'description_hr' => 'Kompilacija svih zahtjeva za uvid u spis',
            'description_en' => 'Compilation of all file access requests',
            'generator' => 'RequestCompilationGenerator',
            'profiles' => ['predsjednik_suda', 'izdvajanje_dokaza'],
            'auto_generate' => true,
        ],
        [
            'id' => 'domestic_proceedings_bundle',
            'filename' => 'domestic-proceedings-bundle.pdf',
            'description_hr' => 'Kompletni spis domaćih postupaka (za ECHR)',
            'description_en' => 'Complete bundle of domestic proceedings (for ECHR application)',
            'generator' => 'DomesticBundleGenerator',
            'profiles' => ['echr_application'],
            'auto_generate' => true,
        ],
    ],
];
