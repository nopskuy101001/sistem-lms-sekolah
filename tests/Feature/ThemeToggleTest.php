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
            ->assertDontSee('data-theme-toggle', false);
    }
}
