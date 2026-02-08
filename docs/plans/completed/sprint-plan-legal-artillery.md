# Pravna Artiljerija — Sprint Plan: RecursiveDocumentWritingAgent Modernizacija

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Cilj:** Modernizirati `RecursiveDocumentWritingAgent` u konfigurabilan sustav za strojno generiranje i slanje pravnih dopisa na sve relevantne forume — od predsjednika suda do Ustavnog suda i ECHR-a. Sustav generira dokumente rekurzivno (outline → sekcije → finalni dokument), formatira ih kao .docx, i raspalijuje direktno putem Gmail API-ja.

**Arhitektura:** Agent koristi Vizra ADK framework za orkestaciju. Konfigurabilan DocumentProfile definira primatelja, pravni temelj, strukturu, ton i format za svaki tip dopisa. Pipeline: (1) LLM generira sadržaj rekurzivno, (2) DocxRenderer renderira u Word, (3) GmailDispatcher šalje na adresu. Sve pokretano preko Artisan komandi.

**Tech Stack:** Laravel 11, PHP 8.3, Vizra ADK, Claude API (Anthropic), docx-js (Node), Google Gmail API (OAuth2), PostgreSQL, PHPUnit/Pest

---

## Faza 1: Temelj — DocumentProfile Registar

### Task 1: Definiraj DocumentProfile konfiguraciju

**Opis:** Svaki tip pravnog dopisa ima svoj "profil" — tko je primatelj, koji pravni temelji, kakva struktura, kakav ton. Umjesto hardkodiranih tipova, koristimo config-driven pristup.

**Files:**
- Create: `config/legal-artillery.php`
- Create: `app/DTOs/DocumentProfile.php`
- Test: `tests/Unit/DTOs/DocumentProfileTest.php`

**Step 1: Napiši padajući test**

```php
// tests/Unit/DTOs/DocumentProfileTest.php
<?php

namespace Tests\Unit\DTOs;

use App\DTOs\DocumentProfile;
use PHPUnit\Framework\TestCase;

class DocumentProfileTest extends TestCase
{
    public function test_creates_profile_from_config_array(): void
    {
        $config = [
            'key' => 'predsjednik_suda',
            'name' => 'Zahtjev predsjedniku suda',
            'recipient' => [
                'title' => 'Predsjednica Općinskog suda u Osijeku',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => ['PZ čl.150 st.4', 'Ustav čl.18'],
            'tone' => 'formal_assertive',
            'structure' => [
                'heading',
                'identification',
                'facts',
                'legal_arguments',
                'requests',
                'signature',
            ],
            'docx_template' => 'legal-formal',
            'requires_attachments' => false,
        ];

        $profile = DocumentProfile::fromArray($config);

        $this->assertEquals('predsjednik_suda', $profile->key);
        $this->assertEquals('Zahtjev predsjedniku suda', $profile->name);
        $this->assertEquals('formal_assertive', $profile->tone);
        $this->assertCount(2, $profile->legalBasis);
        $this->assertCount(6, $profile->structure);
    }

    public function test_loads_profile_from_config_by_key(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');

        $this->assertInstanceOf(DocumentProfile::class, $profile);
        $this->assertEquals('predsjednik_suda', $profile->key);
    }

    public function test_throws_on_unknown_profile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DocumentProfile::fromConfig('nepostojeci_profil');
    }

    public function test_all_profiles_returns_collection(): void
    {
        $profiles = DocumentProfile::all();

        $this->assertNotEmpty($profiles);
        $this->assertContainsOnlyInstancesOf(DocumentProfile::class, $profiles);
    }
}
```

**Step 2: Pokreni test — FAIL**

```bash
php artisan test tests/Unit/DTOs/DocumentProfileTest.php --verbose
```
Expected: FAIL — klasa ne postoji.

**Step 3: Implementiraj DocumentProfile DTO**

```php
// app/DTOs/DocumentProfile.php
<?php

namespace App\DTOs;

use InvalidArgumentException;

class DocumentProfile
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly array $recipient,
        public readonly array $legalBasis,
        public readonly string $tone,
        public readonly array $structure,
        public readonly string $docxTemplate,
        public readonly bool $requiresAttachments = false,
        public readonly ?string $emailSubjectTemplate = null,
        public readonly array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            name: $data['name'],
            recipient: $data['recipient'],
            legalBasis: $data['legal_basis'] ?? [],
            tone: $data['tone'] ?? 'formal',
            structure: $data['structure'] ?? [],
            docxTemplate: $data['docx_template'] ?? 'legal-formal',
            requiresAttachments: $data['requires_attachments'] ?? false,
            emailSubjectTemplate: $data['email_subject_template'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public static function fromConfig(string $key): self
    {
        $profiles = config('legal-artillery.profiles', []);

        if (!isset($profiles[$key])) {
            throw new InvalidArgumentException("Document profile '{$key}' not found in config.");
        }

        return self::fromArray(array_merge(['key' => $key], $profiles[$key]));
    }

    public static function all(): array
    {
        $profiles = config('legal-artillery.profiles', []);

        return array_map(
            fn(string $key, array $data) => self::fromArray(array_merge(['key' => $key], $data)),
            array_keys($profiles),
            array_values($profiles),
        );
    }

    public function recipientLine(): string
    {
        return implode("\n", array_filter([
            $this->recipient['title'] ?? null,
            $this->recipient['address'] ?? null,
        ]));
    }
}
```

**Step 4: Kreiraj konfiguraciju sa svim profilima**

