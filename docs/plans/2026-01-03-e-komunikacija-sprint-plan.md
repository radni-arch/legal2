# e-Komunikacija API — Coverage Analysis & Integration Sprint Plan

## 1. API Surface Summary (from Swagger OpenAPI 3.1.0)

The e-Komunikacija API exposes **38 endpoints** across 4 domain groups, all under the `/api/veliki-korisnici/` prefix (intended for "large users" — law firms, legal entities, etc.):

| Domain | Endpoints | Purpose |
|--------|-----------|---------|
| **Šifrarnici** (Reference Data) | 12 | Courts, procedure types, submission types, participant roles, fee options, settlements, countries |
| **Predmeti** (Cases) | 11 | Case listing/search, case detail, case documents, dispatches per case, Do-Not-Disturb settings |
| **Podnesci** (Submissions) | 10 | Create draft, add attachments, send to court, list/search, delete draft, fees & payment orders |
| **Otpravci** (Dispatches/Deliveries from Court) | 5 | List/search, detail, confirm receipt, download documents, download receipt confirmation |

---

## 2. Current Implementation Assessment

Based on environment configuration (`EKOM_BASE_URL`, `EKOM_TIMEOUT=30`, `EKOM_RETRY_DELAY_MS=300`) and codebase references from the ai-legal-war-machine project, the existing implementation appears to include:

### What Exists (from env/config evidence)
- **HTTP client configuration** — base URL, timeout, retry delay configured
- **Basic authentication/token handling** — referenced in Advocatus analysis as "REST; token šifriran u bazi"
- **Likely partial case read operations** — references to e-Komunikacija for "uvid u predmet" (case insight)

### What's Missing or Incomplete

**Critical Gap: No Šifrarnici (reference data) sync layer.** This is the foundation — every submission requires valid `sudId`, `vrstaPostupkaId`, `vrstaPodneskaId`, `ulogaPodnositeljaId` from these reference endpoints. Without synced reference data, you can't construct valid requests.

---

## 3. Dependency Map (What Must Come First)

```
┌─────────────────────────────────────────────────────────┐
│ LAYER 0: Auth & HTTP Client                             │
│  Bearer token management, retry logic, error handling   │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│ LAYER 1: Šifrarnici (Reference Data Sync)               │
│  → Sudovi (courts)                                      │
│  → Vrste postupaka per court (procedure types)          │
│  → Vrste podnesaka per procedure (submission types)     │
│  → Uloge sudionika/podnositelja (participant roles)     │
│  → Pristojba dodatne opcije (fee options)               │
│  → Razlozi neplaćanja / Osnove oslobođenja              │
│  → Naselja, Države (settlements, countries)             │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│ LAYER 2: Predmeti (Read Operations)                     │
│  → List/search cases (paginated)                        │
│  → Get case detail by ID                                │
│  → Get case by court+oznaka (direct lookup)             │
│  → Download case documents (ZIP)                        │
│  → List dispatches per case                             │
│  → Download dispatch dostavnica                         │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│ LAYER 3: Otpravci (Dispatch Receipt)                    │
│  → List/search dispatches (paginated)                   │
│  → Get dispatch detail                                  │
│  → Download dispatch documents (ZIP)                    │
│  → Download receipt confirmation (PDF)                  │
│  → Confirm receipt (POST action)                        │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│ LAYER 4: Podnesci (Submission Workflow)                  │
│  → Create draft (with stranke, protustranke, sadržaj)   │
│  → Add attachments (prilozi)                            │
│  → Fee calculation & payment                            │
│  → Send to court                                        │
│  → Track submission status                              │
│  → Delete draft                                         │
└─────────────────────────────────────────────────────────┘
```

---

## 4. Endpoint-by-Endpoint Coverage Matrix

### 4.1 Šifrarnici (12 endpoints) — PRIORITY 1

