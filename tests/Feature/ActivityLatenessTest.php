<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Submission;
use App\Models\User;
use App\Models\VideoAttempt;
use App\Models\VideoLesson;
use App\Services\TranscriptReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLatenessTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-12 12:00:00');

        $this->teacher = User::factory()->create(['role' => 'guru']);
        $this->student = User::factory()->create(['role' => 'siswa']);
        $this->classroom = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Kelas Tenggat',
            'subject' => 'Multimedia',
        ]);
        $this->classroom->students()->attach($this->student);
    }

    public function test_late_assignment_submission_records_and_displays_exact_duration(): void
    {
        $assignment = Assignment::create([
            'classroom_id' => $this->classroom->id,
            'title' => 'Tugas dengan tenggat',
            'due_at' => now()->subMinutes(90),
            'points' => 100,
        ]);

        $this->actingAs($this->student)->post(route('submissions.store', $assignment), [
            'content' => 'Jawaban terlambat',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $submission = Submission::where('assignment_id', $assignment->id)->where('user_id', $this->student->id)->firstOrFail();
        $this->assertSame(5400, $submission->late_seconds);
        $this->assertSame('1 jam 30 menit', $submission->lateByLabel($assignment->due_at));

        $this->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee('terlambat 1 jam 30 menit');
        $this->actingAs($this->teacher)->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee('terlambat 1 jam 30 menit');

        $this->put(route('submissions.grade', $submission), [
            'score' => 85,
            'feedback' => 'Sudah diperiksa.',
        ])->assertRedirect();
        $score = app(TranscriptReportService::class)->build($this->classroom)['rows'][0]['scores']['assignment-'.$assignment->id];
        $this->assertSame(85.0, $score['score']);
        $this->assertSame('1 jam 30 menit', $score['late_label']);
        $this->assertSame('Dinilai · Terlambat 1 jam 30 menit', $score['status']);
        $this->get(route('transcripts.index', ['classroom' => $this->classroom->id]))
            ->assertOk()
            ->assertSee('Terlambat 1 jam 30 menit');
    }

    public function test_teacher_sets_video_deadline_and_late_completion_is_recorded_everywhere(): void
    {
        $this->actingAs($this->teacher)->post(route('video-lessons.store', $this->classroom), [
            'title' => 'Video bertenggat',
            'description' => 'Selesaikan semua checkpoint.',
            'due_at' => now()->subMinutes(75)->format('Y-m-d H:i:s'),
            'source_type' => 'youtube',
            'source_url' => 'https://www.youtube.com/watch?v=jfKfPfyJRdk',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $lesson = VideoLesson::firstOrFail();
        $this->assertTrue($lesson->due_at->equalTo(now()->subMinutes(75)));
        $lesson->update(['is_published' => true, 'allow_seeking' => true]);
        $lesson->checkpoints()->create([
            'time_seconds' => 5,
            'question_text' => '2 + 2?',
            'choices' => ['2', '3', '4', '5'],
            'correct_index' => 2,
        ]);

        $this->actingAs($this->student)->get(route('video-lessons.player', $lesson))->assertOk();
        $this->postJson(route('video-lessons.answer', $lesson), [
            'question_id' => $lesson->checkpoints()->firstOrFail()->id,
            'selected_index' => 2,
        ])->assertOk()->assertJsonPath('completed', true);

        $attempt = VideoAttempt::where('video_lesson_id', $lesson->id)->where('user_id', $this->student->id)->firstOrFail();
        $this->assertSame(4500, $attempt->late_seconds);
        $this->assertSame('1 jam 15 menit', $attempt->lateByLabel($lesson->due_at));

        $score = app(TranscriptReportService::class)->build($this->classroom)['rows'][0]['scores']['video-'.$lesson->id];
        $this->assertSame(100.0, $score['score']);
        $this->assertSame('1 jam 15 menit', $score['late_label']);
        $this->assertSame('Dinilai · Terlambat 1 jam 15 menit', $score['status']);

        $this->actingAs($this->teacher)->get(route('video-lessons.edit', $lesson))
            ->assertOk()
            ->assertSee('Terlambat 1 jam 15 menit');
        $this->get(route('transcripts.index', ['classroom' => $this->classroom->id]))
            ->assertOk()
            ->assertSee('Terlambat 1 jam 15 menit');
    }

    public function test_overdue_unfinished_video_gets_explained_zero_and_teacher_can_correct_it(): void
    {
        $lesson = $this->videoLesson('Video tidak selesai', now()->subHour());

        $score = app(TranscriptReportService::class)->build($this->classroom)['rows'][0]['scores']['video-'.$lesson->id];
        $this->assertSame(0.0, $score['score']);
        $this->assertTrue($score['final']);
        $this->assertTrue($score['automatic_zero']);
        $this->assertSame('Nilai otomatis 0 karena video interaktif tidak dikerjakan sampai tenggat waktu. Tenggat telah terlewati 1 jam.', $score['status']);

        $this->actingAs($this->teacher)->put(route('transcripts.scores.update', $this->classroom), [
            'student_id' => $this->student->id,
            'activity_type' => 'video',
            'activity_id' => $lesson->id,
            'score' => 80,
            'reason' => 'Diberi kesempatan susulan',
        ])->assertRedirect();

        $corrected = app(TranscriptReportService::class)->build($this->classroom)['rows'][0]['scores']['video-'.$lesson->id];
        $this->assertSame(80.0, $corrected['score']);
        $this->assertTrue($corrected['manual']);
        $this->assertFalse($corrected['automatic_zero']);
        $this->assertSame('Diberi kesempatan susulan', $corrected['reason']);
    }

    public function test_video_before_deadline_does_not_get_automatic_zero(): void
    {
        $lesson = $this->videoLesson('Video masih terbuka', now()->addHour());
        $score = app(TranscriptReportService::class)->build($this->classroom)['rows'][0]['scores']['video-'.$lesson->id];

        $this->assertNull($score['score']);
        $this->assertFalse($score['final']);
        $this->assertFalse($score['automatic_zero']);
        $this->assertSame('Belum dikerjakan', $score['status']);
    }

    private function videoLesson(string $title, $dueAt): VideoLesson
    {
        $lesson = VideoLesson::create([
            'classroom_id' => $this->classroom->id,
            'creator_id' => $this->teacher->id,
            'title' => $title,
            'due_at' => $dueAt,
            'source_type' => 'youtube',
            'source_url' => 'https://www.youtube.com/watch?v=jfKfPfyJRdk',
            'is_published' => true,
        ]);
        $lesson->checkpoints()->create([
            'time_seconds' => 5,
            'question_text' => 'Checkpoint',
            'choices' => ['A', 'B', 'C', 'D'],
            'correct_index' => 0,
        ]);

        return $lesson;
    }
}
