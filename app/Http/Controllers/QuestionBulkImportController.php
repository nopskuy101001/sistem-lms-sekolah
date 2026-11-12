<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClassroom;
use App\Models\Quiz;
use App\Services\ChunkedQuestionPackageUploadService;
use App\Services\QuestionBulkImportService;
use App\Services\QuestionImportTemplateXlsxExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionBulkImportController extends Controller
{
    use AuthorizesClassroom;

    public function __construct(
        private readonly QuestionBulkImportService $importer,
        private readonly QuestionImportTemplateXlsxExporter $template,
        private readonly ChunkedQuestionPackageUploadService $packageUploads,
    ) {}

    public function import(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeManage($quiz->classroom);

        if ($quiz->attempts()->exists()) {
            return back()->withErrors(['question_file' => 'Soal tidak bisa diimport karena sudah ada siswa yang mengerjakan kuis ini.']);
        }

        $data = $request->validate([
            'question_file' => ['nullable', 'required_without:question_package_upload_token', 'file', 'mimes:csv,txt,xlsx,zip', 'max:122880'],
            'question_package_upload_token' => ['nullable', 'required_without:question_file', 'uuid'],
        ]);
        if ($request->hasFile('question_file') && filled($data['question_package_upload_token'] ?? null)) {
            return back()->withErrors(['question_file' => 'Unggahan paket terduplikasi. Pilih ulang file.']);
        }

        $staging = null;
        try {
            $file = $data['question_file'] ?? null;
            if (filled($data['question_package_upload_token'] ?? null)) {
                $staging = $this->packageUploads->consume($request->user(), $data['question_package_upload_token']);
                $file = new UploadedFile(
                    Storage::disk('local')->path($staging['path']),
                    $staging['name'],
                    'application/zip',
                    null,
                    true,
                );
            }
            $isPackage = strtolower($file->getClientOriginalExtension()) === 'zip';
            $result = $this->importer->import($quiz, $file);
        } finally {
            if ($staging) {
                $this->packageUploads->cleanup($staging['path']);
            }
        }

        $message = $result['questions'].' soal berhasil diimport';
        if ($result['groups'] > 0) {
            $message .= ' beserta '.$result['groups'].' stimulus';
            if ($isPackage) {
                $message .= ' dan media paketnya';
            }
        }

        return back()->with('success', $message.'.');
    }

    public function templateCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fwrite($output, "sep=,\r\n");
            fputcsv($output, QuestionImportTemplateXlsxExporter::HEADERS, ',', '"', '\\', "\r\n");
            fclose($output);
        }, 'template-import-soal.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function templateXlsx(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'template-soal-');
            abort_if($temporaryPath === false, 500, 'Folder sementara tidak tersedia.');

            try {
                $this->template->write($temporaryPath);
                readfile($temporaryPath);
            } finally {
                if (is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }, 'template-import-soal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
