# DOPUNSKE FAZE: Muniicija, Nišan, i Automatska Opskrba

> Faze 7–12 pretvaraju artiljeriju iz generičkog generatora u precizno navođeni sustav koji citira pravu praksu, prilože prave dokumente, i argumentira na razini koju sudac ne može ignorirati.

---

## Faza 7: Pravna Baza Znanja (Legal Knowledge Base)

### Task 9: Seeder za zakonske odredbe — PrecedentSeeder

**Opis:** Strukturirana baza pravnih odredbi koje agent koristi. Svaka odredba ima puni tekst, članak/stavak/točku, naziv zakona, i tagove koji je povezuju s profilima. Agent NE izmišlja citate — vuče ih iz baze.

**Files:**
- Create: `database/migrations/xxxx_create_legal_provisions_table.php`
- Create: `app/Models/LegalProvision.php`
- Create: `database/seeders/LegalProvisionsSeeder.php`
- Test: `tests/Unit/Models/LegalProvisionTest.php`

**Step 1: Napiši padajući test**

```php
// tests/Unit/Models/LegalProvisionTest.php
<?php

namespace Tests\Unit\Models;

use App\Models\LegalProvision;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LegalProvisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_provisions_by_law_and_article(): void
    {
        LegalProvision::factory()->create([
            'law_name' => 'Prekršajni zakon',
            'law_short' => 'PZ',
            'article' => '150',
            'paragraph' => '4',
        ]);

        $results = LegalProvision::forLaw('PZ')->forArticle('150')->get();
        $this->assertCount(1, $results);
    }

    public function test_finds_provisions_by_tag(): void
    {
        LegalProvision::factory()->create([
            'tags' => ['file_access', 'predsjednik_suda'],
        ]);

        $results = LegalProvision::withTag('file_access')->get();
        $this->assertCount(1, $results);
    }

    public function test_finds_provisions_for_profile(): void
    {
        LegalProvision::factory()->create([
            'tags' => ['predsjednik_suda'],
            'law_short' => 'PZ',
            'article' => '150',
        ]);
        LegalProvision::factory()->create([
            'tags' => ['ustavni_sud'],
            'law_short' => 'Ustav',
            'article' => '18',
        ]);

        $results = LegalProvision::forProfile('predsjednik_suda')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('PZ', $results->first()->law_short);
    }

    public function test_formats_citation_string(): void
    {
        $provision = LegalProvision::factory()->create([
            'law_name' => 'Prekršajni zakon',
            'law_short' => 'PZ',
            'article' => '150',
            'paragraph' => '1',
        ]);

        $this->assertEquals('PZ čl.150 st.1', $provision->shortCitation());
        $this->assertStringContainsString('Prekršajni zakon', $provision->fullCitation());
    }
}
```

**Step 2: Pokreni test — FAIL**

```bash
php artisan test tests/Unit/Models/LegalProvisionTest.php --verbose
```

**Step 3: Kreiraj migraciju**

```php
// database/migrations/xxxx_create_legal_provisions_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_provisions', function (Blueprint $table) {
            $table->id();
            $table->string('law_name');           // Prekršajni zakon
            $table->string('law_short', 20);      // PZ
            $table->string('article', 20);        // 150
            $table->string('paragraph', 20)->nullable(); // 1
            $table->string('point', 20)->nullable();     // 2
            $table->text('title')->nullable();     // Naslov članka
            $table->text('full_text');             // Puni tekst odredbe
            $table->text('interpretation')->nullable(); // Kako se tumači u kontekstu
            $table->jsonb('tags')->default('[]');  // ['file_access', 'predsjednik_suda']
            $table->string('source_url')->nullable();
            $table->timestamps();

            $table->index('law_short');
            $table->index('article');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_provisions');
    }
};
```

**Step 4: Implementiraj model**

```php
// app/Models/LegalProvision.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class LegalProvision extends Model
{
    use HasFactory;

    protected $fillable = [
        'law_name', 'law_short', 'article', 'paragraph', 'point',
        'title', 'full_text', 'interpretation', 'tags', 'source_url',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function scopeForLaw(Builder $query, string $short): Builder
    {
        return $query->where('law_short', $short);
    }

    public function scopeForArticle(Builder $query, string $article): Builder
    {
        return $query->where('article', $article);
    }

    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    public function scopeForProfile(Builder $query, string $profileKey): Builder
    {
        return $query->whereJsonContains('tags', $profileKey);
    }

    public function shortCitation(): string
    {
        $cite = "{$this->law_short} čl.{$this->article}";
        if ($this->paragraph) $cite .= " st.{$this->paragraph}";
        if ($this->point) $cite .= " toč.{$this->point}";
        return $cite;
    }

    public function fullCitation(): string
    {
        $cite = "članak {$this->article}.";
        if ($this->paragraph) $cite .= " stavak {$this->paragraph}.";
        if ($this->point) $cite .= " točka {$this->point}.";
        $cite .= " {$this->law_name}";
        return $cite;
    }
}
```

**Step 5: Kreiraj seeder s odredbama relevantnima za naš slučaj**

```php
// database/seeders/LegalProvisionsSeeder.php
<?php

namespace Database\Seeders;

use App\Models\LegalProvision;
use Illuminate\Database\Seeder;

class LegalProvisionsSeeder extends Seeder
{
    public function run(): void
    {
        $provisions = [
            // === PREKRŠAJNI ZAKON ===
            [
                'law_name' => 'Prekršajni zakon',
                'law_short' => 'PZ',
                'article' => '150',
                'paragraph' => '1',
                'title' => 'Razgledavanje i prepisivanje spisa',
                'full_text' => 'Stranke i sudionici u postupku imaju pravo razgledavati i prepisivati spise. To sud može dopustiti i svakomu drugom tko za to ima opravdani interes.',
                'interpretation' => 'Ključno: "svakomu drugom tko za to ima opravdani interes" — šire od stranačkog statusa iz čl.108. Adresat pretrage ima inherentni opravdani interes jer je njegov dom pretražen.',
                'tags' => ['file_access', 'predsjednik_suda', 'ombudsman', 'ministarstvo_pravosudja'],
            ],
            [
                'law_name' => 'Prekršajni zakon',
                'law_short' => 'PZ',
                'article' => '150',
                'paragraph' => '4',
                'title' => 'Razgledavanje spisa završenog postupka',
                'full_text' => 'Kad je postupak završen, dopuštenje za razgledavanje i prepisivanje spisa daje predsjednik suda.',
                'interpretation' => 'Spis Pp Prz-74/2025 arhiviran 10.7.2025 = postupak završen. Nadležan je predsjednik suda, NE sudac Bertok. Jurisdikcijska pogreška.',
                'tags' => ['file_access', 'predsjednik_suda', 'jurisdictional_error'],
            ],
            [
                'law_name' => 'Prekršajni zakon',
                'law_short' => 'PZ',
                'article' => '108',
                'paragraph' => null,
                'title' => 'Stranke u prekršajnom postupku',
                'full_text' => 'Stranke u prekršajnom postupku jesu ovlašteni tužitelj i okrivljenik.',
                'interpretation' => 'Sudac Bertok citira čl.108 da bi uskratila pristup — ali čl.150 st.1 IZRIJEKOM daje pristup i onima koji NISU stranke ("svakomu drugom s opravdanim interesom").',
                'tags' => ['file_access', 'counter_argument'],
            ],

            // === ZAKON O KAZNENOM POSTUPKU ===
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '184',
                'paragraph' => '5',
                'title' => 'Uvid u zapisnike o hitnim radnjama',
                'full_text' => 'Osumnjičenik i njegov branitelj imaju pravo razgledavati predmete koji služe kao dokaz te razgledavati zapisnike o radnjama iz članka 213. ovoga Zakona, koje su provedene bez nazočnosti osumnjičenika ili njegova branitelja, u roku od 30 dana od dana poduzimanja radnje.',
                'interpretation' => 'Pretraga doma = hitna radnja (čl.213 ZKP). Rok od 30 dana od 9.6.2025 istekao, ali pravo je POVRIJEĐENO — temelj za izdvajanje dokaza po čl.10.',
                'tags' => ['defense_rights', 'dorh_production', 'kazneni_sud_motion', 'izdvajanje_dokaza'],
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '10',
                'paragraph' => '2',
                'point' => '2',
                'title' => 'Nezakoniti dokazi — povreda prava obrane',
                'full_text' => 'Nezakoniti su dokazi oni koji su pribavljeni povredom Ustavom, zakonom ili međunarodnim pravom zajamčenih prava obrane.',
                'interpretation' => 'Uskrata uvida u spis pretrage = povreda prava obrane. Dokazi prikupljeni pretragom postaju nezakoniti ako obrana nije mogla provjeriti zakonitost naloga.',
                'tags' => ['evidence_exclusion', 'izdvajanje_dokaza', 'kazneni_sud_motion'],
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '10',
                'paragraph' => '2',
                'point' => '3',
                'title' => 'Nezakoniti dokazi — bitna povreda postupka',
                'full_text' => 'Nezakoniti su dokazi oni koji su pribavljeni povredom odredaba kaznenog postupka koje su izričito predviđene kao razlog nezakonitosti dokaza.',
                'interpretation' => 'Ako pretraga nije provedena sukladno ZKP čl.240-246, dokazi su nezakoniti po ovoj osnovi.',
                'tags' => ['evidence_exclusion', 'izdvajanje_dokaza'],
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '9',
                'paragraph' => '2',
                'title' => 'Dužnost prikupljanja i oslobađajućih dokaza',
                'full_text' => 'Državni odvjetnik je dužan s jednakom pozornošću ispitati i prikupiti kako dokaze koji terete osumnjičenika, odnosno okrivljenika, tako i one koji mu idu u korist.',
                'interpretation' => 'DORH mora pribaviti i oslobađajuće dokaze. Ako prekršajni spis sadrži elemente koji idu u korist obrane, DORH ga mora uključiti.',
                'tags' => ['dorh_production', 'disclosure'],
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '183',
                'paragraph' => null,
                'title' => 'Pravo na razgledavanje spisa',
                'full_text' => 'Pravo na razgledavanje spisa predmeta obuhvaća pravo razgledavanja, prepisivanja, preslikavanja ili snimanja spisa i njihovih priloga.',
                'interpretation' => 'Šire od samog "čitanja" — uključuje kopiranje i snimanje. Važno za zahtjev kaznenom sudu.',
                'tags' => ['defense_rights', 'kazneni_sud_motion', 'dorh_production'],
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '206.f',
                'paragraph' => null,
                'title' => 'Tajnost izvida',
                'full_text' => 'Podaci prikupljeni tijekom izvida su tajni.',
                'interpretation' => 'Sudac Bertok i Županijski sud se pozivaju na ovu odredbu — ali spis je ARHIVIRAN 10.7.2025. Izvidi su završeni. Odredba se NE primjenjuje na arhivirane spise.',
                'tags' => ['counter_argument', 'investigation_secrecy'],
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '342',
                'paragraph' => null,
                'title' => 'Bitne povrede odredaba kaznenog postupka',
                'full_text' => 'Bitna povreda odredaba kaznenog postupka postoji ako je povrijeđeno pravo obrane okrivljenika.',
                'interpretation' => 'Neobjelodanjivanje spisa pretrage = bitna povreda ako se ti dokazi koriste na sudu.',
                'tags' => ['dorh_production', 'defense_rights'],
            ],

            // === USTAV RH ===
            [
                'law_name' => 'Ustav Republike Hrvatske',
                'law_short' => 'Ustav',
                'article' => '18',
                'paragraph' => '1',
                'title' => 'Pravo na žalbu',
                'full_text' => 'Jamči se pravo na žalbu protiv pojedinačnih pravnih akata donesenih u postupku prvog stupnja pred sudom ili drugim ovlaštenim tijelom.',
                'interpretation' => 'Neformalna email odbijanja NE predstavljaju "pojedinačni pravni akt" s pravnom poukom — čime se onemogućuje žalba. Ustavnosudska praksa: U-III-3071/2006.',
                'tags' => ['constitutional', 'ustavni_sud', 'ombudsman', 'all_profiles'],
            ],
            [
                'law_name' => 'Ustav Republike Hrvatske',
                'law_short' => 'Ustav',
                'article' => '29',
                'paragraph' => '1',
                'title' => 'Pravično suđenje',
                'full_text' => 'Svatko ima pravo da zakonom ustanovljeni neovisni i nepristrani sud pravično i u razumnom roku odluči o njegovim pravima i obvezama.',
                'interpretation' => 'Odlučivanje emailom bez obrazloženja i pouke = nije pravično. Ustavnosudska praksa: U-III-2258/2018 — odluke MORAJU biti obrazložene.',
                'tags' => ['constitutional', 'ustavni_sud', 'fair_trial'],
            ],
            [
                'law_name' => 'Ustav Republike Hrvatske',
                'law_short' => 'Ustav',
                'article' => '34',
                'paragraph' => null,
                'title' => 'Nepovredivost doma',
                'full_text' => 'Dom je nepovrediv. Samo sud može obrazloženim pisanim nalogom utemeljenim na zakonu odrediti da se dom pretraži.',
                'interpretation' => 'Pretraga direktno zadire u ovo pravo — adresat pretrage ima ustavno pravo provjeriti je li nalog bio zakonit i obrazložen. Bez uvida u spis = pravo je iluzorno.',
                'tags' => ['constitutional', 'ustavni_sud', 'home_search', 'echr_application'],
            ],
            [
                'law_name' => 'Ustav Republike Hrvatske',
                'law_short' => 'Ustav',
                'article' => '19',
                'paragraph' => '1',
                'title' => 'Sudska kontrola zakonitosti',
                'full_text' => 'Pojedinačni akti državne uprave i tijela koja imaju javne ovlasti moraju biti utemeljeni na zakonu. Zajamčuje se sudska kontrola zakonitosti pojedinačnih akata upravnih vlasti i tijela koja imaju javne ovlasti.',
                'interpretation' => 'Odbijanje donošenja formalnog rješenja onemogućuje sudsku kontrolu zakonitosti.',
                'tags' => ['constitutional', 'ustavni_sud'],
            ],

            // === USTAVNI ZAKON ===
            [
                'law_name' => 'Ustavni zakon o Ustavnom sudu RH',
                'law_short' => 'UZUSRH',
                'article' => '62',
                'paragraph' => '1',
                'title' => 'Iznimka od iscrpljivanja pravnog puta (čl.63 pročišćeni)',
                'full_text' => 'Ustavni sud će pokrenuti postupak po ustavnoj tužbi i prije no što je iscrpljen pravni put, u slučaju kad se osporenim pojedinačnim aktom grubo vrijeđaju ustavna prava, a potpuno je razvidno da bi nepokretanjem ustavnosudskog postupka za podnositelja ustavne tužbe mogle nastati teške i nepopravljive posljedice.',
                'interpretation' => 'Kumulacija: (1) uskrata pristupa spisu + (2) nedonošenje rješenja + (3) prejudiciranje kaznene obrane = grubo vrijeđanje + teške posljedice.',
                'tags' => ['ustavni_sud', 'procedural', 'gross_violation'],
            ],

            // === ZAKON O SUDOVIMA ===
            [
                'law_name' => 'Zakon o sudovima',
                'law_short' => 'ZS',
                'article' => '72',
                'paragraph' => '6',
                'title' => 'Upravni nadzor — ispitivanje pritužbi građana',
                'full_text' => 'Upravni nadzor obuhvaća ispitivanje pritužbi građana na rad suda u pogledu odugovlačenja sudskog postupka, ponašanja sudaca ili drugog osoblja suda prema strankama tijekom postupka.',
                'interpretation' => 'Odbijanje donošenja rješenja = administrativna nepravilnost u "ponašanju sudaca prema strankama".',
                'tags' => ['ministarstvo_pravosudja', 'administrative'],
            ],

            // === ZAKON O PUČKOM PRAVOBRANITELJU ===
            [
                'law_name' => 'Zakon o pučkom pravobranitelju',
                'law_short' => 'ZPP',
                'article' => '4',
                'paragraph' => null,
                'title' => 'Nadležnost pučkog pravobranitelja',
                'full_text' => 'Pučki pravobranitelj štiti ustavna i zakonska prava građana u postupku pred tijelima državne uprave i tijelima s javnim ovlastima.',
                'interpretation' => 'Iako se pravobranitelj obično ne miješa u sudske predmete, systematsko uskraćivanje formalnih odluka prelazi granicu sudske neovisnosti u očitu zlouporabu.',
                'tags' => ['ombudsman'],
            ],
        ];

        foreach ($provisions as $p) {
            LegalProvision::updateOrCreate(
                [
                    'law_short' => $p['law_short'],
                    'article' => $p['article'],
                    'paragraph' => $p['paragraph'] ?? null,
                    'point' => $p['point'] ?? null,
                ],
                $p,
            );
        }
    }
}
```

**Step 6: Pokreni migraciju i seed, pokreni test — PASS**

```bash
php artisan migrate
php artisan db:seed --class=LegalProvisionsSeeder
php artisan test tests/Unit/Models/LegalProvisionTest.php --verbose
```

**Step 7: Commit**

```bash
git add database/migrations/ app/Models/LegalProvision.php database/seeders/LegalProvisionsSeeder.php tests/Unit/Models/LegalProvisionTest.php
git commit -m "feat: LegalProvision model with seeder — 16 provisions for Pp Prz-74 battle"
```

---

### Task 10: Seeder za sudsku praksu — PrecedentSeeder

**Opis:** Strukturirane presude — Ustavni sud, Vrhovni sud, ECHR. Svaka presuda ima citatnu referencu, ključni pravni stav, izvorni tekst citata (na jeziku presude), i tagove koji je povezuju s profilima i argumentima.

**Files:**
- Create: `database/migrations/xxxx_create_legal_precedents_table.php`
- Create: `app/Models/LegalPrecedent.php`
- Create: `database/seeders/LegalPrecedentsSeeder.php`
- Test: `tests/Unit/Models/LegalPrecedentTest.php`

**Step 1: Napiši padajući test**

