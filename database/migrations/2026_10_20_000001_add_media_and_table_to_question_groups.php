<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_groups', function (Blueprint $table) {
            $table->string('media_path')->nullable()->after('stimulus');
            $table->string('media_name')->nullable()->after('media_path');
            $table->string('media_alt')->nullable()->after('media_name');
            $table->json('table_data')->nullable()->after('media_alt');
        });
    }

    public function down(): void
    {
        Schema::table('question_groups', function (Blueprint $table) {
            $table->dropColumn(['media_path', 'media_name', 'media_alt', 'table_data']);
        });
    }
};
