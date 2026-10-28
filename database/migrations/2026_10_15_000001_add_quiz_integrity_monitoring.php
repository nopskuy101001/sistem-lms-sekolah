<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('monitoring_enabled')->default(true)->after('is_published');
            $table->boolean('require_fullscreen')->default(false)->after('monitoring_enabled');
        });

        Schema::create('quiz_security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['quiz_attempt_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_security_events');

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['monitoring_enabled', 'require_fullscreen']);
        });
    }
};
