<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StudentBatchImportService;
use App\Services\StudentImportTemplateXlsxExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        return view('admin.users', compact('users'));
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
            'email' => filled($request->input('email'))
                ? strtolower(trim((string) $request->input('email')))
                : null,
            'phone' => filled($request->input('phone')) ? trim((string) $request->input('phone')) : null,
            'address' => filled($request->input('address')) ? trim((string) $request->input('address')) : null,
        ]);

        $data = $request->validateWithBag('studentCreate', [
            'name' => ['required', 'string', 'max:255'],
            'nomor_induk' => ['required', 'string', 'max:50', 'regex:/^(?=.*[A-Za-z0-9])[A-Za-z0-9.\-\/]+$/', 'unique:users,nomor_induk'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'gender' => ['nullable', 'in:L,P'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
        ], [
            'nomor_induk.regex' => 'NIS hanya boleh berisi huruf, angka, titik, garis miring, atau tanda hubung dan harus memiliki minimal satu huruf/angka.',
            'date_of_birth.before_or_equal' => 'Tanggal lahir tidak boleh melewati hari ini.',
        ]);

        $birthDate = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $data['date_of_birth']);
        $email = $data['email'] ?: StudentBatchImportService::generatedEmailFor($data['nomor_induk']);
        if (User::where('email', $email)->exists()) {
            return back()
                ->withErrors(['email' => 'Email otomatis untuk NIS tersebut sudah digunakan. Isi email lain yang valid.'], 'studentCreate')
                ->withInput();
        }

        User::create([
            ...$data,
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

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Anda tidak bisa menghapus akun sendiri.']);
        }

        $profilePhoto = $user->profile_photo_path;
        $user->notifications()->delete();
        $user->delete();
        if ($profilePhoto) Storage::disk('public')->delete($profilePhoto);

        return back()->with('success', 'Pengguna dihapus.');
    }
}
