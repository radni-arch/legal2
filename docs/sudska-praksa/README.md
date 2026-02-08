# Sudska Praksa Search

> Search Croatian court decisions on odluke.sudovi.hr with keyword analysis and classification.

## Overview

The Sudska Praksa module provides a comprehensive suite of Artisan commands for searching the Croatian court decisions portal (odluke.sudovi.hr), AI-powered keyword generation, database persistence, and trend analysis.

## Commands

| Command | Description |
|---------|-------------|
| `sudska-praksa:search` | Search odluke.sudovi.hr with keyword queries |
| `sudska-praksa:compare` | Compare two search runs for trend detection |
| `sudska-praksa:generate-keywords` | AI-powered keyword generation from case description |
| `sudska-praksa:pipeline` | Full pipeline: AI generate -> search -> persist |

## Installation

The commands are included with the AI Legal War Machine application. No additional installation required.

## Quick Start

```bash
# Generate a sample keywords file
php artisan sudska-praksa:search --generate-keywords

# Run a search with default settings
php artisan sudska-praksa:search

# Run a search with specific options
php artisan sudska-praksa:search --max=10 --format=table
```

## Command Options

| Option | Description | Default |
|--------|-------------|---------|
| `--keywords-file=PATH` | Path to keywords JSON file | `storage/app/keywords/default.json` |
| `--courts=CODES` | Court types to search | `vks,vps,vs,zs` |
| `--delay=MS` | Delay between requests (ms) | `500` |
| `--format=FORMAT` | Output format: `table`, `json`, `csv`, `md`, `all` | `all` |
| `--output=DIR` | Output directory for results | `storage/app/results` |
| `--max=N` | Maximum queries to run | All queries |
| `--filter=CLASS` | Filter by classification | None |
| `--expand` | Auto-expand successful queries | Disabled |
| `--generate-keywords` | Generate sample keywords file | - |
| `--min-results=N` | Minimum result count to display | `0` |
| `--max-results=N` | Maximum result count to display | Unlimited |

## Court Codes

| Code | Court | Description |
|------|-------|-------------|
| `vks` | Vrhovni kazneni sud | Supreme Criminal Court |
| `vps` | Visoki prekrsajni sud | High Misdemeanor Court |
| `vs` | Vrhovni sud | Supreme Court |
| `zs` | Zupanijski sudovi | County Courts |

## Result Classifications

Results are classified based on the number of matches:

| Classification | Result Count | Description |
|----------------|--------------|-------------|
| ULTRA | 1-5 | Highly specific, rare precedents |
| ZLATO (Gold) | 6-15 | Valuable, manageable set |
| SREBRNO (Silver) | 16-50 | Good scope, reviewable |
| BRONCA (Bronze) | 51-150 | Large set, needs filtering |
| BULK | >150 | Too broad, refine query |
| EMPTY | 0 | No results |
| ERROR | -1 | Request failed |

## Keywords File Format

```json
{
  "metadata": {
    "name": "Case Name",
    "description": "Description of the search",
    "version": "1.0",
    "author": "Author Name",
    "created_at": "2026-02-04"
  },
  "categories": [
    {
      "name": "1. Category Name",
      "description": "Category description",
      "queries": [
        {"q": "keyword1 AND keyword2", "comment": "Why this query"},
        {"q": "keyword1 AND keyword3", "comment": "Alternative approach"}
      ]
    }
  ]
}
```

## Examples

### Basic Search

```bash
php artisan sudska-praksa:search --max=5 --format=table
```

### Filter by Classification

```bash
# Show only ULTRA and ZLATO results
php artisan sudska-praksa:search --filter=ultra
php artisan sudska-praksa:search --filter=zlato
```

### Export Results

```bash
# Export to JSON only
php artisan sudska-praksa:search --format=json --output=/path/to/output

# Export to all formats
php artisan sudska-praksa:search --format=all
```

### Custom Keywords File

```bash
php artisan sudska-praksa:search --keywords-file=storage/app/keywords/my-case.json
```

### Auto-Expand Queries

The `--expand` option will automatically generate variations of successful queries:

```bash
php artisan sudska-praksa:search --expand
```

### Database Persistence

Save results to the database for tracking and comparison:

```bash
php artisan sudska-praksa:search --persist
```

## Compare Command

Compare two search runs to detect changes in case law:

```bash
# Compare search run #1 with search run #2
php artisan sudska-praksa:compare 1 2

# Only show changes >= 5 results difference
php artisan sudska-praksa:compare 1 2 --threshold=5
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `search1` | ID of first search run | Required |
| `search2` | ID of second search run | Required |
| `--threshold=N` | Minimum difference to display | `0` |

## Generate Keywords Command

Generate a keywords JSON file using AI based on a case description:

```bash
# From command line description
php artisan sudska-praksa:generate-keywords \
  --case-description="Policija je usla u stan bez naloga..."

# From a file
php artisan sudska-praksa:generate-keywords \
  --case-description=storage/app/cases/case-123.txt

# Custom output path
php artisan sudska-praksa:generate-keywords \
  --case-description="..." \
  --output=storage/app/keywords/my-case.json
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--case-description=TEXT` | Case description (text or file path) | Interactive |
| `--output=PATH` | Output file path | `storage/app/keywords/generated.json` |
| `--model=MODEL` | AI model to use | `claude-sonnet-4-5-20250929` |

## Pipeline Command

Run the full workflow: AI keyword generation -> search -> database persistence:

```bash
# Full pipeline from description
php artisan sudska-praksa:pipeline \
  --case-description="Opis slucaja..."

