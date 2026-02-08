# e-Komunikacija API — V2 Additions (~9h)

*Source: Upute za korištenje API-a za velike korisnike (27.5.2024), 6 example JSON payloads, 7 ZIP file archives, 1 prilog.json*

---

## Sprint 0 Additions (Foundation) — +1h

| Task | Description | Test |
|------|-------------|------|
| **S0-5** | **Multipart/form-data request support** — `POST /podnesci` sends `multipart/form-data` with two parts: (1) `podnesak` part = JSON payload with `Content-Type: application/json`, (2) `files` part = one or more binary files. Similarly `POST /podnesci/{id}/prilozi` uses `prilog` (JSON) + `file` (single binary). File names in JSON `sadrzaj.naziv` **must exactly match** uploaded file names. Build `EKomMultipartRequest` helper. | Test: multipart boundary encoding, file-name matching validation |
| **S0-6** | **BadRequestError DTO** — 400 responses return `{ "id": "uuid", "messages": ["error1", "error2"] }`. Parse into typed `EKomValidationException` with structured message array for UI display. | Test: multi-message error parsing |
| **S0-7** | **Environment config** — Support dual environment: test (`e-komunikacija-test.pravosudje.hr`) and production (`e-komunikacija.pravosudje.hr`). Add `EKOM_ENVIRONMENT=test|production` to config. | Test: URL resolution per environment |
| **S0-8** | **JWT token management** — Token validity is configurable in days (default 30). System stores only UUID claim, not the JWT itself. Support concurrent active tokens and token rotation (create new before old expires). Store encrypted JWT in DB per existing Advocatus pattern. | Test: token expiry detection, rotation |

---

## Sprint 3 Additions (Otpravci) — +0.5h

| Task | Description | Test |
|------|-------------|------|
| **S3-7** | **Receipt deadline calculation** — From PDF: kazneni predmeti = **8 days**, all other procedure types = **15 days** from dispatch date. After deadline, system auto-confirms receipt. Implement `OtpravakDeadlineCalculator` that determines deadline based on procedure type, with alerts at 3-day and 1-day remaining. | Test: criminal vs civil deadline, auto-confirm detection |

---

## Sprint 4 Additions (Podnesci Drafts) — +3h

| Task | Description | Test |
|------|-------------|------|
| **S4-8** | **Stranka `predstavljaniSubjekt` (represented entity) fields** — `CreateStrankaRequest` supports: `predstavljaniSubjektNaziv` (e.g. "Testni obrt"), `predstavljaniSubjektIpsIzvor` (enum: `OBRTNI_REGISTAR`, etc.), `predstavljaniSubjektIps` (identifier, e.g. "123456"). Used when a FIZICKA_OSOBA represents a business entity. Add to builder: `->representingEntity("Testni obrt", IpsSource::OBRTNI_REGISTAR, "123456")` | Test: JSON from `podnesak-novi_postupak-bez_pristojbe.json` stranke[2] |
| **S4-9** | **`vanjskaUstrojstvenaJedinica` and `vanjskiPredmet` fields** — External organizational unit and external case reference on `CreateEkomPodnesakRequest`. Optional fields for tracking external references. Add to builder: `->withExternalRef("test UJ", "test predmet")` | Test: present in novi_postupak-bez_pristojbe example |
| **S4-10** | **Dual submitter identification** — Two mutually exclusive patterns: (1) **New proceeding**: `podnositeljRbr` (1-indexed ordinal pointing to stranke array) + `ulogaPodnositeljaId` (required). (2) **Existing case**: `podnositeljSlobodanUnos` (free-text name, e.g. "podnositelj"). Builder must enforce: `->asNewProceeding()` requires `podnositeljRbr`, `->forExistingCase()` requires `podnositeljSlobodanUnos`. **Note**: some new proceedings also send `podnositeljSlobodanUnos` alongside `podnositeljRbr` (see s_pristojbom examples). | Test: validate each mode rejects wrong submitter field |
| **S4-11** | **`vrijednostPredmetaSpora` (dispute value)** — Decimal field on `CreateEkomPodnesakRequest`. Required for certain procedure types (e.g. Parnični postupak vrstaPostupkaId=1). Used in fee calculation. Builder: `->withDisputeValue(100.00)` | Test: present in both s_pristojbom examples with value 100.00 |
| **S4-12** | **`potvrdaPredmetaKojiNijeMoj` flag** — Boolean on `CreateEkomPodnesakRequest`. Required when inserting submission into existing case that doesn't belong to the user (not in their case list). Builder: `->confirmNotMyCase()` | Test: refuse submission to foreign case without flag |
| **S4-13** | **`zastupanaStranka` flag enforcement** — `CreateStrankaRequest.zastupanaStranka` (boolean). Per business rules: when user has role Odvjetnik and creates new proceeding, at least one stranka MUST have `zastupanaStranka: true`. Builder: `->addStranka(...)->asRepresentedParty()` | Test: validation fails if Odvjetnik + no zastupanaStranka |
| **S4-14** | **Address resolution: settlement disambiguation** — PDF specifies 4 ambiguous HR settlements: Novigrad (×2 in općina Novigrad) and Privlaka (×2 in općina Privlaka). When using `naseljeNaziv` alone, it must be unique in HR. For ambiguous ones, must provide `naseljeNaziv` + `opcinaNaziv` + `zupaijaNaziv` (this triple is always unique). Foreign countries accept `drzavaId` OR 2-letter (`"HR"`) or 3-letter (`"HRV"`) ISO codes. | Test: ambiguous Novigrad resolution, foreign country code lookup |
| **S4-15** | **Existing case mode: empty arrays vs omission** — Per examples, existing case submissions send explicit empty arrays: `"stranke": [], "protustranke": [], "prilozi": []`. Business rule: **no stranke/protustranke allowed** for existing case. Builder must enforce this and serialize empty arrays (not null/omit). | Test: existing case rejects non-empty stranke |

