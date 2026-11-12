<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DiscussionUnreadService
{
    /** @param iterable<int, int|string> $roomIds @return Collection<int, int> */
    public function countsForRooms(User $user, iterable $roomIds): Collection
    {
        if (! $user->isGuru()) {
            return collect();
        }

        $ids = collect($roomIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('discussion_messages as message')
            ->join('discussion_room_user as membership', function ($join) use ($user): void {
                $join->on('membership.discussion_room_id', '=', 'message.discussion_room_id')
                    ->where('membership.user_id', '=', $user->id);
            })
            ->whereIn('message.discussion_room_id', $ids)
            ->where('message.user_id', '!=', $user->id)
            ->where(function ($query): void {
                $query->whereNull('membership.last_read_message_id')
                    ->orWhereColumn('message.id', '>', 'membership.last_read_message_id');
            })
            ->groupBy('message.discussion_room_id')
            ->selectRaw('message.discussion_room_id, COUNT(*) as unread_count')
            ->pluck('unread_count', 'discussion_room_id')
            ->map(fn ($count) => (int) $count);
    }

    public function totalForUser(User $user): int
    {
        if (! $user->isGuru()) {
            return 0;
        }

        return (int) $this->countsForRooms(
            $user,
            DB::table('discussion_room_user')->where('user_id', $user->id)->pluck('discussion_room_id'),
        )->sum();
    }

    public function markRoomRead(User $user, int $roomId): void
    {
        if (! $user->isGuru()) {
            return;
        }

        $lastMessageId = DB::table('discussion_messages')
            ->where('discussion_room_id', $roomId)
            ->max('id');
        DB::table('discussion_room_user')
            ->where('discussion_room_id', $roomId)
            ->where('user_id', $user->id)
            ->update([
                'last_read_message_id' => $lastMessageId,
                'last_read_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
