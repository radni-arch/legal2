<?php

namespace App\Http\Controllers;

use App\Http\Requests\OpenAI\ChatRequest;
use App\Http\Requests\OpenAI\EmbeddingsRequest;
use App\Http\Requests\OpenAI\ResponsesRequest;
use App\Http\Responses\ApiResponse;
use App\Services\OpenAIService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OpenAIController extends Controller
{
    public function __construct(protected OpenAIService $openai) {}

    public function responses(ResponsesRequest $request)
    {
        try {
            $validated = $request->validated();
            $res = $this->openai->responses($validated);
        } catch (\Exception $e) {
            Log::error('OpenAI responses failed', [
                'operation' => 'responses',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    public function chat(ChatRequest $request)
    {
        try {
            $validated = $request->validated();
            $res = $this->openai->chat($validated['messages'], $validated['model'] ?? null, $validated);
        } catch (\Exception $e) {
            Log::error('OpenAI chat failed', [
                'operation' => 'chat',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    public function embeddings(EmbeddingsRequest $request)
    {
        try {
            $validated = $request->validated();
            $res = $this->openai->embeddings($validated['input'], $validated['model'] ?? null);
        } catch (\Exception $e) {
            Log::error('OpenAI embeddings failed', [
                'operation' => 'embeddings',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    public function image(Request $request)
    {
        try {
            $data = $request->validate([
                'prompt' => ['required', 'string'],
                'n' => ['sometimes', 'integer', 'min:1', 'max:8'],
                'size' => ['sometimes', 'string'],
                'response_format' => ['sometimes', 'string'],
            ]);
            $res = $this->openai->imageGenerate($data['prompt'], $data);
        } catch (\Exception $e) {
            Log::error('OpenAI image generation failed', [
                'operation' => 'image',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    public function tts(Request $request)
    {
        try {
            $data = $request->validate([
                'text' => ['required', 'string'],
                'voice' => ['sometimes', 'string'],
                'format' => ['sometimes', 'in:mp3,wav,flac,ogg'],
                'model' => ['sometimes', 'string'],
            ]);
            $audio = $this->openai->tts($data['text'], $data);

            $format = $data['format'] ?? 'mp3';
            $mime = match ($format) {
                'wav' => 'audio/wav',
                'flac' => 'audio/flac',
                'ogg' => 'audio/ogg',
                default => 'audio/mpeg',
            };

            return response($audio, 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="speech.'.$format.'"',
            ]);
        } catch (\Exception $e) {
            Log::error('OpenAI TTS failed', [
                'operation' => 'tts',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Text-to-speech operation failed',
            ], 500);
        }
    }

    public function transcribe(Request $request)
    {
        try {
            $data = $request->validate([
                'audio' => ['required', 'file', 'mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/mp3,audio/ogg,audio/webm'],
                'model' => ['sometimes', 'string'],
                'language' => ['sometimes', 'string'],
                'prompt' => ['sometimes', 'string'],
            ]);

            /** @var UploadedFile $file */
            $file = $data['audio'];
            $path = $file->store('tmp/openai-audio');
            $full = Storage::path($path);
            try {
                $res = $this->openai->transcribe($full, $data);
            } finally {
                // cleanup temp file
                Storage::delete($path);
            }
        } catch (\Exception $e) {
            Log::error('OpenAI transcribe failed', [
                'operation' => 'transcribe',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    // Files API proxies
    public function filesUpload(Request $request)
    {
        try {
            $data = $request->validate([
                'file' => ['required', 'file'],
                'purpose' => ['sometimes', 'string'],
            ]);
            /** @var UploadedFile $file */
            $file = $data['file'];
            $purpose = $data['purpose'] ?? 'assistants';

            $path = $file->store('tmp/openai-files');
            $full = Storage::path($path);
            try {
                $res = $this->openai->fileUpload($full, $purpose);
            } finally {
                Storage::delete($path);
            }
        } catch (\Exception $e) {
            Log::error('OpenAI file upload failed', [
                'operation' => 'filesUpload',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ApiResponse::success($res);
    }

    public function filesList()
    {
        try {
            return ApiResponse::success($this->openai->fileList());
        } catch (\Exception $e) {
            Log::error('OpenAI files list failed', [
                'operation' => 'filesList',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to list files',
            ], 500);
        }
    }

    public function filesDelete(string $fileId)
    {
        try {
            return ApiResponse::success($this->openai->fileDelete($fileId));
        } catch (\Exception $e) {
            Log::error('OpenAI file delete failed', [
                'operation' => 'filesDelete',
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file',
            ], 500);
        }
    }

    // Assistants
    public function assistantsCreate(Request $request)
    {
        try {
            $data = $request->validate([
                'model' => ['sometimes', 'string'],
                'name' => ['sometimes', 'string'],
                'instructions' => ['sometimes', 'string'],
                'tools' => ['sometimes', 'array'],
                'metadata' => ['sometimes', 'array'],
            ]);

            return ApiResponse::success($this->openai->assistantsCreate($data));
        } catch (\Exception $e) {
            Log::error('OpenAI assistant create failed', [
                'operation' => 'assistantsCreate',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create assistant',
            ], 500);
        }
    }

    public function assistantsRetrieve(string $assistantId)
    {
        try {
            return ApiResponse::success($this->openai->assistantsRetrieve($assistantId));
        } catch (\Exception $e) {
            Log::error('OpenAI assistant retrieve failed', [
                'operation' => 'assistantsRetrieve',
                'assistant_id' => $assistantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assistant',
            ], 500);
        }
    }

    public function assistantsList(Request $request)
    {
        try {
            $query = $request->validate([
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);

            return ApiResponse::success($this->openai->assistantsList($query));
        } catch (\Exception $e) {
            Log::error('OpenAI assistants list failed', [
                'operation' => 'assistantsList',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to list assistants',
            ], 500);
        }
    }

    public function assistantsDelete(string $assistantId)
    {
        try {
            return ApiResponse::success($this->openai->assistantsDelete($assistantId));
        } catch (\Exception $e) {
            Log::error('OpenAI assistant delete failed', [
                'operation' => 'assistantsDelete',
                'assistant_id' => $assistantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete assistant',
            ], 500);
        }
    }

    // Vector Stores
    public function vectorStoreCreate(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => ['sometimes', 'string'],
                'description' => ['sometimes', 'string'],
            ]);

            return ApiResponse::success($this->openai->vectorStoreCreate($data));
        } catch (\Exception $e) {
            Log::error('OpenAI vector store create failed', [
                'operation' => 'vectorStoreCreate',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create vector store',
            ], 500);
        }
    }

    public function vectorStoreRetrieve(string $storeId)
    {
        try {
            return ApiResponse::success($this->openai->vectorStoreRetrieve($storeId));
        } catch (\Exception $e) {
            Log::error('OpenAI vector store retrieve failed', [
                'operation' => 'vectorStoreRetrieve',
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vector store',
            ], 500);
        }
    }

    public function vectorStoreList(Request $request)
    {
        try {
            $query = $request->validate([
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);

            return ApiResponse::success($this->openai->vectorStoreList($query));
        } catch (\Exception $e) {
            Log::error('OpenAI vector store list failed', [
                'operation' => 'vectorStoreList',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to list vector stores',
            ], 500);
        }
    }

    public function vectorStoreDelete(string $storeId)
    {
        try {
            return ApiResponse::success($this->openai->vectorStoreDelete($storeId));
        } catch (\Exception $e) {
            Log::error('OpenAI vector store delete failed', [
                'operation' => 'vectorStoreDelete',
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vector store',
            ], 500);
        }
    }

    public function vectorStoreAddFile(Request $request, string $storeId)
    {
        try {
            $data = $request->validate([
                'fileId' => ['required', 'string'],
            ]);

            return ApiResponse::success($this->openai->vectorStoreAddFile($storeId, $data['fileId']));
        } catch (\Exception $e) {
            Log::error('OpenAI vector store add file failed', [
                'operation' => 'vectorStoreAddFile',
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add file to vector store',
            ], 500);
        }
    }

    public function vectorStoreListFiles(Request $request, string $storeId)
    {
        try {
            $query = $request->validate([
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);

            return ApiResponse::success($this->openai->vectorStoreListFiles($storeId, $query));
        } catch (\Exception $e) {
            Log::error('OpenAI vector store list files failed', [
                'operation' => 'vectorStoreListFiles',
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to list files in vector store',
            ], 500);
        }
    }

    public function vectorStoreDeleteFile(string $storeId, string $fileId)
    {
        try {
            return ApiResponse::success($this->openai->vectorStoreDeleteFile($storeId, $fileId));
        } catch (\Exception $e) {
            Log::error('OpenAI vector store delete file failed', [
                'operation' => 'vectorStoreDeleteFile',
                'store_id' => $storeId,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file from vector store',
            ], 500);
        }
    }
}
