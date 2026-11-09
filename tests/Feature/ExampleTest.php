<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Classroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
            ->assertSee('media/logo-smkn1-cerme.png', false)
            ->assertSee('Masuk ke ruang belajarmu')
            ->assertSee('© 2026 Muhammad Misfalah Saputra')
            ->assertSee('RuangKelas dikembangkan oleh Nanonie untuk SMK NEGERI 1 CERME GRESIK')
            ->assertDontSee('data-login-typing', false)
            ->assertDontSee('lms-login-typing', false)
            ->assertSee('lms-login-submit', false)
            ->assertDontSee('Selamat datang kembali')
            ->assertSee('lms-auth-container', false)
            ->assertSee('lms-auth-backdrop', false)
            ->assertDontSee('Satu ruang untuk perjalanan belajar yang lebih berarti.')
            ->assertDontSee('lms-auth-visual', false);

        $this->assertFileExists(public_path('media/logo-smkn1-cerme.png'));
    }

    public function test_public_student_registration_is_disabled(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.store'));

        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->get(route('login'))
            ->assertSee('Hubungi administrator sekolah')
            ->assertDontSee('Buat akun siswa');
    }

    public function test_student_can_open_the_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Kelas Saya')
            ->assertSee('Agenda Terdekat')
            ->assertSee('© 2026 Muhammad Misfalah Saputra');
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
