<?php

namespace App\Http\Controllers;

use App\Models\DiscussionMessage;
use App\Models\User;
use App\Services\StudentBatchImportService;
use App\Services\StudentImportTemplateXlsxExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly StudentBatchImportService $studentImporter,
        private readonly StudentImportTemplateXlsxExporter $studentTemplate,
    ) {}

    public function index(Request $request): View
    {
        $resetUser = null;
        if ($request->filled('reset_user')) {
            $resetUser = User::query()
                ->whereKey($request->integer('reset_user'))
                ->whereIn('role', ['guru', 'siswa'])
                ->firstOrFail();
        }

        $users = User::query()
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('nomor_induk', 'like', $term));
            })
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users', compact('users', 'resetUser'));
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => strtolower(trim((string) $request->input('email'))),
            'nomor_induk' => filled($request->input('nomor_induk'))
                ? trim((string) $request->input('nomor_induk'))
                : null,
        ]);

        $data = $request->validateWithBag('staffCreate', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:admin,guru'],
            'nomor_induk' => ['nullable', 'string', 'max:50', 'regex:/^(?=.*[A-Za-z0-9])[A-Za-z0-9.\-\/]+$/', 'unique:users,nomor_induk'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create($data + ['must_change_password' => true]);

        return back()->with('success', 'Akun '.($data['role'] === 'guru' ? 'guru' : 'admin').' berhasil dibuat. Pengguna wajib mengganti password awal saat login pertama.');
    }

    public function storeStudent(Request $request): RedirectResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'nomor_induk' => trim((string) $request->input('nomor_induk')),
            'date_of_birth' => trim((string) $request->input('date_of_birth')),
            'email' => filled($request->input('email'))
                ? strtolower(trim((string) $request->input('email')))
                : null,
        ]);

        $data = $request->validateWithBag('studentCreate', [
            'name' => ['required', 'string', 'max:255'],
            'nomor_induk' => ['required', 'string', 'max:50', 'regex:/^(?=.*[A-Za-z0-9])[A-Za-z0-9.\-\/]+$/', 'unique:users,nomor_induk'],
            'date_of_birth' => [
                'required',
                'date_format:d/m/Y',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $date = Carbon::createFromFormat('!d/m/Y', (string) $value);
                    } catch (\Throwable) {
                        return;
                    }

                    if (! $date || $date->format('d/m/Y') !== $value) {
                        return;
                    }
                    if ($date->lt(Carbon::create(1900, 1, 1)->startOfDay())) {
                        $fail('Tanggal lahir tidak boleh sebelum 01/01/1900.');
                    } elseif ($date->gt(today())) {
                        $fail('Tanggal lahir tidak boleh melewati hari ini.');
                    }
                },
            ],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'gender' => ['nullable', 'in:L,P'],
        ], [
            'nomor_induk.regex' => 'NIS hanya boleh berisi huruf, angka, titik, garis miring, atau tanda hubung dan harus memiliki minimal satu huruf/angka.',
            'date_of_birth.date_format' => 'Tanggal lahir harus menggunakan format dd/mm/yyyy, contoh 22/03/2005.',
        ]);

        $birthDate = Carbon::createFromFormat('!d/m/Y', $data['date_of_birth']);
        $email = $data['email'] ?: StudentBatchImportService::generatedEmailFor($data['nomor_induk']);
        if (User::where('email', $email)->exists()) {
            return back()
                ->withErrors(['email' => 'Email otomatis untuk NIS tersebut sudah digunakan. Isi email lain yang valid.'], 'studentCreate')
                ->withInput();
        }

        User::create([
            ...$data,
            'date_of_birth' => $birthDate->format('Y-m-d'),
            'email' => $email,
            'role' => 'siswa',
            'password' => $birthDate->format('dmY'),
            'must_change_password' => true,
        ]);

        return back()->with('success', 'Akun siswa berhasil dibuat. Password awal menggunakan tanggal lahir dan wajib diganti saat login pertama.');
    }

    public function importStudents(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ]);

        $result = $this->studentImporter->import($data['student_file']);

        return back()->with('success', $result['created'].' akun siswa berhasil diimport. Password awal menggunakan tanggal lahir dan wajib diganti saat login pertama.');
    }

    public function studentTemplateCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fwrite($output, "sep=,\r\n");
            fputcsv($output, StudentImportTemplateXlsxExporter::HEADERS, ',', '"', '\\', "\r\n");
            fclose($output);
        }, 'template-import-siswa.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function studentTemplateXlsx(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'template-siswa-');
            abort_if($temporaryPath === false, 500, 'Folder sementara tidak tersedia.');

            try {
                $this->studentTemplate->write($temporaryPath);
                readfile($temporaryPath);
            } finally {
                if (is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }, 'template-import-siswa.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless(
            in_array($user->role, ['guru', 'siswa'], true),
            403,
            'Password hanya dapat direset untuk akun guru atau siswa.'
        );

        $data = $request->validateWithBag('passwordReset', [
            'admin_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:admin_password'],
        ], [
            'admin_password.current_password' => 'Password admin tidak sesuai.',
            'password.different' => 'Password sementara harus berbeda dari password admin.',
        ]);

        DB::transaction(function () use ($request, $user, $data) {
            $user->forceFill([
                'password' => $data['password'],
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('admin_password_reset_audits')->insert([
                'admin_id' => $request->user()->id,
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->delete();
            }
        });

        return redirect()->route('admin.users.index')->with(
            'success',
            "Password {$user->name} berhasil direset. Sampaikan password sementara secara aman; pengguna wajib menggantinya saat login."
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Anda tidak bisa menghapus akun sendiri.']);
        }

        $profilePhoto = $user->profile_photo_path;
        $discussionAttachments = DiscussionMessage::query()
            ->whereNotNull('attachment_path')
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('room', fn ($rooms) => $rooms->where('creator_id', $user->id));
            })
            ->pluck('attachment_path')
            ->unique()
            ->all();
        $user->notifications()->delete();
        $user->delete();
        if ($profilePhoto) Storage::disk('public')->delete($profilePhoto);
        Storage::disk('local')->delete($discussionAttachments);

        return back()->with('success', 'Pengguna dihapus.');
    }
}
