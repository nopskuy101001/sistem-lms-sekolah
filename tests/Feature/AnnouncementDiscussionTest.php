<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementDiscussionTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private User $otherStudent;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->create(['role' => 'guru']);
        $this->student = User::factory()->create(['role' => 'siswa']);
        $this->otherStudent = User::factory()->create(['role' => 'siswa']);
        $this->classroom = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Kelas Diskusi Pengumuman',
            'color' => 'indigo',
        ]);
        $this->classroom->students()->attach([$this->student->id, $this->otherStudent->id]);
    }

    public function test_teacher_and_students_can_discuss_under_an_announcement(): void
    {
        $announcement = $this->classroom->announcements()->create([
            'user_id' => $this->teacher->id,
            'body' => 'Besok bawa buku latihan.',
        ]);

        $this->actingAs($this->student)->post(route('announcement-comments.store', $announcement), [
            'body' => 'Apakah perlu membawa penggaris juga?',
        ])->assertRedirectContains('#announcement-'.$announcement->id)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('announcement_comments', [
            'announcement_id' => $announcement->id,
            'user_id' => $this->student->id,
            'body' => 'Apakah perlu membawa penggaris juga?',
        ]);
        $this->assertCount(1, $this->teacher->notifications()->get());
        $this->assertSame('Balasan pengumuman baru', $this->teacher->notifications()->first()->data['title']);

        $this->actingAs($this->teacher)->post(route('announcement-comments.store', $announcement), [
            'body' => 'Ya, penggaris juga dibawa.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(1, $this->student->notifications()->get());
        $this->assertCount(1, $this->otherStudent->notifications()->get());

        $this->actingAs($this->student)->get(route('classrooms.show', $this->classroom))
            ->assertOk()
            ->assertSee('Diskusi kelas')
            ->assertSee('2 pesan')
            ->assertSee('Apakah perlu membawa penggaris juga?')
            ->assertSee('Ya, penggaris juga dibawa.')
            ->assertSee(route('announcement-comments.store', $announcement), false)
            ->assertSee('Tulis tanggapan atau pertanyaan...');
    }

    public function test_outsider_cannot_comment_and_student_cannot_create_main_announcement(): void
    {
        $announcement = $this->classroom->announcements()->create([
            'user_id' => $this->teacher->id,
            'body' => 'Pengumuman guru.',
        ]);
        $outsider = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($outsider)->post(route('announcement-comments.store', $announcement), [
            'body' => 'Pesan penyusup.',
        ])->assertForbidden();
        $this->actingAs($this->student)->post(route('announcements.store', $this->classroom), [
            'body' => 'Siswa tidak boleh membuat topik utama.',
        ])->assertForbidden();
        $this->actingAs($this->student)->post(route('announcement-comments.store', $announcement), [
            'body' => str_repeat('a', 2001),
        ])->assertSessionHasErrors('body');

        $this->assertDatabaseCount('announcement_comments', 0);
        $this->assertDatabaseCount('announcements', 1);
    }

    public function test_student_can_delete_own_message_and_teacher_can_moderate_or_delete_thread(): void
    {
        $announcement = $this->classroom->announcements()->create([
            'user_id' => $this->teacher->id,
            'body' => 'Topik diskusi.',
        ]);
        $own = $announcement->comments()->create(['user_id' => $this->student->id, 'body' => 'Pesan sendiri']);
        $other = $announcement->comments()->create(['user_id' => $this->otherStudent->id, 'body' => 'Pesan siswa lain']);

        $this->actingAs($this->student)->delete(route('announcement-comments.destroy', $other))->assertForbidden();
        $this->delete(route('announcement-comments.destroy', $own))
            ->assertRedirectContains('#announcement-'.$announcement->id);
        $this->assertDatabaseMissing('announcement_comments', ['id' => $own->id]);

        $this->actingAs($this->teacher)->delete(route('announcement-comments.destroy', $other))->assertRedirect();
        $remaining = $announcement->comments()->create(['user_id' => $this->student->id, 'body' => 'Ikut terhapus']);
        $this->delete(route('announcements.destroy', $announcement))->assertRedirect();
        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
        $this->assertDatabaseMissing('announcement_comments', ['id' => $remaining->id]);
    }
}
