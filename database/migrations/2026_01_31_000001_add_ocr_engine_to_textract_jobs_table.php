<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textract_jobs', function (Blueprint $table) {
            $table->string('ocr_engine', 20)->default('textract')->after('status')
                ->comment('OCR engine used: textract, tesseract, or hybrid');
            $table->json('ocr_routing_metadata')->nullable()->after('performance_metrics')
                ->comment('OCR routing decision data: engine comparison, scores, reasons');
        });
    }

    public function down(): void
    {
        Schema::table('textract_jobs', function (Blueprint $table) {
            $table->dropColumn(['ocr_engine', 'ocr_routing_metadata']);
        });
    }
};
