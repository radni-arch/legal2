<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop foreign key first
        Schema::table('eoglasna_keyword_matches', function (Blueprint $table) {
            $table->dropForeign(['notice_uuid']);
        });

        // Change notice_uuid in eoglasna_keyword_matches to string
        Schema::table('eoglasna_keyword_matches', function (Blueprint $table) {
            $table->dropColumn('notice_uuid');
        });

        Schema::table('eoglasna_keyword_matches', function (Blueprint $table) {
            $table->string('notice_uuid')->nullable()->after('keyword_id');
        });

        // Change uuid in eoglasna_notices to string
        Schema::table('eoglasna_notices', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });

        Schema::table('eoglasna_notices', function (Blueprint $table) {
            $table->string('uuid')->nullable()->unique()->after('id');
        });

        // Recreate foreign key
        Schema::table('eoglasna_keyword_matches', function (Blueprint $table) {
            $table->foreign('notice_uuid')
                ->references('uuid')
                ->on('eoglasna_notices')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key first
        Schema::table('eoglasna_keyword_matches', function (Blueprint $table) {
            $table->dropForeign(['notice_uuid']);
        });

        // Revert notice_uuid in eoglasna_keyword_matches to UUID
        Schema::table('eoglasna_keyword_matches', function (Blueprint $table) {
            $table->dropColumn('notice_uuid');
        });

        Schema::table('eoglasna_keyword_matches', function (Blueprint $table) {
            $table->uuid('notice_uuid')->after('keyword_id');
        });

        // Revert uuid in eoglasna_notices to UUID
        Schema::table('eoglasna_notices', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });

        Schema::table('eoglasna_notices', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
        });

        // Recreate foreign key
        Schema::table('eoglasna_keyword_matches', function (Blueprint $table) {
            $table->foreign('notice_uuid')
                ->references('uuid')
                ->on('eoglasna_notices')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }
};