```php
// config/legal-artillery.php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Podnositelj (sender) — zajednički za sve dopise
    |--------------------------------------------------------------------------
    */
    'sender' => [
        'name' => env('LEGAL_SENDER_NAME', 'Andrija Glavaš'),
        'oib' => env('LEGAL_SENDER_OIB', ''),
        'address' => env('LEGAL_SENDER_ADDRESS', 'Primorska ul. 5, 31000 Osijek'),
        'email' => env('LEGAL_SENDER_EMAIL', ''),
        'phone' => env('LEGAL_SENDER_PHONE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Case context — podatci o predmetu Pp Prz-74/2025
    |--------------------------------------------------------------------------
    */
    'case_context' => [
        'case_number' => 'Pp Prz-74/2025',
        'search_date' => '2025-06-09',
        'archive_date' => '2025-07-10',
        'address_searched' => 'Primorska 5, 31000 Osijek',
        'warrant_reference' => 'Pp Prz-74/2025-2',
        'police_request_klasa' => 'NK-214-05/25-01/1155',
        'police_request_urbroj' => '511-07-11-25-2',
        'legal_basis_warrant' => 'PZ čl.159 st.1 toč.1 u vezi ZKP čl.240',
        'suspected_offense' => 'čl.54 st.3 Zakon o suzbijanju zlouporabe droga',
        'judge' => 'Dunja Bertok',
        'denial_date' => '2025-09-03',
        'county_court_response_date' => '2025-09-17',
    ],

    /*
    |--------------------------------------------------------------------------
    | Document profiles — svaki profil = jedan tip dopisa
    |--------------------------------------------------------------------------
    */
    'profiles' => [

        /*
        |----------------------------------------------------------------------
        | 1. Predsjednik suda — novi zahtjev za uvid (PZ čl.150 st.4)
        |----------------------------------------------------------------------
        */
        'predsjednik_suda' => [
            'name' => 'Zahtjev predsjedniku suda za uvid u spis',
            'recipient' => [
                'title' => 'Predsjednica Općinskog suda u Osijeku',
                'institution' => 'Općinski sud u Osijeku',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'PZ čl.150 st.1 — opravdani interes',
                'PZ čl.150 st.4 — predsjednik suda odlučuje za završen postupak',
                'Ustav čl.18 — pravo na žalbu',
                'Ustav čl.34 — nepovredivost doma',
            ],
            'tone' => 'formal_assertive',
            'structure' => [
                'heading',
                'case_reference',
                'identification',
                'facts_chronology',
                'legal_arguments',
                'requests',
                'legal_remedy_demand',
                'signature',
            ],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Zahtjev za uvid u spis {case_number} — PZ čl.150 st.4',
            'requires_attachments' => false,
            'metadata' => [
                'priority' => 'immediate',
                'forum' => 'Općinski sud Osijek',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | 2. DORH — zahtjev za uključivanje spisa u kazneni predmet
        |----------------------------------------------------------------------
        */
        'dorh_production' => [
            'name' => 'Zahtjev DORH-u za pribavljanje spisa',
            'recipient' => [
                'title' => 'Općinsko državno odvjetništvo u Osijeku',
                'institution' => 'Državno odvjetništvo',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'ZKP čl.9 st.2 — dužnost prikupljanja i oslobađajućih dokaza',
                'ZKP čl.184 — prava obrane na uvid',
                'ZKP čl.342 — bitna povreda postupka',
            ],
            'tone' => 'formal_assertive',
            'structure' => [
                'heading',
                'case_reference',
                'identification',
                'connection_criminal_misdemeanor',
                'legal_arguments',
                'disclosure_demand',
                'consequences_warning',
                'signature',
            ],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Zahtjev za pribavljanje prekršajnog spisa {case_number}',
            'requires_attachments' => true,
            'metadata' => [
                'priority' => 'immediate',
                'forum' => 'DORH Osijek',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | 3. Kazneni sud — prijedlog za pribavljanje spisa
        |----------------------------------------------------------------------
        */
        'kazneni_sud_motion' => [
            'name' => 'Prijedlog kaznenom sudu za pribavljanje spisa',
            'recipient' => [
                'title' => 'Općinski kazneni sud u Osijeku',
                'institution' => 'Općinski sud u Osijeku — kazneni odjel',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'ZKP čl.183 — pravo obrane na razgledavanje spisa',
                'ZKP čl.184 st.5 — uvid u hitne radnje',
                'ZKP čl.10 — nezakoniti dokazi',
            ],
            'tone' => 'formal_assertive',
            'structure' => [
                'heading',
                'case_reference',
                'identification',
                'motion_context',
                'evidence_connection',
                'legal_arguments',
                'specific_requests',
                'signature',
            ],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Prijedlog za pribavljanje spisa {case_number}',
            'requires_attachments' => false,
            'metadata' => [
                'priority' => 'before_optuznica',
                'forum' => 'Kazneni sud Osijek',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | 4. Pučki pravobranitelj — pritužba za zlouporabu ovlasti
        |----------------------------------------------------------------------
        */
        'ombudsman' => [
            'name' => 'Pritužba Pučkom pravobranitelju',
            'recipient' => [
                'title' => 'Pučki pravobranitelj — Područni ured Osijek',
                'institution' => 'Ured Pučkog pravobranitelja',
                'address' => 'Hrvatske Republike 19/I, 31000 Osijek',
                'email' => null,
                'phone' => '+385 31 628 054',
            ],
            'legal_basis' => [
                'Zakon o pučkom pravobranitelju čl.22',
                'Ustav čl.93 — pučki pravobranitelj',
                'Očita zloupotreba ovlasti — odbijanje formalnog rješenja',
            ],
            'tone' => 'formal_narrative',
            'structure' => [
                'heading',
                'identification',
                'narrative_chronology',
                'rights_violations',
                'specific_complaint',
                'requested_action',
                'signature',
                'attachments_list',
            ],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Pritužba — uskrata uvida u spis {case_number} bez formalnog rješenja',
            'requires_attachments' => true,
            'metadata' => [
                'priority' => 'immediate',
                'forum' => 'Pučki pravobranitelj',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | 5. Ministarstvo pravosuđa — pritužba za upravni nadzor
        |----------------------------------------------------------------------
        */
        'ministarstvo_pravosudja' => [
            'name' => 'Pritužba Ministarstvu pravosuđa i uprave',
            'recipient' => [
                'title' => 'Ministarstvo pravosuđa i uprave — Pravosudna inspekcija',
                'institution' => 'Ministarstvo pravosuđa i uprave',
                'address' => 'Ulica grada Vukovara 49, 10000 Zagreb',
                'email' => null,
            ],
            'legal_basis' => [
                'Zakon o sudovima čl.72 st.6 — upravni nadzor',
                'Ustav čl.18 — pravo na žalbu',
            ],
            'tone' => 'formal_administrative',
            'structure' => [
                'heading',
                'identification',
                'subject_complaint',
                'facts_chronology',
                'administrative_irregularities',
                'requested_measures',
                'signature',
                'attachments_list',
            ],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Pritužba na rad Općinskog suda u Osijeku — spis {case_number}',
            'requires_attachments' => true,
            'metadata' => [
                'priority' => 'immediate',
                'forum' => 'Ministarstvo pravosuđa',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | 6. Ustavni sud — ustavna tužba (čl.62 iznimka)
        |----------------------------------------------------------------------
        */
        'ustavni_sud' => [
            'name' => 'Ustavna tužba — čl.62 iznimka',
            'recipient' => [
                'title' => 'Ustavni sud Republike Hrvatske',
                'institution' => 'Ustavni sud RH',
                'address' => 'Trg svetog Marka 4, 10000 Zagreb',
                'email' => null,
            ],
            'legal_basis' => [
                'Ustavni zakon čl.62 — grubo vrijeđanje ustavnih prava',
                'Ustav čl.18 — pravo na žalbu',
                'Ustav čl.19 — sudska kontrola zakonitosti',
                'Ustav čl.29 — pravično suđenje',
                'Ustav čl.34 — nepovredivost doma',
            ],
            'tone' => 'formal_constitutional',
            'structure' => [
                'heading_constitutional',
                'identification',
                'challenged_acts',
                'constitutional_provisions_violated',
                'factual_background',
                'constitutional_arguments',
                'article_62_justification',
                'proposed_measures',
                'signature',
                'attachments_list',
            ],
            'docx_template' => 'legal-constitutional',
            'email_subject_template' => 'Ustavna tužba — {case_number} — čl.62 Ustavnog zakona',
            'requires_attachments' => true,
            'metadata' => [
                'priority' => 'within_30_days',
                'forum' => 'Ustavni sud RH',
                'deadline_note' => '30 dana od zadnje odluke ili odmah pod čl.62',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | 7. Kazneni sud — prijedlog za izdvajanje nezakonitih dokaza
        |----------------------------------------------------------------------
        */
        'izdvajanje_dokaza' => [
            'name' => 'Prijedlog za izdvajanje nezakonitih dokaza',
            'recipient' => [
                'title' => 'Općinski kazneni sud u Osijeku',
                'institution' => 'Općinski sud u Osijeku — kazneni odjel',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => [
                'ZKP čl.10 st.2 toč.2 — povreda prava obrane',
                'ZKP čl.10 st.2 toč.3 — bitna povreda postupka',
                'ZKP čl.184 st.5 — uskraćeni uvid u hitne radnje',
                'Ustav čl.29 — pravično suđenje',
            ],
            'tone' => 'formal_aggressive',
            'structure' => [
                'heading',
                'case_reference',
                'identification',
                'evidence_identification',
                'exclusion_grounds',
                'defense_rights_violation',
                'constitutional_dimension',
                'specific_request',
                'signature',
            ],
            'docx_template' => 'legal-formal',
            'email_subject_template' => 'Prijedlog za izdvajanje nezakonitih dokaza — pretraga {case_number}',
            'requires_attachments' => true,
            'metadata' => [
                'priority' => 'at_optuzno_vijece',
                'forum' => 'Kazneni sud Osijek',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | 8. ECHR — Application (Articles 6, 8, 13)
        |----------------------------------------------------------------------
        */
        'echr_application' => [
            'name' => 'ECHR Application',
            'recipient' => [
                'title' => 'European Court of Human Rights',
                'institution' => 'ECHR / ESLJP',
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
            'structure' => [
                'echr_header',
                'applicant_details',
                'respondent_state',
                'statement_of_facts',
                'domestic_proceedings',
                'alleged_violations',
                'article_6_arguments',
                'article_8_arguments',
                'article_13_arguments',
                'exhaustion_of_remedies',
                'timeliness',
                'relief_sought',
                'declaration',
                'signature',
                'annexes',
            ],
            'docx_template' => 'echr-application',
            'email_subject_template' => null,
            'requires_attachments' => true,
            'metadata' => [
                'priority' => 'after_domestic_exhaustion',
                'forum' => 'ECHR Strasbourg',
                'deadline_note' => '4 mjeseca od zadnje domaće odluke',
                'language' => 'en',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tone definitions — kako agent piše za svaki ton
    |--------------------------------------------------------------------------
    */
    'tones' => [
        'formal_assertive' => [
            'system_instruction' => 'Piši formalno, jasno i asertivno. Citiraj pravne odredbe precizno. Koristi imperative ("zahtijevam", "tražim", "pozivam se na"). Izbjegavaj nepotrebnu ljubaznost — budi direktan ali profesionalan.',
            'language' => 'hr',
        ],
        'formal_narrative' => [
            'system_instruction' => 'Piši formalno ali narativno. Kronološki izloži činjenice. Naglasi ljudsku dimenziju — prava pojedinca nasuprot institucijama. Budi precizan ali i empatičan.',
            'language' => 'hr',
        ],
        'formal_administrative' => [
            'system_instruction' => 'Piši suhoparno i administrativno. Citiraj propise i irregularnosti. Fokusiraj se na sistemske nedostatke u radu suda. Ton: neutralan, činjenični, birokratski precizan.',
            'language' => 'hr',
        ],
        'formal_constitutional' => [
            'system_instruction' => 'Piši uzvišeno i ustavnopravno. Naglasi fundamentalna prava i ustavne garancije. Pozivaj se na ustavnosudsku praksu. Ton: ozbiljan, principijelan, državnički.',
            'language' => 'hr',
        ],
        'formal_aggressive' => [
            'system_instruction' => 'Piši oštro i argumentirano. Napadaj pravne pogreške suprotne strane. Koristi jaku pravnu argumentaciju. Ne budi nepristojan, ali budi neumoljivno precizan i nemilosrdan u analizi.',
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
        'from_name' => env('LEGAL_SENDER_NAME', 'Andrija Glavaš'),
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
        'model' => env('LEGAL_LLM_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => env('LEGAL_LLM_MAX_TOKENS', 8192),
        'recursion_depth' => env('LEGAL_RECURSION_DEPTH', 3),
        'output_dir' => storage_path('app/legal-artillery/generated'),
        'archive_sent' => true,
    ],

];
```

