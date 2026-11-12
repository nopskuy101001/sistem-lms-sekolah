<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discussion_rooms', function (Blueprint $table) {
            $table->string('theme_color', 20)->default('indigo')->after('description');
            $table->string('theme_style', 20)->default('aurora')->after('theme_color');
        });
        Schema::table('discussion_room_user', function (Blueprint $table) {
            $table->unsignedBigInteger('last_read_message_id')->nullable()->after('user_id')->index();
            $table->timestamp('last_read_at')->nullable()->after('last_read_message_id');
        });

        DB::table('discussion_messages')
            ->selectRaw('discussion_room_id, MAX(id) as last_message_id')
            ->groupBy('discussion_room_id')
            ->get()
            ->each(function ($row): void {
                DB::table('discussion_room_user')
                    ->where('discussion_room_id', $row->discussion_room_id)
                    ->update([
                        'last_read_message_id' => $row->last_message_id,
                        'last_read_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('discussion_room_user', function (Blueprint $table) {
            $table->dropIndex(['last_read_message_id']);
            $table->dropColumn(['last_read_message_id', 'last_read_at']);
        });
        Schema::table('discussion_rooms', function (Blueprint $table) {
            $table->dropColumn(['theme_color', 'theme_style']);
        });
    }
};
