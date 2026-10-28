<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_lessons', function (Blueprint $table) {
            $table->dropColumn('allow_playback_speed');
            $table->unsignedTinyInteger('skip_seconds')->default(10);
        });
    }

    public function down(): void
    {
        Schema::table('video_lessons', function (Blueprint $table) {
            $table->dropColumn('skip_seconds');
            $table->boolean('allow_playback_speed')->default(true);
        });
    }
};
