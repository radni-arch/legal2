<?php

namespace Database\Seeders;

use App\Models\LegalProvision;
use Illuminate\Database\Seeder;

/**
 * Seeder for Croatian legal provisions relevant to file access and defence rights.
 *
 * Seeds provisions from:
 * - Prekrsajni zakon (PZ)
 * - Zakon o kaznenom postupku (ZKP)
 * - Ustav Republike Hrvatske
 * - Ustavni zakon o Ustavnom sudu RH
 * - Zakon o sudovima (ZS)
 * - Zakon o puckom pravobranitelju (ZPP)
 * - European Convention on Human Rights (ECHR)
 *
 * Usage:
 * php artisan db:seed --class=LegalProvisionsSeeder
 */
class LegalProvisionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provisions = array_map(
            fn(array $provision) => $provision + [
                'rebuts' => [],
                'complements' => [],
                'strength' => 'strong',
            ],
            $this->getProvisions()
        );

        foreach ($provisions as $p) {
            LegalProvision::updateOrCreate(
                [
                    'law_short' => $p['law_short'],
                    'article' => $p['article'],
                    'paragraph' => $p['paragraph'] ?? null,
                    'point' => $p['point'] ?? null,
                ],
                $p
            );
        }

        $this->seedRebutsMatrix();

        $this->command->info('Seeded '.count($provisions).' legal provisions.');
    }

    /**
     * Get all legal provisions to seed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getProvisions(): array
    {
        return [
            // === PREKRSAJNI ZAKON ===
            [
                'law_name' => 'Prekrsajni zakon',
                'law_short' => 'PZ',
                'article' => '150',
                'paragraph' => '1',
                'point' => null,
                'title' => 'Razgledavanje i prepisivanje spisa',
                'full_text' => 'Stranke i sudionici u postupku imaju pravo razgledavati i prepisivati spise. To sud moze dopustiti i svakomu drugom tko za to ima opravdani interes.',
                'interpretation' => 'Kljucno: "svakomu drugom tko za to ima opravdani interes" - sire od stranackog statusa iz cl.108. Adresat pretrage ima inherentni opravdani interes jer je njegov dom pretrazen.',
                'tags' => ['file_access', 'predsjednik_suda', 'ombudsman', 'ministarstvo_pravosudja', 'all_profiles'],
                'rebuts' => ['Odbijanje pristupa jer podnositelj nije stranka iz cl.108 PZ'],
                'source_url' => 'https://www.zakon.hr/z/52/Prekrsajni-zakon',
            ],
            [
                'law_name' => 'Prekrsajni zakon',
                'law_short' => 'PZ',
                'article' => '150',
                'paragraph' => '4',
                'point' => null,
                'title' => 'Razgledavanje spisa zavrsenog postupka',
                'full_text' => 'Kad je postupak zavrsen, dopustenje za razgledavanje i prepisivanje spisa daje predsjednik suda.',
                'interpretation' => 'Spis Pp Prz-74/2025 arhiviran 10.7.2025 = postupak zavrsen. Nadlezan je predsjednik suda, NE sudac. Jurisdikcijska pogreska.',
                'tags' => ['file_access', 'predsjednik_suda', 'jurisdictional_error', 'all_profiles'],
                'rebuts' => ['Argument da sudac koji je vodio postupak odlucuje o zavrsenom spisu'],
                'source_url' => 'https://www.zakon.hr/z/52/Prekrsajni-zakon',
            ],
            [
                'law_name' => 'Prekrsajni zakon',
                'law_short' => 'PZ',
                'article' => '108',
                'paragraph' => null,
                'point' => null,
                'title' => 'Stranke u prekrsajnom postupku',
                'full_text' => 'Stranke u prekrsajnom postupku jesu ovlasteni tuzitelj i okrivljenik.',
                'interpretation' => 'Cl.150 st.1 IZRIJEKOM daje pristup i onima koji NISU stranke ("svakomu drugom s opravdanim interesom").',
                'tags' => ['file_access', 'counter_argument'],
                'source_url' => 'https://www.zakon.hr/z/52/Prekrsajni-zakon',
            ],

            // === ZAKON O KAZNENOM POSTUPKU ===
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '184',
                'paragraph' => '5',
                'point' => null,
                'title' => 'Uvid u zapisnike o hitnim radnjama',
                'full_text' => 'Osumnjicenik i njegov branitelj imaju pravo razgledavati predmete koji sluze kao dokaz te razgledavati zapisnike o radnjama iz clanka 213. ovoga Zakona, koje su provedene bez nazocnosti osumnjicenika ili njegova branitelja, u roku od 30 dana od dana poduzimanja radnje.',
                'interpretation' => 'Pretraga doma = hitna radnja (cl.213 ZKP). Rok od 30 dana istekao, ali pravo je POVRIJEDENO - temelj za izdvajanje dokaza po cl.10.',
                'tags' => ['defence_rights', 'dorh_production', 'kazneni_sud_motion', 'izdvajanje_dokaza'],
                'rebuts' => ['Argument da obrana nema pravo uvida dok traju izvidi'],
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '10',
                'paragraph' => '2',
                'point' => '2',
                'title' => 'Nezakoniti dokazi - povreda prava obrane',
                'full_text' => 'Nezakoniti su dokazi oni koji su pribavljeni povredom Ustavom, zakonom ili medunarodnim pravom zajamcenih prava obrane.',
                'interpretation' => 'Uskrata uvida u spis pretrage = povreda prava obrane. Dokazi prikupljeni pretragom postaju nezakoniti ako obrana nije mogla provjeriti zakonitost naloga.',
                'tags' => ['evidence_exclusion', 'izdvajanje_dokaza', 'kazneni_sud_motion'],
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '10',
                'paragraph' => '2',
                'point' => '3',
                'title' => 'Nezakoniti dokazi - bitna povreda postupka',
                'full_text' => 'Nezakoniti su dokazi oni koji su pribavljeni povredom odredaba kaznenog postupka koje su izricito predvidene kao razlog nezakonitosti dokaza.',
                'interpretation' => 'Ako pretraga nije provedena sukladno ZKP cl.240-246, dokazi su nezakoniti po ovoj osnovi.',
                'tags' => ['evidence_exclusion', 'izdvajanje_dokaza'],
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '9',
                'paragraph' => '2',
                'point' => null,
                'title' => 'Duznost prikupljanja i oslobadajucih dokaza',
                'full_text' => 'Drzavni odvjetnik je duzan s jednakom pozornoscu ispitati i prikupiti kako dokaze koji terete osumnjicenika, odnosno okrivljenika, tako i one koji mu idu u korist.',
                'interpretation' => 'DORH mora pribaviti i oslobadajuce dokaze. Ako prekrsajni spis sadrzi elemente koji idu u korist obrane, DORH ga mora ukljuciti.',
                'tags' => ['dorh_production', 'disclosure'],
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '183',
                'paragraph' => null,
                'point' => null,
                'title' => 'Pravo na razgledavanje spisa',
                'full_text' => 'Pravo na razgledavanje spisa predmeta obuhvaca pravo razgledavanja, prepisivanja, preslikavanja ili snimanja spisa i njihovih priloga.',
                'interpretation' => 'Sire od samog "citanja" - ukljucuje kopiranje i snimanje. Vazno za zahtjev kaznenom sudu.',
                'tags' => ['defence_rights', 'kazneni_sud_motion', 'dorh_production'],
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '206.f',
                'paragraph' => null,
                'point' => null,
                'title' => 'Tajnost izvida',
                'full_text' => 'Podaci prikupljeni tijekom izvida su tajni.',
                'interpretation' => 'Pozivanje na ovu odredbu - ali spis je ARHIVIRAN. Izvidi su zavrseni. Odredba se NE primjenjuje na arhivirane spise.',
                'tags' => ['counter_argument', 'investigation_secrecy'],
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '342',
                'paragraph' => null,
                'point' => null,
                'title' => 'Bitne povrede odredaba kaznenog postupka',
                'full_text' => 'Bitna povreda odredaba kaznenog postupka postoji ako je povrijedeno pravo obrane okrivljenika.',
                'interpretation' => 'Neobjelodanjivanje spisa pretrage = bitna povreda ako se ti dokazi koriste na sudu.',
                'tags' => ['dorh_production', 'defence_rights'],
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],

            // === ZKP — Pretraga doma (cl.243-247) ===
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '243',
                'paragraph' => '5',
                'point' => null,
                'title' => 'Dobrovoljno predavanje predmeta prije pretrage',
                'full_text' => 'Prije pocetka pretrazivanja pozvat ce se osoba kod koje se vrsi pretrazivanje da dobrovoljno preda osobu ili predmete koji se traze.',
                'interpretation' => 'Policija MORA pozvati okrivljenika da dobrovoljno preda trazene predmete PRIJE pocetka pretrage. Propust cini pretragu nezakonitom.',
                'tags' => ['search_procedure', 'izdvajanje_dokaza', 'evidence_exclusion'],
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '244',
                'paragraph' => '1',
                'point' => null,
                'title' => 'Svjedoci pri pretrazi doma',
                'full_text' => 'Pri pretrazivanju stana ili drugih prostorija moraju biti nazocna dva punoljetna gradanina kao svjedoci.',
                'interpretation' => 'Dva svjedoka MORAJU biti nazocna TIJEKOM CIJELE pretrage. Pretraga zapoceta prije dolaska svjedoka je nezakonita.',
                'tags' => ['search_procedure', 'izdvajanje_dokaza', 'evidence_exclusion'],
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '246',
                'paragraph' => '1',
                'point' => null,
                'title' => 'Sadrzaj naredbe za pretragu',
                'full_text' => 'Naredba za pretragu mora sadrzavati: oznaku prostorije ili osobe koja se pretrazuje, razloge za pretragu, predmete ili osobe koje se traze, te upozorenje da se pretres moze provesti i bez pristanka.',
                'interpretation' => 'ESLJP u Modestou v. Greece: opca naredba bez specificnih predmeta = povreda cl.8. Bez uvida ne mozemo provjeriti sadrzi li naredba sve zakonske elemente.',
                'tags' => ['home_search', 'izdvajanje_dokaza', 'echr_application'],
                'complements' => ['ZKP cl.240', 'Ustav cl.34'],
                'strength' => 'devastating',
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '247',
                'paragraph' => null,
                'point' => null,
                'title' => 'Izdvajanje nezakonitog dokaza iz spisa',
                'full_text' => 'Sud ce rjesenjem izdvojiti iz spisa nezakonit dokaz i on se ne smije upotrijebiti u postupku.',
                'interpretation' => 'Temeljni clanak za prijedlog za izdvajanje dokaza. Sud MORA donijeti rjesenje o izdvajanju. Uvodni paragraf podneska se poziva na ovu odredbu.',
                'tags' => ['evidence_exclusion', 'izdvajanje_dokaza'],
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '240',
                'paragraph' => null,
                'point' => null,
                'title' => 'Pretres stana — uvjeti',
                'full_text' => 'Pretres stana, prostorija i pokretnih stvari moze se poduzeti samo ako je vjerojatno da ce se pronaci tragovi kaznenog djela ili predmeti vazni za kazneni postupak.',
                'interpretation' => 'Naredba mora biti utemeljena na konkretnoj vjerojatnosti, ne apstraktnoj sumnji. Bez uvida u spis ne mozemo provjeriti je li ovaj uvjet zadovoljen.',
                'tags' => ['home_search', 'izdvajanje_dokaza', 'echr_application'],
                'complements' => ['Ustav cl.34', 'ZKP cl.10'],
                'strength' => 'devastating',
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '83',
                'paragraph' => null,
                'point' => null,
                'title' => 'Zapisnik — vjernost zapisa',
                'full_text' => 'U zapisnik se unosi bitan sadrzaj poduzete radnje, a osobito podaci vazni za ocjenu zakonitosti. Zapisnik treba vjerno prikazati tijek radnje.',
                'interpretation' => 'Netocni podaci o vremenu pretrage u zapisniku (sluzbeno 11:00-12:45, stvarno 10:25-13:40) predstavljaju tesku povredu ove odredbe i potkopavaju vjerodostojnost svih dokaza.',
                'tags' => ['procedural', 'izdvajanje_dokaza', 'evidence_exclusion'],
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],
            [
                'law_name' => 'Zakon o kaznenom postupku',
                'law_short' => 'ZKP',
                'article' => '9',
                'paragraph' => null,
                'point' => null,
                'title' => 'Nacelo zakonitosti dokaza',
                'full_text' => 'Dokazi pribavljeni na nezakonit nacin ne mogu se koristiti u kaznenom postupku.',
                'interpretation' => 'Opce nacelo koje podupire cl.246 i cl.247 — temelj cjelokupnog prijedloga za izdvajanje.',
                'tags' => ['evidence_exclusion', 'izdvajanje_dokaza'],
                'source_url' => 'https://www.zakon.hr/z/174/Zakon-o-kaznenom-postupku',
            ],

            // === ZAKON O SUZBIJANJU ZLOUPORABE DROGA ===
            [
                'law_name' => 'Zakon o suzbijanju zlouporabe droga',
                'law_short' => 'ZSZD',
                'article' => '54',
                'paragraph' => '3',
                'point' => null,
                'title' => 'Prekrsajne odredbe za posjedovanje droga',
                'full_text' => 'Novcanom kaznom od 5.000,00 do 20.000,00 kuna kaznit ce se za prekrsaj fizicka osoba koja posjeduje drogu iz cl.2 u kolicini koja ne prelazi kolicinu potrebnu za vlastitu uporabu.',
                'interpretation' => 'Naredba navodi "cl.54 st.3" ali NE specificira tocku (tocke 1-18). Formulacija "tocno neutvrdjena kolicina" sugerira da nalog nema konkretnu osnovanu sumnju — samo nagadjanje.',
                'tags' => ['warrant_deficiency', 'izdvajanje_dokaza'],
                'source_url' => 'https://www.zakon.hr/z/293/Zakon-o-suzbijanju-zlouporabe-droga',
            ],

            // === USTAV RH ===
            [
                'law_name' => 'Ustav Republike Hrvatske',
                'law_short' => 'Ustav',
                'article' => '18',
                'paragraph' => '1',
                'point' => null,
                'title' => 'Pravo na zalbu',
                'full_text' => 'Jamci se pravo na zalbu protiv pojedinacnih pravnih akata donesenih u postupku prvog stupnja pred sudom ili drugim ovlastenim tijelom.',
                'interpretation' => 'Neformalna email odbijanja NE predstavljaju "pojedinacni pravni akt" s pravnom poukom - cime se onemogucuje zalba. Ustavnosudska praksa: U-III-3071/2006.',
                'tags' => ['constitutional', 'ustavni_sud', 'ombudsman', 'all_profiles'],
                'rebuts' => ['Argument da neformalni email odgovor zadovoljava zahtjev za odlukom'],
                'source_url' => 'https://www.zakon.hr/z/94/Ustav-Republike-Hrvatske',
            ],
            [
                'law_name' => 'Ustav Republike Hrvatske',
                'law_short' => 'Ustav',
                'article' => '29',
                'paragraph' => '1',
                'point' => null,
                'title' => 'Pravicno sudenje',
                'full_text' => 'Svatko ima pravo da zakonom ustanovljeni neovisni i nepristrani sud pravicno i u razumnom roku odluci o njegovim pravima i obvezama.',
                'interpretation' => 'Odlucivanje emailom bez obrazlozenja i pouke = nije pravicno. Ustavnosudska praksa: U-III-2258/2018 - odluke MORAJU biti obrazlozene.',
                'tags' => ['constitutional', 'ustavni_sud', 'fair_trial', 'kazneni_sud_motion', 'all_profiles'],
                'rebuts' => ['Argument da sud ne mora obrazlagati odbijanje pristupa'],
                'source_url' => 'https://www.zakon.hr/z/94/Ustav-Republike-Hrvatske',
            ],
            [
                'law_name' => 'Ustav Republike Hrvatske',
                'law_short' => 'Ustav',
                'article' => '29',
                'paragraph' => '2',
                'point' => null,
                'title' => 'Pravo okrivljenika na upoznavanje s dokazima',
                'full_text' => 'Osoba optuzena za kazneno djelo ima pravo da se u najkracem roku obavijesti o prirodi i razlozima optuzbe protiv nje i o dokazima koji je terete.',
                'interpretation' => 'Uskrata uvida u spis pretrage = povreda ovog ustavnog prava. Okrivljenik ne moze provjeriti zakonitost naloga ni dokaznog postupka.',
                'tags' => ['constitutional', 'defence_rights', 'izdvajanje_dokaza', 'ustavni_sud'],
                'source_url' => 'https://www.zakon.hr/z/94/Ustav-Republike-Hrvatske',
            ],
            [
                'law_name' => 'Ustav Republike Hrvatske',
                'law_short' => 'Ustav',
                'article' => '34',
                'paragraph' => null,
                'point' => null,
                'title' => 'Nepovredivost doma',
                'full_text' => 'Dom je nepovrediv. Samo sud moze obrazlozenim pisanim nalogom utemeljenim na zakonu odrediti da se dom pretrazi.',
                'interpretation' => 'Pretraga direktno zadire u ovo pravo - adresat pretrage ima ustavno pravo provjeriti je li nalog bio zakonit i obrazlozen. Bez uvida u spis = pravo je iluzorno.',
                'tags' => ['constitutional', 'ustavni_sud', 'home_search', 'echr_application', 'izdvajanje_dokaza'],
                'source_url' => 'https://www.zakon.hr/z/94/Ustav-Republike-Hrvatske',
            ],
            [
                'law_name' => 'Ustav Republike Hrvatske',
                'law_short' => 'Ustav',
                'article' => '19',
                'paragraph' => '1',
                'point' => null,
                'title' => 'Sudska kontrola zakonitosti',
                'full_text' => 'Pojedinacni akti drzavne uprave i tijela koja imaju javne ovlasti moraju biti utemeljeni na zakonu. Zajamcuje se sudska kontrola zakonitosti pojedinacnih akata upravnih vlasti i tijela koja imaju javne ovlasti.',
                'interpretation' => 'Odbijanje donosenja formalnog rjesenja onemogucuje sudsku kontrolu zakonitosti.',
                'tags' => ['constitutional', 'ustavni_sud'],
                'source_url' => 'https://www.zakon.hr/z/94/Ustav-Republike-Hrvatske',
            ],
            [
                'law_name' => 'Ustav Republike Hrvatske',
                'law_short' => 'Ustav',
                'article' => '38',
                'paragraph' => '4',
                'point' => null,
                'title' => 'Pravo na pristup informacijama',
                'full_text' => 'Jamci se pravo na pristup informacijama koje posjeduju tijela javne vlasti.',
                'interpretation' => 'Ustavna osnova za pristup — sud je tijelo javne vlasti, spis je informacija. Ojacava ZPPI argument.',
                'tags' => ['constitutional', 'alternative_avenue', 'ustavni_sud'],
                'complements' => ['ZPPI cl.5'],
                'strength' => 'strong',
                'source_url' => 'https://www.zakon.hr/z/94/Ustav-Republike-Hrvatske',
            ],

            // === USTAVNI ZAKON ===
            [
                'law_name' => 'Ustavni zakon o Ustavnom sudu RH',
                'law_short' => 'UZUSRH',
                'article' => '62',
                'paragraph' => '1',
                'point' => null,
                'title' => 'Iznimka od iscrpljivanja pravnog puta',
                'full_text' => 'Ustavni sud ce pokrenuti postupak po ustavnoj tuzbi i prije no sto je iscrpljen pravni put, u slucaju kad se osporenim pojedinacnim aktom grubo vrijeda ustavna prava, a potpuno je razvidno da bi nepokretanjem ustavnosudskog postupka za podnositelja ustavne tuzbe mogle nastati teske i nepopravljive posljedice.',
                'interpretation' => 'Kumulacija: (1) uskrata pristupa spisu + (2) nedonosenje rjesenja + (3) prejudiciranje kaznene obrane = grubo vrijdanje + teske posljedice.',
                'tags' => ['ustavni_sud', 'procedural', 'gross_violation'],
                'source_url' => 'https://www.zakon.hr/z/351/Ustavni-zakon-o-Ustavnom-sudu-Republike-Hrvatske',
            ],

            // === ZAKON O SUDOVIMA ===
            [
                'law_name' => 'Zakon o sudovima',
                'law_short' => 'ZS',
                'article' => '72',
                'paragraph' => '6',
                'point' => null,
                'title' => 'Upravni nadzor - ispitivanje prituzbi gradana',
                'full_text' => 'Upravni nadzor obuhvaca ispitivanje prituzbi gradana na rad suda u pogledu odugovlacenja sudskog postupka, ponasanja sudaca ili drugog osoblja suda prema strankama tijekom postupka.',
                'interpretation' => 'Odbijanje donosenja rjesenja = administrativna nepravilnost u "ponasanju sudaca prema strankama".',
                'tags' => ['ministarstvo_pravosudja', 'administrative'],
                'source_url' => 'https://www.zakon.hr/z/122/Zakon-o-sudovima',
            ],

            // === SUDSKI POSLOVNIK ===
            [
                'law_name' => 'Sudski poslovnik',
                'law_short' => 'SP',
                'article' => '44',
                'paragraph' => null,
                'point' => null,
                'title' => 'Razgledavanje spisa trecih osoba',
                'full_text' => 'Osobama koje nisu stranke u postupku dopustenje za razgledavanje i prepisivanje spisa daje predsjednik suda, odnosno sudac pojedinac koji vodi postupak.',
                'interpretation' => 'Potvrduje nadleznost predsjednika suda za arhivirane spise. Sudac Bertok nije ovlasten odlucivati o zavrsenim predmetima — jurisdikcijska pogreska.',
                'tags' => ['file_access', 'predsjednik_suda', 'jurisdictional_error'],
                'rebuts' => ['Argument da sudac koji je vodio postupak odlucuje'],
                'complements' => ['PZ cl.150 st.4'],
                'strength' => 'strong',
                'source_url' => null,
            ],

            // === ZAKON O PUCKOM PRAVOBRANITELJU ===
            [
                'law_name' => 'Zakon o puckom pravobranitelju',
                'law_short' => 'ZPP',
                'article' => '4',
                'paragraph' => null,
                'point' => null,
                'title' => 'Nadleznost puckog pravobranitelja',
                'full_text' => 'Pucki pravobranitelj stiti ustavna i zakonska prava gradana u postupku pred tijelima drzavne uprave i tijelima s javnim ovlastima.',
                'interpretation' => 'Iako se pravobranitelj obicno ne mijesa u sudske predmete, systematsko uskracivanje formalnih odluka prelazi granicu sudske neovisnosti u ocitu zlouporabu.',
                'tags' => ['ombudsman'],
                'source_url' => 'https://www.zakon.hr/z/382/Zakon-o-puckom-pravobranitelju',
            ],

            // === ZAKON O PRAVU NA PRISTUP INFORMACIJAMA ===
            [
                'law_name' => 'Zakon o pravu na pristup informacijama',
                'law_short' => 'ZPPI',
                'article' => '5',
                'paragraph' => '1',
                'point' => null,
                'title' => 'Pravo na pristup informacijama',
                'full_text' => 'Informacije su dostupne svakoj domacoj ili stranoj fizickoj i pravnoj osobi u skladu s uvjetima i ogranicenjima ovoga Zakona.',
                'interpretation' => 'Alternativni put za pristup ako ZKP/PZ putevi ne uspiju. Ogranicenje: cl.1 st.3 iskljucuje sudske postupke. Ali: spis je ARHIVIRAN — nije vise "sudski postupak".',
                'tags' => ['alternative_avenue', 'ombudsman'],
                'rebuts' => ['Argument da je spis sudska tajna'],
                'complements' => ['Ustav cl.38 st.4'],
                'strength' => 'moderate',
                'source_url' => null,
            ],

            // === ECHR ===
            [
                'law_name' => 'European Convention on Human Rights',
                'law_short' => 'ECHR',
                'article' => '6',
                'paragraph' => '1',
                'point' => null,
                'title' => 'Right to a fair trial',
                'full_text' => 'In the determination of his civil rights and obligations or of any criminal charge against him, everyone is entitled to a fair and public hearing within a reasonable time by an independent and impartial tribunal established by law.',
                'interpretation' => 'Includes equality of arms, access to evidence, and right to be heard. Denial of access to investigative files violates this article.',
                'tags' => ['echr_application', 'fair_trial'],
                'source_url' => null,
            ],
            [
                'law_name' => 'European Convention on Human Rights',
                'law_short' => 'ECHR',
                'article' => '8',
                'paragraph' => '1',
                'point' => null,
                'title' => 'Right to respect for home',
                'full_text' => 'Everyone has the right to respect for his private and family life, his home and his correspondence.',
                'interpretation' => 'Home searches require proper legal basis and proportionality. Irregular searches without proper safeguards violate this article. Dragojevic v. Croatia (2015) — ESLJP utvrdio povredu cl.8 zbog nedostatnih proceduralnih jamstava.',
                'tags' => ['echr_application', 'home_search', 'izdvajanje_dokaza'],
                'source_url' => null,
            ],
            [
                'law_name' => 'European Convention on Human Rights',
                'law_short' => 'ECHR',
                'article' => '13',
                'paragraph' => null,
                'point' => null,
                'title' => 'Right to an effective remedy',
                'full_text' => 'Everyone whose rights and freedoms as set forth in this Convention are violated shall have an effective remedy before a national authority notwithstanding that the violation has been committed by persons acting in an official capacity.',
                'interpretation' => 'Where court presidents deny access without formal decisions and no appeal exists, this article is violated.',
                'tags' => ['echr_application'],
                'source_url' => null,
            ],
        ];
    }

    /**
     * Seed rebuttal links between provisions and common counter-arguments.
     */
    private function seedRebutsMatrix(): void
    {
        $matrix = [
            ['PZ', '150', '1', ['Odbijanje pristupa jer podnositelj nije stranka iz cl.108 PZ']],
            ['PZ', '150', '4', ['Argument da sudac koji je vodio postupak odlucuje o zavrsenom spisu']],
            ['ZKP', '184', '5', ['Argument da obrana nema pravo uvida dok traju izvidi']],
            ['Ustav', '18', '1', ['Argument da neformalni email odgovor zadovoljava zahtjev za odlukom']],
            ['Ustav', '29', '1', ['Argument da sud ne mora obrazlagati odbijanje pristupa']],
        ];

        foreach ($matrix as [$law, $article, $paragraph, $rebuts]) {
            LegalProvision::where('law_short', $law)
                ->where('article', $article)
                ->where('paragraph', $paragraph)
                ->update(['rebuts' => $rebuts]);
        }
    }
}
