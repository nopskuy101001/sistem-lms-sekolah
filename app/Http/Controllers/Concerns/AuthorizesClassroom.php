<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Classroom;

trait AuthorizesClassroom
{
    /** Hanya guru pemilik kelas / admin. */
    protected function authorizeManage(Classroom $classroom): void
    {
        abort_unless($classroom->canManage(auth()->user()), 403, 'Hanya guru pengampu kelas ini yang boleh melakukan aksi ini.');
    }

    /** Guru pemilik, admin, atau siswa yang tergabung di kelas. */
    protected function authorizeAccess(Classroom $classroom): void
    {
        abort_unless($classroom->canAccess(auth()->user()), 403, 'Anda bukan anggota kelas ini.');
    }
}