| # | Endpoint | Method | Status | Notes |
|---|----------|--------|--------|-------|
| 1 | `/sifrarnici/sudovi` | GET | 🔴 Missing | **Foundation** — all operations require valid `sudId` |
| 2 | `/sifrarnici/sudovi/{sudId}/novi-postupak/vrste-postupaka` | GET | 🔴 Missing | Per-court procedure types for new cases |
| 3 | `/sifrarnici/vrste-postupaka/{id}/vrste-podnesaka/novi-postupak` | GET | 🔴 Missing | Submission types for new proceedings |
| 4 | `/sifrarnici/vrste-postupaka/{id}/vrste-podnesaka/postojeci-predmet` | GET | 🔴 Missing | Submission types for existing cases |
| 5 | `/sifrarnici/vrste-postupaka/{id}/uloge-sudionika` | GET | 🔴 Missing | Participant roles per procedure type |
| 6 | `/sifrarnici/vrste-postupaka/{id}/uloge-podnositelja` | GET | 🔴 Missing | Submitter roles per procedure type |
| 7 | `/sifrarnici/vrste-postupaka/{id}/vrste-podnesaka/{id2}/pristojba-dodatne-opcije/novi-postupak` | GET | 🔴 Missing | Fee options for new proceedings |
| 8 | `/sifrarnici/vrste-postupaka/{id}/vrste-podnesaka/{id2}/pristojba-dodatne-opcije/postojeci-predmet` | GET | 🔴 Missing | Fee options for existing case submissions |
| 9 | `/sifrarnici/razlozi-neplacanja-pristojbe` | GET | 🔴 Missing | Reasons for non-payment of fees |
| 10 | `/sifrarnici/osnove-oslobodjenja-pristojbe` | GET | 🔴 Missing | Fee exemption bases |
| 11 | `/sifrarnici/naselja` | GET | 🔴 Missing | Croatian settlements (for addresses) |
| 12 | `/sifrarnici/drzave` | GET | 🔴 Missing | Countries list |

### 4.2 Predmeti (11 endpoints) — PRIORITY 2

| # | Endpoint | Method | Status | Notes |
|---|----------|--------|--------|-------|
| 1 | `/predmeti` | GET | 🟡 Partial | Paginated search — likely exists but needs filter coverage |
| 2 | `/predmeti/` | GET | 🟡 Partial | Direct lookup by `sudId`+`oznaka` — critical for case resolution |
| 3 | `/predmeti/{id}` | GET | 🟡 Partial | Detail with full `Predmet` schema (sudionici, sudskeRadnje, dokumenti, vezePredmeta) |
| 4 | `/predmeti/{id}/dokumenti/sadrzaj` | GET | 🔴 Missing | **Download case documents as ZIP** — high value for legal analysis |
| 5 | `/predmeti/{id}/otpravci` | GET | 🔴 Missing | Dispatches within a specific case |
| 6 | `/predmeti/{id}/otpravci/{otpravakId}/dostavnica/sadrzaj` | GET | 🔴 Missing | Download delivery receipt document |
| 7 | `/predmeti/{predmetId}/do-not-disturb/turn-on` | POST | 🔴 Missing | Per-case DND toggle |
| 8 | `/predmeti/{predmetId}/do-not-disturb/turn-off` | POST | 🔴 Missing | Per-case DND toggle |
| 9 | `/predmeti/do-not-disturb/turn-on` | POST | 🔴 Missing | Global DND |
| 10 | `/predmeti/do-not-disturb/turn-off` | POST | 🔴 Missing | Global DND |
| 11 | `/predmeti/do-not-disturb/all` | POST | 🔴 Missing | Reset all DND |

### 4.3 Otpravci (5 endpoints) — PRIORITY 3

| # | Endpoint | Method | Status | Notes |
|---|----------|--------|--------|-------|
| 1 | `/otpravci` | GET | 🔴 Missing | Paginated search with rich filters |
| 2 | `/otpravci/{id}` | GET | 🔴 Missing | Returns full `Predmet` schema (not just Otpravak!) |
| 3 | `/otpravci/{id}/dokumenti/sadrzaj` | GET | 🔴 Missing | **Download documents from court dispatch** |
| 4 | `/otpravci/{id}/potvrda-primitka` | GET | 🔴 Missing | Download receipt confirmation PDF |
| 5 | `/otpravci/{id}/potvrdi-primitak` | POST | 🔴 Missing | **Confirm receipt** — legal deadline implications! |

### 4.4 Podnesci (10 endpoints) — PRIORITY 4