**Step 5: Pokreni test — PASS**

```bash
php artisan test tests/Unit/DTOs/DocumentProfileTest.php --verbose
```
Expected: PASS

**Step 6: Commit**

```bash
git add config/legal-artillery.php app/DTOs/DocumentProfile.php tests/Unit/DTOs/DocumentProfileTest.php
git commit -m "feat: add DocumentProfile DTO and legal-artillery config with 8 attack profiles"
```

---

### Task 2: CaseContext value object

**Opis:** Centraliziraj podatke o predmetu. Svi agenti i rendereri koriste isti CaseContext objekt — ne parsiraju config svaki put.

**Files:**
- Create: `app/DTOs/CaseContext.php`
- Create: `app/DTOs/SenderIdentity.php`
- Test: `tests/Unit/DTOs/CaseContextTest.php`

**Step 1: Napiši padajući test**

```php
// tests/Unit/DTOs/CaseContextTest.php
<?php

namespace Tests\Unit\DTOs;

use App\DTOs\CaseContext;
use App\DTOs\SenderIdentity;
use PHPUnit\Framework\TestCase;

class CaseContextTest extends TestCase
{
    public function test_creates_from_config(): void
    {
        $context = CaseContext::fromConfig();

        $this->assertEquals('Pp Prz-74/2025', $context->caseNumber);
        $this->assertEquals('2025-06-09', $context->searchDate);
        $this->assertInstanceOf(SenderIdentity::class, $context->sender);
    }

    public function test_provides_template_variables(): void
    {
        $context = CaseContext::fromConfig();
        $vars = $context->toTemplateVars();

        $this->assertArrayHasKey('case_number', $vars);
        $this->assertArrayHasKey('sender_name', $vars);
        $this->assertArrayHasKey('search_date', $vars);
        $this->assertArrayHasKey('archive_date', $vars);
        $this->assertArrayHasKey('today_date', $vars);
    }

    public function test_interpolates_string_template(): void
    {
        $context = CaseContext::fromConfig();
        $result = $context->interpolate('Spis broj {case_number} — pretraga {search_date}');

        $this->assertStringContainsString('Pp Prz-74/2025', $result);
        $this->assertStringContainsString('2025-06-09', $result);
    }
}
```

**Step 2: Pokreni test — FAIL**

```bash
php artisan test tests/Unit/DTOs/CaseContextTest.php --verbose
```

**Step 3: Implementiraj**

```php
// app/DTOs/SenderIdentity.php
<?php

namespace App\DTOs;

class SenderIdentity
{
    public function __construct(
        public readonly string $name,
        public readonly string $oib,
        public readonly string $address,
        public readonly string $email,
        public readonly string $phone,
    ) {}

    public static function fromConfig(): self
    {
        $cfg = config('legal-artillery.sender');
        return new self(
            name: $cfg['name'],
            oib: $cfg['oib'],
            address: $cfg['address'],
            email: $cfg['email'],
            phone: $cfg['phone'],
        );
    }
}
```

```php
// app/DTOs/CaseContext.php
<?php

namespace App\DTOs;

use Carbon\Carbon;

class CaseContext
{
    public function __construct(
        public readonly string $caseNumber,
        public readonly string $searchDate,
        public readonly string $archiveDate,
        public readonly string $addressSearched,
        public readonly string $warrantReference,
        public readonly string $policeRequestKlasa,
        public readonly string $policeRequestUrbroj,
        public readonly string $legalBasisWarrant,
        public readonly string $suspectedOffense,
        public readonly string $judge,
        public readonly string $denialDate,
        public readonly string $countyCourtResponseDate,
        public readonly SenderIdentity $sender,
    ) {}

    public static function fromConfig(): self
    {
        $case = config('legal-artillery.case_context');
        return new self(
            caseNumber: $case['case_number'],
            searchDate: $case['search_date'],
            archiveDate: $case['archive_date'],
            addressSearched: $case['address_searched'],
            warrantReference: $case['warrant_reference'],
            policeRequestKlasa: $case['police_request_klasa'],
            policeRequestUrbroj: $case['police_request_urbroj'],
            legalBasisWarrant: $case['legal_basis_warrant'],
            suspectedOffense: $case['suspected_offense'],
            judge: $case['judge'],
            denialDate: $case['denial_date'],
            countyCourtResponseDate: $case['county_court_response_date'],
            sender: SenderIdentity::fromConfig(),
        );
    }

    public function toTemplateVars(): array
    {
        return [
            'case_number' => $this->caseNumber,
            'search_date' => $this->searchDate,
            'archive_date' => $this->archiveDate,
            'address_searched' => $this->addressSearched,
            'warrant_reference' => $this->warrantReference,
            'police_klasa' => $this->policeRequestKlasa,
            'police_urbroj' => $this->policeRequestUrbroj,
            'legal_basis_warrant' => $this->legalBasisWarrant,
            'suspected_offense' => $this->suspectedOffense,
            'judge' => $this->judge,
            'denial_date' => $this->denialDate,
            'county_response_date' => $this->countyCourtResponseDate,
            'sender_name' => $this->sender->name,
            'sender_oib' => $this->sender->oib,
            'sender_address' => $this->sender->address,
            'sender_email' => $this->sender->email,
            'sender_phone' => $this->sender->phone,
            'today_date' => Carbon::now()->format('d. F Y.'),
            'today_date_iso' => Carbon::now()->toDateString(),
        ];
    }

    public function interpolate(string $template): string
    {
        $vars = $this->toTemplateVars();
        return preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($vars) {
            return $vars[$matches[1]] ?? $matches[0];
        }, $template);
    }
}
```

**Step 4: Pokreni test — PASS**

```bash
php artisan test tests/Unit/DTOs/CaseContextTest.php --verbose
```

**Step 5: Commit**

```bash
git add app/DTOs/CaseContext.php app/DTOs/SenderIdentity.php tests/Unit/DTOs/CaseContextTest.php
git commit -m "feat: add CaseContext and SenderIdentity value objects"
```

---

## Faza 2: Rekurzivni Motor Generiranja

### Task 3: RecursiveDocumentWriter — jezgra rekurzivnog pisanja

**Opis:** Središnja klasa koja rekurzivno generira sadržaj: (1) generira outline na temelju profila, (2) za svaku sekciju generira detaljan sadržaj, (3) spaja u finalni dokument. Koristi Claude API.

**Files:**
- Create: `app/Services/LegalArtillery/RecursiveDocumentWriter.php`
- Create: `app/Services/LegalArtillery/LlmClient.php` (wrapper za Anthropic API)
- Test: `tests/Unit/Services/LegalArtillery/RecursiveDocumentWriterTest.php`

**Step 1: Napiši padajući test**

```php
// tests/Unit/Services/LegalArtillery/RecursiveDocumentWriterTest.php
<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\RecursiveDocumentWriter;
use Mockery;
use Tests\TestCase;

class RecursiveDocumentWriterTest extends TestCase
{
    public function test_generates_outline_from_profile(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $llm = Mockery::mock(LlmClient::class);

        $llm->shouldReceive('generate')
            ->once()
            ->withArgs(fn($system, $prompt) =>
                str_contains($prompt, 'predsjednik') &&
                str_contains($prompt, 'outline')
            )
            ->andReturn(json_encode([
                'sections' => [
                    ['key' => 'heading', 'title' => 'Zaglavlje', 'guidance' => 'Predmet, pošiljalac, primatelj'],
                    ['key' => 'facts', 'title' => 'Činjenice', 'guidance' => 'Kronologija pretrage i zahtjeva'],
                    ['key' => 'legal', 'title' => 'Pravni temelj', 'guidance' => 'PZ čl.150 st.4, Ustav čl.18'],
                    ['key' => 'request', 'title' => 'Zahtjev', 'guidance' => 'Formalno rješenje s poukom'],
                ],
            ]));

        $writer = new RecursiveDocumentWriter($llm);
        $outline = $writer->generateOutline($profile, $context);

        $this->assertCount(4, $outline['sections']);
        $this->assertEquals('heading', $outline['sections'][0]['key']);
    }

    public function test_generates_section_content(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $llm = Mockery::mock(LlmClient::class);

        $section = [
            'key' => 'legal_arguments',
            'title' => 'Pravni argumenti',
            'guidance' => 'PZ čl.150 st.1 i st.4',
        ];

        $llm->shouldReceive('generate')
            ->once()
            ->andReturn('Temeljem članka 150. stavak 4. Prekršajnog zakona...');

        $writer = new RecursiveDocumentWriter($llm);
        $content = $writer->generateSection($profile, $context, $section);

        $this->assertStringContainsString('članka 150', $content);
    }

    public function test_recursive_full_generation(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $llm = Mockery::mock(LlmClient::class);

        // First call: outline
        $llm->shouldReceive('generate')
            ->once()
            ->withArgs(fn($s, $p) => str_contains($p, 'outline'))
            ->andReturn(json_encode([
                'sections' => [
                    ['key' => 'heading', 'title' => 'Zaglavlje', 'guidance' => 'Header'],
                    ['key' => 'body', 'title' => 'Tijelo', 'guidance' => 'Main body'],
                ],
            ]));

        // Subsequent calls: one per section
        $llm->shouldReceive('generate')
            ->times(2)
            ->andReturn('Generirani sadržaj sekcije.');

        // Final call: polish/review
        $llm->shouldReceive('generate')
            ->once()
            ->withArgs(fn($s, $p) => str_contains($p, 'review') || str_contains($p, 'polish'))
            ->andReturn('Poliran finalni dokument.');

        $writer = new RecursiveDocumentWriter($llm);
        $result = $writer->generate($profile, $context);

        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('sections', $result);
        $this->assertArrayHasKey('profile_key', $result);
        $this->assertNotEmpty($result['content']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Pokreni test — FAIL**

```bash
php artisan test tests/Unit/Services/LegalArtillery/RecursiveDocumentWriterTest.php --verbose
```

**Step 3: Implementiraj LlmClient**

```php
// app/Services/LegalArtillery/LlmClient.php
<?php

