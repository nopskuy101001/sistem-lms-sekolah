<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\QuestionGroup;
use App\Models\Quiz;
use App\Models\User;
use App\Models\VideoLesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChunkedVideoUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->teacher = User::factory()->create(['role' => 'guru']);
        $this->classroom = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Kelas Video',
            'subject' => 'Multimedia',
            'color' => 'indigo',
        ]);
    }

    public function test_chunked_mp4_can_create_quiz_stimulus_despite_small_php_upload_limit(): void
    {
        $quiz = Quiz::create(['classroom_id' => $this->classroom->id, 'title' => 'Kuis Video']);
        $token = $this->uploadMp4();

        $this->actingAs($this->teacher)->post(route('question-groups.store', $quiz), [
            'title' => 'Stimulus unggahan bertahap',
            'stimulus_video_upload_token' => $token,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $group = QuestionGroup::firstOrFail();
        $this->assertSame('upload', $group->video_type);
        $this->assertSame('materi.mp4', $group->video_name);
        Storage::disk('local')->assertExists($group->video_path);
        Storage::disk('local')->assertMissing('temporary/video-staging/'.$this->teacher->id.'/'.$token.'.json');
    }

    public function test_chunked_mp4_can_create_interactive_video_on_public_disk(): void
    {
        $token = $this->uploadMp4();

        $this->actingAs($this->teacher)->post(route('video-lessons.store', $this->classroom), [
            'title' => 'Video Interaktif MP4',
            'source_type' => 'mp4',
            'source_url' => '',
            'video_upload_token' => $token,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $lesson = VideoLesson::firstOrFail();
        $this->assertSame('mp4', $lesson->source_type);
        $this->assertSame('', $lesson->source_url);
        Storage::disk('public')->assertExists($lesson->file_path);
    }

    public function test_invalid_video_signature_and_student_chunk_access_are_rejected(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $uploadId = (string) Str::uuid();
        $payload = [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'total_chunks' => 1,
            'original_name' => 'bukan-video.mp4',
            'total_size' => 12,
            'chunk' => UploadedFile::fake()->createWithContent('chunk.part', 'not-an-mp4!'),
        ];

        $this->actingAs($student)->postJson(route('video-uploads.chunks'), $payload)->assertForbidden();
        $this->actingAs($this->teacher)->postJson(route('video-uploads.chunks'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('chunk');
    }

    public function test_chunked_mp4_can_replace_interactive_video_and_remove_old_file(): void
    {
        Storage::disk('public')->put('video-lessons/old.mp4', 'old-video');
        $lesson = $this->classroom->videoLessons()->create([
            'creator_id' => $this->teacher->id,
            'title' => 'Video Lama',
            'source_type' => 'mp4',
            'source_url' => '',
            'file_path' => 'video-lessons/old.mp4',
        ]);
        $token = $this->uploadMp4();

        $this->actingAs($this->teacher)->put(route('video-lessons.update', $lesson), [
            'title' => 'Video Baru',
            'source_type' => 'mp4',
            'source_url' => '',
            'video_upload_token' => $token,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $lesson->refresh();
        $this->assertSame('Video Baru', $lesson->title);
        $this->assertNotSame('video-lessons/old.mp4', $lesson->file_path);
        Storage::disk('public')->assertMissing('video-lessons/old.mp4');
        Storage::disk('public')->assertExists($lesson->file_path);
    }

    public function test_video_forms_expose_chunked_upload_progress(): void
    {
        $quiz = Quiz::create(['classroom_id' => $this->classroom->id, 'title' => 'Kuis Video']);

        $this->actingAs($this->teacher)->get(route('quizzes.show', $quiz))
            ->assertOk()
            ->assertSee('data-chunked-video-form', false)
            ->assertSee('stimulus_video_upload_token', false)
            ->assertSee('chunked-video-upload.js', false);
        $this->get(route('classrooms.classwork', $this->classroom))
            ->assertOk()
            ->assertSee('video_upload_token', false)
            ->assertSee('data-video-upload-progress', false)
            ->assertSee('File dikirim bertahap');
    }

    private function uploadMp4(): string
    {
        $content = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isomvideo-data";
        $uploadId = (string) Str::uuid();

        $response = $this->actingAs($this->teacher)->postJson(route('video-uploads.chunks'), [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'total_chunks' => 1,
            'original_name' => 'materi.mp4',
            'total_size' => strlen($content),
            'chunk' => UploadedFile::fake()->createWithContent('chunk.part', $content),
        ])->assertOk()->assertJson(['complete' => true]);

        return $response->json('token');
    }
}
