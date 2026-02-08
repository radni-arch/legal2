<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\LogViewerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class LogFileController extends Controller
{
    public function __construct(
        protected LogViewerService $logViewer
    ) {}

    /**
     * List available log files
     *
     * GET /api/logs/files
     */
    public function files(): JsonResponse
    {
        $files = $this->logViewer->getLogFiles();

        $data = array_values(array_map(fn ($f) => [
            'name' => $f['name'],
            'size' => $this->logViewer->formatSize($f['size']),
            'size_bytes' => $f['size'],
            'modified' => date('Y-m-d H:i:s', $f['modified']),
        ], $files));

        return ApiResponse::success($data, 'Log files retrieved');
    }

    /**
     * Get log entries with optional level filtering
     *
     * GET /api/logs/entries?level=ERROR&lines=100&file=laravel.log
     */
    public function entries(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_\-\.]+\.log$/'],
            'level' => ['nullable', 'string', 'max:100'],
            'lines' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('Invalid parameters', $validator->errors());
        }

        $filename = $request->input('file', 'laravel.log');
        $level = $request->input('level');
        $lines = (int) $request->input('lines', 100);

        $filepath = storage_path('logs/'.$filename);

        // Security: verify file exists and is within logs directory
        if (! File::exists($filepath)) {
            return ApiResponse::notFound("Log file '{$filename}' not found");
        }

        $realPath = realpath($filepath);
        $logsDir = realpath(storage_path('logs'));
        if (! $realPath || ! str_starts_with($realPath, $logsDir)) {
            return ApiResponse::notFound("Log file '{$filename}' not found");
        }

        if ($level) {
            $levels = array_map('trim', explode(',', $level));
            if (count($levels) === 1) {
                $entries = $this->logViewer->filterByLevel($filepath, $levels[0], $lines);
            } else {
                $entries = $this->logViewer->filterByLevels($filepath, $levels, $lines);
            }
        } else {
            $rawLines = $this->logViewer->tail($filepath, $lines);
            $entries = array_map(fn ($line) => $this->logViewer->parseLine($line), $rawLines);
        }

        return ApiResponse::success([
            'entries' => $entries,
            'total_returned' => count($entries),
            'file' => $filename,
            'filter' => $level,
        ], 'Log entries retrieved');
    }
}