| # | Endpoint | Method | Status | Notes |
|---|----------|--------|--------|-------|
| 1 | `/podnesci` | GET | 🔴 Missing | List/search submissions |
| 2 | `/podnesci` | POST | 🔴 Missing | **Create draft** — complex: requires stranke, content, court selection |
| 3 | `/podnesci/{id}` | GET | 🔴 Missing | Submission detail |
| 4 | `/podnesci/{id}` | DELETE | 🔴 Missing | Delete draft |
| 5 | `/podnesci/{id}/prilozi` | POST | 🔴 Missing | Add attachment to draft |
| 6 | `/podnesci/{id}/posalji-na-sud` | POST | 🔴 Missing | **Send to court** — point of no return |
| 7 | `/podnesci/{id}/pristojba` | GET | 🔴 Missing | Fee calculation |
| 8 | `/podnesci/{id}/pristojba/nalog-za-placanje` | GET | 🔴 Missing | Download payment order |
| 9 | `/podnesci/{id}/pristojba/dokaz-uplate-oslobodjenja` | GET | 🔴 Missing | Download proof of payment/exemption |
| 10 | `/podnesci/{id}/obavijest-o-primitku` | GET | 🔴 Missing | Download receipt notification |

---

## 5. Key Data Models to Implement

### Core DTOs

| Model | Properties | Usage |
|-------|-----------|-------|
| `Sud` | id, naziv, oznaka, vrsta→VrstaSuda | Every operation references a court |
| `Predmet` | id, status (U_RADU/ARHIVIRAN), oznaka, sud, pisarnica, referada, vrsta, sudionici[], sudskeRadnje[], dokumenti[], vezePredmeta[], doNotDisturb | Case detail — rich nested structure |
| `EkomPodnesak` | id, status (NACRT/POSLAN), sud, predmet, vrstaPostupka, vrstaPodneska, stranke[], protustranke[], prilozi[], pristojba, prosljedjivanja[] | Full submission with nested entities |
| `Otpravak` | id, status (U_DOSTAVI/URUCEN/NEURUCEN), primatelj, datumOtpreme, datumUrucenja, dokumenti[] | Court dispatch with delivery tracking |
| `Pristojba` | vrsta, iznos, placeno, ostatak, detaljiIzracuna, razlogNeplacanja | Fee calculation with payment tracking |

### Request DTOs

| Model | Key Fields | Complexity |
|-------|-----------|------------|
| `CreateEkomPodnesakRequest` | sudId/sudOznaka, vrstaPodneskaId, sadrzaj→CreateDatotekaRequest, stranke[], protustranke[], prilozi[], pristojbaDodatniPodaci | **HIGH** — multiple nested objects, conditional required fields |
| `CreateStrankaRequest` | tip (FIZICKA_OSOBA/PRAVNA_OSOBA/TIJELO), oib, ime/prezime/naziv, ulogaId, adresa→CreateAdresaRequest | Medium — type-dependent validation |
| `CreateAdresaRequest` | drzavaId/naziv/oznaka, zupanijaId, opcinaId, naseljeId, postanskiBroj, ulicaIKucniBroj | **HIGH** — complex geo-resolution logic (multiple ways to identify each entity) |
| `PosaljiEkomPodnesakNaSudRequest` | razlogNeplacanjaId | Low — but triggers irreversible action |

### Enum Values to Track

| Enum | Values | Used In |
|------|--------|---------|
| Predmet.status | `U_RADU`, `ARHIVIRAN` | Case filtering |
| EkomPodnesak.status | `NACRT`, `POSLAN` | Submission lifecycle |
| Otpravak.status | `U_DOSTAVI`, `URUCEN`, `NEURUCEN` | Dispatch tracking |
| PagedOtpravak.status | `PRIMLJEN`, `U_DOSTAVI` | Dispatch listing (different enum!) |
| Stranka.tip | `FIZICKA_OSOBA`, `PRAVNA_OSOBA`, `TIJELO` | Party creation |
| Dokument.tip | `PODNESAK`, `PRILOG`, `ODLUKA`, `ZAPISNIK`, `POZIV`, `UMETNUTI_PREDMET` | Document classification |
| SudskaRadnja.status | `NEAKTIVNA`, `ODREDJENA`, `ODGODJENA`, `IZVRSENA` | Hearing/action tracking |
| valuta | `EUR`, `HRK` | Fee amounts |

---

## 6. Sprint Plan