---

## Sprint 5 Additions (Fees & Sending) — +1.5h

| Task | Description | Test |
|------|-------------|------|
| **S5-6** | **`pristojbaDodatniPodaci` complex builder** — Three distinct sub-patterns revealed by JSON examples: (1) **New proceeding + fee + multiple stranke**: requires `obveznikPlacanjaRbr` (ordinal of paying party). (2) **Existing case + fee**: requires `obveznikPlacanjaSlobodanUnos` (free-text payer name). (3) **Fee option selection**: `dodatnaOpcija` is a **STRING** value matching option name from šifrarnici (e.g. `"5) žalba protiv rješenja na koje se inače ne plaća pristojba"`), NOT an ID. (4) **Partial exemption**: `postotakOslobodjenjaDrugaOsnova` (decimal, e.g. 10.0) + `osnovaOslobodjenjaDrugaOsnovaId` (integer). Build `PristojbaDodatniPodaciBuilder` with type-safe methods. | Test: all 6 JSON examples produce correct pristojbaDodatniPodaci |
| **S5-7** | **Digital signature pre-send validation** — Before `posalji-na-sud`, the podnesak document must be signed with a valid digital signature (`statusValidacijePotpisa` = `PRIHVATLJIV`). Implement pre-flight check that verifies signature status and returns clear error if unsigned/invalid. | Test: refuse send with invalid signature status |
| **S5-8** | **`razlogNeplacanjaId` at send time** — The `PosaljiEkomPodnesakNaSudRequest` body with `razlogNeplacanjaId` is only needed when fee exists but is unpaid AND no 100% exemption. If fee is fully paid or fully exempt, send with empty body. Implement decision logic: check pristojba.ostatak → if 0 or no fee obligation → empty body; if > 0 and no full exemption → require razlogNeplacanjaId. | Test: three send paths (no fee, paid fee, unpaid with reason) |

---

## Sprint 7: Test Fixtures & Integration Tests (Est. 3h) — NEW

**Goal:** Official example payloads as test fixtures, end-to-end integration test coverage

