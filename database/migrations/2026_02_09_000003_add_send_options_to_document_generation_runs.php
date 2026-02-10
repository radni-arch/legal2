<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SOT-005: Add canonical send option columns to document_generation_runs.
 *
 * Moves send_email, as_draft, to_email out of model_config JSON
 * into proper DB columns. Adds dispatch_status and dispatched_at
 * for dispatch lifecycle tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_generation_runs', function (Blueprint $table) {
            $table->boolean('send_email')->default(false)->after('docx_verified');
            $table->boolean('as_draft')->default(false)->after('send_email');
            $table->string('to_email')->nullable()->after('as_draft');
            $table->string('dispatch_status', 50)->nullable()->after('to_email');
            $table->timestamp('dispatched_at')->nullable()->after('dispatch_status');
            $table->text('dispatch_error')->nullable()->after('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('document_generation_runs', function (Blueprint $table) {
            $table->dropColumn([
                'send_email',
                'as_draft',
                'to_email',
                'dispatch_status',
                'dispatched_at',
                'dispatch_error',
            ]);
        });
    }
};
