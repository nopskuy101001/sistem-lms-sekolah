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
                ->assertSee('data-sidebar-theme-menu', false)
                ->assertSee('data-sidebar-theme-option="indigo"', false)
                ->assertSee('data-sidebar-theme-option="ocean"', false)
                ->assertSee('data-sidebar-theme-option="emerald"', false)
                ->assertSee('data-sidebar-theme-option="rose"', false)
                ->assertSee('data-sidebar-theme-option="graphite"', false)
                ->assertSee('Ubah warna sidebar')
                ->assertSee('Aktifkan mode gelap')
                ->assertSeeInOrder(['lms-brand', 'data-sidebar-theme-menu', 'Menu Utama'], false)
                ->assertSeeInOrder(['data-theme-toggle', 'lms-notifications'], false);
        }
    }

    public function test_guest_login_does_not_show_authenticated_theme_control(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-theme-toggle', false)
            ->assertDontSee('data-sidebar-theme-menu', false)
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

    public function test_sidebar_theme_palette_is_persisted_and_has_distinct_color_schemes(): void
    {
        $css = file_get_contents(public_path('css/lms-ui.css'));
        $script = file_get_contents(public_path('js/lms-ui.js'));

        $this->assertIsString($css);
        $this->assertIsString($script);
        foreach (['indigo', 'ocean', 'emerald', 'rose', 'graphite'] as $theme) {
            $this->assertStringContainsString("html[data-sidebar-theme='".$theme."']", $css);
        }
        $this->assertStringContainsString('var(--sidebar-active-start)', $css);
        $this->assertStringContainsString('var(--sidebar-active-end)', $css);
        $this->assertStringContainsString("localStorage.setItem('lms-sidebar-theme'", $script);
        $this->assertStringContainsString('applySidebarTheme', $script);
        $this->assertStringContainsString("sidebarThemeMenu.open = false", $script);
    }
}
