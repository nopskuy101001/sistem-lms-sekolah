<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('subject', 100)->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('discussion_room_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['discussion_room_id', 'user_id']);
        });

        Schema::create('discussion_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->timestamps();
            $table->index(['discussion_room_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_messages');
        Schema::dropIfExists('discussion_room_user');
        Schema::dropIfExists('discussion_rooms');
    }
};
