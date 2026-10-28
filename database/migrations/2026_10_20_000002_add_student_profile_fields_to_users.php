<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('nomor_induk');
            $table->string('gender', 1)->nullable()->after('date_of_birth');
            $table->string('phone', 30)->nullable()->after('gender');
            $table->text('address')->nullable()->after('phone');
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->unique('nomor_induk');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nomor_induk']);
            $table->dropColumn(['date_of_birth', 'gender', 'phone', 'address', 'must_change_password']);
        });
    }
};
