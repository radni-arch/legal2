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
        if (!Schema::hasColumn('cases', 'user_id')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->index('user_id');
            });
        }

        if (!Schema::hasTable('case_user')) {
            Schema::create('case_user', function (Blueprint $table) {
                $table->id();
                $table->string('case_id'); // ULID string from cases table
                $table->unsignedBigInteger('user_id');
                $table->timestamps();

                // Foreign keys
                $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

                // Ensure unique combinations
                $table->unique(['case_id', 'user_id']);
            });

            $user = \App\Models\User::where('email', 'admin@example.com')->first();
            $legalCases = \App\Models\LegalCase::query()->get();
            if ($user) {
                DB::table('case_user')->insert(
                    $legalCases->map(function ($case) use ($user) {
                        return [
                            'case_id' => $case->id,
                            'user_id' => $user->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })->toArray()
                );

                \App\Models\LegalCase::query()->update(['user_id' => $user->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
