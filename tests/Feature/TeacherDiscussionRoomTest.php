<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\DiscussionMessage;
use App\Models\DiscussionRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherDiscussionRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_creates_subject_room_with_matching_teachers_and_invitation(): void
    {
        $creator = User::factory()->create(['role' => 'guru']);
        $mathTeacher = User::factory()->create(['role' => 'guru']);
        $englishTeacher = User::factory()->create(['role' => 'guru']);
        $this->teach($creator, 'Matematika', 'Matematika XI A');
        $this->teach($mathTeacher, 'matematika', 'Matematika XI B');
        $this->teach($englishTeacher, 'Bahasa Inggris', 'Bahasa Inggris XI');

        $this->actingAs($creator)->post(route('discussion-rooms.store'), [
            'name' => 'MGMP Matematika',
            'subject' => 'Matematika',
            'description' => 'Menyusun asesmen bersama.',
            'theme_color' => 'emerald',
            'theme_style' => 'waves',
            'member_ids' => [$mathTeacher->id],
        ])->assertRedirect();

        $room = DiscussionRoom::firstOrFail();
        $this->assertSame('Matematika', $room->subject);
        $this->assertSame('emerald', $room->theme_color);
        $this->assertSame('waves', $room->theme_style);
        $this->assertEqualsCanonicalizing([$creator->id, $mathTeacher->id], $room->members()->pluck('users.id')->all());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $mathTeacher->id,
            'notifiable_type' => User::class,
        ]);

        $this->actingAs($creator)->post(route('discussion-rooms.store'), [
            'name' => 'Ruang salah anggota',
            'subject' => 'Matematika',
            'member_ids' => [$englishTeacher->id],
        ])->assertSessionHasErrors('member_ids');
        $this->assertDatabaseCount('discussion_rooms', 1);
    }

    public function test_room_and_private_attachments_are_limited_to_members_and_admin(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'guru']);
        $member = User::factory()->create(['role' => 'guru']);
        $outsider = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);
        $admin = User::factory()->create(['role' => 'admin']);
        $room = DiscussionRoom::create([
            'creator_id' => $creator->id,
            'name' => 'Diskusi Matematika',
            'subject' => 'Matematika',
        ]);
        $room->members()->attach([$creator->id, $member->id]);

        $this->actingAs($member)->post(route('discussion-messages.store', $room), [
            'body' => 'Berikut contoh diagram untuk dibahas.',
            'attachment' => UploadedFile::fake()->image('diagram.jpg', 800, 600),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $message = DiscussionMessage::firstOrFail();
        Storage::disk('local')->assertExists($message->attachment_path);
        $this->actingAs($member)->get(route('discussion-messages.attachment', $message))->assertOk();
        $this->actingAs($outsider)->get(route('discussion-rooms.show', $room))->assertForbidden();
        $this->get(route('discussion-messages.attachment', $message))->assertForbidden();
        $this->actingAs($student)->get(route('discussion-rooms.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('discussion-rooms.show', $room))
            ->assertOk()->assertSee('Berikut contoh diagram untuk dibahas.');

        $path = $message->attachment_path;
        $this->actingAs($member)->delete(route('discussion-messages.destroy', $message))->assertRedirect();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_creator_manages_members_and_admin_can_remove_room_with_files(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'guru']);
        $firstMember = User::factory()->create(['role' => 'guru']);
        $newMember = User::factory()->create(['role' => 'guru']);
        $wrongSubject = User::factory()->create(['role' => 'guru']);
        $admin = User::factory()->create(['role' => 'admin']);
        foreach ([$creator, $firstMember, $newMember] as $index => $teacher) {
            $this->teach($teacher, 'Produktif RPL', 'RPL '.($index + 1));
        }
        $this->teach($wrongSubject, 'Bahasa Indonesia', 'Bahasa Indonesia');
        $room = DiscussionRoom::create([
            'creator_id' => $creator->id,
            'name' => 'Forum RPL',
            'subject' => 'Produktif RPL',
        ]);
        $room->members()->attach([$creator->id, $firstMember->id]);

        $this->actingAs($creator)->put(route('discussion-rooms.members.update', $room), [
            'member_ids' => [$newMember->id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$creator->id, $newMember->id], $room->members()->pluck('users.id')->all());

        $this->put(route('discussion-rooms.members.update', $room), [
            'member_ids' => [$wrongSubject->id],
        ])->assertSessionHasErrors('member_ids');

        Storage::disk('local')->put('discussion-attachments/modul.pdf', 'pdf');
        $room->messages()->create([
            'user_id' => $creator->id,
            'attachment_path' => 'discussion-attachments/modul.pdf',
            'attachment_name' => 'modul.pdf',
            'attachment_mime' => 'application/pdf',
            'attachment_size' => 3,
        ]);
        $this->actingAs($admin)->delete(route('discussion-rooms.destroy', $room))->assertRedirect(route('discussion-rooms.index'));
        $this->assertDatabaseCount('discussion_rooms', 0);
        Storage::disk('local')->assertMissing('discussion-attachments/modul.pdf');
    }

    public function test_sidebar_exposes_discussion_only_to_teacher_and_admin(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertSee('Diskusi Guru');
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Diskusi Guru');
        $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertDontSee('Diskusi Guru');
    }

    public function test_unread_message_badges_are_per_teacher_and_clear_when_room_is_opened(): void
    {
        $creator = User::factory()->create(['role' => 'guru']);
        $member = User::factory()->create(['role' => 'guru']);
        $this->teach($creator, 'Matematika', 'Matematika A');
        $this->teach($member, 'Matematika', 'Matematika B');
        $room = DiscussionRoom::create([
            'creator_id' => $creator->id,
            'name' => 'Forum Matematika',
            'subject' => 'Matematika',
        ]);
        $room->members()->attach([
            $creator->id => ['last_read_at' => now()],
            $member->id => ['last_read_at' => now()],
        ]);
        $room->messages()->create(['user_id' => $creator->id, 'body' => 'Pesan baru untuk anggota.']);
        $room->messages()->create(['user_id' => $member->id, 'body' => 'Pesan milik sendiri tidak dihitung.']);

        $this->actingAs($member)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-discussion-unread-badge', false)
            ->assertSee('data-unread-count="1"', false);
        $this->get(route('discussion-rooms.index'))
            ->assertOk()
            ->assertSee('data-discussion-room-unread="'.$room->id.'"', false)
            ->assertSee('1 baru');
        $this->getJson(route('discussion-rooms.unread'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rooms.'.$room->id, 1);

        $this->get(route('discussion-rooms.show', $room))
            ->assertOk()
            ->assertSee('1 pesan baru dibaca');
        $this->getJson(route('discussion-rooms.unread'))
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->actingAs($creator)->getJson(route('discussion-rooms.unread'))
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_creator_can_customize_room_theme_but_member_cannot(): void
    {
        $creator = User::factory()->create(['role' => 'guru']);
        $member = User::factory()->create(['role' => 'guru']);
        $room = DiscussionRoom::create([
            'creator_id' => $creator->id,
            'name' => 'Forum Tema',
            'subject' => 'Produktif RPL',
        ]);
        $room->members()->attach([$creator->id, $member->id]);

        $this->actingAs($member)->put(route('discussion-rooms.theme.update', $room), [
            'theme_color' => 'rose', 'theme_style' => 'grid',
        ])->assertForbidden();
        $this->actingAs($creator)->put(route('discussion-rooms.theme.update', $room), [
            'theme_color' => 'violet', 'theme_style' => 'grid',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $room->refresh();
        $this->assertSame('violet', $room->theme_color);
        $this->assertSame('grid', $room->theme_style);
        $this->get(route('discussion-rooms.show', $room))
            ->assertOk()
            ->assertSee('discussion-theme-grid', false)
            ->assertSee('--discussion-primary: #7c3aed', false)
            ->assertSee('Ubah tema ruang');
    }

    public function test_deleting_teacher_account_cleans_discussion_attachments(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $creator = User::factory()->create(['role' => 'guru']);
        $otherTeacher = User::factory()->create(['role' => 'guru']);
        $room = DiscussionRoom::create([
            'creator_id' => $creator->id,
            'name' => 'Ruang yang ikut terhapus',
            'subject' => 'Matematika',
        ]);
        Storage::disk('local')->put('discussion-attachments/arsip.pdf', 'pdf');
        $room->messages()->create([
            'user_id' => $otherTeacher->id,
            'attachment_path' => 'discussion-attachments/arsip.pdf',
            'attachment_name' => 'arsip.pdf',
            'attachment_mime' => 'application/pdf',
            'attachment_size' => 3,
        ]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $creator))->assertRedirect();

        $this->assertDatabaseCount('discussion_rooms', 0);
        Storage::disk('local')->assertMissing('discussion-attachments/arsip.pdf');
    }

    private function teach(User $teacher, string $subject, string $name): Classroom
    {
        return Classroom::create([
            'teacher_id' => $teacher->id,
            'name' => $name,
            'subject' => $subject,
            'color' => 'indigo',
        ]);
    }
}
