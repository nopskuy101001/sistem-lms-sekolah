<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data demo. Semua akun memakai password: password123
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'password123';

        User::create([
            'name' => 'Admin Sekolah',
            'email' => 'admin@sekolah.test',
            'password' => $password,
            'role' => 'admin',
        ]);

        $guru = User::create([
            'name' => 'Bu Sari (Guru Matematika)',
            'email' => 'guru@sekolah.test',
            'password' => $password,
            'role' => 'guru',
            'nomor_induk' => '198501012010012001',
        ]);

        $siswa = collect([
            ['Andi Pratama', '2024001'],
            ['Budi Santoso', '2024002'],
            ['Citra Lestari', '2024003'],
            ['Dewi Anggraini', '2024004'],
            ['Eko Wijaya', '2024005'],
        ])->map(fn ($row, $i) => User::create([
            'name' => $row[0],
            'email' => 'siswa'.($i + 1).'@sekolah.test',
            'password' => $password,
            'role' => 'siswa',
            'nomor_induk' => $row[1],
        ]));

        $kelas = Classroom::create([
            'teacher_id' => $guru->id,
            'name' => 'Matematika Wajib',
            'section' => 'X-A',
            'subject' => 'Matematika',
            'room' => '12',
            'description' => 'Kelas demo kurikulum semester ganjil.',
            'color' => 'indigo',
            'code' => 'MTK10A',
        ]);

        $kelas->students()->attach($siswa->pluck('id'));

        $kelas->announcements()->create([
            'user_id' => $guru->id,
            'body' => "Selamat datang di kelas Matematika X-A!\nMateri minggu ini: Persamaan Linear. Silakan cek tab Tugas & Materi.",
        ]);

        $kelas->materials()->create([
            'title' => 'Pengantar Persamaan Linear',
            'description' => 'Tonton video berikut sebelum mengerjakan tugas.',
            'link' => 'https://www.youtube.com/',
        ]);

        $kelas->assignments()->create([
            'title' => 'Latihan Persamaan Linear 1',
            'instructions' => 'Kerjakan soal nomor 1-10 pada buku paket halaman 25, lalu unggah foto jawaban kamu.',
            'due_at' => now()->addDays(7),
            'points' => 100,
        ]);

        $kuis = $kelas->quizzes()->create([
            'title' => 'Kuis Harian: Persamaan Linear',
            'description' => 'Kerjakan dengan jujur. Pilihan ganda dinilai otomatis, esai dinilai guru.',
            'duration_minutes' => 20,
            'is_published' => true,
        ]);

        $kuis->questions()->createMany([
            [
                'type' => 'pilihan_ganda',
                'body' => 'Nilai x dari persamaan 2x + 6 = 14 adalah ...',
                'option_a' => '2', 'option_b' => '4', 'option_c' => '6', 'option_d' => '10',
                'correct_option' => 'b',
                'points' => 10,
            ],
            [
                'type' => 'pilihan_ganda',
                'body' => 'Jika 3x - 5 = 10, maka x = ...',
                'option_a' => '3', 'option_b' => '4', 'option_c' => '5', 'option_d' => '15',
                'correct_option' => 'c',
                'points' => 10,
            ],
            [
                'type' => 'esai',
                'body' => 'Jelaskan langkah-langkah menyelesaikan persamaan 5x + 2 = 3x + 10.',
                'points' => 20,
            ],
        ]);
    }
}
