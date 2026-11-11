<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_groups', function (Blueprint $table) {
            $table->string('video_type', 20)->nullable()->after('table_data');
            $table->text('video_url')->nullable()->after('video_type');
            $table->string('video_path')->nullable()->after('video_url');
            $table->string('video_name')->nullable()->after('video_path');
            $table->string('video_caption')->nullable()->after('video_name');
        });
    }

    public function down(): void
    {
        Schema::table('question_groups', function (Blueprint $table) {
            $table->dropColumn([
                'video_type',
                'video_url',
                'video_path',
                'video_name',
                'video_caption',
            ]);
        });
    }
};
