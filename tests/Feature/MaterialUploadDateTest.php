<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaterialUploadDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_list_displays_its_upload_date_and_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-19 09:30:00', 'Asia/Jakarta'));

        $teacher = User::factory()->create(['role' => 'guru']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'name' => 'Bahasa Indonesia',
            'color' => 'violet',
        ]);
        $classroom->materials()->create([
            'title' => 'Materi Teks Eksposisi',
            'description' => 'Materi pertemuan pertama.',
        ]);

        $this->actingAs($teacher)
            ->get(route('classrooms.classwork', $classroom))
            ->assertOk()
            ->assertSee('Materi Teks Eksposisi')
            ->assertSee('Diunggah 19 Oktober 2026, 09:30');

        Carbon::setTestNow();
    }
}
