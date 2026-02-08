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
        // A "search run" - one execution of the command
        Schema::create('sudska_praksa_searches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->comment('Case/search name from metadata');
            $table->string('keywords_file')->comment('Path to keywords JSON used');
            $table->string('courts')->default('vks,vps,vs,zs');
            $table->integer('total_queries')->default(0);
            $table->integer('ultra_count')->default(0)->comment('Queries with <=5 results');
            $table->integer('zlato_count')->default(0)->comment('Queries with 6-15 results');
            $table->integer('srebrno_count')->default(0)->comment('Queries with 16-50 results');
            $table->integer('bronca_count')->default(0)->comment('Queries with 51-150 results');
            $table->integer('error_count')->default(0);
            $table->json('metadata')->nullable()->comment('Full metadata from keywords JSON');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Individual query results within a search run
        Schema::create('sudska_praksa_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_id')
                ->constrained('sudska_praksa_searches')
                ->cascadeOnDelete();
            $table->string('category');
            $table->string('category_description')->nullable();
            $table->text('query')->comment('The AND-joined keyword query');
            $table->string('comment')->nullable();
            $table->integer('count')->default(-1)->comment('Number of results, -1 = error');
            $table->string('classification', 20)->default('unknown')
                ->comment('ultra|zlato|srebrno|bronca|bulk|error|empty');
            $table->text('url');
            $table->boolean('is_expanded')->default(false);
            $table->timestamp('fetched_at');
            $table->timestamps();

            // Index for fast lookups
            $table->index(['search_id', 'classification']);
            $table->index(['query', 'count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sudska_praksa_results');
        Schema::dropIfExists('sudska_praksa_searches');
    }
};
