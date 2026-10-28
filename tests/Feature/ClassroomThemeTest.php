<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClassroomThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_upload_replace_and_remove_classroom_theme(): void
    {
        Storage::fake('public');
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($teacher)->post(route('classrooms.store'), [
            'name' => 'Kelas Seni',
            'color' => 'indigo',
            'theme_image' => UploadedFile::fake()->image('tema-pertama.jpg', 1200, 400),
            'theme_zoom' => 150,
            'theme_position_x' => 25,
            'theme_position_y' => 75,
        ])->assertRedirect();

        $classroom = Classroom::sole();
        $firstImage = $classroom->theme_image_path;
        $this->assertNotNull($firstImage);
        $this->assertSame('object-position: 25% 75%; transform: translate(12.5%, -12.5%) scale(1.5);', $classroom->themeImageStyle());
        Storage::disk('public')->assertExists($firstImage);
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('lms-theme-image', false)
            ->assertSee('data-theme-editor', false)
            ->assertSee('scale(1.5)', false);
        $classroom->students()->attach($student);
        $this->actingAs($student)->get(route('classrooms.show', $classroom))
            ->assertOk()->assertSee(asset('storage/'.$firstImage))
            ->assertSee('scale(1.5)', false);

        $this->actingAs($teacher)->put(route('classrooms.update', $classroom), [
            'name' => 'Kelas Seni',
            'color' => 'emerald',
            'theme_image' => UploadedFile::fake()->image('tema-baru.png', 900, 300),
            'theme_zoom' => 200,
            'theme_position_x' => 80,
            'theme_position_y' => 20,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $classroom->refresh();
        $this->assertNotSame($firstImage, $classroom->theme_image_path);
        $this->assertSame(200, $classroom->theme_zoom);
        $this->assertSame(80, $classroom->theme_position_x);
        $this->assertSame(20, $classroom->theme_position_y);
        Storage::disk('public')->assertMissing($firstImage);
        Storage::disk('public')->assertExists($classroom->theme_image_path);

        $secondImage = $classroom->theme_image_path;
        $this->put(route('classrooms.update', $classroom), [
            'name' => 'Kelas Seni',
            'color' => 'emerald',
            'theme_zoom' => 125,
            'theme_position_x' => 60,
            'theme_position_y' => 40,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $classroom->refresh();
        $this->assertSame($secondImage, $classroom->theme_image_path);
        $this->assertSame(125, $classroom->theme_zoom);
        $this->assertSame(60, $classroom->theme_position_x);
        $this->assertSame(40, $classroom->theme_position_y);

        $this->put(route('classrooms.update', $classroom), [
            'name' => 'Kelas Seni',
            'color' => 'emerald',
            'remove_theme_image' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($classroom->refresh()->theme_image_path);
        Storage::disk('public')->assertMissing($secondImage);
    }

    public function test_student_cannot_change_theme_and_invalid_file_is_rejected(): void
    {
        Storage::fake('public');
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Kelas Seni', 'color' => 'indigo']);
        $classroom->students()->attach($student);

        $this->actingAs($student)->put(route('classrooms.update', $classroom), [
            'name' => 'Kelas Diubah',
            'color' => 'rose',
            'theme_image' => UploadedFile::fake()->image('tema.jpg'),
        ])->assertForbidden();
        $this->assertSame('Kelas Seni', $classroom->refresh()->name);

        $this->actingAs($teacher)->put(route('classrooms.update', $classroom), [
            'name' => 'Kelas Seni',
            'color' => 'indigo',
            'theme_image' => UploadedFile::fake()->create('file.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('theme_image');
        $this->put(route('classrooms.update', $classroom), [
            'name' => 'Kelas Seni',
            'color' => 'indigo',
            'theme_zoom' => 300,
        ])->assertSessionHasErrors('theme_zoom');
        $this->assertNull($classroom->refresh()->theme_image_path);
    }
}
