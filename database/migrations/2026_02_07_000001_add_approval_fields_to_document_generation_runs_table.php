<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_generation_runs', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('user_id');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            $table->text('approval_notes')->nullable()->after('approved_by');
            $table->boolean('docx_verified')->default(false)->after('approval_notes');

            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('document_generation_runs', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_at', 'approved_by', 'approval_notes', 'docx_verified']);
        });
    }
};
