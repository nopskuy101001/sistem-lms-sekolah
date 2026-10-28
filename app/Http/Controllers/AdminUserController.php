<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StudentBatchImportService;
use App\Services\StudentImportTemplateXlsxExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:admin,guru,siswa'],
            'nomor_induk' => ['nullable', 'string', 'max:50', 'unique:users,nomor_induk'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        User::create($data);

        return back()->with('success', 'Pengguna berhasil ditambahkan.');
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

        $user->delete();

        return back()->with('success', 'Pengguna dihapus.');
    }
}
