# Document Profiles Reference

Legal Artillery supports 8 document profiles, each targeting a different legal forum or institution.
Profiles are defined in `config/legal-artillery.php` under the `profiles` key.

## Profile Overview

| # | Key | Name | Forum | Priority | Tone |
|---|-----|------|-------|----------|------|
| 1 | `predsjednik_suda` | Zahtjev predsjedniku suda za uvid u spis | Opcinski sud Osijek | immediate | formal_assertive |
| 2 | `dorh_production` | Zahtjev DORH-u za pribavljanje spisa | DORH Osijek | immediate | formal_assertive |
| 3 | `kazneni_sud_motion` | Prijedlog kaznenom sudu za pribavljanje spisa | Kazneni sud Osijek | before_optuznica | formal_assertive |
| 4 | `ombudsman` | Prituzba Puckom pravobranitelju | Pucki pravobranitelj | immediate | formal_narrative |
| 5 | `ministarstvo_pravosudja` | Prituzba Ministarstvu pravosudja i uprave | Ministarstvo pravosudja | immediate | formal_administrative |
| 6 | `ustavni_sud` | Ustavna tuzba — cl.62 iznimka | Ustavni sud RH | within_30_days | formal_constitutional |
| 7 | `izdvajanje_dokaza` | Prijedlog za izdvajanje nezakonitih dokaza | Kazneni sud Osijek | at_optuzno_vijece | formal_aggressive |
| 8 | `echr_application` | ECHR Application | ECHR Strasbourg | after_domestic_exhaustion | formal_international |

## Tone Definitions

| Tone Key | Language | Description |
|----------|----------|-------------|
| `formal_assertive` | hr | Formalno, jasno i asertivno. Imperativi: "zahtijevam", "trazim". |
| `formal_narrative` | hr | Formalno ali narativno. Kronoloski izlozene cinjenice s ljudskom dimenzijom. |
| `formal_administrative` | hr | Suhoparno i administrativno. Fokus na sistemske nedostatke. |
| `formal_constitutional` | hr | Uzvyseno i ustavnopravno. Fundamentalna prava i ustavne garancije. |
| `formal_aggressive` | hr | Ostro i argumentirano. Neumoljivno precizna analiza pravnih pogresaka. |
| `formal_international` | en | Formal international legal English. ECHR case law and structure. |

## Profile Details

### 1. `predsjednik_suda` — Zahtjev predsjedniku suda

**Recipient:** Predsjednica Opcinskog suda u Osijeku, Europska avenija 7, 31000 Osijek

**Legal Basis:**
- PZ cl.150 st.1 — opravdani interes
- PZ cl.150 st.4 — predsjednik suda odlucuje za zavrsen postupak
- Ustav cl.18 — pravo na zalbu
- Ustav cl.34 — nepovredivost doma

**Document Structure:** heading, case_reference, identification, facts_chronology, legal_arguments, requests, legal_remedy_demand, signature

**Attachments Required:** No

**Usage:**
```bash
php artisan legal:fire predsjednik_suda --no-send
php artisan legal:fire predsjednik_suda --draft
```

```php
$agent->fire(profileKey: 'predsjednik_suda', userId: $userId);
```

---

### 2. `dorh_production` — Zahtjev DORH-u

**Recipient:** Opcinsko drzavno odvjetnistvo u Osijeku, Europska avenija 7, 31000 Osijek

**Legal Basis:**
- ZKP cl.9 st.2 — duznost prikupljanja i oslobadajucih dokaza
- ZKP cl.184 — prava obrane na uvid
- ZKP cl.342 — bitna povreda postupka

**Document Structure:** heading, case_reference, identification, connection_criminal_misdemeanor, legal_arguments, disclosure_demand, consequences_warning, signature

**Attachments Required:** Yes

**Usage:**
```bash
php artisan legal:fire dorh_production --no-send
```

```php
$agent->fire(profileKey: 'dorh_production', userId: $userId);
```

---

### 3. `kazneni_sud_motion` — Prijedlog kaznenom sudu

**Recipient:** Opcinski kazneni sud u Osijeku, Europska avenija 7, 31000 Osijek

**Legal Basis:**
- ZKP cl.183 — pravo obrane na razgledavanje spisa
- ZKP cl.184 st.5 — uvid u hitne radnje
- ZKP cl.10 — nezakoniti dokazi

**Document Structure:** heading, case_reference, identification, motion_context, evidence_connection, legal_arguments, specific_requests, signature

**Attachments Required:** No

**Priority Note:** Should be filed before optuznica.

**Usage:**
```bash
php artisan legal:fire kazneni_sud_motion --no-send
```

```php
$agent->fire(profileKey: 'kazneni_sud_motion', userId: $userId);
```

---

### 4. `ombudsman` — Prituzba Puckom pravobranitelju

**Recipient:** Pucki pravobranitelj — Podrucni ured Osijek, Hrvatske Republike 19/I, 31000 Osijek