namespace App\Services\LegalArtillery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens = 8192,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            apiKey: config('services.anthropic.api_key'),
            model: config('legal-artillery.generation.model', 'claude-sonnet-4-20250514'),
            maxTokens: config('legal-artillery.generation.max_tokens', 8192),
        );
    }

    public function generate(string $systemPrompt, string $userPrompt, ?int $maxTokens = null): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $maxTokens ?? $this->maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if ($response->failed()) {
            Log::error('LLM API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("LLM API call failed: {$response->status()}");
        }

        $content = $response->json('content');
        return collect($content)
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");
    }
}
```

**Step 4: Implementiraj RecursiveDocumentWriter**

```php
// app/Services/LegalArtillery/RecursiveDocumentWriter.php
<?php

namespace App\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use Illuminate\Support\Facades\Log;

class RecursiveDocumentWriter
{
    public function __construct(
        private readonly LlmClient $llm,
    ) {}

    /**
     * Full recursive generation pipeline:
     * 1. Generate outline from profile structure
     * 2. Generate each section
     * 3. Polish/review final document
     */
    public function generate(DocumentProfile $profile, CaseContext $context, array $additionalContext = []): array
    {
        Log::info('LegalArtillery: Starting generation', ['profile' => $profile->key]);

        // Step 1: Outline
        $outline = $this->generateOutline($profile, $context, $additionalContext);
        Log::info('LegalArtillery: Outline generated', ['sections' => count($outline['sections'])]);

        // Step 2: Generate each section
        $sections = [];
        $previousSections = [];
        foreach ($outline['sections'] as $section) {
            $content = $this->generateSection(
                $profile,
                $context,
                $section,
                $previousSections,
                $additionalContext,
            );
            $sections[] = [
                'key' => $section['key'],
                'title' => $section['title'],
                'content' => $content,
            ];
            $previousSections[] = ['title' => $section['title'], 'content' => $content];
        }

        // Step 3: Polish
        $fullContent = $this->polishDocument($profile, $context, $sections);

        Log::info('LegalArtillery: Generation complete', ['profile' => $profile->key]);

        return [
            'profile_key' => $profile->key,
            'profile_name' => $profile->name,
            'outline' => $outline,
            'sections' => $sections,
            'content' => $fullContent,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function generateOutline(DocumentProfile $profile, CaseContext $context, array $additional = []): array
    {
        $toneConfig = config("legal-artillery.tones.{$profile->tone}", []);
        $vars = $context->toTemplateVars();

        $systemPrompt = $this->buildSystemPrompt($profile, $toneConfig);

        $userPrompt = <<<PROMPT
Generiraj OUTLINE (strukturu) za sljedeći pravni dopis.

## Tip dopisa
{$profile->name}

## Primatelj
{$profile->recipientLine()}

## Pravni temelji
{$this->formatList($profile->legalBasis)}

## Strukturalne sekcije (obavezne)
{$this->formatList($profile->structure)}

## Kontekst predmeta
- Broj predmeta: {$vars['case_number']}
- Datum pretrage: {$vars['search_date']}
- Datum arhiviranja: {$vars['archive_date']}
- Adresa pretrage: {$vars['address_searched']}
- Sudac: {$vars['judge']}
- Datum odbijanja: {$vars['denial_date']}

## Dodatni kontekst
{$this->formatAdditionalContext($additional)}

Odgovori ISKLJUČIVO u JSON formatu:
{
    "sections": [
        {
            "key": "section_key",
            "title": "Naslov sekcije",
            "guidance": "Kratki opis što ova sekcija treba sadržavati"
        }
    ]
}
PROMPT;

        $response = $this->llm->generate($systemPrompt, $userPrompt, 2048);

        // Parse JSON from response (handle markdown code blocks)
        $json = $this->extractJson($response);
        return json_decode($json, true) ?? ['sections' => []];
    }

    public function generateSection(
        DocumentProfile $profile,
        CaseContext $context,
        array $section,
        array $previousSections = [],
        array $additional = [],
    ): string {
        $toneConfig = config("legal-artillery.tones.{$profile->tone}", []);
        $vars = $context->toTemplateVars();

        $systemPrompt = $this->buildSystemPrompt($profile, $toneConfig);

        $prevContext = '';
        if (!empty($previousSections)) {
            $prevContext = "## Dosad napisane sekcije\n";
            foreach ($previousSections as $prev) {
                $prevContext .= "### {$prev['title']}\n{$prev['content']}\n\n";
            }
        }

        $userPrompt = <<<PROMPT
Napiši sekciju: **{$section['title']}**

## Upute za sekciju
{$section['guidance']}

## Pravni temelji za korištenje
{$this->formatList($profile->legalBasis)}

## Podnositelj
Ime: {$vars['sender_name']}
OIB: {$vars['sender_oib']}
Adresa: {$vars['sender_address']}
E-mail: {$vars['sender_email']}
Tel: {$vars['sender_phone']}

## Kontekst predmeta
- Broj predmeta: {$vars['case_number']}
- Referenca naredbe: {$vars['warrant_reference']}
- Datum pretrage: {$vars['search_date']}
- Datum arhiviranja: {$vars['archive_date']}
- KLASA policijskog zahtjeva: {$vars['police_klasa']}
- URBROJ: {$vars['police_urbroj']}
- Pravni temelj naredbe: {$vars['legal_basis_warrant']}
- Prekršaj: {$vars['suspected_offense']}
- Sudac: {$vars['judge']}
- Datum odbijanja: {$vars['denial_date']}
- Datum odgovora Županijskog suda: {$vars['county_response_date']}

{$prevContext}

Piši SAMO sadržaj ove sekcije. Bez markdown zaglavlja. Samo tekst spreman za Word dokument.
PROMPT;

        return $this->llm->generate($systemPrompt, $userPrompt);
    }

    private function polishDocument(DocumentProfile $profile, CaseContext $context, array $sections): string
    {
        $toneConfig = config("legal-artillery.tones.{$profile->tone}", []);
        $systemPrompt = $this->buildSystemPrompt($profile, $toneConfig);

        $assembled = '';
        foreach ($sections as $section) {
            $assembled .= "## {$section['title']}\n\n{$section['content']}\n\n---\n\n";
        }

        $userPrompt = <<<PROMPT
Pregledaj i poliraj sljedeći pravni dopis. Osiguraj:
1. Pravni citati su potpuni i točni
2. Nema ponavljanja između sekcija
3. Ton je konzistentan
4. Tranzicije između sekcija su glatke
5. Sve činjenice iz konteksta predmeta su ispravne

Vrati FINALNI tekst dokumenta, spreman za formatiranje u Word. Zadrži strukturu sekcija ali ukloni markdown oznake.

## Dokument za review:

{$assembled}
PROMPT;

        return $this->llm->generate($systemPrompt, $userPrompt);
    }

    private function buildSystemPrompt(DocumentProfile $profile, array $toneConfig): string
    {
        $toneInstruction = $toneConfig['system_instruction'] ?? 'Piši formalno.';
        $language = $toneConfig['language'] ?? 'hr';

        return <<<SYSTEM
Ti si specijalizirani pravni pisač za hrvatski pravni sustav.

## Tvoj zadatak
Generiraš pravne dopise tipa: {$profile->name}

## Ton i stil
{$toneInstruction}

## Jezik
Piši na jeziku: {$language}

## Pravila
- Citiraj pravne odredbe potpuno (članak, stavak, točka, naziv zakona)
- Koristi službenu pravnu terminologiju
- Datume piši u formatu "DD. mjesec YYYY." (npr. "9. lipnja 2025.")
- Ne izmišljaj činjenice — koristi SAMO podatke iz konteksta
- Ako nedostaje informacija, označi s [DOPUNITI]
- Svaki zahtjev mora biti konkretan i mjerljiv
SYSTEM;
    }

    private function formatList(array $items): string
    {
        return implode("\n", array_map(fn($i) => "- {$i}", $items));
    }

    private function formatAdditionalContext(array $additional): string
    {
        if (empty($additional)) return 'Nema dodatnog konteksta.';
        return implode("\n", array_map(fn($k, $v) => "- {$k}: {$v}", array_keys($additional), $additional));
    }

    private function extractJson(string $text): string
    {
        // Remove markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            return trim($matches[1]);
        }
        // Try to find raw JSON
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            return $matches[0];
        }
        return $text;
    }
}
```

**Step 5: Pokreni test — PASS**

```bash
php artisan test tests/Unit/Services/LegalArtillery/RecursiveDocumentWriterTest.php --verbose
```

**Step 6: Commit**

```bash
git add app/Services/LegalArtillery/ tests/Unit/Services/LegalArtillery/
git commit -m "feat: RecursiveDocumentWriter with LLM-powered recursive generation"
```

---

## Faza 3: DOCX Renderiranje

### Task 4: DocxRenderer — pretvaranje teksta u Word

**Opis:** Uzima generirani sadržaj i profil, renderira profesionalan .docx dokument s pravilnim zaglavljem, sekcijama, i potpisom.

**Files:**
- Create: `app/Services/LegalArtillery/DocxRenderer.php`
- Create: `resources/legal-artillery/render-docx.js` (Node.js docx-js skripta)
- Test: `tests/Unit/Services/LegalArtillery/DocxRendererTest.php`

**Step 1: Napiši test**

```php
// tests/Unit/Services/LegalArtillery/DocxRendererTest.php
<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\DocxRenderer;
use Tests\TestCase;

