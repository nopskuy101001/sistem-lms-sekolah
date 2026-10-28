<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassCodeRefreshTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'guru']);
        $this->classroom = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Kelas dengan Kode',
            'color' => 'indigo',
        ]);
    }

    public function test_teacher_can_refresh_code_without_removing_existing_students(): void
    {
        $existingStudent = User::factory()->create(['role' => 'siswa']);
        $this->classroom->students()->attach($existingStudent->id);
        $oldCode = $this->classroom->code;

        $this->actingAs($this->teacher)
            ->post(route('classrooms.code.refresh', $this->classroom))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->classroom->refresh();

        $this->assertNotSame($oldCode, $this->classroom->code);
        $this->assertSame(6, strlen($this->classroom->code));
        $this->assertTrue($this->classroom->hasStudent($existingStudent));
    }

    public function test_old_code_is_invalid_and_new_code_can_be_used_to_join(): void
    {
        $lateStudent = User::factory()->create(['role' => 'siswa']);
        $oldCode = $this->classroom->code;

        $this->actingAs($this->teacher)
            ->post(route('classrooms.code.refresh', $this->classroom));
        $newCode = $this->classroom->fresh()->code;

        $this->actingAs($lateStudent)
            ->post(route('classrooms.join'), ['code' => $oldCode])
            ->assertSessionHasErrors('code');
        $this->assertFalse($this->classroom->hasStudent($lateStudent));

        $this->actingAs($lateStudent)
            ->post(route('classrooms.join'), ['code' => $newCode])
            ->assertRedirect(route('classrooms.show', $this->classroom));
        $this->assertTrue($this->classroom->hasStudent($lateStudent));
    }

    public function test_other_teacher_cannot_refresh_class_code(): void
    {
        $otherTeacher = User::factory()->create(['role' => 'guru']);
        $oldCode = $this->classroom->code;

        $this->actingAs($otherTeacher)
            ->post(route('classrooms.code.refresh', $this->classroom))
            ->assertForbidden();

        $this->assertSame($oldCode, $this->classroom->fresh()->code);
    }

    public function test_admin_can_refresh_class_code(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $oldCode = $this->classroom->code;

        $this->actingAs($admin)
            ->post(route('classrooms.code.refresh', $this->classroom))
            ->assertRedirect();

        $this->assertNotSame($oldCode, $this->classroom->fresh()->code);
    }
}
