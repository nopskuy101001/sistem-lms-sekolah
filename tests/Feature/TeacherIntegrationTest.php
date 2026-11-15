<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_open_integration_hub_and_launch_doku(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);

        $this->actingAs($teacher)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertSee('Pusat Integrasi Guru')
            ->assertSee('DoKu')
            ->assertSee('Dokumen Kurikulum SMK Negeri 1 Cerme')
            ->assertSee(config('integrations.teacher.doku.url'), false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_integration_hub_and_navigation_are_teacher_only(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($teacher)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Integrasi guru')
            ->assertSee('href="'.route('integrations.index').'"', false);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Integrasi guru');
        $this->get(route('integrations.index'))->assertForbidden();

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Integrasi guru');
        $this->get(route('integrations.index'))->assertForbidden();
    }
}
