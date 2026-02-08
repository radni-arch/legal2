<?php

declare(strict_types=1);

namespace App\Http\Controllers\Textract;

use App\Http\Controllers\Controller;
use App\Models\TextractDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class TextractFileController extends Controller
{
    /**
     * Serve a TextractDocument PDF file via signed URL.
     *
     * @param  TextractDocument  $document  The document to serve
     */
    public function show(TextractDocument $document): Response
    {
        // Verify document has S3 output path
        if (empty($document->s3_output_path)) {
            abort(404, 'Document file not found');
        }

        // Verify file exists in S3
        if (! Storage::disk('s3')->exists($document->s3_output_path)) {
            abort(404, 'Document file not found in storage');
        }

        // Get file content from S3
        $content = Storage::disk('s3')->get($document->s3_output_path);

        // Return PDF response with appropriate headers
        return response($content, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$document->id.'.pdf"');
    }
}
