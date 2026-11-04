<?php

namespace Tests\Feature;

use App\Http\Controllers\StudentRegistrationController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_public_registration_routes_or_links(): void
    {
        foreach (['register', 'register.store', 'register.notice', 'register.verification', 'register.verify.code', 'register.resend'] as $name) {
            $this->assertFalse(Route::has($name), "Public registration route exists: {$name}");
        }
        foreach (['/register', '/register/verify', '/register/notice', '/register/resend'] as $path) {
            $this->get($path)->assertNotFound();
            $this->post($path)->assertNotFound();
        }
        $this->get('/login')->assertOk()->assertSee('Hubungi administrator sekolah')->assertDontSee('Buat akun siswa');
    }

    public function test_stale_cached_controller_cannot_create_account(): void
    {
        $this->withoutExceptionHandling();
        try {
            app(StudentRegistrationController::class)->store(request());
            $this->fail('Controller must deny public registration.');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            $this->assertTrue(true);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_pending_external_account_cannot_be_activated_and_cannot_login(): void
    {
        User::factory()->create([
            'nomor_induk' => 'NIS-PENDING-88',
            'role' => 'siswa_pending',
            'password' => 'Rahasia123456',
            'email_verified_at' => null,
        ]);
        $this->post(route('login.attempt'), [
            'login' => 'NIS-PENDING-88', 'password' => 'Rahasia123456',
        ])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('users', ['nomor_induk'=>'NIS-PENDING-88', 'role'=>'siswa_pending', 'email_verified_at'=>null]);
    }

    public function test_only_admin_can_create_student_and_used_nis_is_rejected(): void
    {
        $admin = User::factory()->create(['role'=>'admin']);
        $teacher = User::factory()->create(['role'=>'guru']);
        User::factory()->create(['role'=>'siswa_pending','nomor_induk'=>'NIS-CLAIMED']);
        $data = ['name'=>'Siswa Resmi','nomor_induk'=>'NIS-CLAIMED','date_of_birth'=>'2007-01-01'];
        $this->actingAs($teacher)->post(route('admin.users.students.store'), $data)->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.students.store'), $data)
            ->assertSessionHasErrors('nomor_induk', errorBag:'studentCreate');
        $this->assertSame(1, User::where('nomor_induk','NIS-CLAIMED')->count());
    }
}
