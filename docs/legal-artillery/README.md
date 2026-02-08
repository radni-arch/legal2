# Pravna Artiljerija — Legal Document Generator

## Overview

Pravna Artiljerija je sustav za automatsko generiranje pravnih dopisa s rekurzivnim poboljsanjem.
Kombinira AI generiranje s pravnom bazom znanja, automatskom validacijom, i visekanalnim slanjem.

## Features

- **8 profila dopisa** — od predsjednika suda do ECHR-a
- **Rekurzivno poboljsanje** — Worker/Critic pattern s konvergencijom
- **Pravna baza** — 16+ odredbi, 16 presuda, lanci argumenata
- **DOCX generiranje** — profesionalni Word dokumenti
- **Gmail slanje** — automatsko slanje s prilozima
- **e-Komunikacija** — slanje na sud putem API-ja
- **Digitalno potpisivanje** — eID kartica s PKCS#11
- **Response handling** — parsiranje i odgovori na protivnicke dopise
- **Livewire dashboard** — real-time pracenje generiranja

## Quick Start

```bash
# Setup
php artisan migrate
php artisan db:seed --class=LegalProvisionsSeeder
php artisan db:seed --class=LegalPrecedentsSeeder
php artisan legal:gmail-auth

# Generate a single document (no send)
php artisan legal:fire predsjednik_suda --no-send

# Generate with all features
php artisan legal:fire predsjednik_suda --sign --ekom --iterations=5
```

## Artisan Commands

### `legal:fire` — Single Document Generation

```bash
# List all available profiles
php artisan legal:fire --list

# Generate without sending
php artisan legal:fire predsjednik_suda --no-send

# Generate and save as Gmail draft
php artisan legal:fire predsjednik_suda --draft

# Generate with custom recipient email
php artisan legal:fire predsjednik_suda --to=sud@example.com

# Dry run (outline only, no generation)
php artisan legal:fire predsjednik_suda --dry-run

# Add extra context
php artisan legal:fire predsjednik_suda --context=extra_info="Additional details"
```

**Options:**

| Option | Description |
|--------|-------------|
| `--list` | Display all available profiles in a table |
| `--no-send` | Generate document without sending via email |
| `--draft` | Save as Gmail draft instead of sending |
| `--to=EMAIL` | Override the recipient email address |
| `--dry-run` | Generate an outline only, no full document |
| `--context=*` | Additional key=value context pairs |

### `legal:barrage` — Mass Document Generation

```bash
# Fire all immediate-priority profiles as drafts
php artisan legal:barrage --draft

# Fire specific profiles
php artisan legal:barrage --profiles=predsjednik_suda,ombudsman

# Fire all immediate profiles without sending
php artisan legal:barrage --no-send
```

**Options:**

| Option | Description |
|--------|-------------|
| `--profiles=LIST` | Comma-separated profile keys (default: all immediate-priority) |
| `--no-send` | Generate without sending |
| `--draft` | Save all as Gmail drafts |

### `legal:gmail-auth` — Gmail Authentication

```bash
# Authenticate with Gmail API
php artisan legal:gmail-auth
```

Sets up OAuth2 credentials for Gmail sending capabilities.

## Web Dashboard

Visit `/legal-artillery` after authentication to access the Livewire dashboard.

### Routes

| Route | Component | Description |
|-------|-----------|-------------|
| `GET /legal-artillery` | `Dashboard` | Main dashboard with generation history |
| `GET /legal-artillery/new` | `NewGeneration` | Start a new document generation |
| `GET /legal-artillery/run/{id}` | `RunDetails` | View details of a specific run |

All routes require authentication (`auth` middleware).

### Components

- **Dashboard** (`App\Livewire\LegalArtillery\Dashboard`) — Overview of all generation runs with status tracking
- **NewGeneration** (`App\Livewire\LegalArtillery\NewGeneration`) — Form to select profile, configure options, and start generation
- **RunDetails** (`App\Livewire\LegalArtillery\RunDetails`) — Detailed view of a generation run with iterations, scores, and documents
- **GenerationMonitor** (`App\Livewire\LegalArtillery\GenerationMonitor`) — Real-time monitoring of active generation processes

## Architecture

```
LegalArtilleryAgent (facade)
    |-- LegalArtilleryOrchestrator (extends Vizra BaseLlmAgent)
    |       |-- ProfileContextBuilder (legal context injection)
    |       |       |-- LegalProvision (DB)
    |       |       |-- LegalPrecedent (DB)
    |       |       +-- DevastatingArgumentBuilder
    |       |-- LlmClient (Claude API)
    |       +-- Worker/Critic Loop
    |               |-- Worker: generates document version
    |               +-- Critic: evaluates with weighted scoring
    |-- DocxRenderer (Node.js docx-js)
    |-- DigitalSigner (PKCS#11)
    |-- GmailDispatcher (Google API)
    +-- EKomunikacijaDispatcher (SOAP)
```

### Worker/Critic Pattern

The orchestrator uses a recursive improvement loop:

1. **Worker** generates (or improves) the legal document based on profile, case context, and legal provisions
2. **Critic** evaluates the document on five weighted dimensions:
   - Legal rigor (40%) — accuracy of legal citations
   - Persuasiveness (25%) — strength of arguments
   - Clarity (20%) — readability and structure
   - Evidence integration (10%) — supporting evidence usage
   - Formatting (5%) — adherence to legal format standards
3. The loop continues until convergence (improvement delta < threshold) or max iterations reached

### Generation Flow

```
1. Profile selected (config/legal-artillery.php)
2. Legal context assembled (provisions, precedents, arguments)
3. Recursive Worker/Critic loop (configurable iterations)
4. DOCX rendered from final version
5. Optional: Digital signature via eID card
6. Optional: Send via Gmail or e-komunikacija
7. Run details persisted to database
```

## Configuration

All configuration lives in `config/legal-artillery.php`:

- **sender** — Sender identity (name, OIB, address, email)
- **case_context** — Case details (case number, dates, references)
- **profiles** — Document profile definitions (see [PROFILES.md](PROFILES.md))
- **tones** — Tone definitions with system instructions per language
- **gmail** — Gmail API credentials and sending configuration
- **generation** — LLM model, max tokens, recursion depth, output directory

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `LEGAL_SENDER_NAME` | `Andrija Glavas` | Sender full name |
| `LEGAL_SENDER_OIB` | _(empty)_ | Sender OIB (personal identification number) |
| `LEGAL_SENDER_ADDRESS` | `Primorska ul. 5, 31000 Osijek` | Sender address |
| `LEGAL_SENDER_EMAIL` | _(empty)_ | Sender email |
| `LEGAL_GMAIL_ENABLED` | `false` | Enable Gmail sending |
| `LEGAL_GMAIL_CREDENTIALS` | `storage/app/google/credentials.json` | Google OAuth credentials path |
| `LEGAL_GMAIL_TOKEN` | `storage/app/google/token.json` | Google OAuth token path |
| `LEGAL_LLM_MODEL` | `claude-sonnet-4-20250514` | LLM model for generation |
| `LEGAL_LLM_MAX_TOKENS` | `8192` | Max tokens per LLM call |
| `LEGAL_RECURSION_DEPTH` | `3` | Default recursive iteration count |

## Further Reading

- [PROFILES.md](PROFILES.md) — Document profile reference (all 8 profiles)
- [API.md](API.md) — Programmatic API reference
