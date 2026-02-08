<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Courts (Sudovi)
        Schema::create('ekom_courts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_id')->unique();
            $table->string('naziv');
            $table->string('oznaka')->nullable();
            $table->unsignedBigInteger('vrsta_suda_id')->nullable();
            $table->string('vrsta_suda_naziv')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Procedure Types (Vrste Postupaka)
        Schema::create('ekom_procedure_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_id')->unique();
            $table->unsignedBigInteger('court_remote_id')->nullable();
            $table->string('naziv');
            $table->string('oznaka')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('court_remote_id');
        });

        // Submission Types (Vrste Podnesaka)
        Schema::create('ekom_submission_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_id');
            $table->unsignedBigInteger('procedure_type_remote_id');
            $table->string('naziv');
            $table->string('oznaka')->nullable();
            $table->string('context'); // 'novi_postupak' or 'postojeci_predmet'
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['remote_id', 'context']);
            $table->index('procedure_type_remote_id');
        });

        // Participant Roles (Uloge Sudionika/Podnositelja)
        Schema::create('ekom_participant_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_id');
            $table->unsignedBigInteger('procedure_type_remote_id');
            $table->string('naziv');
            $table->string('type'); // 'sudionik' or 'podnositelj'
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['remote_id', 'type']);
            $table->index('procedure_type_remote_id');
        });

        // Fee Options (Pristojba Dodatne Opcije)
        Schema::create('ekom_fee_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_id');
            $table->unsignedBigInteger('procedure_type_remote_id');
            $table->unsignedBigInteger('submission_type_remote_id');
            $table->string('naziv');
            $table->string('context'); // 'novi_postupak' or 'postojeci_predmet'
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['remote_id', 'context']);
            $table->index(['procedure_type_remote_id', 'submission_type_remote_id']);
        });

        // Non-Payment Reasons (Razlozi Neplacanja Pristojbe)
        Schema::create('ekom_non_payment_reasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_id')->unique();
            $table->string('naziv');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Fee Exemptions (Osnove Oslobodjenja Pristojbe)
        Schema::create('ekom_fee_exemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_id')->unique();
            $table->string('naziv');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Settlements (Naselja)
        Schema::create('ekom_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_id')->unique();
            $table->string('naziv');
            $table->string('postanski_broj')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Countries (Države)
        Schema::create('ekom_countries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remote_id')->unique();
            $table->string('naziv');
            $table->string('oznaka')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekom_countries');
        Schema::dropIfExists('ekom_settlements');
        Schema::dropIfExists('ekom_fee_exemptions');
        Schema::dropIfExists('ekom_non_payment_reasons');
        Schema::dropIfExists('ekom_fee_options');
        Schema::dropIfExists('ekom_participant_roles');
        Schema::dropIfExists('ekom_submission_types');
        Schema::dropIfExists('ekom_procedure_types');
        Schema::dropIfExists('ekom_courts');
    }
};
