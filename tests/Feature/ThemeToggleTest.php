<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_toggle_is_available_before_notifications_for_every_role(): void
    {
        foreach (['admin', 'guru', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('data-theme-toggle', false)
                ->assertSee('Aktifkan mode gelap')
                ->assertSeeInOrder(['data-theme-toggle', 'lms-notifications'], false);
        }
    }

    public function test_guest_login_does_not_show_authenticated_theme_control(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-theme-toggle', false)
            ->assertSee('class="lms-password-field', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-controls="password"', false)
            ->assertSee('Tampilkan password');
    }

    public function test_password_visibility_controls_are_available_on_authenticated_forms(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);

        $response = $this->actingAs($student)->get(route('password.change'));

        $response->assertOk()
            ->assertSee('aria-controls="current_password"', false)
            ->assertSee('aria-controls="password"', false)
            ->assertSee('aria-controls="password_confirmation"', false);

        $this->assertSame(3, substr_count($response->getContent(), 'data-password-toggle'));
    }

    public function test_dark_theme_maps_translucent_academic_panels_to_dark_surfaces(): void
    {
        $css = file_get_contents(public_path('css/lms-ui.css'));

        $this->assertStringContainsString('[class~="bg-indigo-50/70"]', $css);
        $this->assertStringContainsString('[class~="bg-amber-50/70"]', $css);
        $this->assertStringContainsString('[class~="bg-slate-50/70"]', $css);
        $this->assertStringContainsString('background-color: #272b55 !important', $css);
        $this->assertStringContainsString('background-color: #1c2840 !important', $css);
    }
}