```php
// tests/Unit/Models/LegalPrecedentTest.php
<?php

namespace Tests\Unit\Models;

use App\Models\LegalPrecedent;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LegalPrecedentTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_precedents_by_court(): void
    {
        LegalPrecedent::factory()->create(['court' => 'USRH']);
        LegalPrecedent::factory()->create(['court' => 'ECHR']);

        $this->assertCount(1, LegalPrecedent::byCourt('USRH')->get());
        $this->assertCount(1, LegalPrecedent::byCourt('ECHR')->get());
    }

    public function test_finds_precedents_for_profile(): void
    {
        LegalPrecedent::factory()->create(['tags' => ['ustavni_sud', 'right_to_appeal']]);
        LegalPrecedent::factory()->create(['tags' => ['echr_application', 'article_6']]);

        $results = LegalPrecedent::forProfile('ustavni_sud')->get();
        $this->assertCount(1, $results);
    }

    public function test_finds_precedents_by_argument_type(): void
    {
        LegalPrecedent::factory()->create(['argument_types' => ['equality_of_arms', 'file_access']]);

        $results = LegalPrecedent::forArgument('equality_of_arms')->get();
        $this->assertCount(1, $results);
    }

    public function test_formats_citation(): void
    {
        $p = LegalPrecedent::factory()->create([
            'case_number' => 'U-III-3071/2006',
            'court' => 'USRH',
            'decision_date' => '2009-03-18',
        ]);

        $this->assertStringContainsString('U-III-3071/2006', $p->citation());
    }

    public function test_returns_quotable_text(): void
    {
        $p = LegalPrecedent::factory()->create([
            'key_quote' => 'Stranke zbog postupanja po pogrešnoj uputi ne smiju trpjeti štetne posljedice.',
            'quote_language' => 'hr',
        ]);

        $this->assertNotEmpty($p->key_quote);
    }
}
```

**Step 2: Pokreni test — FAIL**

**Step 3: Kreiraj migraciju**

```php
// database/migrations/xxxx_create_legal_precedents_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_precedents', function (Blueprint $table) {
            $table->id();
            $table->string('case_number');                // U-III-3071/2006
            $table->string('court', 30);                  // USRH, VSRH, ECHR, VPS
            $table->string('court_full');                  // Ustavni sud RH
            $table->date('decision_date');
            $table->string('applicant')->nullable();       // Garcia Alva
            $table->string('respondent')->nullable();      // Germany
            $table->string('echr_app_number')->nullable(); // 23541/94
            $table->text('legal_issue');                   // Kratki opis pravnog pitanja
            $table->text('key_holding');                   // Ključni pravni stav
            $table->text('key_quote');                     // Citatni tekst iz presude
            $table->string('quote_language', 5)->default('hr'); // hr, en
            $table->text('relevance_to_case');             // Zašto je relevantno za Pp Prz-74
            $table->jsonb('articles_interpreted')->default('[]'); // ['Ustav čl.18', 'ECHR Art.6']
            $table->jsonb('argument_types')->default('[]'); // ['equality_of_arms', 'file_access']
            $table->jsonb('tags')->default('[]');           // ['ustavni_sud', 'echr_application']
            $table->string('source_url')->nullable();
            $table->string('nn_reference')->nullable();    // NN 42/2009
            $table->timestamps();

            $table->index('court');
            $table->index('case_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_precedents');
    }
};
```

**Step 4: Implementiraj model**

```php
// app/Models/LegalPrecedent.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class LegalPrecedent extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_number', 'court', 'court_full', 'decision_date',
        'applicant', 'respondent', 'echr_app_number',
        'legal_issue', 'key_holding', 'key_quote', 'quote_language',
        'relevance_to_case', 'articles_interpreted', 'argument_types',
        'tags', 'source_url', 'nn_reference',
    ];

    protected $casts = [
        'decision_date' => 'date',
        'articles_interpreted' => 'array',
        'argument_types' => 'array',
        'tags' => 'array',
    ];

    public function scopeByCourt(Builder $q, string $court): Builder
    {
        return $q->where('court', $court);
    }

    public function scopeForProfile(Builder $q, string $profileKey): Builder
    {
        return $q->whereJsonContains('tags', $profileKey);
    }

    public function scopeForArgument(Builder $q, string $argType): Builder
    {
        return $q->whereJsonContains('argument_types', $argType);
    }

    public function citation(): string
    {
        $cite = $this->case_number;
        if ($this->court === 'ECHR' && $this->applicant) {
            $cite = "{$this->applicant} v. {$this->respondent} ({$this->echr_app_number})";
        }
        $cite .= ", {$this->decision_date->format('d.m.Y.')}";
        if ($this->nn_reference) {
            $cite .= ", {$this->nn_reference}";
        }
        return $cite;
    }
}
```

**Step 5: Kreiraj seeder s presudama iz istraživanja**

```php
// database/seeders/LegalPrecedentsSeeder.php
<?php

namespace Database\Seeders;

use App\Models\LegalPrecedent;
use Illuminate\Database\Seeder;

class LegalPrecedentsSeeder extends Seeder
{
    public function run(): void
    {
        $precedents = [
            // === USTAVNI SUD RH ===
            [
                'case_number' => 'U-III-3071/2006',
                'court' => 'USRH',
                'court_full' => 'Ustavni sud Republike Hrvatske',
                'decision_date' => '2009-03-18',
                'legal_issue' => 'Pogrešna ili izostala pouka o pravnom lijeku',
                'key_holding' => 'Stranke ne smiju trpjeti štetne posljedice zbog pogrešne ili izostale upute o pravnom lijeku. Temeljni zahtjev vladavine prava je da sudovi poznaju propise i daju zakonitu pouku.',
                'key_quote' => 'Stranke zbog postupanja po pogrešnoj uputi o pravnom lijeku koju daju sudovi ne smiju trpjeti štetne posljedice.',
                'quote_language' => 'hr',
                'relevance_to_case' => 'A fortiori: ako pogrešna pouka krši čl.18, IZOSTALA pouka (email bez rješenja) je još teža povreda. Direktno primjenjivo na naš slučaj gdje nema nikakve pouke.',
                'articles_interpreted' => ['Ustav čl.18 st.1', 'Ustav čl.29 st.1'],
                'argument_types' => ['right_to_appeal', 'formal_decision_required', 'rule_of_law'],
                'tags' => ['ustavni_sud', 'predsjednik_suda', 'ombudsman', 'all_profiles'],
                'nn_reference' => 'NN 42/2009',
                'source_url' => 'https://narodne-novine.nn.hr/clanci/sluzbeni/2009_04_42_983.html',
            ],
            [
                'case_number' => 'U-III-2258/2018',
                'court' => 'USRH',
                'court_full' => 'Ustavni sud Republike Hrvatske',
                'decision_date' => '2020-02-26',
                'legal_issue' => 'Obveza obrazlaganja sudskih odluka',
                'key_holding' => 'Obrazloženja odluka moraju sadržavati dostatne, ozbiljne i relevantne razloge. Nedostatak takvih razloga upućuje na arbitrarnost.',
                'key_quote' => 'Prava zajamčena Ustavom bila bi iluzorna i teorijska, a ne stvarna i učinkovita, kada ne bi postojala obveza sudbene vlasti da svoje odluke obrazloži.',
                'quote_language' => 'hr',
                'relevance_to_case' => 'Email odbijenica ne sadrži NIKAKVO obrazloženje. Prema ovoj odluci, to je arbitrarno postupanje. Prava su "iluzorna" bez obrazloženja.',
                'articles_interpreted' => ['Ustav čl.29'],
                'argument_types' => ['reasoned_decision', 'fair_trial', 'arbitrariness'],
                'tags' => ['ustavni_sud', 'ombudsman', 'all_profiles'],
                'nn_reference' => 'NN 37/2020',
                'source_url' => 'https://narodne-novine.nn.hr/clanci/sluzbeni/2020_03_37_813.html',
            ],

            // === VRHOVNI SUD RH ===
            [
                'case_number' => 'I Kž-135/2018',
                'court' => 'VSRH',
                'court_full' => 'Vrhovni sud Republike Hrvatske',
                'decision_date' => '2018-05-27',
                'legal_issue' => 'Pretraga pod krinkom očevida — nezakoniti dokazi',
                'key_holding' => 'Pretraga provedena bez zakonite naredbe ili pod krinkom druge radnje čini sve prikupljene dokaze nezakonitima po ZKP čl.10.',
                'key_quote' => 'Postupanje koje je po svom sadržaju i značenju predstavljalo pretragu, a ne očevid, zahtijeva sudsku naredbu.',
                'quote_language' => 'hr',
                'relevance_to_case' => 'Ako ne možemo provjeriti naredbu jer nam uskraćuju spis, ne možemo utvrditi je li pretraga bila zakonita — što je samo po sebi osnova za izdvajanje.',
                'articles_interpreted' => ['ZKP čl.10 st.2 toč.3', 'ZKP čl.10 st.2 toč.4'],
                'argument_types' => ['evidence_exclusion', 'search_warrant_validity', 'procedural_violation'],
                'tags' => ['izdvajanje_dokaza', 'kazneni_sud_motion'],
                'source_url' => null,
            ],
            [
                'case_number' => 'I Kž-Us 113/10',
                'court' => 'VSRH',
                'court_full' => 'Vrhovni sud Republike Hrvatske',
                'decision_date' => '2010-09-28',
                'legal_issue' => 'Pravo žalbe na rješenje o odbijanju izdvajanja dokaza',
                'key_holding' => 'Protiv rješenja o odbijanju prijedloga za izdvajanje nezakonitih dokaza dopuštena je posebna žalba.',
                'key_quote' => 'Rješenje o odbijanju prijedloga za izdvajanje nezakonitih dokaza podliježe žalbi.',
                'quote_language' => 'hr',
                'relevance_to_case' => 'Važno za proceduru: ako sud odbije izdvajanje, postoji žalba — što otvara daljnji pravni put prema Ustavnom sudu.',
                'articles_interpreted' => ['ZKP čl.10'],
                'argument_types' => ['evidence_exclusion', 'right_to_appeal'],
                'tags' => ['izdvajanje_dokaza'],
            ],

            // === ECHR ===
            [
                'case_number' => 'Garcia Alva v. Germany',
                'court' => 'ECHR',
                'court_full' => 'European Court of Human Rights',
                'decision_date' => '2001-02-13',
                'applicant' => 'Garcia Alva',
                'respondent' => 'Germany',
                'echr_app_number' => '23541/94',
                'legal_issue' => 'Pristup dokaznom materijalu istrage — jednakost oružja',
                'key_holding' => 'Jednakost oružja nije osigurana ako je branitelju uskraćen pristup dokumentima u istražnom spisu koji su bitni za učinkovito osporavanje zakonitosti.',
                'key_quote' => 'Equality of arms is not ensured if counsel is denied access to those documents in the investigation file which are essential in order effectively to challenge the lawfulness.',
                'quote_language' => 'en',
                'relevance_to_case' => 'DIREKTNO PRIMJENJIVO: branitelju je uskraćen pristup spisu pretrage doma koji je bitan za osporavanje zakonitosti pretrage. Legitimni cilj istrage NE može opravdati bitna ograničenja prava obrane.',
                'articles_interpreted' => ['ECHR Art.5 §4', 'ECHR Art.6'],
                'argument_types' => ['equality_of_arms', 'file_access', 'defense_rights'],
                'tags' => ['echr_application', 'kazneni_sud_motion', 'izdvajanje_dokaza', 'ustavni_sud'],
            ],
            [
                'case_number' => 'Rowe and Davis v. UK',
                'court' => 'ECHR',
                'court_full' => 'European Court of Human Rights (Grand Chamber)',
                'decision_date' => '2000-02-16',
                'applicant' => 'Rowe and Davis',
                'respondent' => 'United Kingdom',
                'echr_app_number' => '28901/95',
                'legal_issue' => 'Obveza objelodanjivanja dokaza',
                'key_holding' => 'Tužiteljstvo mora objelodaniti obrani sve materijalne dokaze. Tužiteljstvo koje samo sudi o objelodanjivanju čini značajne pogreške koje utječu na pravičnost postupka.',
                'key_quote' => 'The prosecution acting as judge in their own cause committed a significant number of errors which affected the fairness of the proceedings.',
                'quote_language' => 'en',
                'relevance_to_case' => 'Sud koji sam odlučuje o uskrati pristupa bez formalnog postupka = "judge in their own cause". Identična situacija — sudac Bertok sama odlučuje bez formalne procedure.',
                'articles_interpreted' => ['ECHR Art.6 §1'],
                'argument_types' => ['prosecution_disclosure', 'fair_trial', 'equality_of_arms'],
                'tags' => ['echr_application', 'dorh_production', 'ustavni_sud'],
            ],
            [
                'case_number' => 'Jasper v. UK',
                'court' => 'ECHR',
                'court_full' => 'European Court of Human Rights',
                'decision_date' => '2000-02-16',
                'applicant' => 'Jasper',
                'respondent' => 'United Kingdom',
                'echr_app_number' => '27052/95',
                'legal_issue' => 'Ograničenja prava obrane — "strogo nužno" i "protuteža"',
                'key_holding' => 'Ograničenja prava obrane dopuštena samo ako su "strogo nužna" i "dovoljno kompenzirana postupcima pred sudom".',
                'key_quote' => 'Restrictions on defence rights are permissible only if strictly necessary and sufficiently counterbalanced by the procedures followed by the judicial authorities.',
                'quote_language' => 'en',
                'relevance_to_case' => 'Email odbijanje NE pruža NIKAKVU protutezu. Nema formalnog postupka, nema obrazloženja, nema mogućnosti žalbe = niti jedan od uvjeta iz Jasper nije zadovoljen.',
                'articles_interpreted' => ['ECHR Art.6 §1'],
                'argument_types' => ['proportionality', 'counterbalancing', 'defense_rights'],
                'tags' => ['echr_application', 'ustavni_sud'],
            ],
            [
                'case_number' => 'Doroż v. Poland',
                'court' => 'ECHR',
                'court_full' => 'European Court of Human Rights',
                'decision_date' => '2020-10-29',
                'applicant' => 'Doroż',
                'respondent' => 'Poland',
                'echr_app_number' => '71205/11',
                'legal_issue' => 'Proporcionalnost pretrage doma — nedostatni razlozi',
                'key_holding' => 'Pretraga doma koja nije opravdana "relevantnim i dostatnim razlozima" i koja ne poštuje načelo proporcionalnosti krši čl.8.',
                'key_quote' => 'The search of the applicant\'s residence was not justified by relevant and sufficient reasons and the principle of proportionality had not been complied with.',
                'quote_language' => 'en',
                'relevance_to_case' => 'Bez pristupa spisu NE MOŽEMO provjeriti jesu li razlozi za pretragu bili "relevantni i dostatni". Sud je dosudio EUR 10.000 naknade.',
                'articles_interpreted' => ['ECHR Art.8'],
                'argument_types' => ['search_proportionality', 'home_inviolability', 'damages'],
                'tags' => ['echr_application', 'ustavni_sud'],
            ],
            [
                'case_number' => 'F.S. v. Croatia',
                'court' => 'ECHR',
                'court_full' => 'European Court of Human Rights',
                'decision_date' => '2023-12-05',
                'applicant' => 'F.S.',
                'respondent' => 'Croatia',
                'echr_app_number' => '8857/16',
                'legal_issue' => 'Nedostatne protuteže u domaćem postupku',
                'key_holding' => 'Protuteže u domaćem postupku bile su "nedostatno učinkovite". Podnositelju nisu dani "nikakvi činjenični elementi" koji su doveli do odluke.',
                'key_quote' => 'The counterbalances in the domestic proceedings were insufficiently effective.',
                'quote_language' => 'en',
                'relevance_to_case' => 'PRESUDA PROTIV HRVATSKE — dokazuje da Hrvatska ima sistemski problem s učinkovitim pravnim sredstvima. Direktno primjenjivo na naš slučaj.',
                'articles_interpreted' => ['ECHR Art.6', 'ECHR Art.13'],
                'argument_types' => ['effective_remedy', 'systemic_violation', 'counterbalancing'],
                'tags' => ['echr_application'],
            ],
            [
                'case_number' => 'Đorđević v. Croatia',
                'court' => 'ECHR',
                'court_full' => 'European Court of Human Rights',
                'decision_date' => '2012-07-24',
                'applicant' => 'Đorđević',
                'respondent' => 'Croatia',
                'echr_app_number' => '41526/10',
                'legal_issue' => 'Nepostojanje učinkovitog pravnog sredstva',
                'key_holding' => 'Povreda čl.13 jer podnositelji nisu imali učinkovito pravno sredstvo za zaštitu svojih prava.',
                'key_quote' => 'The applicants had no effective remedy for the protection of their rights.',
                'quote_language' => 'en',
                'relevance_to_case' => 'Još jedna presuda protiv Hrvatske za čl.13 — sistemski obrazac. Neformalna email odbijanja = nepostojanje pravnog sredstva.',
                'articles_interpreted' => ['ECHR Art.13'],
                'argument_types' => ['effective_remedy', 'systemic_violation'],
                'tags' => ['echr_application'],
            ],
            [
                'case_number' => 'Horvat v. Croatia',
                'court' => 'ECHR',
                'court_full' => 'European Court of Human Rights',
                'decision_date' => '2001-07-26',
                'applicant' => 'Horvat',
                'respondent' => 'Croatia',
                'echr_app_number' => '51585/99',
                'legal_issue' => 'Nesigurnost pravnog sredstva u praksi',
                'key_holding' => 'Nepostojanje sudske prakse ukazuje na nesigurnost pravnog sredstva u praktičnom smislu.',
                'key_quote' => 'The absence of further case-law indicates the present uncertainty of a remedy in practical terms.',
                'quote_language' => 'en',
                'relevance_to_case' => 'Osnovna presuda protiv Hrvatske — kad pravno sredstvo u praksi ne postoji ili je nesigurno, čl.13 je povrijeđen.',
                'articles_interpreted' => ['ECHR Art.13'],
                'argument_types' => ['effective_remedy'],
                'tags' => ['echr_application'],
            ],
        ];

        foreach ($precedents as $p) {
            LegalPrecedent::updateOrCreate(
                ['case_number' => $p['case_number'], 'court' => $p['court']],
                $p,
            );
        }
    }
}
```

**Step 6: Pokreni test — PASS i commit**

```bash
php artisan migrate
php artisan db:seed --class=LegalPrecedentsSeeder
php artisan test tests/Unit/Models/LegalPrecedentTest.php --verbose
git add database/migrations/ app/Models/LegalPrecedent.php database/seeders/LegalPrecedentsSeeder.php tests/Unit/Models/
git commit -m "feat: LegalPrecedent model with 12 key precedents (USRH, VSRH, ECHR)"
```

---

## Faza 8: Prilog Pipeline (Attachment Pipeline)

### Task 11: AttachmentCollector — prikupljanje dokumenata za priloge

**Opis:** Svaki dopis može zahtijevati priloge — prethodne dopise, sudske odgovore, naredbu, izvješće. `AttachmentCollector` zna koji dokumenti postoje u sustavu, automatski ih prilaže, i generira "Popis priloga" sekciju.

