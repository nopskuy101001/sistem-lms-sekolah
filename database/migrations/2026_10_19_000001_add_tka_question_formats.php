<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('stimulus');
            $table->timestamps();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('question_group_id')->nullable()->after('quiz_id')->constrained()->nullOnDelete();
            $table->json('correct_options')->nullable()->after('correct_option');
            $table->json('category_items')->nullable()->after('correct_options');
            $table->string('competency')->nullable()->after('category_items');
            $table->string('subcompetency')->nullable()->after('competency');
            $table->string('cognitive_level', 30)->nullable()->after('subcompetency');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('question_group_id');
            $table->dropColumn(['correct_options', 'category_items', 'competency', 'subcompetency', 'cognitive_level']);
        });

        Schema::dropIfExists('question_groups');
    }
};
