<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminUserReadonlyRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_update_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('admin.users.update'));
    }

    public function test_admin_sees_read_only_role_badges(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['name' => 'Guru Contoh', 'role' => 'guru']);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Role pengguna bersifat read-only')
            ->assertSee('Guru Contoh')
            ->assertSee('Guru')
            ->assertDontSee('onchange="this.form.submit()"', false);
    }

    public function test_manual_put_request_cannot_change_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'guru']);

        $this->actingAs($admin)
            ->put('/admin/users/'.$teacher->id, ['role' => 'admin'])
            ->assertMethodNotAllowed();

        $this->assertDatabaseHas('users', [
            'id' => $teacher->id,
            'role' => 'guru',
        ]);
    }
}
