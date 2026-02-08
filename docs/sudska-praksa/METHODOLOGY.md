# Metodologija za generiranje ključnih riječi - odluke.sudovi.hr

> **Verzija:** 1.0
> **Autor:** 3P Solutions d.o.o.
> **Datum:** 2026-02-04

## Uvod

Ovaj dokument opisuje 6-faznu metodologiju za generiranje učinkovitih ključnih riječi za pretraživanje hrvatske sudske prakse na portalu odluke.sudovi.hr.

## Osnovni principi

### Operator AND

**UVIJEK koristi operator AND** između ključnih riječi. Portal odluke.sudovi.hr koristi logički AND operator za povezivanje pojmova, što znači da će rezultati sadržavati SVE navedene pojmove.

**Nikad ne koristi OR operator** - portal ga ne podržava na očekivan način i rezultati su nepredvidljivi.

### Optimalni broj pojmova

- **2-4 ključne riječi** po upitu je optimalno
- Manje od 2: previše rezultata (bulk)
- Više od 5: premalo ili nula rezultata

### Pravna terminologija

- Koristi **službenu pravnu terminologiju**, ne kolokvijalne izraze
- Koristi **infinitiv/nominativ** oblike riječi
- Koristi **članke zakona** kad je moguće (npr. "čl. 35" ili "članak 35")

### Cilj: Preciznost

- Cilj je dobiti **≤50 rezultata** po upitu
- ULTRA (≤5) i ZLATO (6-15) su najvrjedniji
- SREBRNO (16-50) je još uvijek koristan
- BRONCA (51-150) je preglediv s dodatnim filterima
- BULK (>150) zahtijeva rafiniranje

---

## Faza 1: Identifikacija činjeničnog stanja

### Koraci

1. Pročitaj pažljivo opis slučaja
2. Identificiraj **ključne činjenice**:
   - Vrsta kaznenog djela / pravnog spora
   - Bitne okolnosti (vrijeme, mjesto, način izvršenja)
   - Sudionici (okrivljenik, oštećenik, svjedoci)
   - Dokazi (vrsta, način prikupljanja)

### Primjer

**Opis:** "Policija je ušla u stan bez naloga za pretragu tvrdeći da ima osnove sumnje da se u stanu nalaze dokazi o počinjenom kaznenom djelu."

**Činjenice:**
- Pretraga stana
- Bez sudskog naloga
- Policijska radnja
- Osnove sumnje

---

## Faza 2: Definiranje pravnih pitanja

### Koraci

1. Pretvori činjenice u **pravna pitanja**
2. Identificiraj **relevantne propise**:
   - Kazneni zakon (KZ)
   - Zakon o kaznenom postupku (ZKP)
   - Ustav RH
   - Relevantni posebni zakoni

### Primjer

**Pravna pitanja:**
1. Je li pretraga stana bez naloga zakonita?
2. Jesu li dokazi prikupljeni nezakonitom pretragom dopustivi?
3. Koji su uvjeti za iznimku od načela nezakonitog dokaza?

**Relevantni propisi:**
- ZKP čl. 246 (pretraga doma)
- ZKP čl. 10 (nezakoniti dokazi)
- Ustav čl. 34 (nepovredivost doma)

---

## Faza 3: Generiranje ključnih riječi

### Strategija

Za svako pravno pitanje, generiraj 3-5 varijanti upita:

1. **Osnovni upit**: Temeljni pojmovi
2. **Prošireni upit**: + pravni institut
3. **Specifični upit**: + članak zakona
4. **Alternativni upit**: Sinonimi

### Primjer

```json
{
  "name": "1. Pretraga stana bez naloga",
  "queries": [
    {"q": "pretraga AND stana AND nalog", "comment": "Osnovni"},
    {"q": "pretraga AND doma AND bez AND naloga", "comment": "Prošireni"},
    {"q": "pretraga AND čl. 246 AND ZKP", "comment": "Specifični"},
    {"q": "pretres AND stana", "comment": "Alternativni (pretres umjesto pretraga)"}
  ]
}
```

---

## Faza 4: Kategorizacija upita

### Kategorije

Organiziraj upite u **logičke kategorije**:

1. **Procesna pitanja** - postupovne povrede
2. **Materijalna pitanja** - pitanja krivnje
3. **Dokazna pitanja** - dopustivost i ocjena dokaza
4. **Sankcije** - odmjeravanje kazne
5. **Pravni lijekovi** - žalbeni razlozi