class DocxRendererTest extends TestCase
{
    public function test_renders_docx_from_generation_result(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $generationResult = [
            'profile_key' => 'predsjednik_suda',
            'profile_name' => 'Zahtjev predsjedniku suda za uvid u spis',
            'content' => "ZAHTJEV ZA UVID U SPIS\n\nTemeljem članka 150...",
            'sections' => [
                ['key' => 'heading', 'title' => 'Zaglavlje', 'content' => 'Zahtjev...'],
                ['key' => 'body', 'title' => 'Tijelo', 'content' => 'Temeljem...'],
            ],
            'generated_at' => now()->toIso8601String(),
        ];

        $renderer = new DocxRenderer();
        $path = $renderer->render($profile, $context, $generationResult);

        $this->assertFileExists($path);
        $this->assertStringEndsWith('.docx', $path);

        // Cleanup
        unlink($path);
    }
}
```

**Step 2: Pokreni test — FAIL**

**Step 3: Implementiraj DocxRenderer (PHP wrapper za Node.js skriptu)**

```php
// app/Services/LegalArtillery/DocxRenderer.php
<?php

namespace App\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocxRenderer
{
    private string $outputDir;
    private string $scriptPath;

    public function __construct(?string $outputDir = null)
    {
        $this->outputDir = $outputDir ?? config('legal-artillery.generation.output_dir', storage_path('app/legal-artillery/generated'));
        $this->scriptPath = resource_path('legal-artillery/render-docx.js');

        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    public function render(DocumentProfile $profile, CaseContext $context, array $generationResult): string
    {
        $filename = $this->generateFilename($profile);
        $outputPath = "{$this->outputDir}/{$filename}";

        // Prepare data for Node.js script
        $data = [
            'profile' => [
                'key' => $profile->key,
                'name' => $profile->name,
                'recipient' => $profile->recipient,
                'docx_template' => $profile->docxTemplate,
            ],
            'sender' => [
                'name' => $context->sender->name,
                'oib' => $context->sender->oib,
                'address' => $context->sender->address,
                'email' => $context->sender->email,
                'phone' => $context->sender->phone,
            ],
            'case' => $context->toTemplateVars(),
            'content' => $generationResult['content'],
            'sections' => $generationResult['sections'],
            'output_path' => $outputPath,
        ];

        $jsonPath = tempnam(sys_get_temp_dir(), 'legal_docx_');
        file_put_contents($jsonPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $command = "node {$this->scriptPath} {$jsonPath} 2>&1";
        exec($command, $output, $exitCode);

        unlink($jsonPath);

        if ($exitCode !== 0) {
            Log::error('DocxRenderer: Node.js failed', ['output' => implode("\n", $output)]);
            throw new \RuntimeException("DOCX rendering failed: " . implode("\n", $output));
        }

        Log::info('DocxRenderer: Generated', ['path' => $outputPath]);
        return $outputPath;
    }

    private function generateFilename(DocumentProfile $profile): string
    {
        $date = now()->format('Y-m-d');
        $slug = Str::slug($profile->key);
        $rand = Str::random(6);
        return "{$date}_{$slug}_{$rand}.docx";
    }
}
```

**Step 4: Kreiraj Node.js render skriptu**

```javascript
// resources/legal-artillery/render-docx.js
const fs = require('fs');
const { Document, Packer, Paragraph, TextRun, AlignmentType,
        HeadingLevel, BorderStyle, Header, Footer, PageNumber,
        Tab, TabStopType, TabStopPosition } = require('docx');

const jsonPath = process.argv[2];
if (!jsonPath) {
    console.error('Usage: node render-docx.js <data.json>');
    process.exit(1);
}

const data = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
const { profile, sender, content, sections, output_path } = data;

function buildChildren() {
    const children = [];

    // Recipient block
    if (profile.recipient) {
        children.push(
            new Paragraph({
                alignment: AlignmentType.RIGHT,
                spacing: { after: 0 },
                children: [new TextRun({ text: profile.recipient.title || '', bold: true, size: 24, font: 'Arial' })],
            }),
            new Paragraph({
                alignment: AlignmentType.RIGHT,
                spacing: { after: 200 },
                children: [new TextRun({ text: profile.recipient.address || '', size: 22, font: 'Arial' })],
            })
        );
    }

    // Sender block
    children.push(
        new Paragraph({ spacing: { after: 0 }, children: [new TextRun({ text: sender.name, bold: true, size: 24, font: 'Arial' })] }),
        new Paragraph({ spacing: { after: 0 }, children: [new TextRun({ text: sender.address, size: 22, font: 'Arial' })] }),
        new Paragraph({ spacing: { after: 0 }, children: [new TextRun({ text: `OIB: ${sender.oib}`, size: 22, font: 'Arial' })] }),
        new Paragraph({ spacing: { after: 0 }, children: [new TextRun({ text: `E-mail: ${sender.email}`, size: 22, font: 'Arial' })] }),
        new Paragraph({ spacing: { after: 400 }, children: [new TextRun({ text: `Tel: ${sender.phone}`, size: 22, font: 'Arial' })] }),
    );

    // Title
    children.push(
        new Paragraph({
            heading: HeadingLevel.HEADING_1,
            alignment: AlignmentType.CENTER,
            spacing: { before: 400, after: 400 },
            children: [new TextRun({ text: profile.name.toUpperCase(), bold: true, size: 28, font: 'Arial' })],
        })
    );

    // Case reference
    if (data.case && data.case.case_number) {
        children.push(
            new Paragraph({
                alignment: AlignmentType.CENTER,
                spacing: { after: 400 },
                children: [new TextRun({
                    text: `Predmet: ${data.case.case_number}`,
                    bold: true, italics: true, size: 24, font: 'Arial'
                })],
            })
        );
    }

    // Content — split by sections or by paragraphs
    if (sections && sections.length > 0) {
        for (const section of sections) {
            // Section heading
            children.push(
                new Paragraph({
                    heading: HeadingLevel.HEADING_2,
                    spacing: { before: 300, after: 200 },
                    children: [new TextRun({ text: section.title, bold: true, size: 26, font: 'Arial' })],
                })
            );

            // Section content — split into paragraphs
            const paragraphs = (section.content || '').split('\n').filter(p => p.trim());
            for (const para of paragraphs) {
                children.push(
                    new Paragraph({
                        spacing: { after: 120 },
                        children: [new TextRun({ text: para.trim(), size: 24, font: 'Arial' })],
                    })
                );
            }
        }
    } else {
        // Fallback: use raw content
        const paragraphs = content.split('\n').filter(p => p.trim());
        for (const para of paragraphs) {
            children.push(
                new Paragraph({
                    spacing: { after: 120 },
                    children: [new TextRun({ text: para.trim(), size: 24, font: 'Arial' })],
                })
            );
        }
    }

    // Signature block
    children.push(
        new Paragraph({ spacing: { before: 600 }, children: [] }),
        new Paragraph({
            alignment: AlignmentType.RIGHT,
            spacing: { after: 0 },
            children: [new TextRun({ text: 'S poštovanjem,', size: 24, font: 'Arial' })],
        }),
        new Paragraph({ spacing: { after: 400 }, children: [] }),
        new Paragraph({
            alignment: AlignmentType.RIGHT,
            children: [new TextRun({ text: `_______________________`, size: 24, font: 'Arial' })],
        }),
        new Paragraph({
            alignment: AlignmentType.RIGHT,
            children: [new TextRun({ text: sender.name, bold: true, size: 24, font: 'Arial' })],
        }),
    );

    return children;
}

const doc = new Document({
    styles: {
        default: { document: { run: { font: 'Arial', size: 24 } } },
        paragraphStyles: [
            { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true,
              run: { size: 28, bold: true, font: 'Arial' },
              paragraph: { spacing: { before: 240, after: 240 }, outlineLevel: 0 } },
            { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true,
              run: { size: 26, bold: true, font: 'Arial' },
              paragraph: { spacing: { before: 180, after: 180 }, outlineLevel: 1 } },
        ]
    },
    sections: [{
        properties: {
            page: {
                size: { width: 11906, height: 16838 }, // A4
                margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 },
            }
        },
        headers: {
            default: new Header({
                children: [new Paragraph({
                    alignment: AlignmentType.RIGHT,
                    children: [new TextRun({ text: data.case?.case_number || '', size: 18, font: 'Arial', color: '888888' })],
                })],
            }),
        },
        footers: {
            default: new Footer({
                children: [new Paragraph({
                    alignment: AlignmentType.CENTER,
                    children: [
                        new TextRun({ text: 'Stranica ', size: 18, font: 'Arial', color: '888888' }),
                        new TextRun({ children: [PageNumber.CURRENT], size: 18, font: 'Arial', color: '888888' }),
                    ],
                })],
            }),
        },
        children: buildChildren(),
    }],
});

Packer.toBuffer(doc).then(buffer => {
    fs.writeFileSync(output_path, buffer);
    console.log(`OK: ${output_path}`);
}).catch(err => {
    console.error(`FAIL: ${err.message}`);
    process.exit(1);
});
```

**Step 5: Pokreni test — PASS**

```bash
cd /path/to/project && npm install docx
php artisan test tests/Unit/Services/LegalArtillery/DocxRendererTest.php --verbose
```

**Step 6: Commit**

```bash
git add app/Services/LegalArtillery/DocxRenderer.php resources/legal-artillery/ tests/Unit/Services/LegalArtillery/DocxRendererTest.php
git commit -m "feat: DocxRenderer with Node.js docx-js for Word document generation"
```

---

## Faza 4: Gmail Integracija

### Task 5: Gmail OAuth2 setup i GmailDispatcher

**Opis:** Povezivanje s Gmail API-jem. Agent generira dopis, renderira .docx, i odmah ga šalje na email primatelja (ili čuva draft). Koristi Google API Client za PHP.

**Files:**
- Create: `app/Services/LegalArtillery/GmailDispatcher.php`
- Create: `app/Console/Commands/LegalArtillery/GmailAuthCommand.php`
- Test: `tests/Unit/Services/LegalArtillery/GmailDispatcherTest.php`

**Step 1: Instaliraj Google API klijent**

```bash
composer require google/apiclient:"^2.15"
```

**Step 2: Napiši test**

```php
// tests/Unit/Services/LegalArtillery/GmailDispatcherTest.php
<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\GmailDispatcher;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Mockery;
use Tests\TestCase;

class GmailDispatcherTest extends TestCase
{
    public function test_builds_mime_message_with_attachment(): void
    {
        $dispatcher = new GmailDispatcher();

        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $to = 'test@example.com';
        $subject = 'Test Subject';
        $body = 'Test body text';
        $attachmentPath = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($attachmentPath, 'dummy docx content');

        $mime = $dispatcher->buildMimeMessage($to, $subject, $body, $attachmentPath);

        $this->assertStringContainsString('To: test@example.com', $mime);
        $this->assertStringContainsString('Subject: Test Subject', $mime);
        $this->assertStringContainsString('Content-Disposition: attachment', $mime);

        unlink($attachmentPath);
    }

