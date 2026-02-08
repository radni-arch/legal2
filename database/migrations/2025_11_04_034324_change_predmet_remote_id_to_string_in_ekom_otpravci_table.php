<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ekom_otpravci', function (Blueprint $table) {
            // Change predmet_remote_id from bigint to string (EKOM API returns string IDs)
            // Use raw SQL for PostgreSQL USING clause
            DB::statement('ALTER TABLE ekom_otpravci ALTER COLUMN predmet_remote_id TYPE VARCHAR(255) USING predmet_remote_id::VARCHAR(255)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ekom_otpravci', function (Blueprint $table) {
            // Revert to bigint - use raw SQL with USING clause
            DB::statement('ALTER TABLE ekom_otpravci ALTER COLUMN predmet_remote_id TYPE BIGINT USING predmet_remote_id::BIGINT');
        });
    }
};