### Format

```json
{
  "categories": [
    {
      "name": "1. Zakonitost pretrage",
      "description": "Uvjeti za zakonitu pretragu doma/stana",
      "queries": [...]
    },
    {
      "name": "2. Nezakoniti dokazi",
      "description": "Izdvajanje i plod otrovne stabljike",
      "queries": [...]
    }
  ]
}
```

---

## Faza 5: Validacija i optimizacija

### Pravila validacije

1. **Provjeri format**: Svaki upit koristi AND operator
2. **Provjeri duljinu**: 2-4 pojma je optimalno
3. **Izbjegni duplikate**: Svaki upit mora biti jedinstven
4. **Koristi stručne termine**: Provjeri pravnu terminologiju

### Iteracija

Nakon prvog prolaska:
1. Testiraj upite na odluke.sudovi.hr
2. Upite s >150 rezultata → rafinirati (dodaj pojam)
3. Upite s 0 rezultata → generalizirati (ukloni pojam)
4. Uspješne upite → kreiraj varijacije

---

## Faza 6: Strukturiranje JSON-a

### Obavezna polja

```json
{
  "metadata": {
    "name": "Naziv slučaja",
    "description": "Kratki opis",
    "version": "1.0",
    "created_at": "2026-02-04T12:00:00Z"
  },
  "categories": [
    {
      "name": "1. Kategorija",
      "description": "Opis kategorije",
      "queries": [
        {"q": "upit", "comment": "Zašto ovaj upit"}
      ]
    }
  ]
}
```

### Primjer kompletnog JSON-a

```json
{
  "metadata": {
    "name": "KD-123/24 - Pretraga stana",
    "description": "Analiza zakonitosti pretrage i dopustivosti dokaza",
    "version": "1.0",
    "created_at": "2026-02-04T12:00:00Z",
    "author": "AI Legal War Machine"
  },
  "categories": [
    {
      "name": "1. Zakonitost pretrage",
      "description": "Uvjeti za provođenje pretrage bez naloga",
      "queries": [
        {"q": "pretraga AND stana AND nalog", "comment": "Osnovni upit"},
        {"q": "pretraga AND doma AND iznimka", "comment": "Iznimke od naloga"},
        {"q": "čl. 246 AND ZKP AND pretraga", "comment": "Članak ZKP-a"}
      ]
    },
    {
      "name": "2. Nezakoniti dokazi",
      "description": "Pitanja izdvajanja nezakonitih dokaza",
      "queries": [
        {"q": "nezakonit AND dokaz AND izdvajanje", "comment": "Izdvajanje dokaza"},
        {"q": "plod AND otrovne AND stabljike", "comment": "Fruit of poisonous tree"},
        {"q": "čl. 10 AND ZKP AND dokaz", "comment": "Članak o nezakonitim dokazima"}
      ]
    },
    {
      "name": "3. Temeljna prava",
      "description": "Ustavna jamstva i prava",
      "queries": [
        {"q": "nepovredivost AND doma AND ustav", "comment": "Ustavno pravo"},
        {"q": "članak 34 AND ustav", "comment": "Konkretni članak Ustava"}
      ]
    }
  ]
}
```

---

## Dodatni savjeti

### Korištenje članaka zakona

- Format: "čl. 35" ili "članak 35" (oba rade)
- Uvijek dodaj naziv zakona: "ZKP", "KZ", "Ustav"
- Primjer: `čl. 246 AND ZKP AND pretraga`

### Kombiniranje s pravnim pojmovima

- Koristi etablirane pravne institute: "plod otrovne stabljike", "načelo razmjernosti"
- Koristi latinske termine kad su uobičajeni: "in dubio pro reo"

### Vremenski filtri

- Portal podržava filtriranje po datumu
- Za novije prakse: dodaj godinu ili raspon

### Sudovi

- VKS: Vrhovni kazneni sud (najviša instanca za kaznene predmete)
- VS: Vrhovni sud (građanski i drugi predmeti)
- VPS: Visoki prekršajni sud
- ZS: Županijski sudovi (drugostupanjska tijela)

---

## Kontakt

Za pitanja o metodologiji kontaktirajte 3P Solutions d.o.o.