**Files:**
- Create: `app/Services/LegalArtillery/AttachmentCollector.php`
- Modify: `config/legal-artillery.php` (dodaj `attachments` sekciju)
- Test: `tests/Unit/Services/LegalArtillery/AttachmentCollectorTest.php`

**Step 1: Napiši test**

```php
// tests/Unit/Services/LegalArtillery/AttachmentCollectorTest.php
<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Services\LegalArtillery\AttachmentCollector;
use Tests\TestCase;

class AttachmentCollectorTest extends TestCase
{
    public function test_resolves_attachments_for_profile(): void
    {
        $collector = new AttachmentCollector();
        $attachments = $collector->forProfile('ustavni_sud');

        $this->assertIsArray($attachments);
        // Ustavna tužba treba najviše priloga
        $this->assertNotEmpty($attachments);
    }

    public function test_generates_attachment_list_text(): void
    {
        $collector = new AttachmentCollector();
        $text = $collector->generateAttachmentListSection('ustavni_sud');

        $this->assertStringContainsString('Prilog', $text);
    }

    public function test_collects_existing_files(): void
    {
        // Kreiraj testni file
        $testDir = storage_path('app/legal-artillery/case-documents');
        if (!is_dir($testDir)) mkdir($testDir, 0755, true);
        file_put_contents("{$testDir}/naredba-Pp-Prz-74-2025.pdf", 'test');

        $collector = new AttachmentCollector();
        $files = $collector->collectExistingFiles();

        $this->assertNotEmpty($files);

        unlink("{$testDir}/naredba-Pp-Prz-74-2025.pdf");
    }
}
```

**Step 2: Implementiraj**

```php
// app/Services/LegalArtillery/AttachmentCollector.php
<?php

namespace App\Services\LegalArtillery;

use Illuminate\Support\Facades\Storage;

class AttachmentCollector
{
    private array $attachmentRegistry;
    private string $caseDocDir;

    public function __construct()
    {
        $this->attachmentRegistry = config('legal-artillery.attachments', []);
        $this->caseDocDir = storage_path('app/legal-artillery/case-documents');
    }

    /**
     * Vrati listu priloga potrebnih za profil.
     */
    public function forProfile(string $profileKey): array
    {
        $required = $this->attachmentRegistry[$profileKey] ?? $this->attachmentRegistry['default'] ?? [];
        $existing = $this->collectExistingFiles();

        return array_map(function (array $attachment) use ($existing) {
            $filename = $attachment['filename'] ?? null;
            $attachment['exists'] = $filename && isset($existing[$filename]);
            $attachment['path'] = $attachment['exists'] ? $existing[$filename] : null;
            return $attachment;
        }, $required);
    }

    /**
     * Generiraj "PRILOZI" sekciju za umetanje u dokument.
     */
    public function generateAttachmentListSection(string $profileKey): string
    {
        $attachments = $this->forProfile($profileKey);
        if (empty($attachments)) return '';

        $lines = ["PRILOZI:", ""];
        $i = 1;
        foreach ($attachments as $att) {
            $status = $att['exists'] ? '✓' : '[NEDOSTAJE]';
            $lines[] = "Prilog {$i}: {$att['description']} {$status}";
            $i++;
        }

        return implode("\n", $lines);
    }

    /**
     * Skeniraj direktorij za postojeće dokumente predmeta.
     */
    public function collectExistingFiles(): array
    {
        if (!is_dir($this->caseDocDir)) return [];

        $files = [];
        foreach (scandir($this->caseDocDir) as $file) {
            if ($file === '.' || $file === '..') continue;
            $files[$file] = "{$this->caseDocDir}/{$file}";
        }
        return $files;
    }

    /**
     * Vrati putanje do svih priloga za slanje emailom.
     */
    public function collectFilePaths(string $profileKey): array
    {
        return array_filter(
            array_column($this->forProfile($profileKey), 'path')
        );
    }
}
```

**Step 3: Dodaj attachments konfiguraciju**

Dodaj na kraj `config/legal-artillery.php` prije završnog `];`:

```php
    /*
    |--------------------------------------------------------------------------
    | Prilozi po profilima
    |--------------------------------------------------------------------------
    */
    'attachments' => [
        'default' => [
            ['description' => 'Zahtjev za uvid od 25.08.2025.', 'filename' => 'zahtjev-uvid-25-08-2025.pdf'],
            ['description' => 'Požurnica od 29.08.2025.', 'filename' => 'pozurnica-29-08-2025.pdf'],
            ['description' => 'Odbijenica suca Bertok od 03.09.2025.', 'filename' => 'odbijenica-bertok-03-09-2025.pdf'],
        ],

        'predsjednik_suda' => [
            ['description' => 'Prethodni zahtjevi za uvid (25.08., 29.08., 04.09.2025.)', 'filename' => 'zahtjevi-kompilacija.pdf'],
            ['description' => 'Odgovor suca Bertok od 03.09.2025.', 'filename' => 'odbijenica-bertok-03-09-2025.pdf'],
            ['description' => 'Email predsjednice suda od 05.09.2025.', 'filename' => 'email-predsjednica-05-09-2025.pdf'],
            ['description' => 'Odgovor Županijskog suda od 17.09.2025.', 'filename' => 'zupanijski-sud-odgovor-17-09-2025.pdf'],
        ],

        'ustavni_sud' => [
            ['description' => 'Zahtjev za uvid od 25.08.2025.', 'filename' => 'zahtjev-uvid-25-08-2025.pdf'],
            ['description' => 'Požurnica od 29.08.2025.', 'filename' => 'pozurnica-29-08-2025.pdf'],
            ['description' => 'Žurna predstavka predsjednici suda od 01.09.2025.', 'filename' => 'zurna-predstavka-01-09-2025.pdf'],
            ['description' => 'Odbijenica suca Bertok od 03.09.2025. (Pp Prz-74/2025-7)', 'filename' => 'odbijenica-bertok-03-09-2025.pdf'],
            ['description' => 'Ponovljeni zahtjev od 04.09.2025.', 'filename' => 'ponovljeni-zahtjev-04-09-2025.pdf'],
            ['description' => 'Email predsjednice suda od 05.09.2025.', 'filename' => 'email-predsjednica-05-09-2025.pdf'],
            ['description' => 'Zahtjev za donošenje rješenja od 09.09.2025.', 'filename' => 'zahtjev-rjesenje-09-09-2025.pdf'],
            ['description' => 'Zahtjev za upravni nadzor Županijskom sudu od 12.09.2025.', 'filename' => 'upravni-nadzor-12-09-2025.pdf'],
            ['description' => 'Odgovor Županijskog suda od 17.09.2025.', 'filename' => 'zupanijski-sud-odgovor-17-09-2025.pdf'],
            ['description' => 'Preslika e-Predmet ispisa — spis Pp Prz-74/2025', 'filename' => 'e-predmet-ispis.pdf'],
        ],

        'echr_application' => [
            ['description' => 'Complete chronological file of domestic proceedings (Croatian + English translation)', 'filename' => 'domestic-proceedings-bundle.pdf'],
            ['description' => 'Power of attorney (if represented)', 'filename' => 'power-of-attorney.pdf'],
            ['description' => 'All domestic court decisions and responses', 'filename' => 'domestic-decisions-bundle.pdf'],
        ],

        'ombudsman' => [
            ['description' => 'Kronološki pregled korespondencije sa sudom', 'filename' => 'kronologija-korespondencije.pdf'],
            ['description' => 'Odbijenica suca Bertok od 03.09.2025.', 'filename' => 'odbijenica-bertok-03-09-2025.pdf'],
            ['description' => 'Email predsjednice suda od 05.09.2025. (neformalna odluka)', 'filename' => 'email-predsjednica-05-09-2025.pdf'],
            ['description' => 'Odgovor Županijskog suda od 17.09.2025.', 'filename' => 'zupanijski-sud-odgovor-17-09-2025.pdf'],
        ],

        'dorh_production' => [
            ['description' => 'Preslika naredbe za pretragu (ako dostupna)', 'filename' => 'naredba-pretraga.pdf'],
            ['description' => 'Dokaz o odbijanju pristupa prekršajnom spisu', 'filename' => 'odbijenica-bertok-03-09-2025.pdf'],
        ],

        'ministarstvo_pravosudja' => [
            ['description' => 'Kronološki prikaz korespondencije', 'filename' => 'kronologija-korespondencije.pdf'],
            ['description' => 'Email odgovori suda (neformalne odluke)', 'filename' => 'email-predsjednica-05-09-2025.pdf'],
            ['description' => 'Odgovor Županijskog suda od 17.09.2025.', 'filename' => 'zupanijski-sud-odgovor-17-09-2025.pdf'],
        ],

        'izdvajanje_dokaza' => [
            ['description' => 'Dokaz o uskrati pristupa spisu pretrage', 'filename' => 'odbijenica-bertok-03-09-2025.pdf'],
            ['description' => 'Zahtjevi za uvid (25.08., 29.08., 04.09., 09.09.2025.)', 'filename' => 'zahtjevi-kompilacija.pdf'],
        ],

        'kazneni_sud_motion' => [
            ['description' => 'Dokaz da je prekršajni spis arhiviran', 'filename' => 'e-predmet-ispis.pdf'],
            ['description' => 'Dokaz o odbijanju pristupa', 'filename' => 'odbijenica-bertok-03-09-2025.pdf'],
        ],
    ],
```

**Step 4: Pokreni test — PASS, commit**

```bash
php artisan test tests/Unit/Services/LegalArtillery/AttachmentCollectorTest.php --verbose
git add app/Services/LegalArtillery/AttachmentCollector.php config/legal-artillery.php tests/Unit/Services/LegalArtillery/AttachmentCollectorTest.php
git commit -m "feat: AttachmentCollector with per-profile attachment registry and file scanning"
```

---

## Faza 9: Kontekstualni Injector — Agent Vidi Praksu i Zakone

### Task 12: ProfileContextBuilder — spajanje svega u LLM prompt

**Opis:** Prije nego agent generira dopis, `ProfileContextBuilder` skuplja sve relevantne zakonske odredbe, sudsku praksu, i prilog-listu za taj profil, i pakira ih u strukturirani kontekst koji se injektira u LLM prompt. Ovo je ono što pretvara generičan AI output u precizno navođenu artiljeriju.

**Files:**
- Create: `app/Services/LegalArtillery/ProfileContextBuilder.php`
- Modify: `app/Services/LegalArtillery/RecursiveDocumentWriter.php` (integriraj builder)
- Test: `tests/Unit/Services/LegalArtillery/ProfileContextBuilderTest.php`

**Step 1: Napiši test**

```php
// tests/Unit/Services/LegalArtillery/ProfileContextBuilderTest.php
<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\DocumentProfile;
use App\Models\LegalPrecedent;
use App\Models\LegalProvision;
use App\Services\LegalArtillery\ProfileContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\LegalProvisionsSeeder::class);
        $this->seed(\Database\Seeders\LegalPrecedentsSeeder::class);
    }

    public function test_builds_context_for_predsjednik_suda(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $builder = new ProfileContextBuilder();
        $context = $builder->build($profile);

        $this->assertArrayHasKey('provisions', $context);
        $this->assertArrayHasKey('precedents', $context);
        $this->assertArrayHasKey('attachments_section', $context);
        $this->assertArrayHasKey('injected_prompt', $context);
        $this->assertNotEmpty($context['provisions']);
    }

    public function test_builds_context_for_ustavni_sud(): void
    {
        $profile = DocumentProfile::fromConfig('ustavni_sud');
        $builder = new ProfileContextBuilder();
        $context = $builder->build($profile);

        // Ustavna tužba treba najviše prakse i odredbi
        $this->assertNotEmpty($context['precedents']);
        $this->assertNotEmpty($context['provisions']);
    }

    public function test_builds_context_for_echr(): void
    {
        $profile = DocumentProfile::fromConfig('echr_application');
        $builder = new ProfileContextBuilder();
        $context = $builder->build($profile);

        // ECHR treba ECHR presude
        $echrPrecedents = array_filter(
            $context['precedents'],
            fn($p) => $p['court'] === 'ECHR'
        );
        $this->assertNotEmpty($echrPrecedents);
    }

    public function test_injected_prompt_contains_citations(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $builder = new ProfileContextBuilder();
        $context = $builder->build($profile);

        $this->assertStringContainsString('čl.150', $context['injected_prompt']);
    }

    public function test_injected_prompt_contains_precedent_quotes(): void
    {
        $profile = DocumentProfile::fromConfig('ustavni_sud');
        $builder = new ProfileContextBuilder();
        $context = $builder->build($profile);

        // Treba sadržavati citatne navode iz presuda
        $this->assertStringContainsString('U-III-3071/2006', $context['injected_prompt']);
    }
}
```

**Step 2: Implementiraj ProfileContextBuilder**

```php
// app/Services/LegalArtillery/ProfileContextBuilder.php
<?php

namespace App\Services\LegalArtillery;

use App\DTOs\DocumentProfile;
use App\Models\LegalPrecedent;
use App\Models\LegalProvision;

class ProfileContextBuilder
{
    public function build(DocumentProfile $profile): array
    {
        $provisions = $this->loadProvisions($profile);
        $precedents = $this->loadPrecedents($profile);
        $attachmentCollector = new AttachmentCollector();
        $attachmentsSection = $attachmentCollector->generateAttachmentListSection($profile->key);

        $injectedPrompt = $this->buildInjectedPrompt($provisions, $precedents, $attachmentsSection);

        return [
            'provisions' => $provisions,
            'precedents' => $precedents,
            'attachments_section' => $attachmentsSection,
            'injected_prompt' => $injectedPrompt,
        ];
    }

    private function loadProvisions(DocumentProfile $profile): array
    {
        // Dohvati odredbe taggirane za ovaj profil + one tagirane za sve
        $provisions = LegalProvision::query()
            ->where(function ($q) use ($profile) {
                $q->whereJsonContains('tags', $profile->key)
                  ->orWhereJsonContains('tags', 'all_profiles');
            })
            ->get();

        return $provisions->map(fn($p) => [
            'citation' => $p->shortCitation(),
            'full_citation' => $p->fullCitation(),
            'full_text' => $p->full_text,
            'interpretation' => $p->interpretation,
            'law_name' => $p->law_name,
        ])->toArray();
    }

    private function loadPrecedents(DocumentProfile $profile): array
    {
        $precedents = LegalPrecedent::query()
            ->where(function ($q) use ($profile) {
                $q->whereJsonContains('tags', $profile->key)
                  ->orWhereJsonContains('tags', 'all_profiles');
            })
            ->orderBy('decision_date', 'desc')
            ->get();

        return $precedents->map(fn($p) => [
            'citation' => $p->citation(),
            'court' => $p->court,
            'key_holding' => $p->key_holding,
            'key_quote' => $p->key_quote,
            'quote_language' => $p->quote_language,
            'relevance' => $p->relevance_to_case,
        ])->toArray();
    }

    private function buildInjectedPrompt(array $provisions, array $precedents, string $attachments): string
    {
        $prompt = "## PRAVNA BAZA — KORISTI PRECIZNO\n\n";

        // Odredbe
        $prompt .= "### Zakonske odredbe\n";
        $prompt .= "Citiraj ove odredbe TOČNO — ne izmišljaj tekst.\n\n";
        foreach ($provisions as $p) {
            $prompt .= "**{$p['citation']}** ({$p['law_name']})\n";
            $prompt .= "> {$p['full_text']}\n";
            if ($p['interpretation']) {
                $prompt .= "INTERPRETACIJA: {$p['interpretation']}\n";
            }
            $prompt .= "\n";
        }

        // Presude
        $prompt .= "### Sudska praksa\n";
        $prompt .= "Citiraj presude s brojem odluke i datumom. Citatne navode koristi u originalu.\n\n";
        foreach ($precedents as $p) {
            $prompt .= "**{$p['citation']}** [{$p['court']}]\n";
            $prompt .= "Stav: {$p['key_holding']}\n";
            $prompt .= "Citat: \"{$p['key_quote']}\"\n";
            $prompt .= "Relevantnost: {$p['relevance']}\n\n";
        }

        // Prilozi
        if ($attachments) {
            $prompt .= "### Prilozi za dokument\n";
            $prompt .= "{$attachments}\n\n";
            $prompt .= "Uključi sekciju PRILOZI na kraju dokumenta s gornjim popisom.\n";
        }

        return $prompt;
    }
}
```

**Step 3: Modificiraj RecursiveDocumentWriter da koristi ProfileContextBuilder**

U `RecursiveDocumentWriter::generate()`, ubaci prije generiranja outlina:

```php
// U metodi generate(), dodaj nakon inicijalizacije:
$contextBuilder = new ProfileContextBuilder();
$legalContext = $contextBuilder->build($profile);

// Proslijedi u generateOutline i generateSection kao dio additionalContext:
$additionalContext['__legal_context'] = $legalContext['injected_prompt'];
```

U `buildSystemPrompt()`, dodaj na kraj system prompta:

```php
if (isset($additional['__legal_context'])) {
    $systemPrompt .= "\n\n" . $additional['__legal_context'];
}
```

Ovim se svaki poziv LLM-u obogaćuje cijelom pravnom bazom relevantnom za taj profil.

**Step 4: Pokreni sve testove — PASS, commit**

```bash
php artisan test tests/Unit/Services/LegalArtillery/ --verbose
git add app/Services/LegalArtillery/ProfileContextBuilder.php app/Services/LegalArtillery/RecursiveDocumentWriter.php tests/Unit/Services/LegalArtillery/ProfileContextBuilderTest.php
git commit -m "feat: ProfileContextBuilder injects provisions + precedents into LLM generation"
```

---

## Faza 10: Primjeri Dopisa (Sample Documents)

### Task 13: SampleDocumentStore — referentni uzorci za agenta

**Opis:** Agent piše bolje kad vidi primjer. Za svaki profil dodajemo 1-2 referentna uzorka — skraćene verzije pravih dopisa tog tipa. Agent ih koristi kao stilski i strukturalni vodič, ali NE kopira doslovno.

**Files:**
- Create: `resources/legal-artillery/samples/` direktorij
- Create: Po jedna `.md` datoteka za svaki profil s uzorkom
- Modify: `ProfileContextBuilder` — dodaj `loadSampleDocument()`

**Step 1: Kreiraj primjer za predsjednika suda**

