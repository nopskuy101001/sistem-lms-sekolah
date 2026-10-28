<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use App\Models\VideoAttempt;
use App\Models\VideoLesson;
use App\Services\TranscriptReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoLessonIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Classroom $classroom;
    private VideoLesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->create(['role' => 'guru']);
        $this->student = User::factory()->create(['role' => 'siswa']);
        $this->classroom = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Kelas Video',
            'color' => 'indigo',
        ]);
        $this->classroom->students()->attach($this->student);
        $this->lesson = $this->classroom->videoLessons()->create([
            'creator_id' => $this->teacher->id,
            'title' => 'Video Aljabar',
            'source_type' => 'youtube',
            'source_url' => 'https://www.youtube.com/watch?v=jfKfPfyJRdk',
            'is_published' => true,
        ]);
        $this->lesson->checkpoints()->createMany([
            ['time_seconds' => 5, 'question_text' => '1+1 = ?', 'choices' => ['1','2','3','4'], 'correct_index' => 1],
            ['time_seconds' => 15, 'question_text' => '2+2 = ?', 'choices' => ['2','3','4','5'], 'correct_index' => 2],
        ]);
    }

    public function test_video_shows_in_classwork_and_player_is_same_origin(): void
    {
        $this->actingAs($this->student)->get(route('classrooms.classwork', $this->classroom))
            ->assertOk()->assertSee('Video Interaktif')->assertSee('Video Aljabar');
        $this->get(route('video-lessons.show', $this->lesson))->assertOk()
            ->assertSee(route('video-lessons.player', $this->lesson))
            ->assertDontSee('Lihat tab Nilai');
        $this->get(route('video-lessons.player', $this->lesson))->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertSee('1+1 = ?')
            ->assertSee('id="checkpoint-progress"', false)
            ->assertDontSee('"correct_index":1', false);
    }

    public function test_teacher_editor_uses_duration_aware_checkpoint_sliders(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('video-lessons.edit', $this->lesson))
            ->assertOk()
            ->assertSee('video-time-range', false)
            ->assertSee('video-task/js/editor.js', false)
            ->assertSee('Gunakan posisi video saat ini')
            ->assertSee('Progres Pengerjaan Siswa')
            ->assertSee($this->student->name)
            ->assertSee('Belum mulai');
    }

    public function test_answer_sequence_completion_and_transcript(): void
    {
        $this->actingAs($this->student)->get(route('video-lessons.player', $this->lesson))->assertOk();
        $questions = $this->lesson->checkpoints;

        // The later checkpoint may not be submitted before the earlier one.
        $this->postJson(route('video-lessons.answer', $this->lesson), [
            'question_id' => $questions[1]->id, 'selected_index' => 2,
        ])->assertUnprocessable();
        $this->postJson(route('video-lessons.answer', $this->lesson), [
            'question_id' => $questions[0]->id, 'selected_index' => 1,
        ])->assertOk()->assertJsonPath('completed', false);
        $this->postJson(route('video-lessons.answer', $this->lesson), [
            'question_id' => $questions[1]->id, 'selected_index' => 0,
        ])->assertOk()->assertJsonPath('completed', true)->assertJsonPath('score', 50.0);

        $attempt = VideoAttempt::where('video_lesson_id', $this->lesson->id)->where('user_id', $this->student->id)->firstOrFail();
        $this->assertNotNull($attempt->completed_at);
        $this->assertEquals(50, $attempt->score);
        $this->actingAs($this->teacher)->get(route('classrooms.grades', $this->classroom))
            ->assertOk()->assertSee('Video Aljabar');
        $this->get(route('video-lessons.edit', $this->lesson))
            ->assertOk()
            ->assertSee($this->student->name)
            ->assertSee('Selesai')
            ->assertSee('50/100');
        $report = app(TranscriptReportService::class)->build($this->classroom);
        $this->assertSame('Video Interaktif', $report['activities']->last()['type']);
        $this->assertSame(50.0, $report['rows']->first()['scores']['video-'.$this->lesson->id]['percentage']);
        $csv = $this->get(route('transcripts.csv', $this->classroom))->assertOk();
        $this->assertStringContainsString('Video Interaktif', $csv->streamedContent());
    }

    public function test_teacher_configures_double_tap_skip_before_publish_and_restricted_answers_wait_for_video_time(): void
    {
        $this->actingAs($this->teacher)->post(route('video-lessons.publish', $this->lesson))->assertRedirect();
        $this->put(route('video-lessons.playback.update', $this->lesson), ['skip_seconds' => 5])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->lesson->refresh();
        $this->assertFalse($this->lesson->allow_seeking);
        $this->assertSame(5, $this->lesson->skip_seconds);
        $this->get(route('video-lessons.edit', $this->lesson))
            ->assertOk()->assertSee('Izinkan ketuk dua kali untuk skip video')->assertSee('5 detik');

        $this->post(route('video-lessons.publish', $this->lesson))->assertRedirect();
        $this->put(route('video-lessons.playback.update', $this->lesson), ['allow_seeking' => 1, 'skip_seconds' => 10])
            ->assertSessionHasErrors('playback');

        $this->actingAs($this->student)->get(route('video-lessons.player', $this->lesson))
            ->assertOk()->assertSee('id="playback-progress"', false)
            ->assertSee('Skip video dinonaktifkan oleh guru.')
            ->assertDontSee('id="skip-forward"', false)
            ->assertDontSee('id="playback-speed"', false);
        $questions = $this->lesson->checkpoints;
        $this->postJson(route('video-lessons.answer', $this->lesson), [
            'question_id' => $questions[0]->id, 'selected_index' => 1,
        ])->assertUnprocessable();
        $this->travel(5)->seconds();
        $this->postJson(route('video-lessons.answer', $this->lesson), [
            'question_id' => $questions[0]->id, 'selected_index' => 1,
        ])->assertOk();
        $this->postJson(route('video-lessons.answer', $this->lesson), [
            'question_id' => $questions[1]->id, 'selected_index' => 2,
        ])->assertUnprocessable();
        $this->travel(10)->seconds();
        $this->postJson(route('video-lessons.answer', $this->lesson), [
            'question_id' => $questions[1]->id, 'selected_index' => 2,
        ])->assertOk()->assertJsonPath('completed', true);
        $this->travelBack();

        $this->actingAs($this->teacher)->post(route('video-lessons.publish', $this->lesson))->assertRedirect();
        $this->put(route('video-lessons.playback.update', $this->lesson), ['allow_seeking' => 1, 'skip_seconds' => 10])
            ->assertSessionHasErrors('playback');
        $this->actingAs($this->student)->put(route('video-lessons.playback.update', $this->lesson), ['skip_seconds' => 10])->assertForbidden();
    }

    public function test_teacher_can_enable_double_tap_skip_for_five_or_ten_seconds(): void
    {
        $this->lesson->update(['is_published' => false]);
        $this->actingAs($this->teacher)->put(route('video-lessons.playback.update', $this->lesson), [
            'allow_seeking' => 1,
            'skip_seconds' => 5,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->lesson->refresh();
        $this->assertTrue($this->lesson->allow_seeking);
        $this->assertSame(5, $this->lesson->skip_seconds);
        $this->put(route('video-lessons.playback.update', $this->lesson), [
            'allow_seeking' => 1,
            'skip_seconds' => 10,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->lesson->refresh();
        $this->assertTrue($this->lesson->allow_seeking);
        $this->assertSame(10, $this->lesson->skip_seconds);
        $this->put(route('video-lessons.playback.update', $this->lesson), [
            'allow_seeking' => 1,
            'skip_seconds' => 6,
        ])->assertSessionHasErrors('skip_seconds');
        $this->post(route('video-lessons.publish', $this->lesson))->assertRedirect();
        $this->actingAs($this->student)->get(route('video-lessons.player', $this->lesson))
            ->assertOk()
            ->assertSee('id="skip-forward"', false)
            ->assertSee('id="skip-back"', false)
            ->assertSee('id="playback-progress"', false)
            ->assertSee('sisi kanan video untuk maju 10 detik')
            ->assertDontSee('id="seek-range"', false)
            ->assertDontSee('id="playback-speed"', false);
        $this->postJson(route('video-lessons.answer', $this->lesson), [
            'question_id' => $this->lesson->checkpoints->first()->id, 'selected_index' => 1,
        ])->assertOk();
    }

    public function test_mp4_player_uses_the_same_teacher_playback_permissions(): void
    {
        $this->lesson->update([
            'source_type' => 'mp4',
            'source_url' => 'https://example.com/lesson.mp4',
            'allow_seeking' => false,
            'skip_seconds' => 5,
        ]);

        $this->actingAs($this->student)->get(route('video-lessons.player', $this->lesson))
            ->assertOk()
            ->assertSee('id="mp4-player"', false)
            ->assertSee('id="play-toggle"', false)
            ->assertSee('id="mute-toggle"', false)
            ->assertSee('id="fullscreen-toggle"', false)
            ->assertSee('id="playback-progress"', false)
            ->assertDontSee('id="skip-forward"', false)
            ->assertDontSee('id="playback-speed"', false)
            ->assertSee('Skip video dinonaktifkan oleh guru.');
    }

    public function test_student_can_not_modify_lesson_and_outsider_can_not_watch(): void
    {
        $this->actingAs($this->student)->post(route('video-lessons.publish', $this->lesson))->assertForbidden();
        $stranger = User::factory()->create(['role' => 'siswa']);
        $this->actingAs($stranger)->get(route('video-lessons.player', $this->lesson))->assertForbidden();
        $this->actingAs($stranger)->postJson(route('video-lessons.answer', $this->lesson), [
            'question_id' => $this->lesson->checkpoints->first()->id, 'selected_index' => 1,
        ])->assertForbidden();
    }

    public function test_draft_is_hidden_from_students_but_teacher_can_preview(): void
    {
        $this->lesson->update(['is_published' => false]);
        $this->actingAs($this->student)->get(route('video-lessons.show', $this->lesson))->assertNotFound();
        $this->get(route('classrooms.classwork', $this->classroom))->assertDontSee('Video Aljabar');
        $this->actingAs($this->teacher)->get(route('video-lessons.player', $this->lesson))->assertOk();
        $this->assertDatabaseCount('video_attempts', 0);
    }

    public function test_question_edits_are_locked_after_first_answer(): void
    {
        $this->actingAs($this->student)->get(route('video-lessons.player', $this->lesson));
        $this->postJson(route('video-lessons.answer', $this->lesson), [
            'question_id' => $this->lesson->checkpoints->first()->id, 'selected_index' => 1,
        ])->assertOk();
        $this->actingAs($this->teacher)->post(route('video-checkpoints.store', $this->lesson), [
            'time_seconds' => 25,
            'question_text' => 'New question?',
            'choices' => ['A','B','C','D'],
            'correct_index' => 0,
        ])->assertSessionHasErrors();
        $this->assertDatabaseCount('video_checkpoints', 2);
    }
}
