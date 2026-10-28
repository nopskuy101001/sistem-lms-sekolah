<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClassroom;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\User;
use App\Models\VideoAttempt;
use App\Models\VideoLesson;
use App\Services\TranscriptReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    use AuthorizesClassroom;

    /** Dashboard: daftar kelas milik user. */
    public function index(): View
    {
        $user = auth()->user();

        $withDashboardData = fn ($query) => $query
            ->with('teacher')
            ->withCount(['students', 'assignments', 'materials', 'quizzes', 'videoLessons']);

        if ($user->isAdmin()) {
            $classrooms = $withDashboardData(Classroom::query())->latest()->get();
        } elseif ($user->isGuru()) {
            $classrooms = $withDashboardData($user->teachingClassrooms())->latest()->get();
        } else {
            $classrooms = $withDashboardData($user->joinedClassrooms())->latest('classrooms.created_at')->get();
        }

        $classroomIds = $classrooms->pluck('id');
        $submittedAssignmentIds = Submission::where('user_id', $user->id)->pluck('assignment_id');

        $upcomingAssignments = Assignment::query()
            ->with('classroom')
            ->whereIn('classroom_id', $classroomIds)
            ->whereNotNull('due_at')
            ->where('due_at', '>=', now())
            ->when($user->isSiswa(), fn ($query) => $query->whereNotIn('id', $submittedAssignmentIds))
            ->orderBy('due_at')
            ->limit(5)
            ->get();

        $totalAssignments = (int) $classrooms->sum('assignments_count');
        $totalMaterials = (int) $classrooms->sum('materials_count');
        $totalVideoLessons = $user->isSiswa()
            ? VideoLesson::whereIn('classroom_id', $classroomIds)->where('is_published', true)->count()
            : (int) $classrooms->sum('video_lessons_count');
        $totalQuizzes = $user->isSiswa()
            ? Quiz::whereIn('classroom_id', $classroomIds)->where('is_published', true)->count()
            : (int) $classrooms->sum('quizzes_count');

        if ($user->isSiswa()) {
            $completedAssignments = Submission::where('user_id', $user->id)
                ->whereIn('assignment_id', Assignment::whereIn('classroom_id', $classroomIds)->select('id'))
                ->count();
            $completedQuizzes = QuizAttempt::where('user_id', $user->id)
                ->whereNotNull('finished_at')
                ->whereIn('quiz_id', Quiz::whereIn('classroom_id', $classroomIds)->where('is_published', true)->select('id'))
                ->count();
            $completedVideos = VideoAttempt::where('user_id', $user->id)
                ->whereNotNull('completed_at')
                ->whereIn('video_lesson_id', VideoLesson::whereIn('classroom_id', $classroomIds)->where('is_published', true)->select('id'))
                ->count();
            $totalActivities = $totalAssignments + $totalQuizzes + $totalVideoLessons;
            $completedActivities = $completedAssignments + $completedQuizzes + $completedVideos;
            $progress = $totalActivities > 0 ? (int) round(($completedActivities / $totalActivities) * 100) : 0;

            $dashboardStats = [
                ['value' => $classrooms->count(), 'label' => 'Kelas aktif'],
                ['value' => max($totalActivities - $completedActivities, 0), 'label' => 'Aktivitas tersisa'],
                ['value' => $completedActivities, 'label' => 'Sudah diselesaikan'],
                ['value' => $progress.'%', 'label' => 'Progres belajar'],
            ];
        } else {
            $pendingReviews = Submission::whereNull('score')
                ->whereIn('assignment_id', Assignment::whereIn('classroom_id', $classroomIds)->select('id'))
                ->count();

            $dashboardStats = [
                ['value' => $classrooms->count(), 'label' => 'Kelas dikelola'],
                ['value' => (int) $classrooms->sum('students_count'), 'label' => 'Keikutsertaan siswa'],
                ['value' => $pendingReviews, 'label' => 'Tugas perlu dinilai'],
                ['value' => $totalMaterials + $totalAssignments + $totalQuizzes + $totalVideoLessons, 'label' => 'Konten pembelajaran'],
            ];
        }

        return view('classrooms.dashboard', compact(
            'classrooms', 'upcomingAssignments', 'dashboardStats'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'section' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:100'],
            'room' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', Rule::in(Classroom::COLORS)],
        ]);

        $classroom = Classroom::create($data + ['teacher_id' => $request->user()->id]);

        return redirect()->route('classrooms.show', $classroom)->with('success', 'Kelas berhasil dibuat. Bagikan kode kelas ke siswa.');
    }

    /** Tab Stream: pengumuman kelas. */
    public function show(Classroom $classroom): View
    {
        $this->authorizeAccess($classroom);

        $classroom->load('teacher');
        $announcements = $classroom->announcements()->with('user')->latest()->get();

        $upcoming = collect();
        if (auth()->user()->isSiswa()) {
            $submittedIds = Submission::where('user_id', auth()->id())->pluck('assignment_id');
            $upcoming = $classroom->assignments()
                ->whereNotIn('id', $submittedIds)
                ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now()))
                ->orderByRaw('due_at is null, due_at asc')
                ->limit(5)
                ->get();
        }

        return view('classrooms.show-modern', compact('classroom', 'announcements', 'upcoming'));
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeManage($classroom);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'section' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:100'],
            'room' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', Rule::in(Classroom::COLORS)],
        ]);

        $classroom->update($data);

        return back()->with('success', 'Kelas diperbarui.');
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $this->authorizeManage($classroom);

        $classroom->delete();

        return redirect()->route('dashboard')->with('success', 'Kelas dihapus.');
    }

    /** Batalkan kode lama dan buat kode bergabung yang baru. */
    public function refreshCode(Classroom $classroom): RedirectResponse
    {
        $this->authorizeManage($classroom);

        $classroom->regenerateCode();

        return back()->with('success', 'Kode kelas berhasil diperbarui. Kode lama sudah tidak berlaku.');
    }

    /** Siswa bergabung dengan kode kelas. */
    public function join(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSiswa(), 403, 'Hanya siswa yang bisa bergabung ke kelas.');

        $request->validate(['code' => ['required', 'string', 'max:10']]);

        $classroom = Classroom::where('code', strtoupper(trim($request->code)))->first();

        if (! $classroom) {
            return back()->withErrors(['code' => 'Kode kelas tidak ditemukan.'])->withInput();
        }

        if ($classroom->hasStudent($request->user())) {
            return redirect()->route('classrooms.show', $classroom)->with('success', 'Kamu sudah tergabung di kelas ini.');
        }

        $classroom->students()->attach($request->user()->id);

        return redirect()->route('classrooms.show', $classroom)->with('success', 'Berhasil bergabung ke kelas '.$classroom->name.'.');
    }

    public function leave(Classroom $classroom): RedirectResponse
    {
        abort_unless(auth()->user()->isSiswa(), 403);

        $classroom->students()->detach(auth()->id());

        return redirect()->route('dashboard')->with('success', 'Kamu keluar dari kelas.');
    }

    /** Tab Tugas & Materi. */
    public function classwork(Classroom $classroom): View
    {
        $this->authorizeAccess($classroom);

        $user = auth()->user();
        $isManager = $classroom->canManage($user);

        $assignments = $classroom->assignments()->latest()->get();
        $materials = $classroom->materials()->latest()->get();
        $quizzes = $classroom->quizzes()
            ->when(! $isManager, fn ($q) => $q->where('is_published', true))
            ->withCount('questions')
            ->latest()
            ->get();

        $videoLessons = $classroom->videoLessons()
            ->when(! $isManager, fn ($query) => $query->where('is_published', true))
            ->withCount('checkpoints')->latest()->get();
        $videoAttempts = collect();
        $submissions = collect();
        $attempts = collect();
        if ($user->isSiswa()) {
            $submissions = Submission::where('user_id', $user->id)
                ->whereIn('assignment_id', $assignments->pluck('id'))
                ->get()->keyBy('assignment_id');
            $attempts = QuizAttempt::where('user_id', $user->id)
                ->whereIn('quiz_id', $quizzes->pluck('id'))
                ->get()->keyBy('quiz_id');
            $videoAttempts = VideoAttempt::where('user_id', $user->id)
                ->whereIn('video_lesson_id', $videoLessons->pluck('id'))
                ->get()->keyBy('video_lesson_id');
        }

        return view('classrooms.classwork', compact(
            'classroom', 'assignments', 'materials', 'quizzes', 'videoLessons', 'videoAttempts', 'submissions', 'attempts', 'isManager'
        ));
    }

    /** Tab Anggota. */
    public function people(Classroom $classroom): View
    {
        $this->authorizeAccess($classroom);

        $classroom->load('teacher');
        $students = $classroom->students()->orderBy('name')->get();

        return view('classrooms.people', compact('classroom', 'students'));
    }

    public function removeStudent(Classroom $classroom, User $user): RedirectResponse
    {
        $this->authorizeManage($classroom);

        $classroom->students()->detach($user->id);

        return back()->with('success', $user->name.' dikeluarkan dari kelas.');
    }

    /** Tab Nilai: rekap untuk guru, nilai pribadi untuk siswa. */
    public function grades(Classroom $classroom): View
    {
        $this->authorizeAccess($classroom);

        $user = auth()->user();
        $isManager = $classroom->canManage($user);

        $students = $isManager
            ? $classroom->students()->orderBy('name')->get()
            : collect([$user]);

        $assignments = $classroom->assignments()->orderBy('created_at')->get();
        $quizzes = $classroom->quizzes()
            ->when(! $isManager, fn ($q) => $q->where('is_published', true))
            ->withSum('questions', 'points')
            ->orderBy('created_at')
            ->get();

        $videoLessons = $classroom->videoLessons()
            ->when(! $isManager, fn ($query) => $query->where('is_published', true))
            ->orderBy('created_at')->get();

        $submissions = Submission::whereIn('assignment_id', $assignments->pluck('id'))
            ->whereIn('user_id', $students->pluck('id'))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->keyBy('assignment_id'));

        $attempts = QuizAttempt::whereIn('quiz_id', $quizzes->pluck('id'))
            ->whereIn('user_id', $students->pluck('id'))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->keyBy('quiz_id'));

        $videoAttempts = VideoAttempt::whereIn('video_lesson_id', $videoLessons->pluck('id'))
            ->whereIn('user_id', $students->pluck('id'))->get()
            ->groupBy('user_id')->map(fn ($rows) => $rows->keyBy('video_lesson_id'));

        // Share transcript score resolution (including manual corrections and frozen final snapshot).
        // Students still only see their existing published activity columns.
        $transcript = app(TranscriptReportService::class)->build($classroom);
        $transcriptScores = $transcript['rows']->keyBy(fn ($row) => $row['student']->id)
            ->map(fn ($row) => $row['scores']);

        return view('classrooms.grades', compact(
            'classroom', 'students', 'assignments', 'quizzes', 'videoLessons', 'submissions', 'attempts', 'videoAttempts', 'isManager', 'transcriptScores', 'transcript'
        ));
    }
}
