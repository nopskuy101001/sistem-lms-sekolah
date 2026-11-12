<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\DiscussionRoom;
use App\Models\User;
use App\Notifications\AcademicNotification;
use App\Services\DiscussionUnreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DiscussionRoomController extends Controller
{
    public function __construct(private readonly DiscussionUnreadService $unread) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $rooms = DiscussionRoom::query()
            ->with('creator')
            ->withCount(['members', 'messages'])
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas(
                'members', fn ($members) => $members->whereKey($user->id)
            ))
            ->latest('updated_at')
            ->get();
        $unreadCounts = $this->unread->countsForRooms($user, $rooms->pluck('id'));
        $rooms->each(fn (DiscussionRoom $room) => $room->setAttribute(
            'unread_count', (int) $unreadCounts->get($room->id, 0)
        ));
        $unreadTotal = (int) $unreadCounts->sum();

        $subjects = $this->subjectsFor($user);
        $teachers = $this->teacherCandidates($user);

        return view('discussion-rooms.index', compact('rooms', 'subjects', 'teachers', 'unreadTotal'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'],
            'theme_color' => ['nullable', Rule::in(array_keys(DiscussionRoom::THEME_COLORS))],
            'theme_style' => ['nullable', Rule::in(array_keys(DiscussionRoom::THEME_STYLES))],
            'member_ids' => ['required', 'array', 'min:1', 'max:100'],
            'member_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('role', 'guru'),
            ],
        ]);

        $subject = $this->canonicalSubject($request->user(), $data['subject']);
        $members = $this->eligibleMembers($subject, $data['member_ids'], $request->user());

        if ($members->count() !== count(array_unique($data['member_ids']))) {
            throw ValidationException::withMessages([
                'member_ids' => 'Semua anggota harus merupakan guru yang mengampu mata pelajaran '.$subject.'.',
            ]);
        }

        $room = DB::transaction(function () use ($request, $data, $subject, $members) {
            $room = DiscussionRoom::create([
                'creator_id' => $request->user()->id,
                'name' => trim($data['name']),
                'subject' => $subject,
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'theme_color' => $data['theme_color'] ?? 'indigo',
                'theme_style' => $data['theme_style'] ?? 'aurora',
            ]);

            $memberIds = $members->pluck('id');
            if ($request->user()->isGuru()) {
                $memberIds->push($request->user()->id);
            }
            $room->members()->syncWithPivotValues($memberIds->unique()->all(), [
                'last_read_message_id' => null,
                'last_read_at' => now(),
            ]);

            return $room;
        });

        $members->where('id', '!=', $request->user()->id)->each(fn (User $member) => $member->notify(
            new AcademicNotification(
                'Undangan ruang diskusi',
                $request->user()->name.' mengundang Anda ke ruang '.$room->name.'.',
                route('discussion-rooms.show', $room, false),
                'discussion',
            )
        ));

        return redirect()->route('discussion-rooms.show', $room)
            ->with('success', 'Ruang diskusi berhasil dibuat.');
    }

    public function show(Request $request, DiscussionRoom $discussionRoom): View
    {
        $this->authorizeAccess($request, $discussionRoom);
        $unreadOnOpen = (int) $this->unread
            ->countsForRooms($request->user(), [$discussionRoom->id])
            ->get($discussionRoom->id, 0);
        $this->unread->markRoomRead($request->user(), $discussionRoom->id);
        $discussionRoom->load(['creator', 'members' => fn ($query) => $query->orderBy('name')]);
        $messages = $discussionRoom->messages()->with('user')->latest()->limit(100)->get()->reverse()->values();
        $canManage = $discussionRoom->canManage($request->user());
        $teachers = $canManage
            ? $this->teacherCandidates($request->user(), $discussionRoom->subject)
            : collect();

        return view('discussion-rooms.show', compact(
            'discussionRoom', 'messages', 'canManage', 'teachers', 'unreadOnOpen'
        ));
    }

    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();
        $roomIds = $user->isGuru()
            ? $user->discussionRooms()->pluck('discussion_rooms.id')
            : collect();
        $counts = $this->unread->countsForRooms($user, $roomIds);

        return response()->json([
            'total' => (int) $counts->sum(),
            'rooms' => $counts->all(),
        ]);
    }

    public function updateTheme(Request $request, DiscussionRoom $discussionRoom): RedirectResponse
    {
        $this->authorizeManage($request, $discussionRoom);
        $data = $request->validate([
            'theme_color' => ['required', Rule::in(array_keys(DiscussionRoom::THEME_COLORS))],
            'theme_style' => ['required', Rule::in(array_keys(DiscussionRoom::THEME_STYLES))],
        ]);
        $discussionRoom->update($data);

        return back()->with('success', 'Tema ruang diskusi diperbarui.');
    }

    public function updateMembers(Request $request, DiscussionRoom $discussionRoom): RedirectResponse
    {
        $this->authorizeManage($request, $discussionRoom);
        $data = $request->validate([
            'member_ids' => ['required', 'array', 'min:1', 'max:100'],
            'member_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('role', 'guru'),
            ],
        ]);

        $members = $this->eligibleMembers($discussionRoom->subject, $data['member_ids'], $request->user());
        if ($members->count() !== count(array_unique($data['member_ids']))) {
            throw ValidationException::withMessages([
                'member_ids' => 'Anggota harus merupakan guru yang mengampu '.$discussionRoom->subject.'.',
            ]);
        }

        $oldIds = $discussionRoom->members()->pluck('users.id');
        $memberIds = $members->pluck('id');
        if ($discussionRoom->creator->isGuru()) {
            $memberIds->push($discussionRoom->creator_id);
        }
        $memberIds = $memberIds->unique()->values();
        $discussionRoom->members()->sync($memberIds->all());
        $lastMessageId = $discussionRoom->messages()->max('id');
        $memberIds->diff($oldIds)->each(fn (int $memberId) => $discussionRoom->members()->updateExistingPivot($memberId, [
            'last_read_message_id' => $lastMessageId,
            'last_read_at' => now(),
        ]));

        $members->whereIn('id', $memberIds->diff($oldIds)->all())->each(fn (User $member) => $member->notify(
            new AcademicNotification(
                'Ditambahkan ke ruang diskusi',
                'Anda ditambahkan ke ruang '.$discussionRoom->name.'.',
                route('discussion-rooms.show', $discussionRoom, false),
                'discussion',
            )
        ));

        return back()->with('success', 'Daftar anggota diperbarui.');
    }

    public function destroy(Request $request, DiscussionRoom $discussionRoom): RedirectResponse
    {
        $this->authorizeManage($request, $discussionRoom);
        $paths = $discussionRoom->messages()->whereNotNull('attachment_path')->pluck('attachment_path')->all();
        $discussionRoom->delete();
        Storage::disk('local')->delete($paths);

        return redirect()->route('discussion-rooms.index')->with('success', 'Ruang diskusi dihapus.');
    }

    private function authorizeAccess(Request $request, DiscussionRoom $room): void
    {
        abort_unless($room->canAccess($request->user()), 403);
    }

    private function authorizeManage(Request $request, DiscussionRoom $room): void
    {
        abort_unless($room->canManage($request->user()), 403);
    }

    /** @return Collection<int, string> */
    private function subjectsFor(User $user): Collection
    {
        return Classroom::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('teacher_id', $user->id))
            ->whereNotNull('subject')
            ->where('subject', '!=', '')
            ->pluck('subject')
            ->map(fn ($subject) => trim((string) $subject))
            ->unique(fn ($subject) => mb_strtolower($subject))
            ->sort()
            ->values();
    }

    private function canonicalSubject(User $user, string $requested): string
    {
        $subject = $this->subjectsFor($user)->first(
            fn ($subject) => mb_strtolower($subject) === mb_strtolower(trim($requested))
        );

        if (! $subject) {
            throw ValidationException::withMessages([
                'subject' => 'Pilih mata pelajaran yang sudah tercatat pada kelas yang diampu.',
            ]);
        }

        return $subject;
    }

    /** @param array<int, int|string> $memberIds */
    private function eligibleMembers(string $subject, array $memberIds, User $actor): Collection
    {
        return $this->teacherCandidates($actor, $subject)
            ->whereIn('id', collect($memberIds)->map(fn ($id) => (int) $id))
            ->values();
    }

    /** @return Collection<int, User> */
    private function teacherCandidates(User $actor, ?string $subject = null): Collection
    {
        return User::query()
            ->where('role', 'guru')
            ->when($actor->isGuru(), fn ($query) => $query->whereKeyNot($actor->id))
            ->with(['teachingClassrooms:id,teacher_id,subject'])
            ->orderBy('name')
            ->get()
            ->map(function (User $teacher) {
                $teacher->setAttribute('discussion_subjects', $teacher->teachingClassrooms
                    ->pluck('subject')->filter()->map(fn ($item) => trim((string) $item))
                    ->unique(fn ($item) => mb_strtolower($item))->sort()->values());

                return $teacher;
            })
            ->when($subject, fn (Collection $teachers) => $teachers->filter(
                fn (User $teacher) => $teacher->discussion_subjects->contains(
                    fn ($item) => mb_strtolower($item) === mb_strtolower($subject)
                )
            ))
            ->values();
    }
}
