<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('email');
        });

        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nip_original', 50)->unique();
            $table->string('source_sequence', 30)->nullable();
            $table->unsignedSmallInteger('service_years')->nullable();
            $table->unsignedTinyInteger('service_months')->nullable();
            $table->text('employment_mutation_notes')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_rank_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->string('rank_grade', 50)->nullable();
            $table->date('effective_date')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });

        Schema::create('teacher_position_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->string('position_name', 150)->nullable();
            $table->date('effective_date')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });

        Schema::create('teacher_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->string('training_name', 150)->nullable();
            $table->unsignedTinyInteger('training_month')->nullable();
            $table->unsignedSmallInteger('training_year')->nullable();
            $table->unsignedSmallInteger('duration_hours')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->string('education_field', 150)->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->string('degree_level', 50)->nullable();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_educations');
        Schema::dropIfExists('teacher_trainings');
        Schema::dropIfExists('teacher_position_histories');
        Schema::dropIfExists('teacher_rank_histories');
        Schema::dropIfExists('teacher_profiles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
