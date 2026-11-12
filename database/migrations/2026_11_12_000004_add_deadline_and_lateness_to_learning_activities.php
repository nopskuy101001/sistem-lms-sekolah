<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_lessons', function (Blueprint $table) {
            $table->dateTime('due_at')->nullable()->after('description');
        });

        Schema::table('video_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('late_seconds')->nullable()->after('completed_at');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('late_seconds')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('late_seconds');
        });

        Schema::table('video_attempts', function (Blueprint $table) {
            $table->dropColumn('late_seconds');
        });

        Schema::table('video_lessons', function (Blueprint $table) {
            $table->dropColumn('due_at');
        });
    }
};
