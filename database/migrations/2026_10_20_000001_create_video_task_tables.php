<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('source_type', 12);
            $table->text('source_url');
            $table->string('file_path')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['classroom_id', 'is_published']);
        });
        Schema::create('video_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_lesson_id')->constrained('video_lessons')->cascadeOnDelete();
            $table->unsignedInteger('time_seconds');
            $table->text('question_text');
            $table->json('choices');
            $table->unsignedTinyInteger('correct_index');
            $table->text('explanation')->nullable();
            $table->timestamps();
            $table->unique(['video_lesson_id', 'time_seconds']);
        });
        Schema::create('video_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_lesson_id')->constrained('video_lessons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['video_lesson_id', 'user_id']);
        });
        Schema::create('video_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_attempt_id')->constrained('video_attempts')->cascadeOnDelete();
            $table->foreignId('video_checkpoint_id')->constrained('video_checkpoints')->cascadeOnDelete();
            $table->unsignedTinyInteger('selected_index');
            $table->boolean('is_correct');
            $table->timestamps();
            $table->unique(['video_attempt_id', 'video_checkpoint_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_answers');
        Schema::dropIfExists('video_attempts');
        Schema::dropIfExists('video_checkpoints');
        Schema::dropIfExists('video_lessons');
    }
};
