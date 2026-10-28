<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // admin | guru | siswa
            $table->string('role')->default('siswa')->after('email');
            // NIP untuk guru / NIS untuk siswa
            $table->string('nomor_induk')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'nomor_induk']);
        });
    }
};
