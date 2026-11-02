<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_replace_and_remove_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'guru', 'name' => 'Guru Foto']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()->assertSee('Profil Saya')->assertSee('Pilih foto baru');

        $this->put(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('foto-lama.jpg', 600, 600),
            'profile_photo_zoom' => 175,
            'profile_photo_position_x' => 25,
            'profile_photo_position_y' => 80,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $firstPath = $user->refresh()->profile_photo_path;
        $this->assertNotNull($firstPath);
        $this->assertSame(175, $user->profile_photo_zoom);
        $this->assertSame(25, $user->profile_photo_position_x);
        $this->assertSame(80, $user->profile_photo_position_y);
        $this->assertSame('object-position: 25% 80%; transform: translate(18.75%, -22.5%) scale(1.75);', $user->profilePhotoStyle());
        Storage::disk('public')->assertExists($firstPath);
        $this->get(route('dashboard'))->assertOk()
            ->assertSee(asset('storage/'.$firstPath))
            ->assertSee('scale(1.75)', false)
            ->assertSee(route('profile.edit'));

        $this->put(route('profile.photo.update'), [
            'profile_photo_zoom' => 220,
            'profile_photo_position_x' => 70,
            'profile_photo_position_y' => 30,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($firstPath, $user->refresh()->profile_photo_path);
        $this->assertSame(220, $user->profile_photo_zoom);
        $this->assertSame(70, $user->profile_photo_position_x);
        $this->assertSame(30, $user->profile_photo_position_y);

        $this->put(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('foto-baru.png', 800, 800),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $secondPath = $user->refresh()->profile_photo_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        $this->delete(route('profile.photo.destroy'))->assertRedirect();
        $this->assertNull($user->refresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($secondPath);
    }

    public function test_photo_validation_and_profile_access_are_protected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'siswa']);

        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->actingAs($user)->put(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('profile_photo');
        $this->assertNull($user->refresh()->profile_photo_path);

        $this->put(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('terlalu-besar.jpg')->size(4096),
        ])->assertSessionHasErrors('profile_photo');
        $this->put(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('foto-valid.jpg'),
            'profile_photo_zoom' => 301,
        ])->assertSessionHasErrors('profile_photo_zoom');
    }

    public function test_profile_photo_is_visible_in_class_member_list(): void
    {
        Storage::fake('public');
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'name' => 'Siswa Berfoto']);
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Kelas Profil', 'color' => 'indigo']);
        $classroom->students()->attach($student);

        $this->actingAs($student)->put(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('siswa.webp', 400, 400),
        ])->assertRedirect();
        $path = $student->refresh()->profile_photo_path;

        $this->actingAs($teacher)->get(route('classrooms.people', $classroom))
            ->assertOk()->assertSee('Siswa Berfoto')->assertSee(asset('storage/'.$path));
    }
}
