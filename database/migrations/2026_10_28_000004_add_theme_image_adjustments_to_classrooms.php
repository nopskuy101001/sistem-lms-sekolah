<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('theme_zoom')->default(100);
            $table->unsignedTinyInteger('theme_position_x')->default(50);
            $table->unsignedTinyInteger('theme_position_y')->default(50);
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn(['theme_zoom', 'theme_position_x', 'theme_position_y']);
        });
    }
};