### Sprint 0: Foundation (Est. 4h)

**Goal:** Robust HTTP client with auth, retry, error handling

| Task | Description | Test |
|------|-------------|------|
| **S0-1** | `EKomHttpClient` — Guzzle wrapper with bearer token, base URL from config, timeout, retry with exponential backoff | Unit test: mock responses, verify retry on 429/503 |
| **S0-2** | Error response mapping — parse `BadRequestError`, `ForbiddenError`, `NotFoundError`, `InternalServerError` into typed exceptions | Unit test: each error code → correct exception class |
| **S0-3** | Pagination helper — generic `PaginatedResponse<T>` that handles `page`, `size`, `totalElements`, `totalPages` across all list endpoints | Unit test: iterate pages, detect last page |
| **S0-4** | Config registration — `config/ekom.php` with env variables, service provider binding | Integration test: resolve from container |

### Sprint 1: Šifrarnici Sync (Est. 6h) — BLOCKING

**Goal:** All reference data cached locally for offline use

| Task | Description | Test |
|------|-------------|------|
| **S1-1** | **Database migrations** — `ekom_courts`, `ekom_procedure_types`, `ekom_submission_types`, `ekom_participant_roles`, `ekom_fee_options`, `ekom_fee_exemptions`, `ekom_non_payment_reasons`, `ekom_settlements`, `ekom_countries` | Migration test: up/down |
| **S1-2** | **Eloquent models** — `EkomCourt`, `EkomProcedureType`, `EkomSubmissionType`, `EkomParticipantRole`, etc. with proper relationships (court→procedure_types→submission_types) | Model test: relationships |
| **S1-3** | `SifarniciService` — methods for each of the 12 endpoints, returns typed DTOs | Unit test: mock API, verify DTO mapping |
| **S1-4** | `SifarniciSyncCommand` (`ekom:sync-sifrarnici`) — cascading sync: courts → for each court: procedure types → for each: submission types, roles, fee options. Idempotent upsert. Progress bar. | Integration test: full sync cycle |
| **S1-5** | **Scheduler entry** — weekly sync of šifrarnici, with force-refresh option (`--force`) | Verify cron registration |
| **S1-6** | **Important nuance:** Submission types differ between `novi-postupak` (new proceeding) and `postojeci-predmet` (existing case) — store both variants with a `context` discriminator column | Test: same procedure type returns different submission types per context |

### Sprint 2: Predmeti Read (Est. 5h)

**Goal:** Full case reading, search, and document download

| Task | Description | Test |
|------|-------------|------|
| **S2-1** | `PredmetService::list()` — paginated search with all filters: status[], sudId[], vrstaUpisnikaOznaka[], oznaka, strankaProtustrankaOib, etc. | Test: filter combinations |
| **S2-2** | `PredmetService::getById(int $id)` — returns full `Predmet` DTO with nested sudionici, sudskeRadnje, dokumenti, vezePredmeta | Test: deep DTO hydration |
| **S2-3** | `PredmetService::getByCourtAndOznaka(sudId/sudOznaka, oznaka)` — uses the `GET /predmeti/` endpoint (trailing slash!) with court+oznaka params. **Note:** requires exactly one of `sudId`/`sud`/`sudOznaka` and exactly one of `oznaka`/`predmetOznaka` | Test: lookup by each param variant |
| **S2-4** | `PredmetService::downloadDocuments(int $id, ?array $dokumentIds)` — downloads ZIP of case documents, optionally filtered by document IDs | Test: binary response handling, file storage |
| **S2-5** | `PredmetService::getOtpravci(int $id)` — dispatches within a case | Test: nested otpravak DTOs |
| **S2-6** | `PredmetService::downloadDostavnica(int $predmetId, int $otpravakId)` — delivery receipt document | Test: binary PDF response |
| **S2-7** | `ekom:fetch-predmeti` artisan command — bulk fetch cases with filters, store locally, cross-reference with existing e-Predmet data | Integration test |

### Sprint 3: Otpravci (Est. 4h)

**Goal:** Track and manage court dispatches, confirm receipt

