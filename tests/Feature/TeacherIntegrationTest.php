<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_and_admin_can_open_integration_hub_and_launch_doku(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($teacher)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertSee('Pusat Integrasi')
            ->assertSee('DoKu')
            ->assertSee('Dokumen Kurikulum SMK Negeri 1 Cerme')
            ->assertSee(config('integrations.staff.doku.url'), false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);

        $this->actingAs($admin)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertSee('Pusat Integrasi')
            ->assertSee('DoKu');
    }

    public function test_integration_hub_and_navigation_are_hidden_only_from_students(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($teacher)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Integrasi')
            ->assertSee('href="'.route('integrations.index').'"', false);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Integrasi')
            ->assertSee('href="'.route('integrations.index').'"', false);
        $this->get(route('integrations.index'))->assertOk();

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('integrations.index').'"', false);
        $this->get(route('integrations.index'))->assertForbidden();
    }
}
