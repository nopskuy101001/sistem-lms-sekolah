<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('penalty_enabled')->default(false)->after('require_fullscreen');
            $table->unsignedTinyInteger('penalty_percent_per_violation')->default(2)->after('penalty_enabled');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->decimal('raw_score', 8, 2)->default(0)->after('score');
            $table->decimal('penalty_score', 8, 2)->default(0)->after('raw_score');
        });

        // Hasil lama tidak memiliki penalti; pertahankan nilai sebelumnya sebagai nilai mentah.
        DB::table('quiz_attempts')->update(['raw_score' => DB::raw('score')]);
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn(['raw_score', 'penalty_score']);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['penalty_enabled', 'penalty_percent_per_violation']);
        });
    }
};