| Task | Description | Test |
|------|-------------|------|
| **S3-1** | `OtpravakService::list()` — paginated search with all filters (status, sudId, vrstaUpisnikaOznaka, datumSlanjaSaSuda range, datumPotvrdePrimitka range, samoPrimljeniZbogIstekaRoka) | Test: date range filters |
| **S3-2** | `OtpravakService::getById(int $id)` — returns full `Predmet` (note: API returns Predmet, not just Otpravak!) | Test: verify response schema |
| **S3-3** | `OtpravakService::downloadDocuments(int $id, ?array $dokumentIds)` — ZIP download of dispatch documents | Test: binary handling |
| **S3-4** | `OtpravakService::downloadPotvrdaPrimitka(int $id)` — receipt confirmation PDF | Test: PDF response |
| **S3-5** | `OtpravakService::potvrdiPrimitak(int $id)` — **Confirm receipt (legally significant action!)** — implement with confirmation dialog, audit log, deadline tracking. `zadnjiTrenutakZaPotvrduPrimitka` = legal deadline per ZPP čl. 143.c | Test: confirm only U_DOSTAVI status dispatches |
| **S3-6** | `ekom:check-dispatches` command — check for unconfirmed dispatches approaching deadline, send alerts | Test: deadline calculation |

### Sprint 4: Podnesci — Draft Creation (Est. 8h)

**Goal:** Create submission drafts with full party/attachment support

| Task | Description | Test |
|------|-------------|------|
| **S4-1** | `PodnesakService::list()` — paginated search with all filters | Test: filter combinations |
| **S4-2** | `PodnesakService::getById(int $id)` — full `EkomPodnesak` DTO | Test: deep DTO hydration |
| **S4-3** | **`PodnesakService::createDraft(CreateEkomPodnesakRequest)`** — complex builder pattern for the creation request. Must handle: court selection (sudId OR sudOznaka), content file (CreateDatotekaRequest with PDF, page count), stranke[] (with type-dependent validation), protustranke[], pristojbaDodatniPodaci (conditional). **Two modes:** new proceeding (requires vrstaPostupkaId, ulogaPodnositeljaId, stranke) vs. existing case (requires predmetId/predmetOznaka, no stranke/protustranke) | Test: both modes, validation rules |
| **S4-4** | `CreateEkomPodnesakRequestBuilder` — fluent builder: `->forCourt(id)->asNewProceeding(vrstaPostupkaId)->withContent(pdf, pages)->addStranka(...)` vs. `->forExistingCase(predmetId)->withContent(...)` | Test: builder produces valid request |
| **S4-5** | `PodnesakService::addAttachment(int $id, CreateEkomPrilogRequest)` — add prilog with opis, primjedba, and content file | Test: multipart upload |
| **S4-6** | `PodnesakService::delete(int $id)` — delete draft (only NACRT status) | Test: refuse delete of POSLAN |
| **S4-7** | `CreateAdresaRequestBuilder` — handles the complex geo-resolution (država → županija → općina → naselje → poštanski broj + ulica). Multiple identification strategies per entity | Test: various address construction paths |

### Sprint 5: Podnesci — Fees & Sending (Est. 5h)

**Goal:** Fee handling and submission to court

| Task | Description | Test |
|------|-------------|------|
| **S5-1** | `PodnesakService::getPristojba(int $id)` — fee calculation for a draft | Test: parse Pristojba DTO with all fee fields |
| **S5-2** | `PodnesakService::downloadNalogZaPlacanje(int $id)` — payment order PDF | Test: binary response |
| **S5-3** | `PodnesakService::downloadDokazUplate(int $id)` — proof of payment/exemption | Test: binary response |
| **S5-4** | **`PodnesakService::sendToCourtAsync(int $id, ?int $razlogNeplacanjaId)`** — legally irreversible action. Implement with: pre-flight validation (all required fields present, fee status OK), confirmation step, audit logging, retry-safe idempotency. razlogNeplacanjaId required if fee unpaid (ostatak > 0) and no 100% exemption | Test: pre-flight validation, refuse incomplete submissions |
| **S5-5** | `PodnesakService::downloadObavijestOPrimitku(int $id)` — receipt notification from court after acceptance | Test: only available for POSLAN status |

### Sprint 6: DND Settings & Cross-Cutting (Est. 3h)

**Goal:** Do-Not-Disturb management, monitoring

