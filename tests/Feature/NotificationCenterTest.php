<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_assignment_notifies_class_students_and_renders_unread_badge(): void
    {
        [$teacher, $student, $classroom] = $this->classroomWithStudent();

        $this->actingAs($teacher)->post(route('assignments.store', $classroom), [
            'title' => 'Latihan Aljabar',
            'points' => 100,
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $notification = $student->fresh()->notifications()->sole();
        $this->assertSame('Tugas baru', $notification->data['title']);
        $this->assertSame('assignment', $notification->data['category']);
        $this->assertSame(1, $student->fresh()->unreadNotifications()->count());

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Latihan Aljabar')
            ->assertSee('lms-notification-badge', false)
            ->assertDontSee('lms-notification-dot', false);
    }

    public function test_notification_can_only_be_opened_by_its_owner_and_is_marked_read(): void
    {
        [$teacher, $student, $classroom] = $this->classroomWithStudent();

        $this->actingAs($teacher)->post(route('materials.store', $classroom), [
            'title' => 'Modul Persamaan',
        ])->assertRedirect(route('classrooms.classwork', $classroom));

        $notification = $student->fresh()->notifications()->sole();
        $outsider = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($outsider)
            ->get(route('notifications.open', $notification->id))
            ->assertNotFound();

        $this->actingAs($student)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('classrooms.classwork', $classroom, false));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_join_submission_grading_and_mark_all_read_follow_the_correct_recipient(): void
    {
        [$teacher, $student, $classroom] = $this->classroomWithStudent();
        $newStudent = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($newStudent)->post(route('classrooms.join'), [
            'code' => $classroom->code,
        ])->assertRedirect(route('classrooms.show', $classroom));

        $this->assertSame('Siswa baru bergabung', $teacher->fresh()->notifications()->first()->data['title']);

        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'title' => 'Esai Sejarah',
            'points' => 100,
        ]);

        $this->actingAs($student)->post(route('submissions.store', $assignment), [
            'content' => 'Jawaban siswa.',
        ])->assertRedirect();

        $submission = $assignment->submissions()->where('user_id', $student->id)->sole();
        $this->assertTrue(
            $teacher->fresh()->notifications->contains(fn ($notification) => $notification->data['title'] === 'Tugas dikumpulkan')
        );

        $this->actingAs($teacher)->put(route('submissions.grade', $submission), [
            'score' => 90,
            'feedback' => 'Baik.',
        ])->assertRedirect();

        $this->assertSame('Tugas sudah dinilai', $student->fresh()->notifications()->latest()->first()->data['title']);

        $this->actingAs($teacher)->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $teacher->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $student->fresh()->unreadNotifications()->count());
    }

    private function classroomWithStudent(): array
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'name' => 'Matematika X',
            'color' => 'indigo',
        ]);
        $classroom->students()->attach($student->id);

        return [$teacher, $student, $classroom];
    }
}
