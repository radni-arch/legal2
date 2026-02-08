<?php

namespace App\Http\Controllers;

use App\Http\Requests\Upload\DirectUploadRequest;
use App\Http\Requests\Upload\InitChunkedUploadRequest;
use App\Http\Requests\Upload\UploadChunkRequest;
use App\Http\Responses\ApiResponse;
use App\Services\UploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class UploadController extends Controller
{
    public function __construct(protected UploadService $uploads) {}

    // Direct upload
    public function direct(DirectUploadRequest $request)
    {
        try {
            $validated = $request->validated();
            /** @var UploadedFile $file */
            $file = $validated['file'];
            $res = $this->uploads->directStore($file);
        } catch (\Exception $e) {
            Log::error('Upload direct failed', [
                'operation' => 'direct',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    // Chunked: start
    public function start(InitChunkedUploadRequest $request)
    {
        try {
            $validated = $request->validated();
            $res = $this->uploads->start($validated['filename'], $validated['totalSize'], $validated['chunkSize'], $validated['mime'] ?? null);
        } catch (\Exception $e) {
            Log::error('Upload start failed', [
                'operation' => 'start',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    // Chunked: upload a part
    public function chunk(UploadChunkRequest $request, string $uploadId, int $index)
    {
        try {
            $validated = $request->validated();
            /** @var UploadedFile $part */
            $part = $validated['chunk'];
            $res = $this->uploads->uploadChunk($uploadId, $index, $part);
        } catch (\Exception $e) {
            Log::error('Upload chunk failed', [
                'operation' => 'chunk',
                'upload_id' => $uploadId,
                'index' => $index,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    // Chunked: complete
    public function complete(string $uploadId)
    {
        try {
            $res = $this->uploads->complete($uploadId);
        } catch (\Exception $e) {
            Log::error('Upload complete failed', [
                'operation' => 'complete',
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    // Chunked: cancel
    public function cancel(string $uploadId)
    {
        try {
            $ok = $this->uploads->cancel($uploadId);
        } catch (\Exception $e) {
            Log::error('Upload cancel failed', [
                'operation' => 'cancel',
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success(['ok' => (bool) $ok]);
    }
}
