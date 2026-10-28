<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\QuestionGroup;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NopalVideoTaskCoexistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tka_media_and_video_task_are_accessible_in_the_same_classroom(): void
    {
        Storage::fake('local');

        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'name' => 'Kelas Gabungan',
            'color' => 'indigo',
        ]);
        $classroom->students()->attach($student);
        $quiz = Quiz::create([
            'classroom_id' => $classroom->id,
            'title' => 'Kuis TKA Baru',
            'is_published' => true,
        ]);

        // Private image/table stimulus is a Nopal-only update that must survive the merge.
        $image = UploadedFile::fake()->createWithContent(
            'grafik.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        );
        $this->actingAs($teacher)->post(route('question-groups.store', $quiz), [
            'title' => 'Grafik Penjualan',
            'stimulus_image' => $image,
            'table_text' => "Bulan,Penjualan\nJan,10\nFeb,20",
        ])->assertRedirect();
        $group = QuestionGroup::firstOrFail();
        $this->assertNotNull($group->media_path);
        $this->assertCount(3, $group->table_data);
        $this->get(route('question-groups.media', $group))->assertOk();

        // New VideoTask module must work alongside existing quiz/stimulus routes.
        $lesson = $classroom->videoLessons()->create([
            'creator_id' => $teacher->id,
            'title' => 'Video Persamaan',
            'source_type' => 'youtube',
            'source_url' => 'https://www.youtube.com/watch?v=jfKfPfyJRdk',
            'is_published' => true,
        ]);
        $lesson->checkpoints()->create([
            'time_seconds' => 5,
            'question_text' => 'Hitung 1 + 1',
            'choices' => ['1', '2', '3', '4'],
            'correct_index' => 1,
        ]);
        $this->actingAs($student)->get(route('classrooms.classwork', $classroom))
            ->assertOk()->assertSee('Video Persamaan');
        $this->get(route('video-lessons.player', $lesson))
            ->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->get(route('question-groups.media', $group))->assertOk();
    }
}
