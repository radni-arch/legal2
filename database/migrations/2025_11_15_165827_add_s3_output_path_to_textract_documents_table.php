<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textract_documents', function (Blueprint $table) {
            $table->string('s3_output_path')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('textract_documents', function (Blueprint $table) {
            $table->dropColumn('s3_output_path');
        });
    }
};
