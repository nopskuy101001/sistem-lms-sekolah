<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('shuffle_questions')->default(true)->after('require_fullscreen');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->json('question_order')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('question_order');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('shuffle_questions');
        });
    }
};
