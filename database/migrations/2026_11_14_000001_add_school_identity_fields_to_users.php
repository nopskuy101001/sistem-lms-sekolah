<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nipd', 50)->nullable()->unique()->after('nomor_induk');
            $table->string('nisn', 20)->nullable()->unique()->after('nipd');
            $table->string('birth_place', 100)->nullable()->after('nisn');
            $table->text('nik')->nullable()->after('birth_place');
            $table->string('current_rombel', 100)->nullable()->after('nik');
            $table->string('source_number', 30)->nullable()->after('current_rombel');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nipd']);
            $table->dropUnique(['nisn']);
            $table->dropColumn([
                'nipd',
                'nisn',
                'birth_place',
                'nik',
                'current_rombel',
                'source_number',
            ]);
        });
    }
};
