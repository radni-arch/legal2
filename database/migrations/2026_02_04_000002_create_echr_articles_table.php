<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('echr_articles', function (Blueprint $table) {
            $table->id();
            $table->string('article_code', 20)->unique();
            $table->string('article_name', 200);
            $table->text('description')->nullable();
            $table->string('protocol', 50)->nullable()->comment('P1, P4, P6, P7, P12, P13, P16');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed with core articles
        DB::table('echr_articles')->insert([
            ['article_code' => '2', 'article_name' => 'Right to life', 'description' => null, 'protocol' => null, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['article_code' => '3', 'article_name' => 'Prohibition of torture', 'description' => null, 'protocol' => null, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['article_code' => '5', 'article_name' => 'Right to liberty and security', 'description' => null, 'protocol' => null, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['article_code' => '6', 'article_name' => 'Right to a fair trial', 'description' => null, 'protocol' => null, 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['article_code' => '8', 'article_name' => 'Right to respect for private and family life', 'description' => null, 'protocol' => null, 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['article_code' => '10', 'article_name' => 'Freedom of expression', 'description' => null, 'protocol' => null, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['article_code' => '13', 'article_name' => 'Right to an effective remedy', 'description' => null, 'protocol' => null, 'sort_order' => 13, 'created_at' => now(), 'updated_at' => now()],
            ['article_code' => '14', 'article_name' => 'Prohibition of discrimination', 'description' => null, 'protocol' => null, 'sort_order' => 14, 'created_at' => now(), 'updated_at' => now()],
            ['article_code' => 'P1-1', 'article_name' => 'Protection of property', 'description' => null, 'protocol' => 'P1', 'sort_order' => 101, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('echr_articles');
    }
};