**Legal Basis:**
- Zakon o puckom pravobranitelju cl.22
- Ustav cl.93 — pucki pravobranitelj
- Ocita zloupotreba ovlasti — odbijanje formalnog rjesenja

**Document Structure:** heading, identification, narrative_chronology, rights_violations, specific_complaint, requested_action, signature, attachments_list

**Attachments Required:** Yes

**Usage:**
```bash
php artisan legal:fire ombudsman --no-send
```

```php
$agent->fire(profileKey: 'ombudsman', userId: $userId);
```

---

### 5. `ministarstvo_pravosudja` — Prituzba Ministarstvu

**Recipient:** Ministarstvo pravosudja i uprave — Pravosudna inspekcija, Ulica grada Vukovara 49, 10000 Zagreb

**Legal Basis:**
- Zakon o sudovima cl.72 st.6 — upravni nadzor
- Ustav cl.18 — pravo na zalbu

**Document Structure:** heading, identification, subject_complaint, facts_chronology, administrative_irregularities, requested_measures, signature, attachments_list

**Attachments Required:** Yes

**Usage:**
```bash
php artisan legal:fire ministarstvo_pravosudja --no-send
```

```php
$agent->fire(profileKey: 'ministarstvo_pravosudja', userId: $userId);
```

---

### 6. `ustavni_sud` — Ustavna tuzba

**Recipient:** Ustavni sud Republike Hrvatske, Trg svetog Marka 4, 10000 Zagreb

**Legal Basis:**
- Ustavni zakon cl.62 — grubo vrijedjanje ustavnih prava
- Ustav cl.18 — pravo na zalbu
- Ustav cl.19 — sudska kontrola zakonitosti
- Ustav cl.29 — pravicno sudjenje
- Ustav cl.34 — nepovredivost doma

**Document Structure:** heading_constitutional, identification, challenged_acts, constitutional_provisions_violated, factual_background, constitutional_arguments, article_62_justification, proposed_measures, signature, attachments_list

**DOCX Template:** `legal-constitutional` (specialized template)

**Attachments Required:** Yes

**Deadline:** 30 days from last decision, or immediately under cl.62.

**Usage:**
```bash
php artisan legal:fire ustavni_sud --no-send
```

```php
$agent->fire(profileKey: 'ustavni_sud', userId: $userId);
```

---

### 7. `izdvajanje_dokaza` — Prijedlog za izdvajanje dokaza

**Recipient:** Opcinski kazneni sud u Osijeku, Europska avenija 7, 31000 Osijek

**Legal Basis:**
- ZKP cl.10 st.2 toc.2 — povreda prava obrane
- ZKP cl.10 st.2 toc.3 — bitna povreda postupka
- ZKP cl.184 st.5 — uskraceni uvid u hitne radnje
- Ustav cl.29 — pravicno sudjenje

**Document Structure:** heading, case_reference, identification, evidence_identification, exclusion_grounds, defense_rights_violation, constitutional_dimension, specific_request, signature

**Attachments Required:** Yes

**Priority Note:** File at optuzno vijece stage.

**Usage:**
```bash
php artisan legal:fire izdvajanje_dokaza --no-send
```

```php
$agent->fire(profileKey: 'izdvajanje_dokaza', userId: $userId);
```

---

### 8. `echr_application` — ECHR Application

**Recipient:** European Court of Human Rights, Council of Europe, 67075 Strasbourg Cedex, France

**Legal Basis:**
- ECHR Article 6 — Right to a fair trial
- ECHR Article 8 — Right to respect for private and family life, home
- ECHR Article 13 — Right to an effective remedy
- ECHR Article 34 — Individual applications

**Document Structure:** echr_header, applicant_details, respondent_state, statement_of_facts, domestic_proceedings, alleged_violations, article_6_arguments, article_8_arguments, article_13_arguments, exhaustion_of_remedies, timeliness, relief_sought, declaration, signature, annexes

**DOCX Template:** `echr-application` (specialized ECHR format)

**Language:** English

**Attachments Required:** Yes

**Deadline:** 4 months from last domestic decision.

**Note:** This profile is only available after exhaustion of all domestic remedies. The document is generated in English, unlike all other profiles which use Croatian.

**Usage:**
```bash
php artisan legal:fire echr_application --no-send
```

```php
$agent->fire(profileKey: 'echr_application', userId: $userId);
```

## Adding New Profiles

To add a new document profile:

1. Add the profile definition to `config/legal-artillery.php` under the `profiles` array
2. Define the tone in the `tones` array if a new tone is needed
3. Add the document type mapping in `EKomunikacijaDispatcher::mapDocumentType()`
4. Add any profile-specific legal provisions via seeders

Each profile requires:
- `name` — Display name of the document type
- `recipient` — Title, institution, address, email
- `legal_basis` — Array of legal provisions cited
- `tone` — Key referencing a tone definition
- `structure` — Ordered array of document section keys
- `docx_template` — Template name for DOCX rendering
- `email_subject_template` — Subject line template with `{case_number}` placeholder
- `requires_attachments` — Whether supporting documents are expected
- `metadata` — Priority, forum, deadline notes
