<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add validation and resolved initials columns
     */
    public function up(): void
    {
        // Add validation columns to court_cases
        Schema::table('court_cases', function (Blueprint $table) {
            // Warrant validation results
            $table->boolean('is_confirmed_warrant')->nullable()->after('is_search_warrant');
            $table->string('warrant_confidence', 200)->nullable()->after('is_confirmed_warrant'); // high, medium, low, unlikely
            $table->integer('warrant_validation_score')->nullable()->after('warrant_confidence');
            $table->json('warrant_validation_reasons')->nullable()->after('warrant_validation_score');

            // Warrant type classification
            $table->string('warrant_type', 500)->nullable()->after('warrant_validation_reasons'); // home_search, vehicle_search, business_search, person_search, unclassified

            // Validation metadata
            $table->timestamp('validated_at')->nullable()->after('warrant_type');

            // Index for filtering
            $table->index(['is_confirmed_warrant', 'warrant_confidence']);
            $table->index('warrant_type');
        });

        // Add resolved name columns to case_parties
        Schema::table('case_parties', function (Blueprint $table) {
            // Resolved institution name
            $table->string('resolved_name', 500)->nullable()->after('name');
            $table->string('institution_type', 50)->nullable()->after('resolved_name'); // police_station, police_headquarters, organized_crime_unit, etc.
            $table->string('resolution_confidence', 20)->nullable()->after('institution_type'); // high, medium, low
            $table->boolean('is_institution')->nullable()->after('resolution_confidence');

            // Index
            $table->index('institution_type');
            $table->index('is_institution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('court_cases', function (Blueprint $table) {
            $table->dropIndex(['is_confirmed_warrant', 'warrant_confidence']);
            $table->dropIndex(['warrant_type']);

            $table->dropColumn([
                'is_confirmed_warrant',
                'warrant_confidence',
                'warrant_validation_score',
                'warrant_validation_reasons',
                'warrant_type',
                'validated_at',
            ]);
        });

        Schema::table('case_parties', function (Blueprint $table) {
            $table->dropIndex(['institution_type']);
            $table->dropIndex(['is_institution']);

            $table->dropColumn([
                'resolved_name',
                'institution_type',
                'resolution_confidence',
                'is_institution',
            ]);
        });
    }
};
