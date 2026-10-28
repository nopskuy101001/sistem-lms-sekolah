<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Classroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_login_page_uses_the_new_school_portal_interface(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('RuangKelas')
            ->assertSee('Masuk ke ruang belajarmu')
            ->assertDontSee('Selamat datang kembali')
            ->assertSee('lms-auth-container', false)
            ->assertSee('lms-auth-backdrop', false)
            ->assertDontSee('Satu ruang untuk perjalanan belajar yang lebih berarti.')
            ->assertDontSee('lms-auth-visual', false);
    }

    public function test_framed_video_layout_is_scoped_to_login_page(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertDontSee('lms-auth-container', false)
            ->assertDontSee('lms-auth-page-login', false);
    }

    public function test_student_can_open_the_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Kelas Saya')
            ->assertSee('Agenda Terdekat');
    }

    public function test_teacher_can_open_dashboard_and_class_home(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'name' => 'Bahasa Indonesia',
            'section' => 'XI-A',
            'subject' => 'Bahasa Indonesia',
            'color' => 'violet',
        ]);

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bahasa Indonesia')
            ->assertSee('Tugas perlu dinilai');

        $this->actingAs($teacher)
            ->get(route('classrooms.show', $classroom))
            ->assertOk()
            ->assertSee('Beranda Kelas')
            ->assertSee($classroom->code);
    }
}
