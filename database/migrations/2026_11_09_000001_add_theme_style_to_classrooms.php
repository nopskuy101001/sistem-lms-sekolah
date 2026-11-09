<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('theme_style', 20)->default('orb')->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('theme_style');
        });
    }
};
