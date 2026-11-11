<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('grade_level', 10)->nullable()->after('section');
            $table->string('academic_year', 9)->nullable()->index()->after('grade_level');
            $table->string('semester', 10)->nullable()->after('academic_year');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropIndex(['academic_year']);
            $table->dropColumn(['grade_level', 'academic_year', 'semester']);
        });
    }
};
