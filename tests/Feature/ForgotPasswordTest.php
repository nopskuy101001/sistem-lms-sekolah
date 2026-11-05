<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_password_reset_is_disabled_and_login_points_to_admin(): void
    {
        $this->assertFalse(Route::has('password.request'));
        $this->assertFalse(Route::has('password.email'));
        $this->assertFalse(Route::has('password.reset'));
        $this->assertFalse(Route::has('password.store'));

        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password')->assertNotFound();
        $this->get('/reset-password/token')->assertNotFound();
        $this->post('/reset-password')->assertNotFound();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Lupa password? Hubungi administrator sekolah.');
    }

    public function test_public_registration_remains_closed(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
