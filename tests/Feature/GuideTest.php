<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_the_guide(): void
    {
        $this->get(route('guide.index'))->assertRedirect(route('login'));
    }

    public function test_guide_is_in_the_sidebar_for_every_role_and_shows_role_specific_content(): void
    {
        $expectations = [
            'admin' => ['Panduan untuk Admin', 'Import siswa massal', 'Buka Kelola Pengguna'],
            'guru' => ['Panduan untuk Guru', 'Kelola kuis dan bank soal', 'Validasi transkrip'],
            'siswa' => ['Panduan untuk Siswa', 'Patuhi keamanan kuis', 'Transkrip saya'],
        ];

        foreach ($expectations as $role => $texts) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get(route('guide.index'))
                ->assertOk()
                ->assertSee('Pusat bantuan RuangKelas')
                ->assertSee('href="'.route('guide.index').'"', false)
                ->assertSeeInOrder(['Ringkasan', 'Panduan']);

            foreach ($texts as $text) {
                $response->assertSee($text);
            }
        }
    }

    public function test_guide_sidebar_link_uses_a_question_mark_icon_and_active_state(): void
    {
        $user = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($user)->get(route('guide.index'))
            ->assertOk()
            ->assertSee('class="lms-nav-link active"', false)
            ->assertSee('M9.09 9a3 3', false)
            ->assertSee('data-guide-toc', false)
            ->assertSee('href="#mulai" class="is-active" data-guide-link aria-current="location"', false)
            ->assertSee('href="#panduan-role" data-guide-link', false)
            ->assertSee('href="#akun" data-guide-link', false)
            ->assertSee('js/guide-navigation.js', false)
            ->assertSee('guide-step-card', false)
            ->assertSee('guide-faq', false);
    }
}
