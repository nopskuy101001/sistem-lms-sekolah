<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassroomAcademicMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_teacher_can_tag_and_filter_classes_by_academic_period(): void
    {
        $this->assertFalse(Schema::hasColumn('classrooms', 'semester'));
        Carbon::setTestNow('2026-11-11 09:00:00');
        $teacher = User::factory()->create(['role' => 'guru']);

        $this->actingAs($teacher)->post(route('classrooms.store'), [
            'name' => 'Matematika XI',
            'section' => 'XI RPL 1',
            'grade_level' => 'XI',
            'academic_year' => '2026/2027',
            'color' => 'indigo',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $classroom = Classroom::sole();
        $this->assertSame('XI', $classroom->grade_level);
        $this->assertSame('2026/2027', $classroom->academic_year);
        $this->assertSame(['Kelas XI', '2026/2027'], $classroom->academicTags());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-class-year-filter', false)
            ->assertSee('data-academic-year="2026/2027"', false)
            ->assertSee('Kelas XI');

        $this->get(route('classrooms.show', $classroom))
            ->assertOk()
            ->assertSee('Kelas XI')
            ->assertSee('2026/2027');
    }

    public function test_period_defaults_follow_the_indonesian_school_year_and_invalid_year_is_rejected(): void
    {
        Carbon::setTestNow('2027-02-10 09:00:00');
        $teacher = User::factory()->create(['role' => 'guru']);

        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'name' => 'Kelas Default',
            'color' => 'teal',
        ]);

        $this->assertSame('2026/2027', $classroom->academic_year);

        $this->actingAs($teacher)->post(route('classrooms.store'), [
            'name' => 'Kelas Tahun Salah',
            'academic_year' => '2026/2028',
            'color' => 'indigo',
        ])->assertSessionHasErrors('academic_year');
    }
}