```markdown
<!-- resources/legal-artillery/samples/predsjednik_suda.md -->
# UZORAK: Zahtjev predsjedniku suda za uvid u spis

PREDMET: [Broj spisa] — Zahtjev za uvid u spis temeljem čl.150 st.4 Prekršajnog zakona

PODNOSITELJ:
[Ime i prezime], OIB: [OIB]
[Adresa]
E-mail: [email], Tel: [telefon]

PRIMATELJ:
Predsjednik/predsjednica Općinskog suda u [Grad]
[Adresa suda]

Datum: [Mjesto], [datum]

---

Poštovani/a,

I. ČINJENIČNI OSNOV

Dana [datum pretrage] u mom domu na adresi [adresa] provedena je pretraga temeljem Naredbe tog suda broj [referenca naredbe], izdane na zahtjev [policijska uprava], KLASA: [klasa], URBROJ: [urbroj].

Prema uvidu u e-Predmet, spis sadrži: [nabrojati dokumente vidljive u e-Predmetu].

Spis je arhiviran dana [datum arhiviranja].

II. PRAVNI TEMELJ

Temeljem članka 150. stavak 4. Prekršajnog zakona, kad je postupak završen, dopuštenje za razgledavanje i prepisivanje spisa daje predsjednik suda. Budući da je spis arhiviran, postupak je završen, te je predsjednik suda nadležan za odlučivanje o ovom zahtjevu.

Kao osoba čiji je dom pretražen imam opravdani interes u smislu članka 150. stavka 1. PZ-a, neovisno o statusu stranke iz članka 108. PZ-a, budući da čl.150 st.1 izrijekom navodi "svakomu drugom tko za to ima opravdani interes".

Ustavni sud RH u odluci U-III-3071/2006 od 18. ožujka 2009. utvrdio je da je osiguranje djelotvornog pravnog lijeka temeljno procesno jamstvo zajamčeno Ustavom, te da stranke ne smiju trpjeti štetne posljedice zbog pogrešne ili izostale upute o pravnom lijeku.

III. ZAHTJEV

Zahtijevam:
1. Uvid u cjelokupni spis predmeta [broj spisa], uključujući zahtjev policije s prilozima, naredbu za pretragu, zapisnik o pretrazi, izvješće o intervencijama, i sve priloge.
2. Preslikavanje navedenih dokumenata.
3. Da se o ovom zahtjevu donese formalno rješenje s pravnom poukom, sukladno članku 18. Ustava RH koji jamči pravo na žalbu.

S poštovanjem,

_______________________
[Ime i prezime]
```

**Step 2: Kreiraj primjer za ustavnu tužbu**

```markdown
<!-- resources/legal-artillery/samples/ustavni_sud.md -->
# UZORAK: Ustavna tužba — čl.62 (čl.63 pročišćeni) iznimka

USTAVNOM SUDU REPUBLIKE HRVATSKE
Trg svetog Marka 4, 10000 Zagreb

USTAVNA TUŽBA
podnesena temeljem članka 62. (čl.63 pročišćeni tekst) Ustavnog zakona o Ustavnom sudu Republike Hrvatske

Podnositelj:
[Ime i prezime], OIB: [OIB]
[Adresa], [Grad]

I. OSPORAVANI AKT

Osporavam postupanje Općinskog suda u [Grad] i Županijskog suda u [Grad] koje se očituje u:
(a) Uskrati pristupa spisu [broj spisa] bez donošenja formalnog rješenja
(b) Neformalnom emailu [datum] kojim se potvrdila uskrata
(c) Odgovoru Županijskog suda [datum] kojim je potvrđena uskrata bez formalnog akta

II. POVRIJEĐENE USTAVNE ODREDBE

Članak 18. stavak 1. — Pravo na žalbu
Članak 19. stavak 1. — Sudska kontrola zakonitosti
Članak 29. stavak 1. — Pravo na pravično suđenje
Članak 34. — Nepovredivost doma

III. PRIMJENA ČLANKA 62. USTAVNOG ZAKONA

Ova ustavna tužba podnosi se PRIJE iscrpljenosti pravnog puta jer:
(a) Pravni put ne postoji — neformalna email odluka ne predstavlja akt protiv kojeg je dopuštena žalba
(b) Osporenim postupanjem grubo se vrijeđaju ustavna prava — kumulativno: uskrata pristupa + nedonošenje rješenja + onemogućavanje žalbe
(c) Teške i nepopravljive posljedice — dokazi prikupljeni pretragom koriste se u kaznenom postupku, a obrana ne može provjeriti zakonitost naloga

IV. ČINJENIČNO STANJE
[Kronološki opis]

V. USTAVNOPRAVNA ARGUMENTACIJA
[Argumenti po svakom članku]

VI. PRIJEDLOG

Predlažem da Ustavni sud:
1. Utvrdi povredu članaka 18., 19., 29. i 34. Ustava
2. Ukine osporavano postupanje
3. Naloži Općinskom sudu donošenje formalnog rješenja o zahtjevu za uvid u spis

PRILOZI: [popis]
```

**Step 3: Kreiraj primjere za ostale profile**

Kreiraj analogno za: `dorh_production.md`, `kazneni_sud_motion.md`, `ombudsman.md`, `ministarstvo_pravosudja.md`, `izdvajanje_dokaza.md`, `echr_application.md`.

**Step 4: Dodaj loadSampleDocument u ProfileContextBuilder**

```php
// U ProfileContextBuilder.php, dodaj metodu:
private function loadSampleDocument(string $profileKey): ?string
{
    $path = resource_path("legal-artillery/samples/{$profileKey}.md");
    if (file_exists($path)) {
        return file_get_contents($path);
    }
    return null;
}
```

I u `buildInjectedPrompt()`, dodaj na kraj:

```php
$sample = $this->loadSampleDocument($profile->key ?? '');
if ($sample) {
    $prompt .= "\n### Referentni uzorak\n";
    $prompt .= "Koristi ovaj uzorak kao STILSKI i STRUKTURALNI vodič. NE kopiraj doslovno — adaptiraj na konkretni slučaj.\n\n";
    $prompt .= $sample . "\n";
}
```

**Step 5: Commit**

```bash
git add resources/legal-artillery/samples/ app/Services/LegalArtillery/ProfileContextBuilder.php
git commit -m "feat: sample documents for all 8 profiles — style guides for LLM generation"
```

---

## Faza 11: Argument Validator — Provjera Snage Argumenata

### Task 14: ArgumentValidator — LLM pregledava vlastiti output

**Opis:** Drugi LLM pass koji evaluira generirani dopis: je li svaki pravni citat točan? Je li svaka presuda ispravno citirana? Postoje li logičke rupe? Output je ocjena snage i lista poboljšanja.

**Files:**
- Create: `app/Services/LegalArtillery/ArgumentValidator.php`
- Test: `tests/Unit/Services/LegalArtillery/ArgumentValidatorTest.php`

**Step 1: Napiši test**

```php
// tests/Unit/Services/LegalArtillery/ArgumentValidatorTest.php
<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Services\LegalArtillery\ArgumentValidator;
use App\Services\LegalArtillery\LlmClient;
use Mockery;
use Tests\TestCase;

class ArgumentValidatorTest extends TestCase
{
    public function test_validates_document_and_returns_score(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'overall_score' => 8,
                'citation_accuracy' => 9,
                'argument_strength' => 7,
                'logical_coherence' => 8,
                'issues' => [
                    ['severity' => 'minor', 'description' => 'Nedostaje poziv na čl.34 Ustava u sekciji III'],
                ],
                'improvements' => [
                    'Dodati referencu na Doroż v. Poland za argument proporcionalnosti pretrage',
                ],
                'verdict' => 'FIRE_READY',
            ]));

        $validator = new ArgumentValidator($llm);
        $result = $validator->validate(
            'Testni sadržaj dokumenta s pravnim argumentima...',
            'predsjednik_suda'
        );

        $this->assertEquals(8, $result['overall_score']);
        $this->assertEquals('FIRE_READY', $result['verdict']);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('improvements', $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Implementiraj**

```php
// app/Services/LegalArtillery/ArgumentValidator.php
<?php

namespace App\Services\LegalArtillery;

use App\DTOs\DocumentProfile;
use App\Models\LegalPrecedent;
use App\Models\LegalProvision;
use Illuminate\Support\Facades\Log;

class ArgumentValidator
{
    public function __construct(
        private readonly LlmClient $llm,
    ) {}

    public function validate(string $documentContent, string $profileKey): array
    {
        $profile = DocumentProfile::fromConfig($profileKey);

        // Dohvati "ground truth" za provjeru
        $provisions = LegalProvision::forProfile($profileKey)
            ->orWhere(fn($q) => $q->whereJsonContains('tags', 'all_profiles'))
            ->get();
        $precedents = LegalPrecedent::forProfile($profileKey)
            ->orWhere(fn($q) => $q->whereJsonContains('tags', 'all_profiles'))
            ->get();

        $systemPrompt = <<<SYSTEM
Ti si revizor pravnih dokumenata. Pregledavaš generirane pravne dopise i ocjenjuješ ih.

## Tvoje zadaće:
1. Provjeri jesu li zakonski citati TOČNI (usporedi s bazom odredbi)
2. Provjeri jesu li presude ISPRAVNO citirane (broj, datum, pravni stav)
3. Ocijeni logičku koherentnost argumenata
4. Identificiraj propuštene argumente koji bi ojačali dopis
5. Provjeri ima li kontradikcija
6. Ocijeni ukupnu uvjerljivost za primatelja

## Ljestvica ocjenjivanja (1-10):
- 1-3: WEAK — ne slati, preslabo
- 4-6: NEEDS_WORK — ima temelja ali treba doradu
- 7-8: FIRE_READY — dovoljno snažno za slanje
- 9-10: DEVASTATING — neumoljivо precizno

Odgovori ISKLJUČIVO u JSON formatu.
SYSTEM;

        $provisionContext = $provisions->map(fn($p) =>
            "{$p->shortCitation()}: {$p->full_text}"
        )->implode("\n");

        $precedentContext = $precedents->map(fn($p) =>
            "{$p->citation()}: {$p->key_holding}"
        )->implode("\n");

        $userPrompt = <<<PROMPT
## Dokument za pregled:

{$documentContent}

## Baza zakonskih odredbi (provjeri točnost citata):

{$provisionContext}

## Baza sudske prakse (provjeri točnost navoda):

{$precedentContext}

## Profil dokumenta: {$profile->name}
## Primatelj: {$profile->recipientLine()}

Vrati JSON:
{
    "overall_score": <1-10>,
    "citation_accuracy": <1-10>,
    "argument_strength": <1-10>,
    "logical_coherence": <1-10>,
    "issues": [
        {"severity": "critical|major|minor", "description": "..."}
    ],
    "improvements": ["sugestija 1", "sugestija 2"],
    "missing_precedents": ["presude iz baze koje nisu iskorištene a bile bi devastirajuće"],
    "missing_provisions": ["odredbe iz baze koje nisu citirane a ojačale bi argument"],
    "verdict": "WEAK|NEEDS_WORK|FIRE_READY|DEVASTATING"
}
PROMPT;

        $response = $this->llm->generate($systemPrompt, $userPrompt, 4096);
        $json = $this->extractJson($response);
        $result = json_decode($json, true);

        if (!$result) {
            Log::warning('ArgumentValidator: Failed to parse validation response');
            return ['overall_score' => 0, 'verdict' => 'PARSE_ERROR', 'raw' => $response];
        }

        Log::info('ArgumentValidator', [
            'profile' => $profileKey,
            'score' => $result['overall_score'] ?? 0,
            'verdict' => $result['verdict'] ?? 'UNKNOWN',
        ]);

        return $result;
    }

    private function extractJson(string $text): string
    {
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            return $matches[0];
        }
        return $text;
    }
}
```

**Step 3: Integriraj u FireCommand — `--validate` flag**

U `FireCommand`, dodaj opciju `{--validate : Validiraj dokument prije slanja}` i nakon generacije:

```php
if ($this->option('validate')) {
    $this->info('🔍 Faza 2.5: Validacija argumenata...');
    $validator = new ArgumentValidator($llm);
    $validation = $validator->validate($result['content'], $profileKey);

    $verdict = $validation['verdict'] ?? 'UNKNOWN';
    $score = $validation['overall_score'] ?? 0;

    $this->info("  Ocjena: {$score}/10 — {$verdict}");

    if (!empty($validation['issues'])) {
        foreach ($validation['issues'] as $issue) {
            $icon = match($issue['severity']) {
                'critical' => '🔴',
                'major' => '🟡',
                default => '🔵',
            };
            $this->line("  {$icon} [{$issue['severity']}] {$issue['description']}");
        }
    }

    if ($verdict === 'WEAK' || $verdict === 'NEEDS_WORK') {
        if (!$this->confirm('Dokument nije spreman za paljbu. Nastaviti svejedno?')) {
            return self::SUCCESS;
        }
    }
}
```

**Step 4: Pokreni test — PASS, commit**

```bash
php artisan test tests/Unit/Services/LegalArtillery/ArgumentValidatorTest.php --verbose
git add app/Services/LegalArtillery/ArgumentValidator.php tests/Unit/Services/LegalArtillery/ArgumentValidatorTest.php app/Console/Commands/LegalArtillery/FireCommand.php
git commit -m "feat: ArgumentValidator — LLM-powered document review with scoring and improvements"
```

---

## Faza 12: Auto-Iteracija — Regeneriraj dok nije DEVASTATING

### Task 15: IterativeRefiner — petlja poboljšanja

**Opis:** Kad `ArgumentValidator` vrati `NEEDS_WORK`, agent automatski uzima sugestije, regenerira slabe sekcije, i ponovo validira. Petlja se ponavlja dok ocjena ne dosegne `FIRE_READY` ili se iscrpi max iteracija.

**Files:**
- Create: `app/Services/LegalArtillery/IterativeRefiner.php`
- Modify: `FireCommand` — `--auto-refine` flag
- Test: `tests/Unit/Services/LegalArtillery/IterativeRefinerTest.php`

**Step 1: Napiši test**

```php
// tests/Unit/Services/LegalArtillery/IterativeRefinerTest.php
<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Services\LegalArtillery\ArgumentValidator;
use App\Services\LegalArtillery\IterativeRefiner;
use App\Services\LegalArtillery\LlmClient;
use Mockery;
use Tests\TestCase;