| Task | Description | Test |
|------|-------------|------|
| **S7-1** | **Store official JSON examples as test fixtures** — Copy all 6 podnesak JSONs + 1 prilog JSON into `tests/fixtures/ekom/`. These are the canonical reference payloads from MPU documentation. | Verify: fixture files parse to valid DTOs |
| **S7-2** | **Store example files as test binaries** — Extract all 7 ZIP archives into `tests/fixtures/ekom/files/`. Contains: podnesak.pdf (signed), prilog-1.pdf through prilog-4.pdf, prilog-3.docx (non-PDF attachment example). | Verify: file counts match JSON declarations |
| **S7-3** | **Integration test: New proceeding WITHOUT fee** — `podnesak-novi_postupak-bez_pristojbe.json`: 3 stranke (incl. one with `predstavljaniSubjekt`), 1 protustranka, 3 prilozi, `podnositeljRbr: 2`, `sudOznaka: "OGs zg"` (case-insensitive!), `vanjskaUstrojstvenaJedinica`, `vanjskiPredmet`. Verify builder produces matching JSON + multipart with 4 files (podnesak.pdf + 3 prilozi). | Full round-trip test |
| **S7-4** | **Integration test: New proceeding WITH fee, no extra data** — `podnesak-novi_postupak-s_pristojbom_bez_dodatnih_podataka_pristojbe.json`: 1 stranka, 1 protustranka, 3 prilozi (incl. `.docx`), sends BOTH `sudId` AND `sudOznaka`, `vrijednostPredmetaSpora: 100.00`. Verify fee flow: create → getPristojba → send. | Fee calculation test |
| **S7-5** | **Integration test: New proceeding WITH fee + extra data** — `podnesak-novi_postupak-s_pristojbom_i_dodatnim_podacima_pristojbe.json`: 2 stranke, `obveznikPlacanjaRbr: 1`, `postotakOslobodjenjaDrugaOsnova: 10.0`, `osnovaOslobodjenjaDrugaOsnovaId: 2`. Verify partial exemption handling. | Exemption calculation test |
| **S7-6** | **Integration test: Existing case WITHOUT fee** — `podnesak-postojeci_predmet-bez_pristojbe.json`: `predmetOznaka: "p-1/2023"` (case-insensitive), `podnositeljSlobodanUnos`, 1 prilog. Minimal payload. | Minimum viable existing-case test |
| **S7-7** | **Integration test: Existing case, fee exempt by option** — `podnesak-postojeci_predmet-bez_pristojbe_na_temelju_opcije.json`: `predmetId: 16218227`, `pristojbaDodatniPodaci.dodatnaOpcija: "5) žalba protiv rješenja..."`. No stranke, no prilozi, no protustranke (all omitted, not empty). | Fee option string matching test |
| **S7-8** | **Integration test: Existing case WITH fee + extra data** — `podnesak-postojeci_predmet-s_pristojbom_i_dodatnim_podacima_pristojbe.json`: `predmetId`, `dodatnaOpcija: "1) žalba protiv presude"`, `obveznikPlacanjaSlobodanUnos: "Obveznik Plaćanja"`, explicit empty arrays `stranke: [], protustranke: [], prilozi: []`. | Empty-array serialization + free-text payer test |
| **S7-9** | **Integration test: Add attachment post-creation** — `prilog.json`: `opis`, `primjedba`, `sadrzaj` with `brojStranica: 2` and `ignorirajUpozorenja: true`. Multipart with single `file` part (not `files`). | Separate attachment upload test |
| **S7-10** | **Curl-equivalent test helper** — Build artisan command `ekom:test-submit` that replicates the curl example from PDF (p.12): reads token from file, JSON from file, assembles multipart, sends, saves response. Useful for manual testing against test environment. | Manual test tool |

---

## Effort Summary

| Sprint | Addition | Hours |
|--------|----------|-------|
| Sprint 0 | Multipart, error DTO, env config, JWT | +1h |
| Sprint 3 | Deadline calculator | +0.5h |
| Sprint 4 | 8 new tasks (represented entities, submitter modes, dispute value, flags, address disambiguation, empty arrays) | +3h |
| Sprint 5 | Fee builder, signature check, send logic | +1.5h |
| Sprint 7 | Test fixtures & integration tests (NEW) | +3h |
| **Total** | | **~9h** |
