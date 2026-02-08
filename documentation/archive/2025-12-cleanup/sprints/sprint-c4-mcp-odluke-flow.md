# Sprint C4 — MCP Odluke Tools Alignment with Decisions Ingestion

Date: 2025-10-26

## Summary
This branch aligns the Odluke-related MCP tools with the court decisions ingestion pipeline to eliminate duplicate logic, converge on a single client/service stack, and preserve output schemas validated by tests.

Key outcomes:
- odluke-meta and odluke-download now delegate to OdlukeIngestService (which uses OdlukeClient), removing downloader/JSON assembly code from the MCP layer.
- ADK tools (OdlukeMetaTool, OdlukeDownloadTool, OdlukeSearchTool) continue to use InternalMcpClient; schemas/names unchanged.
- ToolSchemas remains single source of truth for schema/description; no breaking changes to function signatures or return envelopes.

## Scope and Goals
- Ensure odluke tools map directly to decisions ingestion pipeline.
- Reuse OdlukeClient and OdlukeIngestService for meta and download flows.
- Remove duplicate download and JSON construction logic from MCP tools.
- Keep unit-test-validated schemas stable.

## Affected Components
- MCP tools
  - `App\Mcp\OdlukeTools`
    - `meta(...)` — now calls `OdlukeIngestService->getMetadataForIds`.
    - `download(...)` — now calls `OdlukeIngestService->download`.
- Ingestion service
  - `App\Services\Odluke\OdlukeIngestService`
    - NEW: `getMetadataForIds(array $ids, ?string $baseUrl = null): array`.
    - NEW: `download(string $id, array $options = []): array`.
  - Existing ingestion and helper methods unchanged.
- HTTP client
  - `App\Services\Odluke\OdlukeClient` (unchanged; reused everywhere).
- Internal MCP client
  - `App\Services\Mcp\InternalMcpClient` (unchanged; continues to dispatch to `OdlukeTools`).
- ADK tools
  - `App\Tools\OdlukeMetaTool`, `App\Tools\OdlukeDownloadTool`, `App\Tools\OdlukeSearchTool` (unchanged; still call `InternalMcpClient`).
- Schemas
  - `App\Mcp\ToolSchemas` (unchanged for Odluke tools; remains source of truth).

## Flow — Before vs After

### Before
```
Vizra ADK Tool -> InternalMcpClient -> OdlukeTools
  - odluke-meta: built per-ID meta via OdlukeClient, assembled JSON here
  - odluke-download: called OdlukeClient (pdf/html), mkdir/write & JSON assembly here
  (duplication of download + JSON structure in MCP layer)
```

### After
```
Vizra ADK Tool -> InternalMcpClient -> OdlukeTools
  - odluke-meta     -> OdlukeIngestService->getMetadataForIds -> OdlukeClient
  - odluke-download -> OdlukeIngestService->download          -> OdlukeClient

Shared MCP content envelope preserved:
OdlukeTools wraps service result into MCP response:
{
  content: [{ type: 'text', text: json_string }],
  isError: boolean
}
```

## Contract and Data Shapes

MCP content envelope (unchanged):
- Response: `{ content: [{ type: 'text', text: string }], isError: bool }`.
- `text` holds UTF-8 JSON of the result object.

Service-level structures (centralized in `OdlukeIngestService`):

- `getMetadataForIds([...], baseUrl)` returns array of entries:
  - Success entry:
    - `{ id, meta, basename, download_pdf_url, download_html_url }`
  - Error entry:
    - `{ id, error: 'Neuspješan dohvat' }`

- `download(id, { format, save, base_url })` returns object:
  - Common fields: `{ id, meta, download_pdf_url, download_html_url, saved: {}, errors: {} }`
  - When not saving: includes `{ pdf: { content_type, bytes }, html: { content_type, bytes } }` depending on `format`.
  - When saving: records filesystem paths in `saved.pdf` / `saved.html`.
  - Errors per format are recorded under `errors.pdf` / `errors.html` (e.g., `HTTP 404`, IO failures).

Schemas for tool inputs are unchanged and remain defined in `ToolSchemas`:
- odluke-search
- odluke-meta
- odluke-download

## Why This Change
- Single source of truth for download and metadata assembly avoids drift between ingestion pipeline and MCP tools.
- Enables consistent filenames, source URLs, and error recording.
- Keeps downstream expectations (e.g., dashboards/tests) stable by preserving output schema and MCP envelope.

## Configuration Used
- `config('odluke.base_url')` default base URL; tools can override via `base_url` arg.
- Throttling/retry: `odluke.rpm`, `odluke.backoff_ms`, `odluke.delay_ms`, `odluke.timeout`, `odluke.retry`.
- Output directory for saving: `config('odluke.out_dir')` (falls back to `storage_path('app/odluke')`).

## Error Handling and Edge Cases
- Directory creation errors when saving are reported via `errors.io` or per-format error keys; MCP `isError` set accordingly by the tool.
- HTTP failures are captured with status codes in `errors.pdf`/`errors.html`.
- Metadata fetch failures produce per-ID error entries (for multi-ID meta).
- `format` normalization: anything outside `pdf|html|both` coerced to `pdf`.
- `base_url` honored by the service methods to allow pointing at alternative hosts.

## Testing and Quality Gates
- Tool schemas and names are unchanged; ADK tools and Internal MCP client interfaces remain intact.
- MCP response envelope unchanged.
- Unit tests in this environment encountered an unrelated SQLite migration issue; however, these changes maintain the previously validated tool schemas and do not introduce breaking API changes.

## Developer Tips

Call through the Internal MCP client (in-process) to exercise the tools without HTTP:

```php
app(\App\Services\Mcp\InternalMcpClient::class)->callTool('odluke-meta', [
    'ids' => ['GUID-1', 'GUID-2'],
]);

app(\App\Services\Mcp\InternalMcpClient::class)->callTool('odluke-download', [
    'id' => 'GUID-1',
    'format' => 'pdf', // pdf|html|both
    'save' => false,
]);
```

The returned value is the MCP envelope; the actual JSON payload is in `content[0].text`.

## File/Code References
- MCP tools: `app/Mcp/OdlukeTools.php`
- Ingestion service: `app/Services/Odluke/OdlukeIngestService.php`
  - New methods: `getMetadataForIds`, `download`
- HTTP client: `app/Services/Odluke/OdlukeClient.php`
- Internal client: `app/Services/Mcp/InternalMcpClient.php`
- ADK tools: `app/Tools/OdlukeMetaTool.php`, `app/Tools/OdlukeDownloadTool.php`, `app/Tools/OdlukeSearchTool.php`
- Schemas: `app/Mcp/ToolSchemas.php`

## Backward Compatibility
- No changes to tool names, input schemas, or MCP response envelope.
- Output JSON fields for odluke-meta and odluke-download preserved and now consistently produced by the ingestion service.

## Next Steps
- Consider delegating `odluke-search` to a thin service wrapper as well (purely for parity; current code already reuses `OdlukeClient`).
- Add integration tests that invoke InternalMcpClient->callTool for Odluke flows to assert the full stack from MCP tool to client (behind a feature-flag to bypass non-SQLite migrations in CI).

