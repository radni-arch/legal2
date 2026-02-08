<?php

namespace App\Http\Livewire;

use Carbon\Carbon;
use Livewire\Component;

class GupTimeline extends Component
{
    public array $officialItems;

    public array $realItems;

    public bool $showModal = false;

    public ?array $currentItem = null;

    public int $currentAssetIndex = 0;

    public ?array $currentAsset = null;

    /**
     * @var string[]
     */
    protected $listeners = [
        'openEvidence' => 'openEvidence',
    ];

    /**
     * @return void
     */
    public function mount()
    {
        $this->officialItems = [
            [
                'id' => 'o1',
                'content' => 'Nulti Dokument <br> NK-214-05/25-01/1155 <br> 511-07-11-25-2',
                'start' => Carbon::parse('2025-06-09T10:30:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T11:00:00', 'Europe/Zagreb'),
                'type' => 'range',
                'title' => 'Zahtjev policije za pretragu — Pp Prz-74/2025-1',
                'className' => 'item-official',
                'location' => 'Prekršajni sud',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/timeline_graphql.pdf', 'caption' => 'Epredmet GraphQL podatci - nulti dokument'],
                ],
                'detailsHtml' => <<<'HTML'
<h1>Nulti Dokument</h1>
<p>Policijska uprava osječko-baranjska, Sektor kriminalističke policije, Služba organiziranog kriminaliteta, podnosi obrazloženi zahtjev Općinskom sudu u Osijeku za izdavanje naredbe za pretragu doma i drugih prostorija kojima se koristi Andrija Glavaš, Primorska 5, Osijek (klasa: NK-214-05/25-01/1155, urbroj: 511-07-11-25-2, 9. lipnja 2025.).</p>
<p>U zahtjevu se navodi osnovana sumnja na počinjenje prekršaja prema Zakonu o suzbijanju zlouporabe droga (npr. posjedovanje tvari poput "amfetamin-speed", THC i dr.).</p>
<details><summary>Pravne reference</summary>
<ul>
<li>Zakon o suzbijanju zlouporabe droga — čl. 64 st. 3 i/ili čl. 54 st. 3</li>
<li>Zakon o kaznenom postupku (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li>
</ul>
</details>
HTML,
            ],
            [
                'id' => 'o2',
                'content' => 'Pretraga doma i drugih prostorija <br> Pp Prz-74/2025-2',
                'start' => Carbon::parse('2025-06-09T11:00:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T12:45:00', 'Europe/Zagreb'),
                'type' => 'range',
                'location' => 'Primorska 5',
                // 'type' => 'background',
                'title' => 'Pretraga doma i drugih prostorija (Osijek, Primorska 5)',
                'className' => 'item-official',
                // 'className' => 'bg-runway-off',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/NaredbaPretresPpPrz74.pdf', 'caption' => 'Naredba za pretragu doma'],
                ],
                'detailsHtml' => <<<'HTML'
<p>Po naredbi Općinskog suda u Osijeku, Prekršajni odjel broj Pp Prz-74/2025-2, izvršena je pretraga doma i drugih prostorija. Pronađeno i oduzeto (POPOP):</p>
<ul>
<li>Hašiš, PE vrećica, 18,00 g (POPOP 01422485)</li>
<li>Konoplja, 2 cvjetna vrha, staklenka, 2,09 g, &gt;0,3% THC (POPOP 01422485)</li>
<li>Konoplja, usitnjeni cvjetni vrhovi, PE vrećica, 2,35 g, &gt;0,3% THC (POPOP 01422485)</li>
<li>Konoplja, više cvjetnih vrhova, PE vrećica, 21,4 g, &gt;0,3% THC (POPOP 01422486)</li>
<li>MDMA, bijela grumenasta materija, PE vrećica, 0,38 g (POPOP 01422486)</li>
<li>Amfetamin „speed“, PE vrećica, 3 g (POPOP 01422486)</li>
<li>Psilocibin gljive, staklenka, 6,9 g (POPOP 01422488)</li>
<li>Digitalna vaga „digital scala“, 0,01–100 g, s tragovima konoplje (POPOP 01422487)</li>
<li>Digitalna vaga „On balance CJ-20 Scale“, 0,001–20 g, s priborom (POPOP 01422487)</li>
<li>Automatska puška M70 AB, ser. br. 669991; 2 spremnika s 60 kom. streljiva 7,62 mm; dodatnih 80 kom. streljiva 7,62 mm; bajonet; pribor za čišćenje (POPOP 01422489)</li>
<li>Dodatna 3 spremnika s 89 kom. streljiva 7,62 mm; 2 kutije s 80 kom. streljiva 7,62 mm (POPOP 01422490)</li>
</ul>
<details><summary>Pravne reference</summary>
<ul>
<li>Naredba: Općinski sud Osijek, Prekršajni odjel, Pp Prz-74/2025-2</li>
<li>KZ — čl. 190. st. 2. (Neovlaštena proizvodnja i promet drogama)</li>
<li>KZ — čl. 331. st. 1. i st. 3. (Nedozvoljeno posjedovanje, izrada i nabavljanje oružja i eksplozivnih tvari)</li>
</ul>
</details>
HTML,
            ],
            [
                'id' => 'o3',
                'content' => 'Potvrda',
                'start' => Carbon::parse('2025-06-09T11:10:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-photo-offi',
                'title' => 'Potvrda',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/OduzetoPotvrda1.pdf', 'caption' => 'Potvrda'],
                ],
            ],
            [
                'id' => 'o4',
                'content' => 'Potvrda',
                'start' => Carbon::parse('2025-06-09T11:20:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-photo-offi',
                'title' => 'Potvrda',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/OduzetoPotvrda2.pdf', 'caption' => 'Potvrda'],
                ],
            ],
            [
                'id' => 'o5',
                'content' => 'Potvrda',
                'start' => Carbon::parse('2025-06-09T11:30:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-photo-offi',
                'title' => 'Potvrda',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/OduzetoPotvrda3.pdf', 'caption' => 'Potvrda'],
                ],
            ],
            [
                'id' => 'o6',
                'content' => 'Potvrda',
                'start' => Carbon::parse('2025-06-09T11:40:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-photo-offi',
                'title' => 'Potvrda',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/OduzetoPotvrda4.pdf', 'caption' => 'Potvrda'],
                ],
            ],
            [
                'id' => 'o7',
                'content' => 'Potvrda',
                'start' => Carbon::parse('2025-06-09T11:50:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-photo-offi',
                'title' => 'Potvrda',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/OduzetoPotvrda5.pdf', 'caption' => 'Potvrda'],
                ],
            ],
            [
                'id' => 'o8',
                'content' => 'Potvrda',
                'start' => Carbon::parse('2025-06-09T12:00:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-photo-offi',
                'title' => 'Potvrda',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/OduzetoPotvrda6.pdf', 'caption' => 'Potvrda'],
                ],
            ],
            [
                'id' => 'o9',
                'content' => 'Ispitivanje uzoraka i nalazi',
                'start' => Carbon::parse('2025-06-09T12:00:00', 'Europe/Zagreb'),
                'type' => 'point',
                'title' => 'Preliminarno ispitivanje uzoraka i nalazi',
                'className' => 'item-interview-offi',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/SluzbenaZabiljeskaIspitivanjeMaterije.pdf', 'caption' => 'Ispitivanje uzoraka poljskim testovima'],
                ],
                'detailsHtml' => '<p>Po obavljenom vaganju provedeno je preliminarno ispitivanje uzoraka sljedećim testovima:</p><ol><li>M.M.C. International B.V. General Screening / Multi Party Drugs Test</li><li>M.M.C. International B.V. Cannabis Test</li><li>M.M.C. International B.V. Crystal Meth/XTC Test (Meth)</li><li>M.M.C. International B.V. Amphetamines/MDMA</li><li>M.M.C. International B.V. Opiates/Amphetamines Test</li></ol><p>Rezultati ukazuju na osnove sumnje u prisutnost: konoplje s &gt;0,3% THC (više uzoraka), MDMA, amfetamina ("speed") te psilocibinskih gljiva. Privremeno oduzeti predmeti bit će proslijeđeni na pohranu u Centar za forenzična ispitivanja, istraživanja i vještačenja "Ivan Vučetić" u Zagrebu.</p><p><em>Napomena:</em> Točnu vrstu tvari, masu i udio djelatne tvari moguće je odrediti isključivo vještačenjem u CFIIV "Ivan Vučetić".</p><details><summary>Pravne i predmetne reference</summary><ul><li>Službena zabilješka sastavljena u PU osječko-baranjskoj: 09.06.2025.</li><li>Predmeti proslijeđeni: CFIIV "Ivan Vučetić" (Zagreb)</li></ul></details>',
            ],
            [
                'id' => 'o10',
                'content' => 'Oduzimanje mobitela bez naloga',
                'start' => Carbon::parse('2025-06-09T12:50:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T13:15:00', 'Europe/Zagreb'),
                'type' => 'range',
                'location' => 'PU Osječko-baranjska, Lav Mirski',
                'title' => 'Privremeno oduzimanje mobilnog uređaja (POPOP 01422491)',
                'className' => 'item-off',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/ZapisnikOduzimanjeMobitelaBezNaloga.pdf', 'caption' => 'Oduzimanje mobitela bez naloga'],
                ],
                'detailsHtml' => '<p>Istoga dana, u prostorijama PU Osječko-baranjske, privremeno je oduzet mobilni telefon marke „Huawei nova 9 SE“ (IMEI: 8679090622498823 i 867909063998821; pozivni brojevi: 098/965 5609, 095/584 6314).</p><details><summary>Pravne reference</summary><ul><li>POPOP ser. br. 01422491</li><li>ZKP — odredbe o privremenom oduzimanju predmeta</li></ul></details> <p>Provedena dokazna radnja privremenog oduzimanja predmeta bez naloga. Izdana potvrda o oduzimanju: br. 01 922 437. Popis privremeno oduzetih predmeta priložen uz zapisnik.</p><details><summary>Pravne reference</summary><ul><li>Članak 261. Zakona o kaznenom postupku (ZKP)</li><li>Članak 212. ZKP</li><li>Članak 85. stavak 1. i 7. ZKP (prava prisutnih osoba)</li><li>Članak 206.f ZKP (tajnost izvida)</li><li>Članak 213. stavak 3. ZKP (nejavnost istraživanja / tajnost)</li><li>Članak 231. stavak 2. ZKP (nejavnost istrage / tajnost)</li></ul></details>',
            ],
            [
                'id' => 'o11',
                'content' => '???',
                'start' => Carbon::parse('2025-06-09T13:15:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T14:35:00', 'Europe/Zagreb'),
                'type' => 'range',
                'location' => '',
                'title' => '???',
                'className' => 'item-off',
                'assets' => [],
                'detailsHtml' => '?',
            ],
            [
                'id' => 'o12',
                'content' => 'Pouka o pravima osumnjičenika',
                'start' => Carbon::parse('2025-06-09T14:35:00', 'Europe/Zagreb'),
                'type' => 'point',
                'location' => 'PU Osječko-baranjska, Lav Mirski',
                'title' => 'Pouka o pravima osumnjičenika',
                'className' => 'item-off',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/PoukaOPravimaOsumnjicenikaPpPrz74.pdf', 'caption' => 'Antidatirana pouka'],
                ],
                'detailsHtml' => '<p>Temeljem članka 208.a st. 1 i 2 Zakona o kaznenom postupku (ZKP), osumnjičenik Andrija Glavaš (rođen 14.10.1989. u Osijeku) poučen je o svojim pravima. Mjesto: Osijek. Vrijeme: 09.06.2025. u 14:35 sati. Policijski službenik: Aleksandar Sitarić. Predmet: 511-07-11-K-51/25.</p><ul><li>Pravo na branitelja</li><li>Pravo na tumačenje i prevođenje</li><li>Pravo da nije dužan iskazivati niti odgovarati na pitanja</li><li>Pravo da u svakom trenutku može napustiti policijske prostorije, osim u slučaju uhićenja</li></ul><details><summary>Pravne reference</summary><ul><li>Zakon o kaznenom postupku — čl. 208.a st. 1 i 2</li><li>Zakon o kaznenom postupku — čl. 8 (tumačenje i prevođenje)</li><li>Zakon o kaznenom postupku — čl. 108 (napuštanje policijskih prostorija)</li></ul></details>',
            ],
            [
                'id' => 'o13',
                'content' => 'Ispitivanje i AV snimanje osumnjičenika',
                'start' => Carbon::parse('2025-06-09T14:45:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T15:12:00', 'Europe/Zagreb'),
                'type' => 'range',
                'location' => 'PU Osječko-baranjska, Lav Mirski',
                'title' => 'Ispitivanje i AV snimanje osumnjičenika – PU osječko-baranjska',
                'className' => 'item-off',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/iznudjenaIzjava.pdf', 'caption' => 'Iznudjena izjava'],
                ],
                'detailsHtml' => '<p>U prostorijama PU osječko-baranjske započinje audio-video snimanje i ispitivanje osumnjičenika Andrije Glavaša. Prisotni: policijski službenik Aleksandar Simović, zapisničar Mate Surać, stručna osoba za tehničko snimanje Miroslav Pandurević i osumnjičenik. Osobe su upozorene na tajnost izvida i mogućnost korištenja snimke kao dokaza.</p><p>Osumnjičenik potvrđuje da razumije jezik postupka, prima pisanu pouku o pravima, te se najprije odriče prava na branitelja uz upozorenje o posljedicama, uz napomenu da to pravo može zatražiti u bilo kojem trenutku.</p><p>Tereti se za: neovlaštenu proizvodnju i promet drogama te nedozvoljeno posjedovanje, izradu i nabavljanje oružja i eksplozivnih tvari. Pretraga je pronašla više vrsta droga i oružje.</p><details><summary>Pravne reference</summary><ul><li>Zakon o kaznenom postupku – čl. 22 st. 1 (podatci pri prvom ispitivanju, pravna pouka)</li><li>Kazneni zakon – čl. 190 st. 2 (neovlaštena proizvodnja i promet drogama)</li><li>Kazneni zakon – čl. 31 st. 1 i 3 (nedozvoljeno posjedovanje, izrada i nabavljanje oružja i eksplozivnih tvari); u jednom navodu spomenut i čl. 301 st. 1 i 3</li></ul></details><details><summary>Oduzeti/pronađeni predmeti (sažetak)</summary><ul><li>Hašiš: ukupno 18 g</li><li>Konoplja/cvjetni vrhovi: 2,09 g; 2,35 g; 21,4 g (sadržaj THC > 0,3%)</li><li>MDMA: ~0,36 g</li><li>Amfetamin (speed): ~3 g</li><li>Psilocibinske gljive: ~6,9 g</li><li>Digitalne vage: 2 kom (0,01–100 g; 0,001–20 g) s tragovima biljne materije</li><li>Automatska puška M70 AB, 5 spremnika, ~309 kom streljiva 7,62 mm, bajunet, pribor za čišćenje</li></ul></details><p>Osumnjičenik u obrani navodi da marihuanu konzumira rekreativno (ne bavi se prodajom), a pušku je zadržao iz znatiželje nakon što je prijatelj preminuo. Na kraju se odriče prava na pregled/reprodukciju snimke i čitanje zapisnika; ispitivanje završava u 15:12.</p>',
            ],
        ];

        $this->realItems = [
            [
                'id' => 'r1',
                'content' => 'Zahtjev za pretragu — Pp Prz-74/2025',
                'start' => Carbon::parse('2025-06-09T09:00:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T10:00:00', 'Europe/Zagreb'),
                'type' => 'range',
                'location' => 'Prekršajni sud',
                'title' => 'Zahtjev za pretragu',
                'className' => 'item-real',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/timeline_graphql.pdf', 'caption' => 'Epredmet GraphQL podatci - nulti dokument'],
                ],
                'detailsHtml' => '<h1>Nulti Dokument</h1><p>Policijska uprava osječko-baranjska, Sektor kriminalističke policije, Služba organiziranog kriminaliteta, podnosi obrazloženi zahtjev Općinskom sudu u Osijeku za izdavanje naredbe za pretragu doma i drugih prostorija kojima se koristi Andrija Glavaš, Primorska 5, Osijek (klasa: NK-214-05/25-01/1155, urbroj: 511-07-11-25-2, 9. lipnja 2025.).</p><p>U zahtjevu se navodi osnovana sumnja na počinjenje prekršaja prema Zakonu o suzbijanju zlouporabe droga (npr. posjedovanje tvari poput "amfetamin-speed", THC i dr.).</p><details><summary>Pravne reference</summary><ul><li>Zakon o suzbijanju zlouporabe droga — čl. 64 st. 3 i/ili čl. 54 st. 3</li><li>Zakon o kaznenom postupku (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li></ul></details>',
            ],
            [
                'id' => 'r2',
                'content' => 'Pretres prije pretresa',
                'location' => 'Primorska 5',
                'start' => Carbon::parse('2025-06-09T10:10:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T11:00:00', 'Europe/Zagreb'),
                'type' => 'range',
                'title' => 'Pretres prije pretresa',
                'className' => 'item-real',
                'assets' => [
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/messenger.png', 'caption' => 'Pretres prije početka pretresa'],
                ],
                'detailsHtml' => '<h1>Pretres</h1><p>Oni su zapravo došli oko 10:10, ja sam taman radio. Tj. bio sam na pozivu, čuo sam njihovo lupanje po vratima ali nisam mogao odmah reagirati zbog poziva u kojemu sam bio. U međuvremenu šaljem poruku ženi da vidim zna li ona tko lupa, tj. da li je nekoga/nešto naručivala.</p><p>Ona odgovara da nezna. Osim lupanja počeli su me zvati i na mobitel s skrivenog broja. Poziv završava i odlazim vidjeti tko je. Nakon što sam otvorio vrata, pretres je faktički već počeo a ja sam kidnapiran iz radne sobe i laptopa (kidnapiran s posla). Ovo se dogodilo oko cca 10:25 vidljivo na Facebook Messengeru, gdje vlada radijska tišina do 11:13.</p><details><summary>Pravne reference</summary><ul><li>Zakon o suzbijanju zlouporabe droga — čl. 64 st. 3 i/ili čl. 54 st. 3</li><li>Zakon o kaznenom postupku (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 130/20, 80/22, 36/24, 72/25)</li></ul></details>',
            ],
            [
                'id' => 'r3',
                'content' => 'FB poruka: netko lupa i zvoni',
                'start' => Carbon::parse('2025-06-09T10:22:00', 'Europe/Zagreb'),
                'type' => 'point',
                'title' => 'FB poruka: netko lupa i zvoni (10:22)',
                'className' => 'item-real',
                'assets' => [
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/messenger2.png', 'caption' => 'FB poruka: netko lupa i zvoni'],
                ],
            ],
            [
                'id' => 'r4',
                'content' => 'K-9 ulazak prije svjedoka',
                'location' => 'Primorska 5',
                'start' => Carbon::parse('2025-06-09T10:40:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T10:55:00', 'Europe/Zagreb'),
                'type' => 'range',
                'title' => 'K-9 ulazak',
                'className' => 'item-k9',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/NaknadnaPouka.pdf', 'caption' => 'Odricanje od branitelja bez vremena, potpisano u policijskoj postaji a piše Primorska 5'],
                ],
                'detailsHtml' => '<h1>Neformalni početak</h1><p>Nakon otvaranja vrata, sami su se pustili unutra, te su obznanili kako imaju nalog za pretres. U stanu je bio i moj pas Božo - ovo ih je znatno omelo, nisu računali na još jednog psa. Tako da sam bio primoran (bez mog pristanka) izači van stana s njima i Božom, iz razloga što su oni doveli K-9 tim za traženje droge (daleko pretjerano ako se mene pita, radi količina za osobnu uporabu). Krenuli smo van, prvo njih 4 a zadnji ja i Božo. Kako sam izašao iz stana, primjertio sam da desno od vrata čovjek s psom. On se malo stisnio uza zid, kako bi interakcija pas VS pas prošla glatko. Te čim smo mi izašli on je brže bolje ušao u stan i zatvorio vrata. Od ovog trenutka on je faktički potpuno sam u stanu. Mi smo izašli ispred, te sam tada prvi puta čuo spomen na danas već famozne "pakete". Doduše tada je bio samo "paket" u jednini. Također valja istaknuti da oni čak ni tada nisu bili u stanju jasno i glasno artikulirati taj paket. Više su aludirali na naručivanje paketa, nego što su direktno pitali/komentirali. Sveukupno smo vani bili 10-ak minuta. Dovoljno za 2 cigare zapaliti. Usred toga, jedan od njih je išao u potragu za svjedocima. Prvo se popeo na kat iznad te je tamo zamolio susjedu da bude svjedok, ona se samo spustila na kat ispod te pretpostavljam ušla u stan u kojemu nije bilo mene ili supruge (tj. vlasnika stana). U stanu je vidjela nepoznatog muškarca s psom koji je bio potpuno samu u stanu cca 5-10 minuta. Drugi svjedok je nađen na kraju ulice, nakon susjede. Te je drugi svjedok prošao pored nas i vidio da stojimo vani. Ovo sve svjedoci mogu i potvrditi. Kada je K-9 bio gotovo, nazvao je jednog od policajaca na mobitel i javio mu da \'ima svega\'. Pretpostavljam da je koristeči psa pronašao veliku teglu u kojoj je bilo 20g marihuane u jednoj vrečici i 18g hašiša u drugoj. To je vrlo vjerovatno, i to zbog korištenja psa te snažnog mirisa već spomenuthi substanci. Kada sam se ja vratio svjedoci su već bili unutra.</p><details></details>',
            ],
            [
                'id' => 'r5',
                'content' => '👤 Svjedok 1 uveden',
                'start' => Carbon::parse('2025-06-09T10:46:00', 'Europe/Zagreb'),
                'type' => 'point',
                'title' => 'Svjedok 1 uveden ~(10:46)',
                'className' => 'item-real',
                'assets' => [],
                'detailsHtml' => '',
            ],
            [
                'id' => 'r6',
                'content' => '👤 Svjedok 2 uveden',
                'start' => Carbon::parse('2025-06-09T10:51:00', 'Europe/Zagreb'),
                'type' => 'point',
                'title' => 'Svjedok 2 uveden ~(10:51)',
                'className' => 'item-real',
                'assets' => [],
                'detailsHtml' => '',
            ],
            [
                'id' => 'r7',
                'content' => 'K-9 poziv: „ima svega“',
                'start' => Carbon::parse('2025-06-09T10:55:00', 'Europe/Zagreb'),
                'type' => 'point',
                'title' => 'K-9 dojava',
                'className' => 'item-k9',
                'assets' => [],
                'detailsHtml' => '<h1>Završetak inicijalnog K-9 pretraživanja</h1><p>Nakon cca 10 minuta boravka K-9 tima u stanu bez prisutnosti vlasnika, vodič telefonom javlja policajcu ispred da „ima svega“, što je vjerojatno vezano uz veću teglu s marihuanom i hašišem.</p>',
            ],
            [
                'id' => 'r8',
                'content' => 'Pretres zapravo',
                'location' => 'Primorska 5',
                'start' => Carbon::parse('2025-06-09T11:00:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T13:45:00', 'Europe/Zagreb'),
                'type' => 'range',
                'title' => 'Pretres (detalji)',
                'className' => 'item-real',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/ZapisnikOPretresu.pdf', 'caption' => 'Zapisnik o pretresu'],
                ],
                'detailsHtml' => '<h1>Neformalni početak</h1><p>Nakon otvaranja vrata, sami su se pustili unutra, te su obznanili kako imaju nalog za pretres. U stanu je bio i moj pas Božo - ovo ih je znatno omelo, nisu računali na još jednog psa. Tako da sam bio primoran (bez mog pristanka) izači van stana s njima i Božom, iz razloga što su oni doveli K-9 tim za traženje droge (daleko pretjerano ako se mene pita, radi količina za osobnu uporabu). Krenuli smo van, prvo njih 4 a zadnji ja i Božo. Kako sam izašao iz stana, primjertio sam da desno od vrata čovjek s psom. On se malo stisnio uza zid, kako bi interakcija pas VS pas prošla glatko. Te čim smo mi izašli on je brže bolje ušao u stan i zatvorio vrata. Od ovog trenutka on je faktički potpuno sam u stanu. Mi smo izašli ispred, te sam tada prvi puta čuo spomen na danas već famozne "pakete". Doduše tada je bio samo "paket" u jednini. Također valja istaknuti da oni čak ni tada nisu bili u stanju jasno i glasno artikulirati taj paket. Više su aludirali na naručivanje paketa, nego što su direktno pitali/komentirali. Sveukupno smo vani bili 10-ak minuta. Dovoljno za 2 cigare zapaliti. Usred toga, jedan od njih je išao u potragu za svjedocima. Prvo se popeo na kat iznad te je tamo zamolio susjedu da bude svjedok, ona se samo spustila na kat ispod te pretpostavljam ušla u stan u kojemu nije bilo mene ili supruge (tj. vlasnika stana). U stanu je vidjela nepoznatog muškarca s psom koji je bio potpuno samu u stanu cca 5-10 minuta. Drugi svjedok je nađen na kraju ulice, nakon susjede. Te je drugi svjedok prošao pored nas i vidio da stojimo vani. Ovo sve svjedoci mogu i potvrditi. Kada je K-9 bio gotovo, nazvao je jednog od policajaca na mobitel i javio mu da \'ima svega\'. Pretpostavljam da je koristeči psa pronašao veliku teglu u kojoj je bilo 20g marihuane u jednoj vrečici i 18g hašiša u drugoj. To je vrlo vjerovatno, i to zbog korištenja psa te snažnog mirisa već spomenuthi substanci. Kada sam se ja vratio svjedoci su već bili unutra.</p><details></details>',
            ],
            [
                'id' => 'r9',
                'content' => 'Tegla potvrda',
                'start' => Carbon::parse('2025-06-09T11:09:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-photo-real',
                'title' => 'Tegla potvrda',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/OduzetoPotvrda1.pdf', 'caption' => 'Tegla potvrda'],
                ],
            ],
            [
                'id' => 'r10',
                'content' => '💬 Poruka ženi',
                'start' => Carbon::parse('2025-06-09T11:13:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-real',
                'title' => "SMS: 'Pretres, našli teglu...' (11:13)",
                'icon' => '💬',
                'assets' => [
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/messenger3.png', 'caption' => 'Poruka supruzi'],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/messenger2.png', 'caption' => 'Poruka supruzi'],
                ],
            ],
            [
                'id' => 'r11',
                'content' => 'Potvrda',
                'start' => Carbon::parse('2025-06-09T11:20:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-photo-real',
                'title' => 'Potvrda',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/OduzetoPotvrda2.pdf', 'caption' => 'Potvrda'],
                ],
            ],
            [
                'id' => 'r12',
                'content' => 'Potvrda',
                'start' => Carbon::parse('2025-06-09T11:30:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-photo-real',
                'title' => 'Potvrda',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/OduzetoPotvrda3.pdf', 'caption' => 'Potvrda'],
                ],
            ],
            [
                'id' => 'r13',
                'content' => 'Potvrda',
                'start' => Carbon::parse('2025-06-09T11:40:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-photo-real',
                'title' => 'Potvrda',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/OduzetoPotvrda4.pdf', 'caption' => 'Potvrda'],
                ],
            ],
            [
                'id' => 'r14',
                'content' => 'Potvrda',
                'start' => Carbon::parse('2025-06-09T11:50:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-photo-real',
                'title' => 'Potvrda',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/OduzetoPotvrda5.pdf', 'caption' => 'Potvrda'],
                ],
            ],
            [
                'id' => 'r15',
                'content' => 'Potvrda',
                'start' => Carbon::parse('2025-06-09T12:00:00', 'Europe/Zagreb'),
                'type' => 'point',
                'className' => 'item-photo-real',
                'title' => 'Potvrda',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/OduzetoPotvrda6.pdf', 'caption' => 'Potvrda'],
                ],
            ],
            [
                'id' => 'r16',
                'content' => 'Robocop',
                'location' => 'Primorska 5',
                'start' => Carbon::parse('2025-06-09T12:05:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T12:30:00', 'Europe/Zagreb'),
                'type' => 'range',
                'title' => 'Robocop',
                'className' => 'item-k9',
                'assets' => [],
                'detailsHtml' => '<p>Policajac koji je došao radi puške.</p>',
            ],
            [
                'id' => 'r17',
                'content' => '👤 Svjedoci odlaze',
                'start' => Carbon::parse('2025-06-09T12:45:00', 'Europe/Zagreb'),
                'type' => 'point',
                'title' => 'Svjedoci odlaze',
                'className' => 'item-real',
                'assets' => [],
                'detailsHtml' => '',
            ],
            [
                'id' => 'r18',
                'content' => '📸 Fotografiranje',
                'location' => 'Primorska 5',
                'start' => Carbon::parse('2025-06-09T13:13:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T13:40:00', 'Europe/Zagreb'),
                'type' => 'range',
                'title' => 'EXIF foto-elaborata (13:16–13:40)',
                'className' => 'item-photo',
                'icon' => '📸',
                'assets' => [
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8563.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8564.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8565.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8566.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8567.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8568.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8569.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8570.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8571.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8572.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8573.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8574.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8575.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8576.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8577.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8578.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8579.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8580.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8581.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8582.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8583.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8584.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8585.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8586.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8587.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8588.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8589.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8590.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8591.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8592.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8593.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8594.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8595.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8596.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8597.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8598.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8599.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8600.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8601.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8602.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8603.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8604.JPG', 'caption' => ''],
                    ['kind' => 'image', 'src' => storage_path('app/private/docs').'/04_KOPIJA_COPY/OKA-185-25- GLAVA?, PRIMORSKA 5.OSIJEK/IMG_8605.JPG', 'caption' => ''],
                ],
                'detailsHtml' => '<h1>Foto elaborat – metapodaci</h1><p>Iz metapodataka (EXIF) fotografija iz ZDO foto-elaborata proizlazi da su snimke nastajale između 13:16 i 13:40. Na jednoj se vidi Messenger poruka i TradingView s cijenom zlata – oba elementa potvrđuju točnost vremenskih oznaka.</p>',
            ],
            [
                'id' => 'r19',
                'content' => 'Oduzimanje mobitela bez naloga',
                'location' => 'PU Osječko-baranjska, Lav Mirski',
                'start' => Carbon::parse('2025-06-09T13:50:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T14:15:00', 'Europe/Zagreb'),
                'type' => 'range',
                'title' => 'Privremeno oduzimanje mobilnog uređaja (POPOP 01422491)',
                'className' => 'item-real',
                'assets' => [
                    'kind' => 'pdf', 'src' => storage_path('app/private/docs').'/ZapisnikOduzimanjeMobitelaBezNaloga.pdf', 'caption' => 'Oduzimanje mobitela bez naloga',
                ],
            ],
            [
                'id' => 'r20',
                'content' => 'Neformalno ispitivanje',
                'location' => 'PU Osječko-baranjska, Lav Mirski',
                'start' => Carbon::parse('2025-06-09T14:16:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T14:44:00', 'Europe/Zagreb'),
                'type' => 'range',
                'title' => 'Neformalno ispitivanje',
                'className' => 'item-real',
                'assets' => [],
                'detailsHtml' => '<h1>Neformalno ispitivanje</h1><p>U ovom periodu se događalo neformalno ispitivanje koje nije zabilježeno na snimci. Također su učestale i prijetnje istražnim zatvorom. Oni su se uhvatili za to što sam poduzetnik i imam obrt te su ponavljali da ću završiti u istražnom zatvoru te samim time izgubiti klijente i svoj legalni biznis. Naravno, to mogu spriječiti ako surađujem. U ovom periodu se iskristalizirala priča o \'naručivanju s interneta\'. Kako nisam htio ničije ime spominjati to je zvučalo kako dobar izlaz. Ono čega nisam bio u potpunosti svjestan je činjenica da se dogodio \'Gunglov paket\'. Drugim riječima namjernim prešučivanjem informacija za što me se uopče sumnjići sam doveden u zabludu te je od mene iznuđena izjava o naručivanju s interneta - što uopče nije istina i ne može se potvrditi s objektivnim dokazima (bankovini podatci, poštanski podatci, Wester Union podatci...). No ista priče nije smetala policajcima već su poticali tu priču (sada samo to ponoviš/formaliziraš kao izjavu koju daješ i onda idemo svi kući - izbjegavaš istražni zatvor). Također je došlo i do kopanja po mobitelu, bez ikakvog naloga ili kontrole. </p>',
            ],
            [
                'id' => 'r21',
                'content' => 'Iznuđena izjava',
                'location' => 'PU Osječko-baranjska, Lav Mirski',
                'start' => Carbon::parse('2025-06-09T14:45:00', 'Europe/Zagreb'),
                'end' => Carbon::parse('2025-06-09T15:12:00', 'Europe/Zagreb'),
                'type' => 'range',
                'title' => 'Iznuđena izjava',
                'className' => 'item-real',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/iznudjenaIzjava.pdf', 'caption' => 'Iznudjena izjava'],
                ],
                'detailsHtml' => '<p>Već letimičan pogled na transkript vrišti da su formalnosti odrađene više radi snimke nego radi stvarne zaštite mojih prava. <br> Policajac gotovo školski inscenira čitanje prava: prvo Vas pita jeste li primili pouku <b>(a zapravo Vam je istu gurao na potpis usred snimanja, ne prije njega)</b>, potom opet formalno spominje pravo na branitelja, pa još jednom objašnjava značenje branitelja i opet traži potvrdu odricanja – sve to unutar par minuta snimke. Taj neobično naglašen fokus na proceduralne fraze ukazuje na to da je *“odrađivanje” prava orkestrirano*: policajac ponavlja i podcrtava formalnosti pred kamerom kako bi naknadno papirnato pokrio njihove propuste prije snimanja. Drugim riječima, dojam je da se prava recitiraju “za zapisnik”, a ne zato što su Vam od srca i pravodobno objašnjena. \n\n**Primjer:** U transkriptu oko 8. minute (08:40-09:30) imate situaciju gdje Vam policajac kaže da imate pravo na branitelja, Vi kažete da ne želite, onda on punih minutu monologizira o značaju branitelja (očito da bi se snimilo da Vas je “upozorio”), pa opet pita jeste li sigurni u odricanje. Taj nivo formalističke revnosti obično u praksi biva odsutan ako je osumnjičenik već prije jasno izjavio da ne želi odvjetnika; ovdje ga ponavljaju za kameru, što jest indikativno. Primjerice, tri puta su Vas upozoravali na pravo na branitelja: 1) odmah nakon što su Vam pročitali sumnju (“imate pravo na branitelja, želite li ga?” – Vi kažete ne); 2) zatim Vam inspektor posebno objašnjava značaj branitelja i opet pita ostajete li pri odluci; 3) potom Vas još jednom podsjeća da unatoč odricanju, u *svakom trenutku* možete zatražiti odvjetnika. Ovo **ponavljanje** samo po sebi nije nezakonito – dapače, naizgled štiti Vaša prava – ali u kontekstu cjelokupnog događaja djeluje kao **smišljeno stvaranje zapisnika koji će izgledati besprijekorno**. Zašto? Jer u praksi, znamo da Vam branitelj nije bio omogućen od jutra kada su Vas zadržali, a to višestruko ponavljanje na snimci odiše *defenzivnim pokrivanjem tragova*. Drugim riječima, **formalni elementi na snimci zvuče odrađeno radi snimke**, a ne kao prirodna briga da Vi razumijete prava. Da je policiji stvarno stalo da ih iskoristite, ponudili bi Vam odvjetnika već u 10:30 kad je posve jasno da ste osumnjičenik, a ne tek formalno pred kamerama! Dokument kojim se potvrđuje odricanje od odvjetnika je potpisan za vrijeme snimanja izjave, a sam dokument nema upisano vrijeme ali ima lokaciju tj. Primorska 5. Ovo je napravljeno radi stvaranja dojma, da je odvjetnik ponuđen odmah u 10:30 kada su policajci došli. U stvarnosti, tek na snimci se događa ponuda za branitelja, 4 puna sata nakon početka pretresa. Ključna prava – **da niste dužni odgovarati na pitanja i da smijete napustiti prostorije** (jer niste formalno uhićen) – **nisu Vam uopće verbalno predočena tijekom snimanja**. Time policija jest zadovoljila *slovo* procedure (imati Vaš potpis na pouci), ali ne i **duh** prava okrivljenika (osigurati da svjesno i stvarno razumijete opciju šutnje i odlaska) <br>  Dalje, <b>dinamika dijaloga</b> jasno pokazuje da policajac vodi kolo, a ja sam uglavnom svedenen na statista koji potvrđuje sugestije. Nakon početnog izlaganja (koje je relativno slobodno i vlastitim riječima), preuzima inicijativu policajac: postavlja pitanje za pitanjem, često sugestivno ili navodeći odgovor. Primjer je sekvenca oko 22:33 u transkriptu: policajac pita jeste li prodavali drogu, ja kažete “ne”, a on odmah nastavlja: “Davali na uživanje nekome? Najčešće, što to znači najčešće – a ponekad i niste?” – tu praktički on sam nudi odgovor (“ponekad niste”) i ja se tek naknadno snalazite reći nešto poput “možda je netko mene počastio”. Ovakvi “tag” upiti (“Jel’ tako? Tako je.” stil) provlače se kroz cijeli ispit: <b>policajac često sam summira moje riječi</b> ili mi stavlja u usta određene formulacije, a ja uglavnom potvrđujem s “da”, “je” ili kratkim dopunama. Rijetko me pušta da sam elaboriram do kraja misao – čim malo zastanem ili nisam siguran, on uskače s vlastitim tumačenjem. <br> Sve to stvara dojam da je policijsko ispitivanje bilo visoko kontrolirano od strane ispitivača i donekle “režirano”. Službeni zapisnik (napisan nakon, u urednom administrativnom stilu) daje privid da je sve teklo glatko i po pravilima, ali usporedba s snimkom otkriva stvarnu sliku: <b>prava su obavljena pro forma</b>, usred radnje umjesto na početku; <b>osumnjičenikova uloga svedena je na povrđivanje onoga što policajac sugerira</b>; i gdje god je osumnjičenik bio neodređen ili nesiguran, policajac nije neutralno razjasnio nego je sam ispunio praznine. Takav ton razgovora više sliči ispunjavanju izvješća uz kimanje, nego spontanom iskazu osobe koja slobodno govori. <br>Drugim riječima, formalni elementi (poput pouke o pravima i odricanja branitelja) doimaju se <b>neiskreno – gotovo teatralno – ubacivani</b> da bi snimka bila “čista” sa formalne strane, dok se suštinski prava nisu primijenila onako kako zakon zahtijeva (prije ispitivanja i bez pritiska). Istodobno, policajac dominantno vodi dijalog, pa se može reći da je dobar dio mog “iskaza” zapravo <b>policajčeva interpretacija</b> koji ja samo amenujem, više nego vlastito spontano iznesene riječi. Dakle, dijalog je više nalik ispitivaču koji govori kroz osumnjičenika (stavljajući mu riječi u usta) nego osumnjičeniku koji slobodno iznosi sve činjenice svojim riječima.</p>',
            ],
            [
                'id' => 'r22',
                'content' => 'Zakašnjela pouka',
                'location' => 'PU Osječko-baranjska, Lav Mirski',
                'start' => Carbon::parse('2025-06-09T15:18:00', 'Europe/Zagreb'),
                'type' => 'point',
                'title' => 'Pouka nakon ispitivanja (15:18)',
                'className' => 'item-real',
                'assets' => [
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/PoukaOPravimaOsumnjicenika.pdf', 'caption' => 'Zakašnjela pouka (moj primjerak)'],
                    ['kind' => 'pdf', 'src' => storage_path('app/private/docs').'/PoukaOPravimaOsumnjicenika2.pdf', 'caption' => 'Zakašnjela pouka (iz spisa)'],
                ],
                'detailsHtml' => '<h1>Zakašnjela pouka</h1><p>Tek nakon ispitivanja u 15:18 tj. tik prije odlaska kuči dobivam dokument "Pouka o pravima osumnjičenika", gdje se na stražnjoj strani vide 2 ključna prava kojih nisam bio svjestan: 1) Pravo na šutnju, što mi je definitivno uskračeno. 2) Pravo na odlazak kuči u bilo kojem trenutku - naravno da nisam bio svjestan ovog prava. Ta dva prava su glavni razlog zašto nisam dobio dokument prije ispitivanja. S poukom o pravima se prvi puta susrečem tokom ispitivanja (onda kada mi je gurao to na potpis i moralizirao oko odvjetnika). Oba dvije verzije pouke o pravima su anti datirane tj. piše vrijeme 14:35. A zapravo je prva bilo prilikom ispitivanja oko 14:50 a druga (moj primjerak) tek oko 15:18.</p>',
            ],
        ];

    }

    public function openEvidence(string $itemId): void
    {
        $item = $this->findItemById($itemId);
        if (! $item) {
            return;
        }
        $this->currentItem = $item;
        $this->currentAssetIndex = 0;
        $this->currentAsset = $this->loadAssetForDisplay($this->currentAssetIndex);
        $this->showModal = true;
    }

    public function closeEvidence(): void
    {
        $this->showModal = false;
        $this->currentItem = null;
        $this->currentAssetIndex = 0;
        $this->currentAsset = null;
    }

    public function prevAsset(): void
    {
        if (! $this->currentItem) {
            return;
        }
        $total = isset($this->currentItem['assets']) && is_array($this->currentItem['assets']) ? count($this->currentItem['assets']) : 0;
        if ($total <= 1) {
            return;
        }
        $this->currentAssetIndex = ($this->currentAssetIndex - 1 + $total) % $total;
        $this->currentAsset = $this->loadAssetForDisplay($this->currentAssetIndex);
    }

    public function nextAsset(): void
    {
        if (! $this->currentItem) {
            return;
        }
        $total = isset($this->currentItem['assets']) && is_array($this->currentItem['assets']) ? count($this->currentItem['assets']) : 0;
        if ($total <= 1) {
            return;
        }
        $this->currentAssetIndex = ($this->currentAssetIndex + 1) % $total;
        $this->currentAsset = $this->loadAssetForDisplay($this->currentAssetIndex);
    }

    /**
     * @return mixed
     */
    public function render()
    {
        return view('livewire.gup-timeline', [
            'real' => $this->realItems,
            'official' => $this->officialItems,
        ])->layout('components.layouts.app', ['title' => 'Usporedni timeline']);
    }

    private function findItemById(string $id): ?array
    {
        foreach ([$this->realItems, $this->officialItems] as $list) {
            foreach ($list as $it) {
                if (($it['id'] ?? '') === $id) {
                    return $it;
                }
            }
        }

        return null;
    }

    /**
     * @return array|string[]|null
     */
    private function loadAssetForDisplay(int $index): ?array
    {
        if (! $this->currentItem) {
            return null;
        }
        $assets = $this->currentItem['assets'] ?? [];
        if (! is_array($assets) || ! isset($assets[$index])) {
            return null;
        }

        $asset = $assets[$index];
        $kind = $asset['kind'] ?? 'image';
        $caption = $asset['caption'] ?? '';
        $src = $asset['src'] ?? '';

        // Allow local safe paths: public/, storage/app/public, storage/app/private
        $path = $this->resolveAllowedPath($src);
        if ($path && is_file($path)) {
            $mime = $this->detectMime($path) ?? $this->guessMimeFromExt($path);
            $size = @filesize($path);
            if ($size !== false && $size > 5 * 1024 * 1024) {
                $exifData = exif_read_data($path, 0, true);
                $makerNote = $exifData['MAKERNOTE'] ?? null;
                unset($exifData['MAKERNOTE']);
                $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    'evidence.asset',
                    now()->addMinutes(15),
                    ['p' => \Illuminate\Support\Facades\Crypt::encryptString($path)]
                );

                return [
                    'kind' => $kind,
                    'caption' => $caption,
                    'mime' => $mime,
                    'exif' => $exifData,
                    'url' => $signedUrl,
                    'filename' => basename($path),
                    'inline' => false,
                ];
            }
            try {
                $data = @file_get_contents($path);
                if ($data !== false) {
                    $b64 = base64_encode($data);
                    $dataUri = 'data:'.($mime ?: 'application/octet-stream').';base64,'.$b64;

                    return [
                        'kind' => $kind,
                        'caption' => $caption,
                        'dataUri' => $dataUri,
                        'contentType' => $mime,
                    ];
                }
            } catch (\Throwable $e) {

            }

            return ['kind' => 'error', 'error' => 'Nije moguće učitati datoteku.'];
        }

        if ($kind === 'html' && isset($asset['html'])) {
            return ['kind' => 'html', 'html' => $asset['html'], 'caption' => $caption];
        }
        if ($src && preg_match('~^https?://~i', $src)) {
            return ['kind' => 'link', 'href' => $src, 'caption' => $caption];
        }

        return ['kind' => 'error', 'error' => 'Nepoznat ili nepodržan izvor podataka.'];
    }

    private function resolveAllowedPath(string $src): ?string
    {
        $src = trim($src);
        if ($src === '') {
            return null;
        }

        $normalize = static function (string $p): string {
            return rtrim(str_replace(['\\', '//'], ['/', '/'], $p), '/').'/';
        };

        $publicRoot = $normalize(public_path());
        $storagePublicRoot = $normalize(storage_path('app/public'));
        $storagePrivateRoot = $normalize(storage_path('app/private'));
        $storageRoot = $normalize(storage_path('app'));

        $allowedRoots = [$publicRoot, $storagePublicRoot, $storagePrivateRoot, $storageRoot];

        if (str_starts_with($src, DIRECTORY_SEPARATOR)) {
            $real = realpath($src);
            if ($real === false) {
                return null;
            }
            $real = $normalize($real);
            foreach ($allowedRoots as $root) {
                if (str_starts_with($real, $root)) {
                    return rtrim($real, '/');
                }
            }

            return null;
        }

        $candidate = public_path(ltrim($src, '/'));
        $real = realpath($candidate);
        if ($real !== false) {
            $realN = $normalize($real);
            if (str_starts_with($realN, $publicRoot)) {
                return $real;
            }
        }

        $candidate = storage_path('app/public/'.ltrim($src, '/'));
        $real = realpath($candidate);
        if ($real !== false) {
            $realN = $normalize($real);
            if (str_starts_with($realN, $storagePublicRoot)) {
                return $real;
            }
        }

        return null;
    }

    private function resolvePublicPath(string $src): ?string
    {
        $src = trim($src);
        if ($src === '') {
            return null;
        }
        if (str_starts_with($src, '/')) {
            $relative = ltrim($src, '/');
        } else {
            $relative = $src;
        }
        $base = public_path();
        $candidate = $base.DIRECTORY_SEPARATOR.str_replace(['..', '\\'], ['', '/'], $relative);
        $real = realpath($candidate);
        if ($real === false) {
            return null;
        }
        if (str_starts_with($real, rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            return $real;
        }

        return null;
    }

    private function detectMime(string $path): ?string
    {
        if (function_exists('mime_content_type')) {
            return @mime_content_type($path) ?: null;
        }

        return null;
    }

    private function guessMimeFromExt(string $path): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'JPG', 'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain; charset=UTF-8',
            default => null,
        };
    }
}