| Task | Description | Test |
|------|-------------|------|
| **S6-1** | `PredmetService::turnOnDnd(int $predmetId)` / `turnOffDnd` — per-case DND | Test: toggle states |
| **S6-2** | `PredmetService::turnOnGlobalDnd()` / `turnOffGlobalDnd()` / `resetAllDnd()` | Test: global toggle returns DTO with counts |
| **S6-3** | Health check command — verify API connectivity, token validity, basic endpoint accessibility | Test: smoke test |
| **S6-4** | Rate limiting / throttle middleware — respect API limits | Test: backoff behavior |

---

## 7. Critical Implementation Notes

### 7.1 Authentication
The API uses `/api/veliki-korisnici/` prefix indicating "large user" (law firm / legal entity) access. Authentication is likely bearer token obtained via NIAS/e-Građani OAuth flow. The existing Advocatus implementation stored the token encrypted in the database — follow the same pattern.

### 7.2 Pagination Gotchas
- Pages start from **0** (not 1)
- Max page size is **100**
- Sort format: `field,asc` or `field,desc` — note the comma separator
- Otpravci sort description has a typo: says "asc" for both directions

### 7.3 Two Different Predmet List Endpoints
- `GET /predmeti` (no trailing slash) — paginated search with filters, returns `PagedPredmet[]`
- `GET /predmeti/` (WITH trailing slash) — direct lookup by court+oznaka, returns single `Predmet`
- These are **different endpoints** with different parameters and responses!

### 7.4 Otpravak Status Inconsistency
- `Otpravak` schema: `U_DOSTAVI`, `URUCEN`, `NEURUCEN`
- `PagedOtpravak` schema: `PRIMLJEN`, `U_DOSTAVI`
- The list endpoint uses `PRIMLJEN` (not `URUCEN`) — map accordingly

### 7.5 File Upload Convention
Content files (`sadrzaj`) use `CreateDatotekaRequest` with `naziv` (filename), `brojStranica` (page count — auto-detected for PDF, mandatory for non-PDF), and `ignorirajUpozorenja` (override format warnings). The actual binary is likely sent as multipart — verify with API testing.

### 7.6 Legal Deadline Tracking (Otpravci)
`zadnjiTrenutakZaPotvrduPrimitka` is legally critical — after this datetime, the dispatch is considered received regardless of explicit confirmation (per ZPP čl. 143.c, ZUS čl. 110, ZKP čl. 172.a). Implement deadline alerts.

### 7.7 Pristojba (Fee) Flow
1. Create draft → system calculates fee
2. Check `pristojba.ostatak` — if > 0, payment needed
3. If paying: download `nalog-za-placanje`, pay externally, fee updates
4. If exempt: set `postotakOslobodjenjaDrugaOsnova` = 100 + `osnovaOslobodjenjaDrugaOsnovaId`
5. If not paying: must provide `razlogNeplacanjaId` when sending
6. e-Komunikacija gives 50% fee discount for electronic submission

---

## 8. Estimated Total Effort

| Sprint | Focus | Est. Hours |
|--------|-------|-----------|
| Sprint 0 | Foundation (HTTP client, error handling, pagination) | 4h |
| Sprint 1 | Šifrarnici sync (reference data — **blocking**) | 6h |
| Sprint 2 | Predmeti read (cases, documents) | 5h |
| Sprint 3 | Otpravci (dispatches, receipt confirmation) | 4h |
| Sprint 4 | Podnesci drafts (creation, parties, attachments) | 8h |
| Sprint 5 | Podnesci fees & sending | 5h |
| Sprint 6 | DND, monitoring, cross-cutting | 3h |
| **Total** | | **~35h** |

### Recommended Execution Order
1. **S0 → S1** (first, always — can't do anything without reference data)
2. **S2** (read cases — immediate value for legal analysis pipeline)
3. **S3** (dispatches — time-sensitive due to legal deadlines)
4. **S4 → S5** (submission workflow — highest complexity)
5. **S6** (polish)

### Quick Wins After Sprint 1+2
Once šifrarnici are synced and case reading works, you can immediately:
- Cross-reference e-Komunikacija cases with your e-Predmet statistical analysis
- Download actual case documents (decisions, records) for OCR/AI analysis
- Track case status changes across your monitored courts
- Build a unified case view combining e-Predmet metadata + e-Komunikacija documents