# From file with query expansion
php artisan sudska-praksa:pipeline \
  --case-file=storage/app/cases/my-case.txt \
  --expand

# Specific courts
php artisan sudska-praksa:pipeline \
  --case-description="..." \
  --courts=vks,vs
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--case-description=TEXT` | Case description | Interactive |
| `--case-file=PATH` | File with case description | - |
| `--expand` | Auto-expand keywords | Disabled |
| `--courts=CODES` | Court types to search | `vks,vps,vs,zs` |

## Architecture

```
app/
  Console/Commands/
    SudskaPraksaSearch.php          # Main search command
    SudskaPraksaCompare.php         # Trend comparison
    SudskaPraksaGenerateKeywords.php # AI keyword generation
    SudskaPraksaPipeline.php        # Full pipeline
  Models/
    SudskaPraksaSearch.php          # Search run model
    SudskaPraksaResult.php          # Individual result model
  Services/
    SudskaPraksaService.php         # Core HTTP probing logic
  Validators/
    KeywordsValidator.php           # Keywords JSON validation

config/
  sudska-praksa.php                 # Configuration

storage/app/
  keywords/                         # Keywords JSON files
    default.json                    # Default keywords
  results/                          # Output files (JSON/CSV/MD)

database/migrations/
  *_create_sudska_praksa_tables.php # Database schema

docs/sudska-praksa/
  METHODOLOGY.md                    # 6-phase keyword methodology
  README.md                         # This file
```

## Configuration

Configuration is stored in `config/sudska-praksa.php`. Key settings:

```php
return [
    'base_url' => env('SUDSKA_PRAKSA_BASE_URL', 'https://odluke.sudovi.hr/Document/DisplayList'),
    'default_courts' => env('SUDSKA_PRAKSA_COURTS', 'vks,vps,vs,zs'),
    'delay_ms' => env('SUDSKA_PRAKSA_DELAY', 500),
    'timeout' => env('SUDSKA_PRAKSA_TIMEOUT', 15),
    'thresholds' => [
        'ultra' => 5,
        'zlato' => 15,
        'srebrno' => 50,
        'bronca' => 150,
    ],
];
```

## Environment Variables

Add to `.env`:

```
SUDSKA_PRAKSA_BASE_URL=https://odluke.sudovi.hr/Document/DisplayList
SUDSKA_PRAKSA_COURTS=vks,vps,vs,zs
SUDSKA_PRAKSA_DELAY=500
SUDSKA_PRAKSA_TIMEOUT=15
SUDSKA_PRAKSA_MAX_RETRIES=3
SUDSKA_PRAKSA_RETRY_DELAY=2000
```

| Variable | Default | Description |
|----------|---------|-------------|
| `SUDSKA_PRAKSA_BASE_URL` | odluke.sudovi.hr/... | Base search URL |
| `SUDSKA_PRAKSA_COURTS` | `vks,vps,vs,zs` | Default courts |
| `SUDSKA_PRAKSA_DELAY` | `500` | Request delay (ms) |
| `SUDSKA_PRAKSA_TIMEOUT` | `15` | HTTP timeout (s) |
| `SUDSKA_PRAKSA_MAX_RETRIES` | `3` | Max retry attempts |
| `SUDSKA_PRAKSA_RETRY_DELAY` | `2000` | Base retry delay (ms) |

## Retry Logic

The service automatically retries failed requests with exponential backoff:
- Retries on 5xx server errors
- Retries on connection exceptions
- Backoff: `retry_delay * attempt` (e.g., 2s, 4s, 6s for 3 attempts)

## Methodology

For detailed guidance on creating effective keyword queries, see [METHODOLOGY.md](./METHODOLOGY.md).

Key principles:
1. Always use AND operator between keywords
2. Optimal query length: 2-4 keywords
3. Use official legal terminology
4. Target ULTRA/ZLATO classifications for best results

## Output Formats

### Table (console)

```
+----------------+------------+
| Klasifikacija  | Broj upita |
+----------------+------------+
| ULTRA          | 3          |
| ZLATO          | 5          |
| SREBRNO        | 2          |
+----------------+------------+
```

### JSON

Full structured output with metadata, summary, and all results.

### CSV

Spreadsheet-compatible format for further analysis.

### Markdown

Human-readable report with tables and links.

## Troubleshooting

### No Results

- Check your keyword spelling
- Try broader terms (fewer AND conditions)
- Verify court codes are correct

### Too Many Results

- Add more specific keywords
- Use legal article references (e.g., "cl. 246 AND ZKP")

### Connection Errors

- Check internet connectivity
- Increase `--delay` to avoid rate limiting
- Verify `SUDSKA_PRAKSA_BASE_URL` is correct

## Related Documentation

- [METHODOLOGY.md](./METHODOLOGY.md) - 6-phase keyword generation methodology
- [config/sudska-praksa.php](../../config/sudska-praksa.php) - Configuration reference

## License

Part of AI Legal War Machine. Copyright 3P Solutions d.o.o.
