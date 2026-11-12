<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClassroom;
use App\Models\Classroom;
use App\Models\VideoAttempt;
use App\Models\VideoLesson;
use App\Services\VideoAnswerService;
use App\Services\ChunkedVideoUploadService;
use App\Support\VideoSource;
use App\Support\AcademicNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VideoLessonController extends Controller
{
    use AuthorizesClassroom;

    public function __construct(private readonly ChunkedVideoUploadService $videoUploads) {}

    public function store(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeManage($classroom);
        $data = $this->validateVideo($request);
        $upload = $this->videoUpload($request);
        $path = $upload['path'] ?? null;

        try {
            $lesson = $classroom->videoLessons()->create([
                'creator_id' => $request->user()->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'source_type' => $data['source_type'],
                'source_url' => $path ? '' : $data['source_url'],
                'file_path' => $path,
            ]);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('video-lessons.edit', $lesson)
            ->with('success', 'Video dibuat. Tambahkan checkpoint, kemudian publikasikan.');
    }

    public function edit(VideoLesson $videoLesson): View
    {
        $this->authorizeManage($videoLesson->classroom);
        $videoLesson->load(['checkpoints', 'classroom']);
        $hasAnswers = $videoLesson->attempts()->whereHas('answers')->exists();
        $students = $videoLesson->classroom->students()->orderBy('name')->get();
        $studentAttempts = $videoLesson->attempts()
            ->whereIn('user_id', $students->pluck('id'))
            ->withCount('answers')
            ->get()
            ->keyBy('user_id');
        $attemptCount = $studentAttempts->whereNotNull('completed_at')->count();
        $inProgressCount = $studentAttempts->whereNull('completed_at')->count();
        $notStartedCount = max($students->count() - $studentAttempts->count(), 0);
        $youtubeId = $videoLesson->source_type === 'youtube'
            ? VideoSource::youtubeId($videoLesson->source_url)
            : null;

        return view('video-lessons.edit', compact(
            'videoLesson',
            'hasAnswers',
            'attemptCount',
            'inProgressCount',
            'notStartedCount',
            'students',
            'studentAttempts',
            'youtubeId'
        ));
    }

    public function update(Request $request, VideoLesson $videoLesson): RedirectResponse
    {
        $this->authorizeManage($videoLesson->classroom);
        $data = $this->validateVideo($request, $videoLesson);
        $upload = $this->videoUpload($request);
        $newPath = $upload['path'] ?? null;
        $oldPath = $videoLesson->file_path;
        $sourceChanged = $newPath || ($data['source_type'] !== $videoLesson->source_type)
            || (! empty($data['source_url']) && $data['source_url'] !== $videoLesson->source_url);
        if ($sourceChanged && $videoLesson->attempts()->whereHas('answers')->exists()) {
            if ($newPath) Storage::disk('public')->delete($newPath);
            return back()->withErrors(['source_url' => 'Sumber video terkunci karena sudah ada jawaban siswa. Buat video baru untuk mengganti sumber.']);
        }
        $videoLesson->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'source_type' => $data['source_type'],
            'source_url' => $newPath ? '' : ($data['source_url'] ?? $videoLesson->source_url),
            'file_path' => $newPath ?: ($sourceChanged ? null : $oldPath),
        ]);
        if ($oldPath && $oldPath !== $videoLesson->file_path) Storage::disk('public')->delete($oldPath);
        return back()->with('success', 'Detail video berhasil diperbarui.');
    }

    public function publish(VideoLesson $videoLesson): RedirectResponse
    {
        $this->authorizeManage($videoLesson->classroom);
        if (! $videoLesson->is_published && ! $videoLesson->checkpoints()->exists()) {
            return back()->withErrors(['publish' => 'Tambahkan minimal satu pertanyaan sebelum memublikasikan video.']);
        }
        $videoLesson->update(['is_published' => ! $videoLesson->is_published]);

        if ($videoLesson->is_published) {
            AcademicNotifier::students(
                $videoLesson->classroom,
                'Video task dipublikasikan',
                $videoLesson->title.' sudah tersedia di kelas '.$videoLesson->classroom->name.'.',
                route('video-lessons.show', $videoLesson, false),
                'video',
            );
        }

        return back()->with('success', $videoLesson->is_published ? 'Video dipublikasikan.' : 'Video disembunyikan dari siswa.');
    }

    public function updatePlayback(Request $request, VideoLesson $videoLesson): RedirectResponse
    {
        $this->authorizeManage($videoLesson->classroom);
        if ($videoLesson->is_published || $videoLesson->attempts()->whereHas('answers')->exists()) {
            return back()->withErrors(['playback' => 'Sembunyikan video sebelum mengubah pengaturan pemutaran. Pengaturan terkunci setelah ada jawaban siswa.']);
        }

        $request->validate([
            'allow_seeking' => ['sometimes', 'boolean'],
            'skip_seconds' => ['required', 'integer', Rule::in([5, 10])],
        ]);
        $videoLesson->update([
            'allow_seeking' => $request->boolean('allow_seeking'),
            'skip_seconds' => (int) $request->input('skip_seconds'),
        ]);

        return back()->with('success', 'Pengaturan pemutaran siswa berhasil disimpan.');
    }

    public function destroy(VideoLesson $videoLesson): RedirectResponse
    {
        $this->authorizeManage($videoLesson->classroom);
        $classroom = $videoLesson->classroom;
        $path = $videoLesson->file_path;
        $videoLesson->delete();
        if ($path) Storage::disk('public')->delete($path);
        return redirect()->route('classrooms.classwork', $classroom)->with('success', 'Video dan nilai terkait telah dihapus.');
    }

    public function show(Request $request, VideoLesson $videoLesson): View
    {
        $this->authorizeWatch($request, $videoLesson);
        $videoLesson->load('classroom', 'checkpoints');
        $isManager = $videoLesson->classroom->canManage($request->user());
        $attempt = $isManager ? null : VideoAttempt::firstOrCreate([
            'video_lesson_id' => $videoLesson->id,
            'user_id' => $request->user()->id,
        ]);
        $answeredIds = $attempt ? $attempt->answers()->pluck('video_checkpoint_id')->all() : [];
        return view('video-lessons.show', compact('videoLesson', 'attempt', 'answeredIds', 'isManager'));
    }

    public function player(Request $request, VideoLesson $videoLesson): \Illuminate\Http\Response
    {
        $this->authorizeWatch($request, $videoLesson);
        $videoLesson->load('checkpoints');
        $preview = $videoLesson->classroom->canManage($request->user());
        $attempt = $preview ? null : VideoAttempt::firstOrCreate([
            'video_lesson_id' => $videoLesson->id, 'user_id' => $request->user()->id,
        ]);
        $answeredIds = $attempt ? $attempt->answers()->pluck('video_checkpoint_id')->all() : [];
        $questions = $videoLesson->checkpoints->map(function ($checkpoint) use ($preview) {
            $data = [
                'id' => $checkpoint->id,
                'time_seconds' => $checkpoint->time_seconds,
                'question_text' => $checkpoint->question_text,
                'choices' => $checkpoint->choices,
            ];
            if ($preview) {
                $data['correct_index'] = $checkpoint->correct_index;
                $data['explanation'] = $checkpoint->explanation;
            }
            return $data;
        })->values()->all();
        $youtubeId = $videoLesson->source_type === 'youtube' ? VideoSource::youtubeId($videoLesson->source_url) : null;
        $parentOrigin = $request->getSchemeAndHttpHost();
        $html = view('video-lessons.player', compact(
            'videoLesson', 'preview', 'attempt', 'answeredIds', 'questions', 'youtubeId', 'parentOrigin'
        ))->render();
        return response($html)->header('Content-Security-Policy', "frame-ancestors 'self'")
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Cache-Control', 'no-store');
    }

    public function media(Request $request, VideoLesson $videoLesson): BinaryFileResponse
    {
        $this->authorizeWatch($request, $videoLesson);
        abort_unless(
            $videoLesson->source_type === 'mp4'
                && $videoLesson->file_path
                && Storage::disk('public')->exists($videoLesson->file_path),
            404,
        );

        return response()->file(Storage::disk('public')->path($videoLesson->file_path), [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'inline; filename="video-interaktif-'.$videoLesson->id.'.mp4"',
            'Cache-Control' => 'private, max-age=3600',
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function answer(Request $request, VideoLesson $videoLesson, VideoAnswerService $service): JsonResponse
    {
        $this->authorizeWatch($request, $videoLesson);
        abort_unless($request->user()->isSiswa(), 403, 'Hanya siswa yang dapat menjawab.');
        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'selected_index' => ['required', 'integer', 'between:0,3'],
        ]);
        $wasCompleted = VideoAttempt::where('video_lesson_id', $videoLesson->id)
            ->where('user_id', $request->user()->id)
            ->whereNotNull('completed_at')
            ->exists();
        $result = $service->submit(
                $videoLesson,
                $request->user()->id,
                (int) $data['question_id'],
                (int) $data['selected_index']
            );

        if (! $wasCompleted && $result['completed']) {
            AcademicNotifier::teacher(
                $videoLesson->classroom,
                'Video task diselesaikan',
                $request->user()->name.' menyelesaikan '.$videoLesson->title.'.',
                route('video-lessons.edit', $videoLesson, false),
                'submission',
            );
        }

        return response()->json(
            $result,
            options: JSON_PRESERVE_ZERO_FRACTION
        );
    }

    public function checkpointStore(Request $request, VideoLesson $videoLesson): RedirectResponse
    {
        $this->authorizeManage($videoLesson->classroom);
        $this->ensureCheckpointsEditable($videoLesson);
        $data = $this->checkpointData($request);
        if ($videoLesson->checkpoints()->where('time_seconds', $data['time_seconds'])->exists()) {
            return back()->withErrors(['time_seconds' => 'Sudah ada pertanyaan pada detik tersebut.'])->withInput();
        }
        $videoLesson->checkpoints()->create($data);
        return back()->with('success', 'Pertanyaan checkpoint berhasil ditambahkan.');
    }

    public function checkpointUpdate(Request $request, VideoLesson $videoLesson, \App\Models\VideoCheckpoint $checkpoint): RedirectResponse
    {
        $this->authorizeManage($videoLesson->classroom);
        abort_unless($checkpoint->video_lesson_id === $videoLesson->id, 404);
        $this->ensureCheckpointsEditable($videoLesson);
        $data = $this->checkpointData($request);
        if ($videoLesson->checkpoints()->where('time_seconds', $data['time_seconds'])->where('id', '!=', $checkpoint->id)->exists()) {
            return back()->withErrors(['time_seconds' => 'Sudah ada pertanyaan pada detik tersebut.'])->withInput();
        }
        $checkpoint->update($data);
        return back()->with('success', 'Checkpoint diperbarui.');
    }

    public function checkpointDestroy(VideoLesson $videoLesson, \App\Models\VideoCheckpoint $checkpoint): RedirectResponse
    {
        $this->authorizeManage($videoLesson->classroom);
        abort_unless($checkpoint->video_lesson_id === $videoLesson->id, 404);
        $this->ensureCheckpointsEditable($videoLesson);
        if ($videoLesson->is_published && $videoLesson->checkpoints()->count() <= 1) {
            return back()->withErrors(['question' => 'Sembunyikan video dahulu sebelum menghapus satu-satunya pertanyaan.']);
        }
        $checkpoint->delete();
        return back()->with('success', 'Checkpoint dihapus.');
    }

    private function authorizeWatch(Request $request, VideoLesson $lesson): void
    {
        $classroom = $lesson->classroom;
        $this->authorizeAccess($classroom);
        abort_unless($classroom->canManage($request->user()) || $lesson->is_published, 404);
    }

    private function ensureCheckpointsEditable(VideoLesson $lesson): void
    {
        if ($lesson->attempts()->whereHas('answers')->exists()) {
            throw ValidationException::withMessages(['question' => 'Pertanyaan terkunci setelah ada jawaban siswa, agar nilai lama tetap konsisten. Buat video baru bila ingin mengganti soal.']);
        }
    }

    private function checkpointData(Request $request): array
    {
        $data = $request->validate([
            'time_seconds' => ['required', 'integer', 'between:0,86400'],
            'question_text' => ['required', 'string', 'max:3000'],
            'choices' => ['required', 'array', 'size:4'],
            'choices.*' => ['required', 'string', 'max:500'],
            'correct_index' => ['required', 'integer', 'between:0,3'],
            'explanation' => ['nullable', 'string', 'max:3000'],
        ]);
        $data['choices'] = array_values($data['choices']);
        return $data;
    }

    private function validateVideo(Request $request, ?VideoLesson $lesson = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'source_type' => ['required', Rule::in(['youtube', 'mp4'])],
            'source_url' => ['nullable', 'string', 'max:2000'],
            'video_file' => ['nullable', 'file', 'mimes:mp4', 'max:102400'],
            'video_upload_token' => ['nullable', 'uuid'],
        ]);
        $type = $data['source_type'];
        $hasFile = $request->hasFile('video_file') || filled($data['video_upload_token'] ?? null);
        if ($request->hasFile('video_file') && filled($data['video_upload_token'] ?? null)) {
            throw ValidationException::withMessages(['video_file' => 'Unggahan video terduplikasi. Pilih ulang file MP4.']);
        }
        if ($hasFile && $type !== 'mp4') {
            throw ValidationException::withMessages(['video_file' => 'Unggahan file hanya tersedia untuk sumber MP4.']);
        }
        $url = trim($data['source_url'] ?? '');
        if ($hasFile && $url !== '') {
            throw ValidationException::withMessages(['source_url' => 'Pilih salah satu sumber MP4: URL atau unggah file.']);
        }
        $canKeepExisting = $lesson && $type === $lesson->source_type && $url === '' && ! $hasFile;
        if (! $canKeepExisting && ! $hasFile) {
            $valid = $type === 'youtube' ? VideoSource::youtubeId($url) !== null : VideoSource::isMp4Url($url);
            if (! $valid) {
                throw ValidationException::withMessages(['source_url' => 'Masukkan URL YouTube yang valid atau URL HTTPS/HTTP langsung ke file .mp4.']);
            }
        }
        return $data;
    }

    /** @return array{path: string, name?: string, size?: int}|null */
    private function videoUpload(Request $request): ?array
    {
        if ($request->filled('video_upload_token')) {
            return $this->videoUploads->consume(
                $request->user(),
                $request->string('video_upload_token')->toString(),
                'video-lessons',
                'public',
            );
        }

        if ($request->hasFile('video_file')) {
            $file = $request->file('video_file');
            $path = $file->store('video-lessons', 'public');
            if (! $path) {
                throw ValidationException::withMessages(['video_file' => 'Video MP4 gagal disimpan.']);
            }

            return ['path' => $path, 'name' => $file->getClientOriginalName(), 'size' => $file->getSize()];
        }

        return null;
    }
}