    public function test_resolves_email_subject_from_profile(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $dispatcher = new GmailDispatcher();
        $subject = $dispatcher->resolveSubject($profile, $context);

        $this->assertStringContainsString('Pp Prz-74/2025', $subject);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 3: Pokreni test — FAIL**

**Step 4: Implementiraj GmailDispatcher**

```php
// app/Services/LegalArtillery/GmailDispatcher.php
<?php

namespace App\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\Log;

class GmailDispatcher
{
    private ?GoogleClient $client = null;

    public function send(
        DocumentProfile $profile,
        CaseContext $context,
        string $docxPath,
        ?string $toEmail = null,
        bool $asDraft = false,
    ): array {
        $gmail = $this->getGmailService();

        $to = $toEmail ?? $profile->recipient['email'] ?? null;
        if (!$to && !$asDraft) {
            Log::warning('GmailDispatcher: No email for recipient, saving as draft');
            $asDraft = true;
        }

        $subject = $this->resolveSubject($profile, $context);
        $body = $this->buildEmailBody($profile, $context);
        $mimeRaw = $this->buildMimeMessage($to ?? '', $subject, $body, $docxPath);

        $message = new Message();
        $message->setRaw(rtrim(strtr(base64_encode($mimeRaw), '+/', '-_'), '='));

        if ($asDraft) {
            $draft = new Gmail\Draft();
            $draft->setMessage($message);
            $result = $gmail->users_drafts->create('me', $draft);
            Log::info('GmailDispatcher: Draft saved', ['id' => $result->getId()]);
            return ['status' => 'draft', 'id' => $result->getId()];
        }

        $result = $gmail->users_messages->send('me', $message);
        Log::info('GmailDispatcher: Sent', ['id' => $result->getId(), 'to' => $to]);

        return ['status' => 'sent', 'id' => $result->getId(), 'to' => $to];
    }

    public function resolveSubject(DocumentProfile $profile, CaseContext $context): string
    {
        $template = $profile->emailSubjectTemplate ?? $profile->name;
        return $context->interpolate($template);
    }

    public function buildMimeMessage(string $to, string $subject, string $body, ?string $attachmentPath = null): string
    {
        $boundary = md5(uniqid(rand(), true));
        $fromEmail = config('legal-artillery.gmail.from_email');
        $fromName = config('legal-artillery.gmail.from_name');
        $cc = config('legal-artillery.gmail.cc');

        $headers = [];
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        if ($to) {
            $headers[] = "To: {$to}";
        }
        if ($cc) {
            $headers[] = "Cc: {$cc}";
        }
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

        $mime = implode("\r\n", $headers) . "\r\n\r\n";

        // Body part
        $mime .= "--{$boundary}\r\n";
        $mime .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mime .= chunk_split(base64_encode($body)) . "\r\n";

        // Attachment
        if ($attachmentPath && file_exists($attachmentPath)) {
            $filename = basename($attachmentPath);
            $fileData = file_get_contents($attachmentPath);

            $mime .= "--{$boundary}\r\n";
            $mime .= "Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document; name=\"{$filename}\"\r\n";
            $mime .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $mime .= chunk_split(base64_encode($fileData)) . "\r\n";
        }

        $mime .= "--{$boundary}--";

        return $mime;
    }

    private function buildEmailBody(DocumentProfile $profile, CaseContext $context): string
    {
        $vars = $context->toTemplateVars();
        return implode("\n", [
            "Poštovani,",
            "",
            "U prilogu dostavljam: {$profile->name}",
            "Predmet: {$vars['case_number']}",
            "",
            "S poštovanjem,",
            $vars['sender_name'],
            $vars['sender_address'],
            "OIB: {$vars['sender_oib']}",
            "E-mail: {$vars['sender_email']}",
        ]);
    }

    private function getGmailService(): Gmail
    {
        if (!$this->client) {
            $this->client = new GoogleClient();
            $this->client->setApplicationName('Legal Artillery');
            $this->client->setScopes([Gmail::GMAIL_COMPOSE, Gmail::GMAIL_SEND]);

            $credPath = config('legal-artillery.gmail.credentials_path');
            $tokenPath = config('legal-artillery.gmail.token_path');

            $this->client->setAuthConfig($credPath);
            $this->client->setAccessType('offline');

            if (file_exists($tokenPath)) {
                $token = json_decode(file_get_contents($tokenPath), true);
                $this->client->setAccessToken($token);

                if ($this->client->isAccessTokenExpired()) {
                    $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    file_put_contents($tokenPath, json_encode($this->client->getAccessToken()));
                }
            } else {
                throw new \RuntimeException(
                    "Gmail token not found. Run: php artisan legal:gmail-auth"
                );
            }
        }

        return new Gmail($this->client);
    }
}
```

**Step 5: Implementiraj Gmail Auth komandu**

```php
// app/Console/Commands/LegalArtillery/GmailAuthCommand.php
<?php

namespace App\Console\Commands\LegalArtillery;

use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Illuminate\Console\Command;

class GmailAuthCommand extends Command
{
    protected $signature = 'legal:gmail-auth';
    protected $description = 'Authenticate with Gmail API for Legal Artillery dispatch';

    public function handle(): int
    {
        $client = new GoogleClient();
        $client->setApplicationName('Legal Artillery');
        $client->setScopes([Gmail::GMAIL_COMPOSE, Gmail::GMAIL_SEND]);
        $client->setAuthConfig(config('legal-artillery.gmail.credentials_path'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $authUrl = $client->createAuthUrl();
        $this->info("Otvori ovaj URL u pregledniku:");
        $this->line($authUrl);

        $code = $this->ask('Unesi autorizacijski kod');

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            $this->error("Greška: {$token['error']}");
            return self::FAILURE;
        }

        $tokenPath = config('legal-artillery.gmail.token_path');
        $tokenDir = dirname($tokenPath);
        if (!is_dir($tokenDir)) {
            mkdir($tokenDir, 0700, true);
        }

        file_put_contents($tokenPath, json_encode($token));
        $this->info("Token spremljen: {$tokenPath}");

        return self::SUCCESS;
    }
}
```

**Step 6: Pokreni test — PASS**

```bash
php artisan test tests/Unit/Services/LegalArtillery/GmailDispatcherTest.php --verbose
```

**Step 7: Commit**

```bash
git add app/Services/LegalArtillery/GmailDispatcher.php app/Console/Commands/LegalArtillery/ tests/Unit/Services/LegalArtillery/GmailDispatcherTest.php composer.json composer.lock
git commit -m "feat: GmailDispatcher with OAuth2 auth and MIME attachment support"
```

---

## Faza 5: Artisan Komande — Okidači Artiljerije

### Task 6: FireCommand — glavna komanda za paljbu

**Opis:** `php artisan legal:fire predsjednik_suda` — generira dopis, renderira .docx, opcionalno šalje mailom.

**Files:**
- Create: `app/Console/Commands/LegalArtillery/FireCommand.php`
- Test: `tests/Feature/Commands/LegalArtillery/FireCommandTest.php`

**Step 1: Napiši test**

```php
// tests/Feature/Commands/LegalArtillery/FireCommandTest.php
<?php

namespace Tests\Feature\Commands\LegalArtillery;

use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\GmailDispatcher;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\RecursiveDocumentWriter;
use Mockery;
use Tests\TestCase;

class FireCommandTest extends TestCase
{
    public function test_fire_generates_document(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn(json_encode([
            'sections' => [
                ['key' => 'heading', 'title' => 'Test', 'guidance' => 'Test'],
            ],
        ]), 'Test content', 'Final polished content');

        $this->app->instance(LlmClient::class, $llm);

        $this->artisan('legal:fire', ['profile' => 'predsjednik_suda', '--no-send' => true])
            ->expectsOutput('Artiljerija pogodila cilj!')
            ->assertExitCode(0);
    }

    public function test_fire_lists_profiles(): void
    {
        $this->artisan('legal:fire', ['--list' => true])
            ->expectsOutputToContain('predsjednik_suda')
            ->expectsOutputToContain('dorh_production')
            ->expectsOutputToContain('ustavni_sud')
            ->assertExitCode(0);
    }

    public function test_fire_rejects_invalid_profile(): void
    {
        $this->artisan('legal:fire', ['profile' => 'invalid_key'])
            ->expectsOutputToContain('ne postoji')
            ->assertExitCode(1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Pokreni test — FAIL**

**Step 3: Implementiraj FireCommand**

```php
// app/Console/Commands/LegalArtillery/FireCommand.php
<?php

namespace App\Console\Commands\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\GmailDispatcher;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\RecursiveDocumentWriter;
use Illuminate\Console\Command;

class FireCommand extends Command
{
    protected $signature = 'legal:fire
        {profile? : Ključ profila dokumenta (npr. predsjednik_suda)}
        {--list : Prikaži sve dostupne profile}
        {--no-send : Generiraj dokument bez slanja emailom}
        {--draft : Spremi kao Gmail draft umjesto slanja}
        {--to= : Override email adrese primatelja}
        {--context=* : Dodatan kontekst (key=value)}
        {--dry-run : Samo generiraj outline bez pisanja}';

    protected $description = '🔥 Pravna Artiljerija — generiraj i ispali pravni dopis';

    public function handle(): int
    {
        // List mode
        if ($this->option('list')) {
            return $this->listProfiles();
        }

        $profileKey = $this->argument('profile');
        if (!$profileKey) {
            $this->error('Navedi profil. Koristi --list za popis dostupnih profila.');
            return self::FAILURE;
        }

        // Validate profile
        try {
            $profile = DocumentProfile::fromConfig($profileKey);
        } catch (\InvalidArgumentException $e) {
            $this->error("Profil '{$profileKey}' ne postoji. Koristi --list za popis.");
            return self::FAILURE;
        }

        $context = CaseContext::fromConfig();

        $this->info("🎯 Cilj: {$profile->name}");
        $this->info("📍 Primatelj: {$profile->recipientLine()}");
        $this->info("⚖️  Pravni temelj: " . implode(', ', $profile->legalBasis));
        $this->newLine();

        // Parse additional context
        $additionalContext = [];
        foreach ($this->option('context') as $ctx) {
            [$key, $value] = explode('=', $ctx, 2);
            $additionalContext[$key] = $value;
        }

        // Step 1: Generate
        $this->info('📝 Faza 1: Rekurzivno generiranje sadržaja...');
        $llm = app(LlmClient::class);
        $writer = new RecursiveDocumentWriter($llm);

        if ($this->option('dry-run')) {
            $outline = $writer->generateOutline($profile, $context, $additionalContext);
            $this->info('📋 Outline:');
            foreach ($outline['sections'] as $i => $section) {
                $this->line("  " . ($i + 1) . ". [{$section['key']}] {$section['title']}");
                $this->line("     ↳ {$section['guidance']}");
            }
            return self::SUCCESS;
        }

        $result = $writer->generate($profile, $context, $additionalContext);
        $this->info("✅ Generirano: " . count($result['sections']) . " sekcija");

        // Step 2: Render DOCX
        $this->info('📄 Faza 2: Renderiranje Word dokumenta...');
        $renderer = new DocxRenderer();
        $docxPath = $renderer->render($profile, $context, $result);
        $this->info("✅ Dokument: {$docxPath}");

        // Step 3: Send (optional)
        if (!$this->option('no-send') && config('legal-artillery.gmail.enabled')) {
            $this->info('📧 Faza 3: Slanje putem Gmail...');
            $dispatcher = new GmailDispatcher();
            $to = $this->option('to') ?? null;
            $isDraft = $this->option('draft');

            $sendResult = $dispatcher->send($profile, $context, $docxPath, $to, $isDraft);

            if ($sendResult['status'] === 'sent') {
                $this->info("✅ Poslano na: {$sendResult['to']}");
            } else {
                $this->info("✅ Draft spremljen (ID: {$sendResult['id']})");
            }
        }

        $this->newLine();
        $this->info('💥 Artiljerija pogodila cilj!');

        return self::SUCCESS;
    }

    private function listProfiles(): int
    {
        $profiles = DocumentProfile::all();
        $this->info('📋 Dostupni profili pravne artiljerije:');
        $this->newLine();

        $rows = [];
        foreach ($profiles as $profile) {
            $rows[] = [
                $profile->key,
                $profile->name,
                $profile->recipient['institution'] ?? '-',
                $profile->metadata['priority'] ?? '-',
                $profile->tone,
            ];
        }

        $this->table(
            ['Ključ', 'Naziv', 'Forum', 'Prioritet', 'Ton'],
            $rows,
        );

        $this->newLine();
        $this->line('Korištenje: php artisan legal:fire <ključ> [--no-send] [--draft] [--dry-run]');

        return self::SUCCESS;
    }
}
```

**Step 4: Registriraj LlmClient u ServiceProvider**

```php
// Dodaj u app/Providers/AppServiceProvider.php → register()
$this->app->singleton(\App\Services\LegalArtillery\LlmClient::class, function () {
    return \App\Services\LegalArtillery\LlmClient::fromConfig();
});
```

**Step 5: Pokreni test — PASS**

```bash
php artisan test tests/Feature/Commands/LegalArtillery/FireCommandTest.php --verbose
```

**Step 6: Commit**

```bash
git add app/Console/Commands/LegalArtillery/FireCommand.php app/Providers/AppServiceProvider.php tests/Feature/Commands/LegalArtillery/
git commit -m "feat: legal:fire Artisan command - the artillery trigger"
```

---

### Task 7: BarrageCommand — masovna paljba po svim ciljevima

**Opis:** `php artisan legal:barrage` — generira i šalje SVE dopise istovremeno. Paralelni napad.

**Files:**
- Create: `app/Console/Commands/LegalArtillery/BarrageCommand.php`
- Test: `tests/Feature/Commands/LegalArtillery/BarrageCommandTest.php`

**Step 1: Napiši test**

```php
// tests/Feature/Commands/LegalArtillery/BarrageCommandTest.php
<?php

namespace Tests\Feature\Commands\LegalArtillery;

use App\Services\LegalArtillery\LlmClient;
use Mockery;
use Tests\TestCase;

class BarrageCommandTest extends TestCase
{
    public function test_barrage_fires_selected_profiles(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn(
            json_encode(['sections' => [['key' => 'h', 'title' => 'H', 'guidance' => 'H']]]),
            'Content', 'Polished'
        );
        $this->app->instance(LlmClient::class, $llm);

        $this->artisan('legal:barrage', [
            '--profiles' => 'predsjednik_suda,ombudsman',
            '--no-send' => true,
        ])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Implementiraj BarrageCommand**

```php
// app/Console/Commands/LegalArtillery/BarrageCommand.php
<?php

namespace App\Console\Commands\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\GmailDispatcher;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\RecursiveDocumentWriter;
use Illuminate\Console\Command;

class BarrageCommand extends Command
{
    protected $signature = 'legal:barrage
        {--profiles= : Comma-separated lista profila (default: sve immediate)}
        {--no-send : Samo generiraj bez slanja}
        {--draft : Spremi sve kao draftove}';

    protected $description = '💣 Masovna paljba — generiraj i ispali sve dopise odjednom';

    public function handle(): int
    {
        $context = CaseContext::fromConfig();
        $llm = app(LlmClient::class);
        $writer = new RecursiveDocumentWriter($llm);
        $renderer = new DocxRenderer();

        // Determine which profiles to fire
        $profileKeys = $this->resolveProfiles();

        $this->info("💣 BARRAGE MODE — Ciljeva: " . count($profileKeys));
        $this->newLine();

        $results = [];
        foreach ($profileKeys as $key) {
            try {
                $profile = DocumentProfile::fromConfig($key);
                $this->info("🎯 Paljba: {$profile->name}...");

                $genResult = $writer->generate($profile, $context);
                $docxPath = $renderer->render($profile, $context, $genResult);

                $sendResult = ['status' => 'local', 'path' => $docxPath];
                if (!$this->option('no-send') && config('legal-artillery.gmail.enabled')) {
                    $dispatcher = new GmailDispatcher();
                    $sendResult = $dispatcher->send(
                        $profile, $context, $docxPath,
                        asDraft: $this->option('draft')
                    );
                }

                $results[] = [
                    'profile' => $key,
                    'status' => '✅ ' . ($sendResult['status'] ?? 'generated'),
                    'path' => $docxPath,
                ];
                $this->info("  ✅ Pogodak: {$key}");

            } catch (\Exception $e) {
                $results[] = [
                    'profile' => $key,
                    'status' => '❌ ' . $e->getMessage(),
                    'path' => '-',
                ];
                $this->error("  ❌ Promašaj: {$key} — {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->table(['Profil', 'Status', 'Putanja'], $results);

        $hits = count(array_filter($results, fn($r) => str_starts_with($r['status'], '✅')));
        $this->newLine();
        $this->info("💥 Rezultat: {$hits}/" . count($results) . " pogodaka");

        return self::SUCCESS;
    }

    private function resolveProfiles(): array
    {
        if ($this->option('profiles')) {
            return explode(',', $this->option('profiles'));
        }

        // Default: all "immediate" priority profiles
        return collect(DocumentProfile::all())
            ->filter(fn($p) => ($p->metadata['priority'] ?? '') === 'immediate')
            ->map(fn($p) => $p->key)
            ->values()
            ->toArray();
    }
}
```

**Step 3: Pokreni test — PASS**

```bash
php artisan test tests/Feature/Commands/LegalArtillery/ --verbose
```

**Step 4: Commit**

```bash
git add app/Console/Commands/LegalArtillery/BarrageCommand.php tests/Feature/Commands/LegalArtillery/BarrageCommandTest.php
git commit -m "feat: legal:barrage command - fire all artillery simultaneously"
```

---

## Faza 6: Vizra ADK Integracija

### Task 8: Registracija u Vizra ADK agent registry

**Opis:** Integracija LegalArtilleryAgent kao Vizra agent — tako da ga sustav prepoznaje i može orkestrirati s drugim agentima.

**Files:**
- Create: `app/Agents/LegalArtilleryAgent.php`
- Modify: `config/vizra-adk.php` (dodaj agent registraciju)
- Test: `tests/Unit/Agents/LegalArtilleryAgentTest.php`

**Step 1: Napiši test**

```php
// tests/Unit/Agents/LegalArtilleryAgentTest.php
<?php

namespace Tests\Unit\Agents;

use App\Agents\LegalArtilleryAgent;
use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\LlmClient;
use Mockery;
use Tests\TestCase;

class LegalArtilleryAgentTest extends TestCase
{
    public function test_agent_lists_available_profiles(): void
    {
        $agent = app(LegalArtilleryAgent::class);
        $profiles = $agent->availableProfiles();

        $this->assertNotEmpty($profiles);
        $this->assertArrayHasKey('predsjednik_suda', $profiles);
        $this->assertArrayHasKey('ustavni_sud', $profiles);
    }

    public function test_agent_executes_fire_action(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn(
            json_encode(['sections' => [['key' => 'h', 'title' => 'T', 'guidance' => 'G']]]),
            'Content',
            'Polished',
        );
        $this->app->instance(LlmClient::class, $llm);

        $agent = app(LegalArtilleryAgent::class);
        $result = $agent->fire('predsjednik_suda', sendEmail: false);

        $this->assertEquals('predsjednik_suda', $result['profile_key']);
        $this->assertArrayHasKey('docx_path', $result);
        $this->assertFileExists($result['docx_path']);

        // Cleanup
        if (file_exists($result['docx_path'])) {
            unlink($result['docx_path']);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Implementiraj LegalArtilleryAgent**

```php
// app/Agents/LegalArtilleryAgent.php
<?php

namespace App\Agents;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\GmailDispatcher;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\RecursiveDocumentWriter;
use Illuminate\Support\Facades\Log;

class LegalArtilleryAgent
{
    public function __construct(
        private readonly LlmClient $llm,
    ) {}

    public function availableProfiles(): array
    {
        $profiles = DocumentProfile::all();
        $result = [];
        foreach ($profiles as $profile) {
            $result[$profile->key] = [
                'name' => $profile->name,
                'forum' => $profile->recipient['institution'] ?? '-',
                'priority' => $profile->metadata['priority'] ?? '-',
                'legal_basis' => $profile->legalBasis,
            ];
        }
        return $result;
    }

    public function fire(
        string $profileKey,
        array $additionalContext = [],
        bool $sendEmail = false,
        bool $asDraft = false,
        ?string $toEmail = null,
    ): array {
        $profile = DocumentProfile::fromConfig($profileKey);
        $context = CaseContext::fromConfig();

        Log::info('LegalArtilleryAgent: FIRE', ['profile' => $profileKey]);

        // Generate
        $writer = new RecursiveDocumentWriter($this->llm);
        $genResult = $writer->generate($profile, $context, $additionalContext);

        // Render
        $renderer = new DocxRenderer();
        $docxPath = $renderer->render($profile, $context, $genResult);

        // Send
        $sendResult = null;
        if ($sendEmail && config('legal-artillery.gmail.enabled')) {
            $dispatcher = new GmailDispatcher();
            $sendResult = $dispatcher->send($profile, $context, $docxPath, $toEmail, $asDraft);
        }

        return [
            'profile_key' => $profileKey,
            'profile_name' => $profile->name,
            'docx_path' => $docxPath,
            'sections_count' => count($genResult['sections']),
            'send_result' => $sendResult,
            'generated_at' => $genResult['generated_at'],
        ];
    }

    public function barrage(
        ?array $profileKeys = null,
        bool $sendEmail = false,
        bool $asDraft = true,
    ): array {
        $keys = $profileKeys ?? collect(DocumentProfile::all())
            ->filter(fn($p) => ($p->metadata['priority'] ?? '') === 'immediate')
            ->map(fn($p) => $p->key)
            ->toArray();

        $results = [];
        foreach ($keys as $key) {
            try {
                $results[$key] = $this->fire($key, sendEmail: $sendEmail, asDraft: $asDraft);
            } catch (\Exception $e) {
                Log::error("LegalArtilleryAgent: Failed {$key}", ['error' => $e->getMessage()]);
                $results[$key] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
```

**Step 3: Pokreni test — PASS**

```bash
php artisan test tests/Unit/Agents/LegalArtilleryAgentTest.php --verbose
```

**Step 4: Commit**

```bash
git add app/Agents/LegalArtilleryAgent.php tests/Unit/Agents/LegalArtilleryAgentTest.php
git commit -m "feat: LegalArtilleryAgent with fire/barrage capabilities, Vizra-ready"
```

---

## Korištenje — Quick Reference

```bash
# Setup Gmail (jednom)
php artisan legal:gmail-auth

# Pregledaj sve profile
php artisan legal:fire --list

# Generiraj samo outline (dry run)
php artisan legal:fire predsjednik_suda --dry-run

# Generiraj dokument bez slanja
php artisan legal:fire predsjednik_suda --no-send

# Generiraj i spremi kao Gmail draft
php artisan legal:fire ustavni_sud --draft

# Generiraj i odmah pošalji
php artisan legal:fire ombudsman

# Šalji na custom adresu
php artisan legal:fire ministarstvo_pravosudja --to=inspekcija@mpu.hr

# 💣 BARRAGE — sve "immediate" ciljeve odjednom
php artisan legal:barrage --draft

# Barrage — specifični ciljevi
php artisan legal:barrage --profiles=predsjednik_suda,ombudsman,ministarstvo_pravosudja --no-send

# Barrage — SVE ciljeve
php artisan legal:barrage --profiles=predsjednik_suda,dorh_production,kazneni_sud_motion,ombudsman,ministarstvo_pravosudja,ustavni_sud,izdvajanje_dokaza --draft
```

## Matrica Prioriteta Paljbe

| Profil | Prioritet | Kad paliti |
|--------|-----------|------------|
| `predsjednik_suda` | 🔴 Odmah | Prvi korak — novi zahtjev PZ čl.150 st.4 |
| `dorh_production` | 🔴 Odmah | Paralelno — kroz obranu u kaznenom |
| `ombudsman` | 🔴 Odmah | Paralelno — pritužba na zlouporabu |
| `ministarstvo_pravosudja` | 🔴 Odmah | Paralelno — upravni nadzor |
| `kazneni_sud_motion` | 🟡 Prije optužnice | Kad stigne optužnica |
| `izdvajanje_dokaza` | 🟡 Na optužnom vijeću | Na raspravi / vijeću |
| `ustavni_sud` | 🟠 30 dana | Kad se iscrpe niži forumi (ili čl.62) |
| `echr_application` | 🔵 4 mjeseca | Tek nakon domaćih putova |