class IterativeRefinerTest extends TestCase
{
    public function test_refines_until_fire_ready(): void
    {
        $llm = Mockery::mock(LlmClient::class);

        // Refinement call
        $llm->shouldReceive('generate')
            ->andReturn('Poboljšani sadržaj dokumenta s jačim argumentima...');

        $validator = Mockery::mock(ArgumentValidator::class);

        // First validation: NEEDS_WORK
        $validator->shouldReceive('validate')
            ->once()
            ->andReturn([
                'overall_score' => 5,
                'verdict' => 'NEEDS_WORK',
                'improvements' => ['Dodaj referencu na Garcia Alva'],
                'missing_precedents' => ['Garcia Alva v. Germany'],
                'issues' => [],
            ]);

        // Second validation: FIRE_READY
        $validator->shouldReceive('validate')
            ->once()
            ->andReturn([
                'overall_score' => 8,
                'verdict' => 'FIRE_READY',
                'improvements' => [],
                'issues' => [],
            ]);

        $refiner = new IterativeRefiner($llm, $validator, maxIterations: 3);
        $result = $refiner->refine('Originalni sadržaj...', 'predsjednik_suda');

        $this->assertEquals('FIRE_READY', $result['final_verdict']);
        $this->assertEquals(2, $result['iterations']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Implementiraj**

```php
// app/Services/LegalArtillery/IterativeRefiner.php
<?php

namespace App\Services\LegalArtillery;

use Illuminate\Support\Facades\Log;

class IterativeRefiner
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly ArgumentValidator $validator,
        private readonly int $maxIterations = 3,
    ) {}

    public function refine(string $content, string $profileKey): array
    {
        $currentContent = $content;
        $history = [];

        for ($i = 1; $i <= $this->maxIterations; $i++) {
            Log::info("IterativeRefiner: Iteration {$i}/{$this->maxIterations}", ['profile' => $profileKey]);

            $validation = $this->validator->validate($currentContent, $profileKey);
            $verdict = $validation['verdict'] ?? 'UNKNOWN';
            $score = $validation['overall_score'] ?? 0;

            $history[] = [
                'iteration' => $i,
                'score' => $score,
                'verdict' => $verdict,
                'issues_count' => count($validation['issues'] ?? []),
            ];

            if (in_array($verdict, ['FIRE_READY', 'DEVASTATING'])) {
                return [
                    'content' => $currentContent,
                    'final_verdict' => $verdict,
                    'final_score' => $score,
                    'iterations' => $i,
                    'history' => $history,
                ];
            }

            // Regeneriraj s feedback-om
            $currentContent = $this->regenerateWithFeedback(
                $currentContent,
                $validation,
                $profileKey,
            );
        }

        // Max iteracija dosegnuto
        $finalValidation = $this->validator->validate($currentContent, $profileKey);
        return [
            'content' => $currentContent,
            'final_verdict' => $finalValidation['verdict'] ?? 'MAX_ITERATIONS',
            'final_score' => $finalValidation['overall_score'] ?? 0,
            'iterations' => $this->maxIterations,
            'history' => $history,
        ];
    }

    private function regenerateWithFeedback(string $content, array $validation, string $profileKey): string
    {
        $issues = collect($validation['issues'] ?? [])->map(fn($i) =>
            "[{$i['severity']}] {$i['description']}"
        )->implode("\n");

        $improvements = implode("\n", $validation['improvements'] ?? []);
        $missingPrecedents = implode(", ", $validation['missing_precedents'] ?? []);
        $missingProvisions = implode(", ", $validation['missing_provisions'] ?? []);

        $systemPrompt = <<<SYSTEM
Ti si pravni redaktor. Dobio si dokument i popis problema/poboljšanja iz recenzije. Tvoj zadatak je POBOLJŠATI dokument:
1. Ispravi sve identificirane probleme
2. Implementiraj predložena poboljšanja
3. Dodaj nedostajuće presude i zakonske odredbe
4. Zadrži strukturu i ton dokumenta
5. Vrati POTPUNI poboljšani dokument
SYSTEM;

        $userPrompt = <<<PROMPT
## Originalni dokument:

{$content}

## Identificirani problemi:
{$issues}

## Predložena poboljšanja:
{$improvements}

## Nedostajuće presude koje treba dodati:
{$missingPrecedents}

## Nedostajuće zakonske odredbe:
{$missingProvisions}

Vrati POTPUNI poboljšani dokument. Bez komentara — samo finalni tekst.
PROMPT;

        return $this->llm->generate($systemPrompt, $userPrompt);
    }
}
```

**Step 3: Dodaj u FireCommand `--auto-refine` flag**

```php
// U FireCommand signature dodaj:
{--auto-refine : Automatski poboljšavaj dok nije FIRE_READY (max 3 iteracije)}

// U handle(), nakon generacije i validacije:
if ($this->option('auto-refine')) {
    $this->info('🔄 Auto-refinement aktiviran...');
    $refiner = new IterativeRefiner($llm, new ArgumentValidator($llm));
    $refined = $refiner->refine($result['content'], $profileKey);

    $this->info("  Iteracije: {$refined['iterations']}");
    $this->info("  Finalna ocjena: {$refined['final_score']}/10 — {$refined['final_verdict']}");

    foreach ($refined['history'] as $h) {
        $icon = $h['verdict'] === 'FIRE_READY' ? '✅' : '🔄';
        $this->line("    {$icon} Iter {$h['iteration']}: {$h['score']}/10 ({$h['verdict']})");
    }

    $result['content'] = $refined['content'];
}
```

**Step 4: Pokreni test — PASS, commit**

```bash
php artisan test tests/Unit/Services/LegalArtillery/IterativeRefinerTest.php --verbose
git add app/Services/LegalArtillery/IterativeRefiner.php app/Console/Commands/LegalArtillery/FireCommand.php tests/Unit/Services/LegalArtillery/IterativeRefinerTest.php
git commit -m "feat: IterativeRefiner — auto-improve until FIRE_READY or DEVASTATING"
```

---

## Finalni Pipeline — Kompletna Artiljerijska Salva

```bash
# 1. Setup (jednom)
php artisan migrate
php artisan db:seed --class=LegalProvisionsSeeder
php artisan db:seed --class=LegalPrecedentsSeeder
php artisan legal:gmail-auth

# 2. Stavi dokumente predmeta u storage
cp *.pdf storage/app/legal-artillery/case-documents/

# 3. Precizna paljba s validacijom i auto-refinementom
php artisan legal:fire predsjednik_suda --validate --auto-refine --draft

# 4. Kad si zadovoljan — oštro
php artisan legal:fire predsjednik_suda

# 5. Masovna paljba — sve immediate ciljeve
php artisan legal:barrage --draft

# 6. Totalni napad
php artisan legal:barrage --profiles=predsjednik_suda,dorh_production,kazneni_sud_motion,ombudsman,ministarstvo_pravosudja --auto-refine
```

## Arhitektura Pipeline-a

```
DocumentProfile (config)
       │
       ├── ProfileContextBuilder
       │      ├── LegalProvision (DB) ──── zakonske odredbe + interpretacija
       │      ├── LegalPrecedent (DB) ──── presude + citati + relevantnost
       │      ├── AttachmentCollector ──── prilozi iz storage/
       │      └── SampleDocument (.md) ── stilski vodič
       │
       ▼
RecursiveDocumentWriter (LLM)
       │
       ├── 1. generateOutline() ─── struktura iz profila
       ├── 2. generateSection() ─── svaka sekcija pojedinačno, s kontekstom prethodnih
       └── 3. polishDocument() ─── finalni pregled i zaglađivanje
       │
       ▼
ArgumentValidator (LLM pass 2)
       │
       ├── Provjera citata vs DB odredbi
       ├── Provjera presuda vs DB prakse
       ├── Ocjena: WEAK → NEEDS_WORK → FIRE_READY → DEVASTATING
       │
       ▼ (ako NEEDS_WORK)
IterativeRefiner (max 3 iteracije)
       │
       └── Regeneriraj s feedbackom → ponovo validiraj → dok nije FIRE_READY
       │
       ▼
DocxRenderer (Node.js docx-js)
       │
       └── Profesionalni .docx s zaglavljem, sekcijama, potpisom
       │
       ▼
GmailDispatcher (Google API)
       │
       ├── Send (odmah na email) ili Draft (za pregled)
       └── Attachments iz AttachmentCollector
```

## Kontrolna Lista: Što Agent Ima na Raspolaganju

| Komponenta | Opis | Status |
|------------|------|--------|
| 16 zakonskih odredbi | PZ, ZKP, Ustav, UZUSRH, ZS, ZPP — s punim tekstom i interpretacijom | Task 9 |
| 12 sudskih presuda | 2× USRH, 2× VSRH, 8× ECHR — s citatima na izvornom jeziku | Task 10 |
| 8 profila napada | Od predsjednika suda do ECHR-a — svaki s tonom, strukturom, pravnim temeljima | Task 1 |
| 8 uzoraka dopisa | Referentni stilski vodiči za svaki tip | Task 13 |
| Registar priloga | Koji dokumenti idu uz koji dopis, s provjerom postojanja | Task 11 |
| Auto-validacija | LLM pregledava vlastiti output i ocjenjuje snagu | Task 14 |
| Auto-iteracija | Poboljšava dok nije FIRE_READY | Task 15 |
| Gmail salva | Šalje .docx s prilozima direktno iz terminala | Task 5 |

---

# APPENDIX: Ojačanje Artiljerije — 5 Kritičnih Dimenzija

> Appendix odgovara na 5 pitanja:
> 1. Primjeri dopisa/žalbi za svaki profil
> 2. Referenca na zakone — puni tekst, interpretacija, međusobne veze
> 3. Referenca na postojeće dokumente + prilozi
> 4. Sudska praksa — domaća + ECHR
> 5. Strategije za "pomesti ih" — devastirajuća argumentacija

---

## A1: Primjeri Dopisa i Žalbi — Obogaćivanje Task 13

Task 13 (SampleDocumentStore) definira uzorke, ali nedostaju **konkretni primjeri** za svih 8 profila s pravnim formulacijama specifičnima za naš slučaj. Ovo nije generički template — ovo su **stilski vodiči kalibrirani na Pp Prz-74/2025**.

### Task 13a: Kompletni Uzorci za Sve Profile

**Opis:** Za svaki od 8 profila kreirati puni referentni uzorak (sample) koji sadrži: (1) točnu strukturu, (2) stilski ton, (3) ključne formulacije, (4) pravne citate kako se koriste u kontekstu, (5) prijedlog odluke. Agent koristi ove uzorke kao "few-shot" primjere — ne kopira ih doslovno, već uči stil i strukturu.

**Files:**
- Create: `resources/legal-artillery/samples/predsjednik_suda.md` (obogaćeni)
- Create: `resources/legal-artillery/samples/ustavni_sud.md` (obogaćeni)
- Create: `resources/legal-artillery/samples/dorh_production.md`
- Create: `resources/legal-artillery/samples/kazneni_sud_motion.md`
- Create: `resources/legal-artillery/samples/ombudsman.md`
- Create: `resources/legal-artillery/samples/ministarstvo_pravosudja.md`
- Create: `resources/legal-artillery/samples/izdvajanje_dokaza.md`
- Create: `resources/legal-artillery/samples/echr_application.md`
- Create: `resources/legal-artillery/samples/_common_blocks.md` (zajednički blokovi teksta)

**Implementacija:**

Svaki sample mora sadržavati ove sekcije:

```
# UZORAK: [Naslov dokumenta]
## META
- profil: [key]
- ton: [formal_assertive|formal_narrative|...]
- jezik: [hr|en]
- primatelj: [institucija]

## ZAGLAVLJE
[Točan format zaglavlja s placeholder varijablama]

## TIJELO
### I. ČINJENIČNI OSNOV
[Narativ specifičan za ovaj tip dopisa — što se naglašava]

### II. PRAVNI TEMELJ
[Koje odredbe se citiraju i KAKO — s primjerom formulacije]

### III. ARGUMENTACIJA
[Stil argumentiranja — agresivan vs. nartativan vs. ustavnopravni]

### IV. ZAHTJEV / PRIJEDLOG
[Točna formulacija zahtjeva za ovaj tip]

### V. PRILOZI
[Popis priloga specifičan za ovaj tip]

## STIL-NAPOMENE
[Specifične upute za LLM o tonu, frazama koje koristiti/izbjegavati]
```

**Primjer: `dorh_production.md`**

```markdown
# UZORAK: Zahtjev DORH-u za pribavljanje i uključivanje spisa

## META
- profil: dorh_production
- ton: formal_assertive
- jezik: hr
- primatelj: Županijsko državno odvjetništvo u Osijeku

## ZAGLAVLJE
ŽUPANIJSKO DRŽAVNO ODVJETNIŠTVO U OSIJEKU
Europska avenija 7, 31000 Osijek

Predmet: Kazneni postupak K-{kazneni_broj} — Zahtjev za pribavljanje
         spisa prekršajnog predmeta Pp Prz-74/2025

## TIJELO
### I. ČINJENIČNI OSNOV
Protiv mene se vodi kazneni postupak pred Općinskim kaznenim sudom u Osijeku
pod brojem K-{kazneni_broj}. Optužnica se, između ostalog, temelji na dokazima
prikupljenima pretragom mog doma na adresi Primorska 5, Osijek, provedenom
dana 9. lipnja 2025. na temelju Naredbe Općinskog suda u Osijeku, Pp Prz-74/2025-2.

Prekršajni spis Pp Prz-74/2025 arhiviran je 10. srpnja 2025. Moji zahtjevi
za uvid u taj spis (25.8., 29.8., 1.9., 4.9. i 9.9.2025.) odbijeni su
bez donošenja formalnog rješenja.

### II. PRAVNI TEMELJ
Sukladno članku 9. stavku 2. ZKP-a, državni odvjetnik dužan je s jednakom
pozornošću prikupiti kako dokaze koji terete, tako i one koji idu u korist
osumnjičenika. Spis Pp Prz-74/2025 može sadržavati elemente koji ukazuju na
nezakonitost pretrage, čime bi se aktivirala primjena članka 10. stavka 2. ZKP-a.

Članak 184. stavak 5. ZKP-a jamči pravo uvida u zapisnike o hitnim radnjama
u roku od 30 dana od poduzimanja radnje. Pretraga doma kvalificira se kao
hitna radnja iz članka 213. ZKP-a.

### III. ARGUMENTACIJA
Prava obrane iz članka 6. stavka 3. točke (b) EKLJP-a zahtijevaju da obrana
ima pristup materijalima potrebnima za pripremu svoje obrane. ESLJP je u predmetu
Garcia Alva protiv Njemačke (23541/94) utvrdio da se "jednakost oružja ne osigurava
ako je branitelju uskraćen pristup dokumentima bitnim za učinkovito osporavanje
zakonitosti postupanja."

Uskrata pristupa prekršajnom spisu iz kojeg proizlaze dokazi korišteni u kaznenom
postupku čini povredu prava obrane koja, prema članku 10. stavku 2. točki 2. ZKP-a,
dokaze čini nezakonitima.

### IV. ZAHTJEV
Zahtijevam da Državno odvjetništvo:
1. Pribavi spis Pp Prz-74/2025 od Općinskog suda u Osijeku
2. Uključi ga u spis kaznenog predmeta K-{kazneni_broj}
3. Omogući obrani pristup svim dokumentima iz tog spisa sukladno čl.183 i 184 ZKP-a

### V. PRILOZI
1. Dokaz o uskrati pristupa — odgovor suca Bertok od 03.09.2025.
2. Dokaz o arhiviranju spisa — ispis iz e-Predmet sustava
3. Prethodni zahtjevi za uvid (kronološki)

## STIL-NAPOMENE
- Ton: asertivan ali profesionalan — ovo je zahtjev zakonskom obvezniku, ne molba
- Naglasiti DUŽNOST DORH-a (čl.9 st.2 ZKP) — ne "molim" nego "zahtijevam sukladno"
- Povezati s kaznenim postupkom — DORH-u je u interesu osigurati zakonitost dokaza
- Upozoriti na posljedice: ako se dokazi utemelje na nezakonitoj pretrazi, pada optužnica
```

**Primjer: `echr_application.md`**

```markdown
# UZORAK: ECHR Application Form — Sections E, F, G

## META
- profil: echr_application
- ton: formal_international
- jezik: en
- primatelj: European Court of Human Rights

## SECTION E — STATEMENT OF FACTS

The applicant resides at Primorska 5, Osijek, Croatia. On 9 June 2025, Croatian
police executed a search of the applicant's home pursuant to a warrant (Pp Prz-74/2025-2)
issued by the Osijek Municipal Court on the same date, based on suspected violation of
Article 54(3) of the Act on the Suppression of Drug Abuse.

The case file was archived on 10 July 2025. Between 25 August and 17 September 2025,
the applicant submitted five separate requests for access to the file. All were denied
without a formal decision (rješenje) containing a legal remedy instruction, in violation
of Article 18(1) of the Croatian Constitution which guarantees the right of appeal.

The Municipal Court judge (D. Bertok) responded by informal email on 3 September 2025,
citing Article 108 of the Misdemeanour Act (party status). The Court President responded
by informal email on 5 September 2025, upholding the denial. The County Court responded
by informal email on 17 September 2025, declining to intervene.

At no point was a formal judicial decision issued.

## SECTION F — ALLEGED VIOLATIONS

### Article 6 § 1 — Right to a fair trial (equality of arms)
The denial of access to the search warrant file prevents the applicant from verifying
the lawfulness of the search and preparing an effective defence in related criminal
proceedings. This violates the principle of equality of arms as established in
Garcia Alva v. Germany (No. 23541/94, § 39, 13 February 2001).

### Article 8 — Right to respect for private life and home
The search of the applicant's home constitutes interference with Article 8 rights.
Without access to the warrant file, the applicant cannot verify whether the interference
was "in accordance with the law" and "necessary in a democratic society" as required by
Article 8 § 2. Cf. Modestou v. Greece (No. 51693/13), Doroż v. Poland (No. 71205/11).

### Article 13 — Right to an effective remedy
No effective remedy exists because: (a) no formal decision was issued against which to
appeal; (b) email denials contain no reasoning; (c) the County Court declined to intervene.
Cf. Đorđević v. Croatia (No. 41526/10), Horvat v. Croatia.

## SECTION G — ADMISSIBILITY (Article 35)
The applicant has been unable to exhaust domestic remedies because no formal decision
exists against which remedies can be exercised. The Constitutional Court complaint under
Article 63 (formerly 62) of the Constitutional Act requires a formal act to challenge.
The absence of such act is itself the core violation.

## STIL-NAPOMENE
- Write in precise, understated international legal English — no dramatic language
- Structure mirrors ECHR form exactly
- Every claim links to specific ECHR case law
- Domestic law cited to show exhaustion barriers, not as primary argument
- Damages section should reference Doroż v. Poland (EUR 10,000 non-pecuniary)
```

**Zajednički blokovi: `_common_blocks.md`**

Sadrži blokove teksta koji se ponavljaju u više profila:

```markdown
# ZAJEDNIČKI BLOKOVI — za interpolaciju u profile

## BLOCK: KRONOLOGIJA
Dana 9. lipnja 2025., na temelju Naredbe Općinskog suda u Osijeku broj
Pp Prz-74/2025-2, provedena je pretraga doma podnositelja na adresi
Primorska 5, Osijek. Naredba je izdana na zahtjev MUP-a — PU osječko-baranjske,
temeljem sumnje na prekršaj iz čl.54 st.3 Zakona o suzbijanju zlouporabe droga.

Spis je arhiviran 10. srpnja 2025.

Podnositelj je podnio zahtjeve za uvid u spis:
- 25. kolovoza 2025. — prvi zahtjev
- 29. kolovoza 2025. — požurnica
- 1. rujna 2025. — žurna predstavka predsjednici suda
- 4. rujna 2025. — ponovljeni zahtjev
- 9. rujna 2025. — zahtjev za donošenje formalnog rješenja
- 12. rujna 2025. — zahtjev za upravni nadzor Županijskom sudu

Odgovori sudova:
- 3. rujna 2025. — email suca Bertok: poziv na čl.108 PZ (stranački status)
- 5. rujna 2025. — email predsjednice suda: potvrda odbijanja
- 17. rujna 2025. — email Županijskog suda: nema temelja za intervenciju

Ni u jednom slučaju nije doneseno formalno rješenje s pravnom poukom.

## BLOCK: PROTUARGUMENT_CL108
Pozivanje na čl.108 PZ-a je pravno neutemeljeno. Članak 108. definira stranke
prekršajnog postupka (ovlašteni tužitelj i okrivljenik), ali članak 150. stavak 1.
PZ-a IZRIJEKOM predviđa pristup spisu i za osobe koje nisu stranke:
"To sud može dopustiti i svakomu drugom tko za to ima opravdani interes."
Osoba čiji je dom pretražen po definiciji ima opravdani interes — njezina su
ustavna prava iz čl.34 Ustava direktno pogođena.

## BLOCK: PROTUARGUMENT_CL206F
Pozivanje na tajnost izvida iz čl.206.f ZKP-a je ireleventno jer:
(a) Spis je ARHIVIRAN 10.7.2025. — izvidi su završeni
(b) Čl.206.f štiti TEKUĆE izvide, ne arhivirane spise
(c) I tijekom tekućih izvida, čl.184 st.5 ZKP jamči pravo uvida
(d) ESLJP: tajnost ne smije poništiti pravo na pristup materijalima
    bitnim za obranu (Garcia Alva, §39)

## BLOCK: USTAVNA_POVREDA_TRIPLE
Kumulativna povreda triju ustavnih odredbi:
1. Čl.18 st.1 — Pravo na žalbu onemogućeno jer ne postoji formalni akt
   protiv kojeg se žalba podnosi
2. Čl.29 st.1 — Pravo na pravično suđenje jer odluka donesena bez
   obrazloženja, usmenoili emailom
3. Čl.34 — Nepovredivost doma jer adresat pretrage ne može provjeriti
   zakonitost zadiranja u ovo pravo
```

**Step: Integracija u ProfileContextBuilder**

Dodati u `ProfileContextBuilder::buildInjectedPrompt()`:

```php
// Učitaj common blocks
$commonBlocks = $this->loadCommonBlocks();
if ($commonBlocks) {
    $prompt .= "\n### Zajednički blokovi teksta\n";
    $prompt .= "Koristi ove blokove kao polazište za odgovarajuće sekcije.\n";
    $prompt .= "Adaptiraj ih, ne kopiraj doslovno.\n\n";
    $prompt .= $commonBlocks . "\n";
}

// Učitaj sample za profil
$sample = $this->loadSampleDocument($profile->key);
if ($sample) {
    $prompt .= "\n### Referentni uzorak dokumenta\n";
    $prompt .= "Koristi kao stilski i strukturalni vodič. NE kopiraj — adaptiraj.\n\n";
    $prompt .= $sample . "\n";
}
```

```php
private function loadCommonBlocks(): ?string
{
    $path = resource_path('legal-artillery/samples/_common_blocks.md');
    return file_exists($path) ? file_get_contents($path) : null;
}
```

**Commit:**
```bash
git add resources/legal-artillery/samples/
git commit -m "feat: complete sample documents for all 8 profiles + common text blocks"
```

---

## A2: Referenca na Zakone — Obogaćivanje Task 9

Task 9 (LegalProvisionsSeeder) ima 16 odredbi. Na temelju pravnog istraživanja, dodajemo **dodatne odredbe** i **međusobne veze** (koji članci pobijaju koje argumente protivne strane).

### Task 9a: Prošireni Seeder + Međusobne Veze

**Opis:** Dodati nove odredbe otkrivene istraživanjem, uvesti `rebuts` polje (koje argumente pobija ova odredba), i `complements` polje (s kojim odredbama čini jači argument u kombinaciji).

**Migration update:**

```php
// Nova migracija: xxxx_add_relations_to_legal_provisions.php
Schema::table('legal_provisions', function (Blueprint $table) {
    $table->jsonb('rebuts')->default('[]');        // Koje protivne argumente pobija
    $table->jsonb('complements')->default('[]');   // S čime tvori jači combo
    $table->string('strength')->default('strong'); // weak|moderate|strong|devastating
    $table->text('killer_quote')->nullable();       // Najjača rečenica za citiranje
});
```

**Nove odredbe za dodati u seeder:**

```php
// === ZAKON O PRAVU NA PRISTUP INFORMACIJAMA (ZPPI) ===
[
    'law_name' => 'Zakon o pravu na pristup informacijama',
    'law_short' => 'ZPPI',
    'article' => '5',
    'paragraph' => '1',
    'title' => 'Pravo na pristup informacijama',
    'full_text' => 'Informacije su dostupne svakoj domaćoj ili stranoj fizičkoj i pravnoj osobi u skladu s uvjetima i ograničenjima ovoga Zakona.',
    'interpretation' => 'Alternativni put za pristup ako ZKP/PZ putevi ne uspiju. Ograničenje: čl.1 st.3 isključuje sudske postupke. Ali: spis je ARHIVIRAN — nije više "sudski postupak".',
    'tags' => ['alternative_avenue', 'ombudsman'],
    'rebuts' => ['Argument da je spis sudska tajna'],
    'complements' => ['Ustav čl.38 st.4'],
    'strength' => 'moderate',
    'killer_quote' => null,
],

// === USTAV čl.38 st.4 — Pravo na informaciju ===
[
    'law_name' => 'Ustav Republike Hrvatske',
    'law_short' => 'Ustav',
    'article' => '38',
    'paragraph' => '4',
    'title' => 'Pravo na pristup informacijama',
    'full_text' => 'Jamči se pravo na pristup informacijama koje posjeduju tijela javne vlasti.',
    'interpretation' => 'Ustavna osnova za pristup — sud je tijelo javne vlasti, spis je informacija. Ojačava ZPPI argument.',
    'tags' => ['constitutional', 'alternative_avenue', 'ustavni_sud'],
    'rebuts' => [],
    'complements' => ['ZPPI čl.5'],
    'strength' => 'strong',
],

// === SUDSKI POSLOVNIK čl.44 ===
[
    'law_name' => 'Sudski poslovnik',
    'law_short' => 'SP',
    'article' => '44',
    'paragraph' => null,
    'title' => 'Razgledavanje spisa trećih osoba',
    'full_text' => 'Osobama koje nisu stranke u postupku dopuštenje za razgledavanje i prepisivanje spisa daje predsjednik suda, odnosno sudac pojedinac koji vodi postupak.',
    'interpretation' => 'Potvrđuje nadležnost predsjednika suda za arhivirane spise. Sudac Bertok nije ovlašten odlučivati o završenim predmetima — jurisdikcijska pogreška.',
    'tags' => ['file_access', 'predsjednik_suda', 'jurisdictional_error'],
    'rebuts' => ['Argument da sudac koji je vodio postupak odlučuje'],
    'complements' => ['PZ čl.150 st.4'],
    'strength' => 'strong',
    'killer_quote' => 'dopuštenje za razgledavanje daje predsjednik suda',
],

// === ZKP čl.240-246 — Pretres stana i prostorija ===
[
    'law_name' => 'Zakon o kaznenom postupku',
    'law_short' => 'ZKP',
    'article' => '240',
    'paragraph' => null,
    'title' => 'Pretres stana — uvjeti',
    'full_text' => 'Pretres stana, prostorija i pokretnih stvari može se poduzeti samo ako je vjerojatno da će se pronaći tragovi kaznenog djela ili predmeti važni za kazneni postupak.',
    'interpretation' => 'Naredba mora biti utemeljena na konkretnoj vjerojatnosti, ne apstraktnoj sumnji. Bez uvida u spis ne možemo provjeriti je li ovaj uvjet zadovoljen.',
    'tags' => ['home_search', 'izdvajanje_dokaza', 'echr_application'],
    'rebuts' => [],
    'complements' => ['Ustav čl.34', 'ZKP čl.10'],
    'strength' => 'devastating',
    'killer_quote' => null,
],

// === ZKP čl.246 — Naredba za pretragu ===
[
    'law_name' => 'Zakon o kaznenom postupku',
    'law_short' => 'ZKP',
    'article' => '246',
    'paragraph' => '1',
    'title' => 'Sadržaj naredbe za pretragu',
    'full_text' => 'Naredba za pretragu mora sadržavati: oznaku prostorije ili osobe koja se pretražuje, razloge za pretragu, predmete ili osobe koje se traže, te upozorenje da se pretres može provesti i bez pristanka.',
    'interpretation' => 'ESLJP u Modestou v. Greece: opća naredba bez specifičnih predmeta = povreda čl.8. Bez uvida ne možemo provjeriti sadrži li naredba sve zakonske elemente.',
    'tags' => ['home_search', 'izdvajanje_dokaza', 'echr_application'],
    'rebuts' => [],
    'complements' => ['ZKP čl.240', 'Ustav čl.34'],
    'strength' => 'devastating',
    'killer_quote' => null,
],
```

**Rebuts Matrix — Koji argument pobija što:**

```php
// Dodati u seeder kao posebnu metodu za ažuriranje rebuts veza
private function seedRebutsMatrix(): void
{
    $matrix = [
        // PZ čl.150 st.1 pobija argument "niste stranka po čl.108"
        ['PZ', '150', '1', ['Odbijanje pristupa jer podnositelj nije stranka iz čl.108 PZ']],

        // PZ čl.150 st.4 pobija argument "sudac odlučuje o pristupu"
        ['PZ', '150', '4', ['Argument da sudac koji je vodio postupak odlučuje o završenom spisu']],

        // ZKP čl.184 st.5 pobija argument "obrana nema pravo uvida u izvide"
        ['ZKP', '184', '5', ['Argument da obrana nema pravo uvida dok traju izvidi']],

        // ZKP čl.206.f (kontekst) — NE pobija ništa, ali objašnjava zašto protivna strana griješi
        // (ovo je u 'counter_argument' tagu — interpretacija objašnjava zašto ne vrijedi)

        // Ustav čl.18 pobija argument "emailom je dovoljan odgovor"
        ['Ustav', '18', '1', ['Argument da neformalni email odgovor zadovoljava zahtjev za odlukom']],

        // Ustav čl.29 pobija argument "sud ne mora obrazlagati odbijanje"
        ['Ustav', '29', '1', ['Argument da sud ne mora obrazlagati odbijanje pristupa']],
    ];

    foreach ($matrix as [$law, $article, $paragraph, $rebuts]) {
        LegalProvision::where('law_short', $law)
            ->where('article', $article)
            ->where('paragraph', $paragraph)
            ->update(['rebuts' => $rebuts]);
    }
}
```

**Strength Rating Logika:**

```
devastating = sama odredba je dovoljna da sruši protivnički argument
strong      = čvrst temelj, ali treba combo s drugom odredbom
moderate    = koristan, ali sam po sebi nije presudannot
weak        = pozadinski kontekst, ne citira se u prvom redu
```

Dodati scope u `LegalProvision` model:

```php
public function scopeDevastating(Builder $query): Builder
{
    return $query->where('strength', 'devastating');
}

public function scopeWithRebuttals(Builder $query): Builder
{
    return $query->whereJsonLength('rebuts', '>', 0);
}

// Dohvati odredbe koje čine "combo" s danom odredbom
public function getComplements(): Collection
{
    if (empty($this->complements)) return collect();

    return static::query()
        ->where(function ($q) {
            foreach ($this->complements as $ref) {
                // Parse "PZ čl.150" format
                if (preg_match('/^(\w+)\s+čl\.(\d+)(?:\s+st\.(\d+))?/', $ref, $m)) {
                    $q->orWhere(function ($sub) use ($m) {
                        $sub->where('law_short', $m[1])
                            ->where('article', $m[2]);
                        if (isset($m[3])) $sub->where('paragraph', $m[3]);
                    });
                }
            }
        })
        ->get();
}
```

**Commit:**
```bash
git add database/migrations/ database/seeders/LegalProvisionsSeeder.php app/Models/LegalProvision.php
git commit -m "feat: expanded legal provisions with rebuts matrix, complements, and strength ratings"
```

---

## A3: Referenca na Postojeće Dokumente + Prilozi — Obogaćivanje Task 11

Task 11 (AttachmentCollector) definira registar priloga, ali nedostaje: (1) stvarni popis dokumenata koji POSTOJE u sustavu, (2) automatsko generiranje kronoloških sastavaka, (3) smart-linkanje između dokumenata.

### Task 11a: DocumentInventory — Automatsko Prepoznavanje Dokumenata

**Opis:** Sustav koji skenira `storage/app/legal-artillery/case-documents/` direktorij, prepoznaje tipove dokumenata po imenu i sadržaju, i gradi inventar. Svaki dokument ima: tip, datum, opis, OCR status, i listu profila kojima je relevantan.

**Files:**
- Create: `app/Services/LegalArtillery/DocumentInventory.php`
- Create: `config/legal-artillery-documents.php`
- Test: `tests/Unit/Services/LegalArtillery/DocumentInventoryTest.php`

**Konfiguracija:**

```php
// config/legal-artillery-documents.php
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
```

**DocumentInventory implementacija:**

```php
// app/Services/LegalArtillery/DocumentInventory.php
<?php

namespace App\Services\LegalArtillery;

class DocumentInventory
{
    private array $documents;
    private array $generatedDocs;
    private string $caseDocDir;

    public function __construct()
    {
        $this->documents = config('legal-artillery-documents.documents', []);
        $this->generatedDocs = config('legal-artillery-documents.generated_documents', []);
        $this->caseDocDir = storage_path('app/legal-artillery/case-documents');
    }

    /**
     * Status svakog dokumenta — postoji li fizički?
     */
    public function audit(): array
    {
        $results = ['found' => [], 'missing' => [], 'critical_missing' => []];

        foreach ($this->documents as $doc) {
            $path = "{$this->caseDocDir}/{$doc['filename']}";
            if (file_exists($path)) {
                $results['found'][] = $doc;
            } else {
                $results['missing'][] = $doc;
                if ($doc['critical'] ?? false) {
                    $results['critical_missing'][] = $doc;
                }
            }
        }

        return $results;
    }

    /**
     * Vrati sortiran popis priloga za profil — s oznakom postoji/nedostaje.
     */
    public function forProfile(string $profileKey): array
    {
        $relevant = array_filter($this->documents, function ($doc) use ($profileKey) {
            $profiles = $doc['profiles'] ?? [];
            return in_array($profileKey, $profiles) || in_array('all', $profiles);
        });

        // Sortiraj kronološki
        usort($relevant, fn($a, $b) => strcmp($a['date'], $b['date']));

        return array_map(function ($doc) {
            $path = "{$this->caseDocDir}/{$doc['filename']}";
            $doc['exists'] = file_exists($path);
            $doc['path'] = $doc['exists'] ? $path : null;
            return $doc;
        }, $relevant);
    }

    /**
     * Generiraj formatirani popis priloga za DOCX.
     */
    public function generateAttachmentList(string $profileKey, string $language = 'hr'): string
    {
        $docs = $this->forProfile($profileKey);
        $descKey = $language === 'en' ? 'description_en' : 'description_hr';

        $lines = [];
        $i = 1;
        foreach ($docs as $doc) {
            $status = $doc['exists'] ? '' : ' [NEDOSTAJE]';
            $note = '';
            if (!$doc['exists'] && ($doc['notes'] ?? null)) {
                $note = " — {$doc['notes']}";
            }
            $lines[] = "Prilog {$i}: {$doc[$descKey]}{$status}{$note}";
            $i++;
        }

        return implode("\n", $lines);
    }

    /**
     * Koliko ključnih dokumenata nedostaje?
     */
    public function criticalMissingCount(): int
    {
        return count($this->audit()['critical_missing']);
    }
}
```

**Artisan audit komanda:**

```php
// Dodati u FireCommand kao opciju
// php artisan legal:fire predsjednik_suda --audit
if ($this->option('audit')) {
    $inventory = new DocumentInventory();
    $audit = $inventory->audit();

    $this->info("📦 Inventar dokumenata:");
    $this->info("  ✅ Pronađeno: " . count($audit['found']));
    $this->warn("  ❌ Nedostaje: " . count($audit['missing']));
    if (count($audit['critical_missing']) > 0) {
        $this->error("  🔴 KRITIČNI koji nedostaju: " . count($audit['critical_missing']));
        foreach ($audit['critical_missing'] as $doc) {
            $this->error("     - {$doc['description_hr']}");
        }
    }
    return;
}
```

**Commit:**
```bash
git add app/Services/LegalArtillery/DocumentInventory.php config/legal-artillery-documents.php
git commit -m "feat: DocumentInventory with full case document registry and audit capability"
```

---

## A4: Sudska Praksa — Obogaćivanje Task 10

Task 10 (LegalPrecedentsSeeder) definira strukturu. Na temelju pravnog istraživanja, puni se **konkretnim presudama** s citatima na izvornom jeziku.

### Task 10a: Kompletni Seeder sa Svim Presudama

**Opis:** 12+ presuda — USRH, VSRH, ECHR — svaka s brojem odluke, datumom, ključnim pravnim stavom, citatom na izvornom jeziku, i mappingom na profile.

**Novi precedenti za dodati:**

```php
$precedents = [
    // ============================================================
    // USTAVNI SUD RH
    // ============================================================
    [
        'court' => 'USRH',
        'case_number' => 'U-III-3071/2006',
        'decision_date' => '2009-03-18',
        'published_in' => 'NN 42/2009',
        'source_url' => 'https://narodne-novine.nn.hr/clanci/sluzbeni/2009_04_42_983.html',
        'parties' => null,
        'key_holding' => 'Pogrešna ili izostala uputa o pravnom lijeku čini povredu čl.18 st.1 i čl.29 st.1 Ustava. Stranka ne smije trpjeti štetne posljedice zbog postupanja po pogrešnoj uputi.',
        'key_quote' => 'Ustavni sud na kraju podsjeća da je temeljni zahtjev svakog pravnog poretka utemeljenog na načelu vladavine prava da sudovi poznaju propise koje primjenjuju u konkretnim slučajevima i da daju zakonitu i pravilnu uputu o pravnom lijeku. Stoga stranke zbog postupanja po pogrešnoj uputi o pravnom lijeku koju daju sudovi ne smiju trpjeti štetne posljedice.',
        'quote_language' => 'hr',
        'relevance_to_case' => 'Direktno primjenjiva: ako pogrešna uputa = povreda, tada NIKAKVA uputa (email odbijanje) = a fortiori teža povreda. Koristiti u ustavnoj tužbi kao stožernu presudu.',
        'tags' => ['ustavni_sud', 'predsjednik_suda', 'ombudsman', 'all_profiles'],
        'strength' => 'devastating',
    ],
    [
        'court' => 'USRH',
        'case_number' => 'U-III-2258/2018',
        'decision_date' => '2020-02-26',
        'published_in' => 'NN 37/2020',
        'source_url' => 'https://narodne-novine.nn.hr/clanci/sluzbeni/2020_03_37_813.html',
        'parties' => null,
        'key_holding' => 'Obrazloženja sudskih odluka moraju sadržavati dostatne, ozbiljne i relevantne razloge. Nedostatak razloga = arbitrarnost.',
        'key_quote' => 'Prava zajamčena Ustavom i međunarodnim pravnim aktima... bila bi iluzorna i teorijska, a ne stvarna i učinkovita, kada ne bi postojala obveza sudbene vlasti da... svoje odluke obrazloži.',
        'quote_language' => 'hr',
        'relevance_to_case' => 'Email bez obrazloženja = arbitrarna odluka. Koristiti za čl.29 argument i za argument da prava postaju "iluzorna i teorijska".',
        'tags' => ['ustavni_sud', 'predsjednik_suda', 'ombudsman', 'all_profiles'],
        'strength' => 'devastating',
    ],
    [
        'court' => 'USRH',
        'case_number' => 'U-I-4497/2005',
        'decision_date' => '2006-11-22',
        'published_in' => 'NN 2/2007',
        'source_url' => null,
        'parties' => null,
        'key_holding' => 'Čl.34 Ustava zahtijeva obrazloženi pisani sudski nalog za pretragu doma. Sudska kontrola pretrage mora biti stvarna, ne formalna.',
        'key_quote' => null,
        'quote_language' => 'hr',
        'relevance_to_case' => 'Podržava argument da čl.34 zahtijeva mogućnost naknadne provjere zakonitosti pretrage — što je nemoguće bez uvida u spis.',
        'tags' => ['ustavni_sud', 'home_search', 'echr_application'],
        'strength' => 'strong',
    ],

    // ============================================================
    // VRHOVNI SUD RH
    // ============================================================
    [
        'court' => 'VSRH',
        'case_number' => 'I Kž-135/2018',
        'decision_date' => '2018-05-27',
        'published_in' => null,
        'source_url' => null,
        'parties' => null,
        'key_holding' => 'Pretraga provedena pod krinkom "očevida" bez naredbe za pretragu = nezakonita. Svi dokazi prikupljeni takvom pretragom su nezakoniti po čl.10 ZKP.',
        'key_quote' => null,
        'quote_language' => 'hr',
        'relevance_to_case' => 'Ako analiza naredbe pokaže procesne nedostatke, ova presuda podržava argument za izdvajanje. Bez uvida — ne možemo ni analizirati.',
        'tags' => ['izdvajanje_dokaza', 'kazneni_sud_motion', 'dorh_production'],
        'strength' => 'strong',
    ],
    [
        'court' => 'VSRH',
        'case_number' => 'I Kž-Us 113/10',
        'decision_date' => '2010-09-28',
        'published_in' => null,
        'source_url' => null,
        'parties' => null,
        'key_holding' => 'Protiv rješenja kojim se odbija prijedlog za izdvajanje nezakonitih dokaza dopuštena je žalba.',
        'key_quote' => null,
        'quote_language' => 'hr',
        'relevance_to_case' => 'Važno za proceduralni aspekt: čak i ako kazneni sud odbije izdvajanje, postoji žalba — za razliku od sadašnje situacije gdje nema NI odluke NI žalbe.',
        'tags' => ['izdvajanje_dokaza', 'kazneni_sud_motion'],
        'strength' => 'moderate',
    ],
    [
        'court' => 'VSRH',
        'case_number' => 'Pravno shvaćanje od 8.3.2019.',
        'decision_date' => '2019-03-08',
        'published_in' => 'Kazneni odjel VSRH',
        'source_url' => 'https://www.vsrh.hr/CustomPages/Static/HRV/Files/Radovi/DKos/Nezakoniti%20dokazi-Opatija%202017.pdf',
        'parties' => null,
        'key_holding' => 'Sud mora odlučiti o svakom prijedlogu za izdvajanje nezakonitih dokaza. Odbijanje bez razmatranja = bitna povreda odredaba kaznenog postupka.',
        'key_quote' => 'Sud o svakom takvom prijedlogu mora odlučiti, osim ako ne utvrdi da je riječ o zlouporabi prava.',
        'quote_language' => 'hr',
        'relevance_to_case' => 'Ako buduća obrana podnese prijedlog za izdvajanje, sud ga NE smije ignorirati. Ovo je backup za kazneni postupak.',
        'tags' => ['izdvajanje_dokaza', 'kazneni_sud_motion'],
        'strength' => 'strong',
    ],

    // ============================================================
    // ECHR — Pristup spisu i jednakost oružja
    // ============================================================
    [
        'court' => 'ECHR',
        'case_number' => '23541/94',
        'decision_date' => '2001-02-13',
        'published_in' => 'ECHR Reports',
        'source_url' => 'https://hudoc.echr.coe.int/eng?i=001-59208',
        'parties' => 'Garcia Alva v. Germany',
        'key_holding' => 'Jednakost oružja nije osigurana ako branitelju nije omogućen pristup dokumentima iz istražnog spisa koji su bitni za učinkovito osporavanje zakonitosti postupanja. Tajnost istrage ne opravdava potpunu uskratu pristupa.',
        'key_quote' => 'Equality of arms is not ensured if counsel is denied access to those documents in the investigation file which are essential in order effectively to challenge the lawfulness of his client\'s detention.',
        'quote_language' => 'en',
        'relevance_to_case' => 'STOŽERNA ECHR presuda za naš slučaj. Direktno primjenjiva: branitelju je uskraćen pristup spisu pretrage koji je bitan za osporavanje zakonitosti. Koristiti u ECHR prijavi i ustavnoj tužbi.',
        'tags' => ['echr_application', 'ustavni_sud', 'dorh_production', 'all_profiles'],
        'strength' => 'devastating',
    ],
    [
        'court' => 'ECHR',
        'case_number' => '28901/95',
        'decision_date' => '2000-02-16',
        'published_in' => 'ECHR Reports 2000-II',
        'source_url' => 'https://hudoc.echr.coe.int/eng?i=001-58496',
        'parties' => 'Rowe and Davis v. United Kingdom',
        'key_holding' => 'Tužiteljstvo mora otkriti obrani sve materijalne dokaze u svom posjedu. Tužiteljstvo koje samo odlučuje o objelodanjivanju je "sudac u vlastitom predmetu" — povreda čl.6.',
        'key_quote' => 'The prosecution authorities should disclose to the defence all material evidence in their possession for or against the accused.',
        'quote_language' => 'en',
        'relevance_to_case' => 'Primjenjiva na DORH zahtjev: DORH mora pribaviti i objelodaniti spis pretrage. Ako DORH sam odlučuje što obrana smije vidjeti = "judge in own cause".',
        'tags' => ['echr_application', 'dorh_production'],
        'strength' => 'devastating',
    ],
    [
        'court' => 'ECHR',
        'case_number' => '51693/13',
        'decision_date' => '2017-03-16',
        'published_in' => null,
        'source_url' => 'https://hudoc.echr.coe.int/eng?i=001-172280',
        'parties' => 'Modestou v. Greece',
        'key_holding' => 'Općenita naredba za pretragu bez specifikacije predmeta = povreda čl.8. Pretraga od 12.5 sati bez prisutnosti stanara i bez specifičnog opisa traženih predmeta je nerazmjerna.',
        'key_quote' => null,
        'quote_language' => 'en',
        'relevance_to_case' => 'Podržava argument da bez uvida u naredbu ne možemo provjeriti je li bila specifična ili općenita. Ako je bila općenita kao u Modestou = čl.8 povreda.',
        'tags' => ['echr_application', 'izdvajanje_dokaza', 'home_search'],
        'strength' => 'strong',
    ],
    [
        'court' => 'ECHR',
        'case_number' => '71205/11',
        'decision_date' => '2020-10-29',
        'published_in' => null,
        'source_url' => 'https://hudoc.echr.coe.int/eng?i=001-205528',
        'parties' => 'Doroż v. Poland',
        'key_holding' => 'Pretraga temeljena na nedovoljnim dokazima o stvarnoj kriminalnoj aktivnosti = nerazmjerna. Sud dodijelio EUR 10,000 neimovinske štete.',
        'key_quote' => 'The search of the applicant\'s residence was not justified by "relevant" and "sufficient" reasons and the principle of proportionality had not been complied with.',
        'quote_language' => 'en',
        'relevance_to_case' => 'Referenca za visinu odštete u ECHR prijavi + argument da naredba mora biti temeljena na "relevantnim i dovoljnim razlozima" — bez uvida ne možemo to provjeriti.',
        'tags' => ['echr_application', 'home_search'],
        'strength' => 'strong',
    ],

    // ============================================================
    // ECHR — Predmeti protiv Hrvatske
    // ============================================================
    [
        'court' => 'ECHR',
        'case_number' => '41526/10',
        'decision_date' => '2012-07-24',
        'published_in' => null,
        'source_url' => 'https://hudoc.echr.coe.int/eng?i=001-112322',
        'parties' => 'Đorđević v. Croatia',
        'key_holding' => 'Hrvatska povrijedila čl.13 EKLJP jer podnositelji nisu imali djelotvorni pravni lijek za zaštitu svojih prava.',
        'key_quote' => null,
        'quote_language' => 'en',
        'relevance_to_case' => 'Dokazuje OBRAZAC — Hrvatska ima sistemski problem s djelotvornim pravnim lijekovima. Koristiti za čl.13 argument u ECHR prijavi.',
        'tags' => ['echr_application'],
        'strength' => 'strong',
    ],
    [
        'court' => 'ECHR',
        'case_number' => '8857/16',
        'decision_date' => '2023-12-05',
        'published_in' => null,
        'source_url' => null,
        'parties' => 'F.S. v. Croatia',
        'key_holding' => 'Protuteze u domaćem postupku bile su "nedovoljno učinkovite" — podnositelju nisu dani "nikakvi činjenični elementi" na temelju kojih je donesena odluka.',
        'key_quote' => null,
        'quote_language' => 'en',
        'relevance_to_case' => 'IZUZETNO RELEVANTNA — gotovo identična činjenična situacija: uskrata pristupa materijalima bez obrazloženja. Koristiti kao recentnu presudu protiv Hrvatske.',
        'tags' => ['echr_application', 'ustavni_sud'],
        'strength' => 'devastating',
    ],
    [
        'court' => 'ECHR',
        'case_number' => '25703/11',
        'decision_date' => '2015-11-20',
        'published_in' => null,
        'source_url' => null,
        'parties' => 'Dvorski v. Croatia [GC]',
        'key_holding' => 'Strogi standardi za odricanje od jamstava pravičnog suđenja — prava se ne smiju "usput" zanemarivati.',
        'key_quote' => null,
        'quote_language' => 'en',
        'relevance_to_case' => 'Grand Chamber presuda protiv Hrvatske — pojačava argument da se pravo na pristup spisu ne smije uskratiti neformalnim emailom.',
        'tags' => ['echr_application'],
        'strength' => 'moderate',
    ],
    [
        'court' => 'ECHR',
        'case_number' => 'Horvat v. Croatia',
        'decision_date' => '2001-07-26',
        'published_in' => null,
        'source_url' => null,
        'parties' => 'Horvat v. Croatia',
        'key_holding' => 'Nedostatak prakse ukazuje na neizvjesnost pravnog lijeka u praktičnom smislu — nepostojanje djelotvornog lijeka.',
        'key_quote' => 'The absence of further case-law indicates the present uncertainty of a remedy in practical terms.',
        'quote_language' => 'en',
        'relevance_to_case' => 'Fundamentalna čl.13 presuda protiv Hrvatske. Neformalno email odbijanje stvara "neizvjesnost pravnog lijeka u praktičnom smislu".',
        'tags' => ['echr_application'],
        'strength' => 'strong',
    ],

    // ============================================================
    // ECHR — Pretraga doma i nadzor
    // ============================================================
    [
        'court' => 'ECHR',
        'case_number' => '10828/84',
        'decision_date' => '1993-02-25',
        'published_in' => 'ECHR Series A No. 256-A',
        'source_url' => null,
        'parties' => 'Funke v. France',
        'key_holding' => 'Ovlasti pretrage zahtijevaju odgovarajuće zaštitne mjere (safeguards) protiv proizvoljnog postupanja, čak i kad nije potrebno prethodno sudsko odobrenje.',
        'key_quote' => null,
        'quote_language' => 'en',
        'relevance_to_case' => 'Argument da i kad naredba postoji, moraju postojati zaštitne mjere — uključujući naknadnu provjeru zakonitosti, što je u našem slučaju onemogućeno.',
        'tags' => ['echr_application', 'home_search'],
        'strength' => 'moderate',
    ],

    // ============================================================
    // ECHR — Dragojević i Bašić (Hrvatska, nezakoniti dokazi)
    // ============================================================
    [
        'court' => 'ECHR',
        'case_number' => '68955/11',
        'decision_date' => '2015-01-15',
        'published_in' => null,
        'source_url' => null,
        'parties' => 'Dragojević v. Croatia',
        'key_holding' => 'Retroaktivna opravdanja deficijentnih naloga nisu dopuštena. Neadekvatno obrazložen nalog za pretragu može rezultirati povredom čl.8 i zahtijevati izdvajanje dokaza.',
        'key_quote' => null,
        'quote_language' => 'en',
        'relevance_to_case' => 'Ključno za izdvajanje dokaza — ako naredba nije adekvatno obrazložena (što ne možemo provjeriti bez uvida), dokazi su nezakoniti. Presuda PROTIV HRVATSKE.',
        'tags' => ['izdvajanje_dokaza', 'echr_application', 'home_search'],
        'strength' => 'devastating',
    ],
    [
        'court' => 'ECHR',
        'case_number' => '22251/13',
        'decision_date' => '2018-10-25',
        'published_in' => null,
        'source_url' => null,
        'parties' => 'Bašić v. Croatia',
        'key_holding' => 'Neadekvatno obrazložen nalog za prisluškivanje = povreda čl.8. Logika primjenjiva i na naloge za pretragu.',
        'key_quote' => null,
        'quote_language' => 'en',
        'relevance_to_case' => 'Još jedna presuda PROTIV HRVATSKE na temu neadekvatnog obrazloženja naloga. Pojačava Dragojević argument.',
        'tags' => ['izdvajanje_dokaza', 'echr_application'],
        'strength' => 'strong',
    ],
];
```

**Model metode za pametno dohvaćanje:**

```php
// Dodati u LegalPrecedent model

/**
 * Pronađi najjače presude za profil.
 */
public function scopeDevastatingForProfile(Builder $query, string $profileKey): Builder
{
    return $query->whereJsonContains('tags', $profileKey)
                 ->where('strength', 'devastating');
}

/**
 * Pronađi presude po sudu.
 */
public function scopeFromCourt(Builder $query, string $court): Builder
{
    return $query->where('court', $court);
}

/**
 * Generiraj citatni blok spreman za umetanje u LLM prompt.
 */
public function toCitationBlock(): string
{
    $block = "**{$this->parties ?? $this->case_number}** ({$this->court}, {$this->decision_date})";
    $block .= "\nStav: {$this->key_holding}";
    if ($this->key_quote) {
        $block .= "\nCitat [{$this->quote_language}]: \"{$this->key_quote}\"";
    }
    $block .= "\nRelevantnost: {$this->relevance_to_case}";
    return $block;
}
```

**Commit:**
```bash
git add database/seeders/LegalPrecedentsSeeder.php app/Models/LegalPrecedent.php
git commit -m "feat: 16 precedents (USRH, VSRH, ECHR) with strength ratings and citation blocks"
```

---

## A5: Strategija "Pomesti Ih" — Devastirajuća Argumentacija

Ovo je najvažnija dimenzija. Tri mehanizma koji transformiraju "dobar dopis" u "artiljerijski udar":

### Task 16: DevastatingArgumentBuilder — Kombinirani Udari

**Opis:** Umjesto izoliranih pravnih argumenata, gradimo **lančane argumente** (chain arguments) koji svaki sljedeći čine jačim. Princip: svaki protivnički odgovor otvara novu ranu.

**Files:**
- Create: `app/Services/LegalArtillery/DevastatingArgumentBuilder.php`
- Test: `tests/Unit/Services/LegalArtillery/DevastatingArgumentBuilderTest.php`

**Koncept: Argument Chains**

Svaki chain je niz koraka koji vodi do neizbježnog zaključka:

```php
// app/Services/LegalArtillery/DevastatingArgumentBuilder.php
<?php

namespace App\Services\LegalArtillery;

use App\Models\LegalPrecedent;
use App\Models\LegalProvision;

class DevastatingArgumentBuilder
{
    /**
     * Definirane devastirajuće lance argumenata.
     * Svaki lanac je niz koraka: premisa → zaključak → pojačanje → nokaut.
     */
    private array $chains = [

        // ================================================================
        // CHAIN 1: "Ne možete iscrpiti ono što ne postoji"
        // ================================================================
        'exhaustion_impossibility' => [
            'name' => 'Nemogućnost iscrpljivanja pravnog puta',
            'target_profiles' => ['ustavni_sud', 'echr_application'],
            'steps' => [
                [
                    'label' => 'PREMISA',
                    'argument' => 'Članak 18. st.1 Ustava jamči pravo na žalbu protiv pojedinačnih pravnih akata.',
                    'provisions' => ['Ustav čl.18 st.1'],
                ],
                [
                    'label' => 'ČINJENICA',
                    'argument' => 'Zahtjev za uvid u spis odbijen je neformalnim emailom, bez donošenja rješenja.',
                    'provisions' => [],
                ],
                [
                    'label' => 'LOGIČKI ZAKLJUČAK',
                    'argument' => 'Neformalni email NIJE pojedinačni pravni akt u smislu čl.18. Ne sadrži izreku, obrazloženje, ni pravnu pouku.',
                    'provisions' => ['Ustav čl.18 st.1', 'Ustav čl.29 st.1'],
                    'precedent' => 'U-III-3071/2006',
                ],
                [
                    'label' => 'POJAČANJE',
                    'argument' => 'Ustavni sud je u U-III-2258/2018 utvrdio da prava postaju "iluzorna i teorijska" kad odluke nisu obrazložene. Email bez obrazloženja = iluzorna odluka.',
                    'precedent' => 'U-III-2258/2018',
                ],
                [
                    'label' => 'NOKAUT',
                    'argument' => 'Iscrpljivanje pravnog puta pretpostavlja POSTOJANJE pravnog puta. Kad ne postoji formalni akt, ne postoji ni akt protiv kojeg se podnosi žalba. Zahtjev za iscrpljivanje nepostojećeg puta je contradictio in adjecto. Stoga se primjenjuje čl.62(63) Ustavnog zakona — podnositelj nije dužan iscrpiti ono što ne postoji.',
                    'provisions' => ['UZUSRH čl.62 st.1'],
                ],
            ],
            'killer_summary' => 'Ne možete iscrpiti pravni put koji ne postoji. Neformalni email nije pravni akt. Tražiti od podnositelja da se žali na email = tražiti žalbu na ništa.',
        ],

        // ================================================================
        // CHAIN 2: "Tajnost istrage ne pokriva arhivirane spise"
        // ================================================================
        'investigation_secrecy_demolished' => [
            'name' => 'Rušenje argumenta tajnosti izvida',
            'target_profiles' => ['predsjednik_suda', 'ombudsman', 'ustavni_sud'],
            'steps' => [
                [
                    'label' => 'PROTIVNIČKA POZICIJA',
                    'argument' => 'Sud se poziva na čl.206.f ZKP — tajnost izvida — kao razlog uskrate pristupa.',
                    'provisions' => ['ZKP čl.206.f'],
                ],
                [
                    'label' => 'ČINJENICA 1',
                    'argument' => 'Spis je arhiviran 10. srpnja 2025. Arhiviranje znači da su izvidi završeni i predmet zaključen.',
                    'provisions' => [],
                ],
                [
                    'label' => 'LOGIČKI ZAKLJUČAK',
                    'argument' => 'Čl.206.f štiti tajnost TEKUĆIH izvida. Kad su izvidi završeni i spis arhiviran, nema što štititi — svrha tajnosti je ispunjena.',
                    'provisions' => ['ZKP čl.206.f'],
                ],
                [
                    'label' => 'POJAČANJE 1',
                    'argument' => 'Čak i tijekom tekućih izvida, čl.184 st.5 ZKP jamči pravo uvida u zapisnike o hitnim radnjama u roku 30 dana. Pretraga doma = hitna radnja.',
                    'provisions' => ['ZKP čl.184 st.5'],
                ],
                [
                    'label' => 'POJAČANJE 2',
                    'argument' => 'ESLJP u Garcia Alva v. Germany: tajnost istrage NE opravdava potpunu uskratu pristupa dokumentima bitnim za osporavanje zakonitosti postupanja.',
                    'precedent' => 'Garcia Alva v. Germany (23541/94)',
                ],
                [
                    'label' => 'NOKAUT',
                    'argument' => 'Pozivanje na čl.206.f za arhivirani spis je ili: (a) nepoznavanje propisa — sud ne razlikuje tekuće od završenih izvida, ili (b) namjerna zlouporaba ovlasti — korištenje tajnosti kao izgovora za uskratu prava. U oba slučaja, radi se o povredi čl.29 Ustava.',
                    'provisions' => ['Ustav čl.29 st.1'],
                ],
            ],
            'killer_summary' => 'Tajnost izvida = tekući izvidi. Spis je arhiviran = izvidi završeni. Pozivanje na tajnost za zaključeni predmet je ili neznanje ili zlouporaba.',
        ],

        // ================================================================
        // CHAIN 3: "Čl.108 vs čl.150 — pogrešna odredba"
        // ================================================================
        'wrong_provision_applied' => [
            'name' => 'Sud primjenjuje pogrešnu odredbu',
            'target_profiles' => ['predsjednik_suda', 'ombudsman', 'ministarstvo_pravosudja', 'ustavni_sud'],
            'steps' => [
                [
                    'label' => 'PROTIVNIČKA POZICIJA',
                    'argument' => 'Sudac Bertok odbija pristup pozivajući se na čl.108 PZ — podnositelj nije stranka u prekršajnom postupku.',
                    'provisions' => ['PZ čl.108'],
                ],
                [
                    'label' => 'PROTUARGUMENT',
                    'argument' => 'Čl.108 definira stranke. Ali čl.150 st.1 IZRIJEKOM proširuje pristup spisu i na osobe koje NISU stranke: "svakomu drugom tko za to ima opravdani interes".',
                    'provisions' => ['PZ čl.150 st.1'],
                ],
                [
                    'label' => 'POJAČANJE 1',
                    'argument' => 'Nadalje, čl.150 st.4 predviđa da u završenom postupku o pristupu odlučuje PREDSJEDNIK SUDA, ne sudac koji je vodio postupak. Sudac Bertok nije nadležan.',
                    'provisions' => ['PZ čl.150 st.4'],
                ],
                [
                    'label' => 'POJAČANJE 2',
                    'argument' => 'Osoba čiji je dom pretražen temeljem naredbe ima inherentni opravdani interes — njezina su prava iz čl.34 Ustava direktno pogođena.',
                    'provisions' => ['Ustav čl.34'],
                ],
                [
                    'label' => 'NOKAUT',
                    'argument' => 'Primjena čl.108 umjesto čl.150 na zahtjev za pristup spisu je fundamentalna pravna pogreška koja: (1) primjenjuje pogrešnu odredbu, (2) ignorira širu odredbu u istom zakonu, (3) donosi je nenadležna osoba. USRH U-III-3071/2006: sudovi su dužni poznavati propise koje primjenjuju.',
                    'provisions' => ['PZ čl.108', 'PZ čl.150 st.1', 'PZ čl.150 st.4'],
                    'precedent' => 'U-III-3071/2006',
                ],
            ],
            'killer_summary' => 'Sud primjenjuje čl.108 (definicija stranaka) umjesto čl.150 (pristup spisu). To je kao da citirate definiciju automobila kad vas netko pita za prometna pravila. Pogrešna odredba, pogrešna osoba, pogrešan zaključak.',
        ],

        // ================================================================
        // CHAIN 4: "Circulus vitiosus — začarani krug"
        // ================================================================
        'vicious_circle' => [
            'name' => 'Začarani krug uskrate',
            'target_profiles' => ['ustavni_sud', 'echr_application', 'ombudsman'],
            'steps' => [
                [
                    'label' => 'KORAK 1',
                    'argument' => 'Da bih osporio zakonitost pretrage, trebam vidjeti naredbu i spis.',
                ],
                [
                    'label' => 'KORAK 2',
                    'argument' => 'Da bih vidio spis, trebam podnijeti zahtjev.',
                ],
                [
                    'label' => 'KORAK 3',
                    'argument' => 'Zahtjev je odbijen — ali neformalno, bez rješenja.',
                ],
                [
                    'label' => 'KORAK 4',
                    'argument' => 'Da bih se žalio na odbijanje, trebam formalno rješenje.',
                ],
                [
                    'label' => 'KORAK 5',
                    'argument' => 'Rješenje neće biti doneseno — sud odgovara samo emailom.',
                ],
                [
                    'label' => 'ZAKLJUČAK',
                    'argument' => 'Situacija je circulus vitiosus — ne mogu pristupiti dokazima bez odluke, a ne mogu dobiti odluku bez pristupa postupku. ESLJP u Horvat v. Croatia: "neizvjesnost pravnog lijeka u praktičnom smislu" = povreda čl.13 EKLJP.',
                    'precedent' => 'Horvat v. Croatia',
                ],
            ],
            'killer_summary' => 'Sustav je dizajniran tako da blokira sam sebe. Trebam spis da bih se branio. Trebam odluku da bih dobio spis. Neću dobiti odluku jer se sud koristi emailom. To je Kafkina košmara pretočena u pravnu stvarnost.',
        ],

        // ================================================================
        // CHAIN 5: "Plod otrovnog drveta" (Fruit of poisonous tree)
        // ================================================================
        'fruit_of_poisonous_tree' => [
            'name' => 'Kontaminacija svih dokaza',
            'target_profiles' => ['kazneni_sud_motion', 'izdvajanje_dokaza', 'dorh_production'],
            'steps' => [
                [
                    'label' => 'PREMISA 1',
                    'argument' => 'Dokazi korišteni u kaznenom postupku prikupljeni su pretragom doma temeljem naredbe Pp Prz-74/2025-2.',
                ],
                [
                    'label' => 'PREMISA 2',
                    'argument' => 'Obrani je uskraćen pristup spisu pretrage — ne možemo provjeriti zakonitost naredbe.',
                ],
                [
                    'label' => 'PRAVNI ZAKLJUČAK',
                    'argument' => 'Uskrata pristupa spisu = povreda prava obrane (čl.10 st.2 toč.2 ZKP). Dokazi prikupljeni uz povredu prava obrane su nezakoniti.',
                    'provisions' => ['ZKP čl.10 st.2 toč.2'],
                ],
                [
                    'label' => 'POJAČANJE',
                    'argument' => 'Čak i ako se naredba pokaže zakonitom, sam postupak uskrate pristupa kontaminira dokaze. Dragojević v. Croatia: retroaktivno opravdanje deficijentnog naloga nije dopušteno.',
                    'precedent' => 'Dragojević v. Croatia (68955/11)',
                ],
                [
                    'label' => 'KASKADNI EFEKT',
                    'argument' => 'ZKP čl.10 st.2 toč.4: "Plod otrovnog drveta" — SVI dokazi izvedeni iz nezakonite pretrage su nezakoniti. To uključuje: fizičke dokaze, fotografije, svjedočenja policije o pronađenom, i sve što je iz toga proizašlo.',
                    'provisions' => ['ZKP čl.10 st.2 toč.4'],
                ],
                [
                    'label' => 'NOKAUT',
                    'argument' => 'Optužnica se temelji na dokazima iz pretrage. Pretraga se ne može verificirati. Neverificirana pretraga = sumnja u zakonitost. Sumnja + uskrata obrane = nezakoniti dokazi. Nezakoniti dokazi = urušavanje optužnice.',
                ],
            ],
            'killer_summary' => 'Ako ne mogu provjeriti zakonitost pretrage, ne mogu se braniti. Ako se ne mogu braniti, dokazi su nezakoniti. Ako su dokazi nezakoniti, sve izvedeno iz njih pada.',
        ],
    ];

    /**
     * Dohvati lanac argumenata za profil.
     */
    public function getChainsForProfile(string $profileKey): array
    {
        return array_filter($this->chains, function ($chain) use ($profileKey) {
            return in_array($profileKey, $chain['target_profiles']);
        });
    }

    /**
     * Generiraj tekst za LLM prompt — injekcija lance u generiranje.
     */
    public function buildArgumentInjection(string $profileKey): string
    {
        $chains = $this->getChainsForProfile($profileKey);
        if (empty($chains)) return '';

        $text = "## DEVASTIRAJUĆI LANCI ARGUMENATA\n\n";
        $text .= "Koristi ove lance argumenata kao okosnicu dokumenta. ";
        $text .= "Svaki lanac vodi do neizbježnog zaključka. ";
        $text .= "NE preskači korake — svaki korak gradi na prethodnom.\n\n";

        foreach ($chains as $key => $chain) {
            $text .= "### LANAC: {$chain['name']}\n";
            foreach ($chain['steps'] as $step) {
                $text .= "**{$step['label']}**: {$step['argument']}\n";
                if (isset($step['provisions'])) {
                    $text .= "  Odredbe: " . implode(', ', $step['provisions']) . "\n";
                }
                if (isset($step['precedent'])) {
                    $text .= "  Presuda: {$step['precedent']}\n";
                }
            }
            $text .= "\n💀 ZAKLJUČAK: {$chain['killer_summary']}\n\n";
            $text .= "---\n\n";
        }

        return $text;
    }

    /**
     * Dohvati samo killer_summary za brzi pregled.
     */
    public function getKillerSummaries(string $profileKey): array
    {
        $chains = $this->getChainsForProfile($profileKey);
        return array_map(fn($c) => [
            'name' => $c['name'],
            'summary' => $c['killer_summary'],
        ], $chains);
    }
}
```

### Task 17: ProfileContextBuilder v2 — Integracija Svega

**Opis:** Proširiti ProfileContextBuilder da injektira CIJELI kontekst: odredbe + presude + lance argumenata + uzorke + prilog-listu. Redoslijed materije u promptu je bitan.

**Modifikacija `ProfileContextBuilder::build()`:**

```php
public function build(DocumentProfile $profile): array
{
    $provisions = $this->loadProvisions($profile);
    $precedents = $this->loadPrecedents($profile);

    $attachmentCollector = new AttachmentCollector();
    $attachmentsSection = $attachmentCollector->generateAttachmentListSection($profile->key);

    $argumentBuilder = new DevastatingArgumentBuilder();
    $argumentInjection = $argumentBuilder->buildArgumentInjection($profile->key);
    $killerSummaries = $argumentBuilder->getKillerSummaries($profile->key);

    $commonBlocks = $this->loadCommonBlocks();
    $sample = $this->loadSampleDocument($profile->key);

    // Redoslijed u promptu je BITAN — od najvažnijeg do kontekstualnog:
    $injectedPrompt = $this->buildInjectedPrompt(
        provisions: $provisions,
        precedents: $precedents,
        argumentInjection: $argumentInjection,
        sample: $sample,
        commonBlocks: $commonBlocks,
        attachmentsSection: $attachmentsSection,
    );

    return [
        'provisions' => $provisions,
        'precedents' => $precedents,
        'argument_chains' => $killerSummaries,
        'attachments_section' => $attachmentsSection,
        'injected_prompt' => $injectedPrompt,
    ];
}

private function buildInjectedPrompt(
    array $provisions,
    array $precedents,
    string $argumentInjection,
    ?string $sample,
    ?string $commonBlocks,
    string $attachmentsSection,
): string {
    $prompt = '';

    // 1. LANCI ARGUMENATA — ovo je okosnica dokumenta
    if ($argumentInjection) {
        $prompt .= $argumentInjection . "\n";
    }

    // 2. ZAKONSKE ODREDBE — precizni citati
    $prompt .= "## PRAVNA BAZA — CITIRAJ PRECIZNO\n\n";
    $prompt .= "### Zakonske odredbe\n";
    foreach ($provisions as $p) {
        $prompt .= "**{$p['citation']}** ({$p['law_name']})\n";
        $prompt .= "> {$p['full_text']}\n";
        if ($p['interpretation']) {
            $prompt .= "INTERPRETACIJA: {$p['interpretation']}\n";
        }
        if (!empty($p['rebuts'])) {
            $prompt .= "POBIJA: " . implode('; ', $p['rebuts']) . "\n";
        }
        $prompt .= "\n";
    }

    // 3. SUDSKA PRAKSA — s citatima
    $prompt .= "### Sudska praksa\n";
    foreach ($precedents as $p) {
        $prompt .= "**{$p['citation']}** [{$p['court']}]\n";
        $prompt .= "Stav: {$p['key_holding']}\n";
        if ($p['key_quote']) {
            $prompt .= "Citat [{$p['quote_language']}]: \"{$p['key_quote']}\"\n";
        }
        $prompt .= "Relevantnost: {$p['relevance']}\n\n";
    }

    // 4. ZAJEDNIČKI BLOKOVI — formulacije za interpolaciju
    if ($commonBlocks) {
        $prompt .= "### Zajednički blokovi teksta\n";
        $prompt .= "Koristi kao polazište — adaptiraj, ne kopiraj.\n\n";
        $prompt .= $commonBlocks . "\n\n";
    }

    // 5. UZORAK — stilski vodič
    if ($sample) {
        $prompt .= "### Referentni uzorak dokumenta\n";
        $prompt .= "Stilski i strukturalni vodič — NE kopiraj doslovno.\n\n";
        $prompt .= $sample . "\n\n";
    }

    // 6. PRILOZI
    if ($attachmentsSection) {
        $prompt .= "### Prilozi\n";
        $prompt .= "Uključi PRILOZI sekciju na kraju dokumenta:\n\n";
        $prompt .= $attachmentsSection . "\n";
    }

    return $prompt;
}
```

### Task 18: ArgumentValidator v2 — Provjera Snage Lanca

**Opis:** Proširiti Task 14 (ArgumentValidator) da provjerava ne samo citate, nego i **snagu lanca argumenata**: je li svaki korak logički slijedi iz prethodnog? Je li nokaut neizbježan iz premisa?

**Dodati u validation prompt:**

```php
private function buildValidationPrompt(string $generatedDocument, string $profileKey): string
{
    $argumentBuilder = new DevastatingArgumentBuilder();
    $chains = $argumentBuilder->getChainsForProfile($profileKey);

    $prompt = "Pregledaj ovaj pravni dokument i ocijeni:\n\n";
    $prompt .= "## 1. TOČNOST CITATA\n";
    $prompt .= "Je li svaka pravna odredba citirana točno? (Da/Ne + greška)\n\n";
    $prompt .= "## 2. TOČNOST PRESUDA\n";
    $prompt .= "Je li svaka presuda ispravno navedena s brojem i datumom? (Da/Ne + greška)\n\n";
    $prompt .= "## 3. SNAGA LANCA ARGUMENATA\n";

    foreach ($chains as $chain) {
        $prompt .= "Lanac '{$chain['name']}':\n";
        $prompt .= "- Je li svaki korak logički slijedi iz prethodnog? (Da/Ne)\n";
        $prompt .= "- Je li nokaut neizbježan iz premisa? (Da/Ne)\n";
        $prompt .= "- Postoji li rupa koju protivnik može iskoristiti? (Da/Ne + koja)\n";
    }

    $prompt .= "\n## 4. TON I STIL\n";
    $prompt .= "Je li ton konzistentan s profilom? Ima li pretjeranih emocija ili slabih formulacija?\n\n";
    $prompt .= "## 5. UKUPNA OCJENA\n";
    $prompt .= "Ocijeni snagu dokumenta: WEAK / MODERATE / STRONG / DEVASTATING / FIRE_READY\n\n";
    $prompt .= "## 6. PRIJEDLOZI ZA POBOLJŠANJE\n";
    $prompt .= "Navedi max 3 konkretna poboljšanja koja bi podigli ocjenu.\n\n";
    $prompt .= "---\nDOKUMENT ZA PREGLED:\n\n{$generatedDocument}";

    return $prompt;
}
```

**Ocjene i pragovi:**

```
WEAK         = dokument ima logičke rupe, nedostaje praksa, protivnik može lako odgovoriti
MODERATE     = solidno, ali nedovoljno precizno — treba docijeliti citate
STRONG       = svi citati točni, argumenti logični, ali nokaut nije neizbiježan
DEVASTATING  = svaki argument vodi u nokaut, protivnik nema odgovor
FIRE_READY   = DEVASTATING + stilski besprijekoran + svi prilozi navedeni
```

Task 15 (IterativeRefiner) regenerira dokument dok ne postigne `FIRE_READY`.

**Commit:**
```bash
git add app/Services/LegalArtillery/DevastatingArgumentBuilder.php
git commit -m "feat: DevastatingArgumentBuilder with 5 chain arguments for maximum legal impact"
```

---

## Ukupna Kontrolna Lista: Što Sustav Ima Nakon Appendixa

| Komponenta | Opis | Task |
|------------|------|------|
| 16+ zakonskih odredbi | PZ, ZKP, Ustav, UZUSRH, ZS, ZPP, ZPPI, SP — s rebuttalima i combo vezama | Task 9 + 9a |
| 16 sudskih presuda | 3× USRH, 3× VSRH, 10× ECHR — s citatima na izvornom jeziku | Task 10 + 10a |
| 8 profila napada | Od predsjednika suda do ECHR-a — svaki s tonom, strukturom, pravnim temeljima | Task 1 |
| 8 kompletnih uzoraka | Puni referentni dokumenti za svaki profil + zajednički blokovi | Task 13 + 13a |
| 12 registriranih dokumenata | Svaki s datumom, tipom, i mappingom na profile | Task 11 + 11a |
| Auto-generirani prilozi | Kronologije, kompilacije, ECHR bundle — automatski | Task 11a |
| 5 lanci argumenata | Od "ne možete iscrpiti nepostojeće" do "plod otrovnog drveta" | Task 16 |
| Rebuttals matrica | Koji naš argument pobija koji protivnički | Task 9a |
| Strength rating | Svaka odredba i presuda ocjenjena: weak → devastating | Task 9a + 10a |
| Auto-validacija s lancima | LLM provjerava snagu lanca, ne samo citate | Task 18 |
| Auto-iteracija do FIRE_READY | Poboljšava dok nije devastirajuće | Task 15 |
| Gmail salva | Šalje .docx s prilozima direktno iz terminala | Task 5 |

### Redoslijed Izvršenja Novih Taskova

```
Task 9a  → Prošireni LegalProvisions (rebuts, complements, strength)
Task 10a → Kompletni LegalPrecedents (16 presuda)
Task 11a → DocumentInventory (registar dokumenata + audit)
Task 13a → Kompletni Sample Documents (8 profila + common blocks)
Task 16  → DevastatingArgumentBuilder (5 lanaca)
Task 17  → ProfileContextBuilder v2 (integracija svega)
Task 18  → ArgumentValidator v2 (provjera snage lanca)
```

Sve se gradi na postojećim taskovima 1-15 i proširuje ih. Svaki novi task ima jasan test i commit point.
