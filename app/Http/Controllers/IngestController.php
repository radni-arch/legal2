<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ingest\IngestFileRequest;
use App\Http\Requests\Ingest\IngestLawsCroatiaRequest;
use App\Http\Requests\Ingest\IngestTextRequest;
use App\Http\Requests\Ingest\SearchVectorRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Law;
use App\Services\IngestPipelineService;
use App\Services\LawIngestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class IngestController extends Controller
{
    public function __construct(protected IngestPipelineService $pipeline, protected LawIngestService $laws) {}

    public function ingestText(IngestTextRequest $request)
    {
        $this->authorize('create', Law::class);

        try {
            $validated = $request->validated();
            $namespace = $validated['namespace'] ?? 'default';
            $res = $this->pipeline->ingestText($validated['agent'], $namespace, $validated['text'], $validated);
        } catch (\Exception $e) {
            Log::error('Ingest text failed', [
                'operation' => 'ingestText',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    public function ingestFile(IngestFileRequest $request)
    {
        $this->authorize('create', Law::class);

        try {
            $validated = $request->validated();
            /** @var UploadedFile $file */
            $file = $validated['file'];
            $namespace = $validated['namespace'] ?? 'default';

            $path = $file->store('tmp/ingest');
            $full = Storage::path($path);
            try {
                $res = $this->pipeline->ingestFile($validated['agent'], $namespace, $full, $file->getClientMimeType(), $validated);
            } finally {
                Storage::delete($path);
            }
        } catch (\Exception $e) {
            Log::error('Ingest file failed', [
                'operation' => 'ingestFile',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    public function search(SearchVectorRequest $request)
    {
        $this->authorize('viewAny', Law::class);

        try {
            $validated = $request->validated();
            $res = $this->pipeline->search($validated['agent'], $validated['namespace'] ?? null, $validated['query'], $validated['limit'] ?? 5);
        } catch (\Exception $e) {
            Log::error('Ingest search failed', [
                'operation' => 'search',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    public function ingestLaws(IngestLawsCroatiaRequest $request)
    {
        $this->authorize('create', Law::class);

        try {
            $validated = $request->validated();
            $res = $this->laws->ingest($validated);
        } catch (\Exception $e) {
            Log::error('Ingest laws failed', [
                'operation' => 'ingestLaws',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }
}
